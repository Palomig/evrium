<?php
/**
 * Детальная диагностика расписания из students.schedule
 * Показывает что видит бот
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isLoggedIn()) {
    jsonError('Необходима авторизация', 401);
}

$dayOfWeek = (int)date('N');
$dayOfWeekStr = (string)$dayOfWeek;
$today = date('Y-m-d');
$currentTime = date('H:i');

$result = [
    'current_day' => $dayOfWeek,
    'current_day_str' => $dayOfWeekStr,
    'current_time' => $currentTime,
    'today' => $today,
    'students_total' => 0,
    'students_with_schedule' => 0,
    'students_with_lessons_today' => 0,
    'unique_lessons_today' => [],
    'lessons_template_today' => [],
    'raw_schedules' => [],
    'comparison' => []
];

// 1. Получаем всех студентов
$allStudents = dbQuery(
    "SELECT id, name, schedule, teacher_id FROM students WHERE active = 1",
    []
);
$result['students_total'] = count($allStudents);

// 2. Анализируем schedule каждого студента
$uniqueLessons = [];
foreach ($allStudents as $student) {
    if (!$student['schedule']) {
        continue;
    }

    $result['students_with_schedule']++;

    $schedule = json_decode($student['schedule'], true);
    if (!is_array($schedule)) {
        $result['raw_schedules'][] = [
            'student' => $student['name'],
            'error' => 'Invalid JSON',
            'raw' => substr($student['schedule'], 0, 200)
        ];
        continue;
    }

    // Сохраняем первые 3 расписания для отладки
    if (count($result['raw_schedules']) < 3) {
        $result['raw_schedules'][] = [
            'student' => $student['name'],
            'teacher_id_column' => $student['teacher_id'],
            'schedule_keys' => array_keys($schedule),
            'schedule' => $schedule
        ];
    }

    // Проверяем ОБА варианта ключа: число и строку
    $daySchedule = null;
    $keyUsed = null;

    if (isset($schedule[$dayOfWeek]) && is_array($schedule[$dayOfWeek])) {
        $daySchedule = $schedule[$dayOfWeek];
        $keyUsed = 'int:' . $dayOfWeek;
    } elseif (isset($schedule[$dayOfWeekStr]) && is_array($schedule[$dayOfWeekStr])) {
        $daySchedule = $schedule[$dayOfWeekStr];
        $keyUsed = 'str:' . $dayOfWeekStr;
    }

    if (!$daySchedule) {
        continue;
    }

    $result['students_with_lessons_today']++;

    foreach ($daySchedule as $slot) {
        if (!isset($slot['time'])) continue;

        $time = substr($slot['time'], 0, 5);

        // Анализируем teacher_id
        $slotTeacherId = null;
        $teacherIdSource = 'none';

        if (isset($slot['teacher_id'])) {
            if ($slot['teacher_id'] === '' || $slot['teacher_id'] === null) {
                $teacherIdSource = 'slot_empty';
            } else {
                $slotTeacherId = (int)$slot['teacher_id'];
                $teacherIdSource = 'slot:' . $slotTeacherId;
            }
        }

        $teacherId = $slotTeacherId ?: (int)$student['teacher_id'];
        if (!$slotTeacherId) {
            $teacherIdSource .= ' -> fallback:' . $student['teacher_id'];
        }

        if (!$teacherId) {
            continue;
        }

        $key = "{$teacherId}_{$time}";
        if (!isset($uniqueLessons[$key])) {
            $uniqueLessons[$key] = [
                'key' => $key,
                'teacher_id' => $teacherId,
                'time' => $time,
                'subject' => $slot['subject'] ?? 'Мат.',
                'room' => $slot['room'] ?? 1,
                'students' => [],
                'teacher_id_sources' => []
            ];
        }
        $uniqueLessons[$key]['students'][] = $student['name'];
        $uniqueLessons[$key]['teacher_id_sources'][] = $teacherIdSource;
    }
}

// Сортируем по времени
usort($uniqueLessons, fn($a, $b) => strcmp($a['time'], $b['time']));

// Добавляем имена преподавателей
$teachers = [];
$teacherRows = dbQuery("SELECT id, name, telegram_id FROM teachers WHERE active = 1", []);
foreach ($teacherRows as $t) {
    $teachers[$t['id']] = $t;
}

foreach ($uniqueLessons as &$lesson) {
    $t = $teachers[$lesson['teacher_id']] ?? null;
    $lesson['teacher_name'] = $t['name'] ?? "ID:{$lesson['teacher_id']}";
    $lesson['teacher_has_telegram'] = $t && !empty($t['telegram_id']);
    $lesson['student_count'] = count($lesson['students']);
}

$result['unique_lessons_today'] = array_values($uniqueLessons);

// 3. Получаем уроки из lessons_template для сравнения
$templates = dbQuery(
    "SELECT lt.*, t.name as teacher_name, t.telegram_id
     FROM lessons_template lt
     JOIN teachers t ON lt.teacher_id = t.id
     WHERE lt.day_of_week = ? AND lt.active = 1 AND t.active = 1
     ORDER BY lt.time_start",
    [$dayOfWeek]
);

foreach ($templates as $tpl) {
    $result['lessons_template_today'][] = [
        'id' => $tpl['id'],
        'teacher_id' => $tpl['teacher_id'],
        'teacher_name' => $tpl['teacher_name'],
        'time_start' => substr($tpl['time_start'], 0, 5),
        'subject' => $tpl['subject'] ?? '',
        'expected_students' => $tpl['expected_students'],
        'students_json' => $tpl['students'] ?? '[]',
        'has_telegram' => !empty($tpl['telegram_id'])
    ];
}

// 4. Сравнение: что есть в template но нет в schedule
foreach ($result['lessons_template_today'] as $tpl) {
    $tplKey = "{$tpl['teacher_id']}_{$tpl['time_start']}";
    $foundInSchedule = false;
    foreach ($result['unique_lessons_today'] as $lesson) {
        if ($lesson['key'] === $tplKey) {
            $foundInSchedule = true;
            $result['comparison'][] = [
                'key' => $tplKey,
                'status' => 'MATCH',
                'template_students' => $tpl['expected_students'],
                'schedule_students' => $lesson['student_count']
            ];
            break;
        }
    }
    if (!$foundInSchedule) {
        $result['comparison'][] = [
            'key' => $tplKey,
            'status' => 'TEMPLATE_ONLY',
            'message' => "Урок {$tpl['time_start']} у {$tpl['teacher_name']} есть в lessons_template, но НЕ найден в students.schedule!"
        ];
    }
}

// Уроки которые есть в schedule, но нет в template
foreach ($result['unique_lessons_today'] as $lesson) {
    $foundInTemplate = false;
    foreach ($result['lessons_template_today'] as $tpl) {
        if ("{$tpl['teacher_id']}_{$tpl['time_start']}" === $lesson['key']) {
            $foundInTemplate = true;
            break;
        }
    }
    if (!$foundInTemplate) {
        $result['comparison'][] = [
            'key' => $lesson['key'],
            'status' => 'SCHEDULE_ONLY',
            'message' => "Урок {$lesson['time']} у {$lesson['teacher_name']} есть в students.schedule, но НЕ найден в lessons_template"
        ];
    }
}

jsonSuccess($result);
