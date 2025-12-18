<?php
/**
 * API для планировщика расписания
 * Обрабатывает drag & drop операции перемещения учеников
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

// Устанавливаем JSON заголовки
header('Content-Type: application/json; charset=utf-8');

// Требуем авторизацию
if (!isLoggedIn()) {
    jsonError('Требуется авторизация', 401);
}

// Получаем действие
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Маршрутизация по действиям
switch ($action) {
    case 'move_student':
        handleMoveStudent();
        break;
    case 'get_schedule':
        handleGetSchedule();
        break;
    default:
        jsonError('Неизвестное действие', 400);
}

/**
 * Переместить ученика в новый слот расписания
 * Обновляет students.schedule JSON и проверяет lessons_template
 */
function handleMoveStudent() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    // Валидация входных данных
    $studentId = filter_var($data['student_id'] ?? 0, FILTER_VALIDATE_INT);
    $fromDay = filter_var($data['from_day'] ?? 0, FILTER_VALIDATE_INT);
    $fromTime = $data['from_time'] ?? '';
    $fromRoom = filter_var($data['from_room'] ?? 0, FILTER_VALIDATE_INT);
    $toDay = filter_var($data['to_day'] ?? 0, FILTER_VALIDATE_INT);
    $toTime = $data['to_time'] ?? '';
    $toRoom = filter_var($data['to_room'] ?? 0, FILTER_VALIDATE_INT);
    $teacherId = filter_var($data['teacher_id'] ?? 0, FILTER_VALIDATE_INT);

    // Базовая валидация
    if (!$studentId) {
        jsonError('Неверный ID ученика', 400);
    }

    if ($toDay < 1 || $toDay > 7) {
        jsonError('Неверный день недели', 400);
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $toTime)) {
        jsonError('Неверный формат времени', 400);
    }

    if ($toRoom < 1 || $toRoom > 3) {
        jsonError('Неверный номер кабинета', 400);
    }

    // Получаем текущие данные ученика
    $student = dbQueryOne(
        "SELECT id, name, class, schedule, teacher_id FROM students WHERE id = ? AND active = 1",
        [$studentId]
    );

    if (!$student) {
        jsonError('Ученик не найден', 404);
    }

    // Парсим текущее расписание
    $schedule = $student['schedule'] ? json_decode($student['schedule'], true) : [];
    if (!is_array($schedule)) {
        $schedule = [];
    }

    // Определяем предмет: берём из исходного слота или "Мат." по умолчанию
    $subject = 'Мат.';

    // Ищем исходный слот и его предмет
    $fromDayStr = (string)$fromDay;
    if (isset($schedule[$fromDayStr]) && is_array($schedule[$fromDayStr])) {
        foreach ($schedule[$fromDayStr] as $slot) {
            if (isset($slot['time']) && substr($slot['time'], 0, 5) === $fromTime) {
                $subject = $slot['subject'] ?? 'Мат.';
                break;
            }
        }
    } elseif (isset($schedule[$fromDay]) && is_array($schedule[$fromDay])) {
        foreach ($schedule[$fromDay] as $slot) {
            if (isset($slot['time']) && substr($slot['time'], 0, 5) === $fromTime) {
                $subject = $slot['subject'] ?? 'Мат.';
                break;
            }
        }
    }

    // Удаляем старый слот из расписания
    if ($fromDay && $fromTime) {
        $schedule = removeSlotFromSchedule($schedule, $fromDay, $fromTime, $fromRoom);
    }

    // Добавляем новый слот в расписание
    $schedule = addSlotToSchedule($schedule, $toDay, $toTime, $toRoom, $subject, $teacherId ?: $student['teacher_id']);

    // Сохраняем обновлённое расписание в БД
    try {
        $scheduleJson = json_encode($schedule, JSON_UNESCAPED_UNICODE);

        dbExecute(
            "UPDATE students SET schedule = ?, updated_at = NOW() WHERE id = ?",
            [$scheduleJson, $studentId]
        );

        // Проверяем/создаём lessons_template для нового слота
        ensureLessonTemplate($toDay, $toTime, $toRoom, $teacherId ?: $student['teacher_id'], $subject);

        // Проверяем и удаляем пустой шаблон из исходного слота
        if ($fromDay && $fromTime && $fromRoom) {
            cleanupEmptyTemplate($fromDay, $fromTime, $fromRoom);
        }

        // Логируем действие
        logAudit('student_schedule_moved', 'student', $studentId, [
            'from' => ['day' => $fromDay, 'time' => $fromTime, 'room' => $fromRoom],
        ], [
            'to' => ['day' => $toDay, 'time' => $toTime, 'room' => $toRoom],
            'subject' => $subject
        ], 'Ученик перемещён в планировщике');

        jsonSuccess([
            'message' => 'Ученик успешно перемещён',
            'student_id' => $studentId,
            'new_schedule' => $schedule
        ]);

    } catch (Exception $e) {
        error_log("Failed to move student: " . $e->getMessage());
        jsonError('Ошибка при обновлении расписания', 500);
    }
}

/**
 * Удалить слот из расписания ученика
 */
function removeSlotFromSchedule($schedule, $day, $time, $room) {
    $dayStr = (string)$day;
    $time = substr($time, 0, 5); // Убеждаемся что формат HH:MM

    // Проверяем оба варианта ключа (число и строка)
    $dayKey = isset($schedule[$dayStr]) ? $dayStr : (isset($schedule[$day]) ? $day : null);

    if ($dayKey === null || !is_array($schedule[$dayKey])) {
        return $schedule;
    }

    $daySchedule = $schedule[$dayKey];

    // Формат 3: массив объектов [{"time": "17:00", "room": 1, ...}, ...]
    if (isset($daySchedule[0]) && is_array($daySchedule[0])) {
        $newDaySchedule = [];
        foreach ($daySchedule as $slot) {
            $slotTime = substr($slot['time'] ?? '', 0, 5);
            $slotRoom = (int)($slot['room'] ?? 1);

            // Пропускаем слот если совпадает время и кабинет
            if ($slotTime === $time && $slotRoom === $room) {
                continue;
            }
            $newDaySchedule[] = $slot;
        }

        if (empty($newDaySchedule)) {
            unset($schedule[$dayKey]);
        } else {
            $schedule[$dayKey] = array_values($newDaySchedule);
        }
    }
    // Формат 1: одиночный объект {"time": "17:00", ...}
    elseif (isset($daySchedule['time'])) {
        $slotTime = substr($daySchedule['time'], 0, 5);
        $slotRoom = (int)($daySchedule['room'] ?? 1);

        if ($slotTime === $time && $slotRoom === $room) {
            unset($schedule[$dayKey]);
        }
    }
    // Формат 2: просто время "17:00"
    elseif (is_string($daySchedule)) {
        $slotTime = substr($daySchedule, 0, 5);
        if ($slotTime === $time) {
            unset($schedule[$dayKey]);
        }
    }

    return $schedule;
}

/**
 * Добавить слот в расписание ученика
 */
function addSlotToSchedule($schedule, $day, $time, $room, $subject, $teacherId) {
    $dayStr = (string)$day;
    $time = substr($time, 0, 5); // Убеждаемся что формат HH:MM

    $newSlot = [
        'time' => $time,
        'room' => (int)$room,
        'subject' => $subject,
        'teacher_id' => (int)$teacherId
    ];

    // Проверяем существует ли уже расписание для этого дня
    $dayKey = isset($schedule[$dayStr]) ? $dayStr : (isset($schedule[$day]) ? $day : $dayStr);

    if (!isset($schedule[$dayKey])) {
        // Нет расписания на этот день - создаём массив
        $schedule[$dayKey] = [$newSlot];
    } elseif (is_array($schedule[$dayKey])) {
        // Формат 3: массив слотов
        if (isset($schedule[$dayKey][0]) && is_array($schedule[$dayKey][0])) {
            // Проверяем нет ли уже такого слота
            $exists = false;
            foreach ($schedule[$dayKey] as $slot) {
                if (substr($slot['time'] ?? '', 0, 5) === $time && (int)($slot['room'] ?? 1) === (int)$room) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $schedule[$dayKey][] = $newSlot;
            }
        }
        // Формат 1: одиночный объект - конвертируем в массив
        elseif (isset($schedule[$dayKey]['time'])) {
            $existingSlot = $schedule[$dayKey];
            $schedule[$dayKey] = [$existingSlot, $newSlot];
        }
    }
    // Формат 2: просто время - конвертируем в формат 3
    elseif (is_string($schedule[$dayKey])) {
        $existingSlot = [
            'time' => $schedule[$dayKey],
            'room' => 1,
            'subject' => 'Мат.'
        ];
        $schedule[$dayKey] = [$existingSlot, $newSlot];
    }

    return $schedule;
}

/**
 * Убедиться что существует шаблон урока для данного слота
 * Если нет - создаём новый
 */
function ensureLessonTemplate($day, $time, $room, $teacherId, $subject) {
    // Формируем time_start и time_end
    $timeStart = $time . ':00';
    $hour = (int)substr($time, 0, 2);
    $timeEnd = sprintf('%02d:00:00', $hour + 1);

    // Проверяем существует ли уже шаблон
    $existing = dbQueryOne(
        "SELECT id FROM lessons_template
         WHERE day_of_week = ? AND time_start = ? AND room = ? AND active = 1",
        [$day, $timeStart, $room]
    );

    if ($existing) {
        // Шаблон уже есть - ничего не делаем
        return $existing['id'];
    }

    // Создаём новый шаблон
    // Преобразуем сокращённый предмет в полный
    $subjectMap = [
        'Мат.' => 'Математика',
        'Физ.' => 'Физика',
        'Инф.' => 'Информатика'
    ];
    $fullSubject = $subjectMap[$subject] ?? $subject;

    try {
        $templateId = dbExecute(
            "INSERT INTO lessons_template
             (teacher_id, day_of_week, room, time_start, time_end, lesson_type,
              subject, expected_students, tier, active)
             VALUES (?, ?, ?, ?, ?, 'group', ?, 6, 'C', 1)",
            [$teacherId, $day, $room, $timeStart, $timeEnd, $fullSubject]
        );

        if ($templateId) {
            logAudit('template_auto_created', 'template', $templateId, null, [
                'day' => $day,
                'time' => $timeStart,
                'room' => $room,
                'teacher_id' => $teacherId,
                'subject' => $fullSubject
            ], 'Шаблон создан автоматически из планировщика');
        }

        return $templateId;

    } catch (Exception $e) {
        error_log("Failed to create lesson template: " . $e->getMessage());
        // Не прерываем выполнение - перемещение ученика важнее
        return null;
    }
}

/**
 * Проверить и удалить пустой шаблон урока
 * Если в слоте больше нет учеников - деактивируем шаблон
 */
function cleanupEmptyTemplate($day, $time, $room) {
    $timeStart = substr($time, 0, 5) . ':00';

    // Проверяем есть ли ещё ученики в этом слоте
    $students = dbQuery("
        SELECT id, schedule FROM students WHERE active = 1 AND schedule IS NOT NULL
    ", []);

    $hasStudents = false;
    foreach ($students as $student) {
        $schedule = json_decode($student['schedule'], true);
        if (!is_array($schedule)) continue;

        // Проверяем оба варианта ключа
        $dayKey = isset($schedule[(string)$day]) ? (string)$day : (isset($schedule[$day]) ? $day : null);
        if ($dayKey === null) continue;

        $daySchedule = $schedule[$dayKey];

        // Формат 3: массив слотов
        if (is_array($daySchedule) && isset($daySchedule[0]) && is_array($daySchedule[0])) {
            foreach ($daySchedule as $slot) {
                $slotTime = substr($slot['time'] ?? '', 0, 5);
                $slotRoom = (int)($slot['room'] ?? 1);
                if ($slotTime === substr($time, 0, 5) && $slotRoom === (int)$room) {
                    $hasStudents = true;
                    break 2;
                }
            }
        }
        // Формат 1: одиночный объект
        elseif (is_array($daySchedule) && isset($daySchedule['time'])) {
            $slotTime = substr($daySchedule['time'], 0, 5);
            $slotRoom = (int)($daySchedule['room'] ?? 1);
            if ($slotTime === substr($time, 0, 5) && $slotRoom === (int)$room) {
                $hasStudents = true;
                break;
            }
        }
        // Формат 2: просто время
        elseif (is_string($daySchedule)) {
            $slotTime = substr($daySchedule, 0, 5);
            if ($slotTime === substr($time, 0, 5) && (int)$room === 1) {
                $hasStudents = true;
                break;
            }
        }
    }

    // Если учеников больше нет - деактивируем шаблон
    if (!$hasStudents) {
        try {
            $template = dbQueryOne(
                "SELECT id FROM lessons_template
                 WHERE day_of_week = ? AND time_start = ? AND room = ? AND active = 1",
                [$day, $timeStart, $room]
            );

            if ($template) {
                dbExecute(
                    "UPDATE lessons_template SET active = 0, updated_at = NOW() WHERE id = ?",
                    [$template['id']]
                );

                logAudit('template_auto_deactivated', 'template', $template['id'],
                    ['active' => 1],
                    ['active' => 0],
                    'Шаблон деактивирован автоматически (нет учеников)'
                );
            }
        } catch (Exception $e) {
            error_log("Failed to cleanup empty template: " . $e->getMessage());
        }
    }
}

/**
 * Получить полное расписание для отображения
 */
function handleGetSchedule() {
    // Получить всех активных учеников с расписанием
    $students = dbQuery("
        SELECT s.id, s.name, s.class, s.tier, s.teacher_id, s.schedule,
               t.name as teacher_name
        FROM students s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        WHERE s.active = 1 AND s.schedule IS NOT NULL
        ORDER BY s.name
    ", []);

    // Построить структуру данных
    $scheduleGrid = [];

    foreach ($students as $student) {
        if (!$student['schedule']) continue;

        $schedule = json_decode($student['schedule'], true);
        if (!is_array($schedule)) continue;

        foreach ($schedule as $dayKey => $daySchedule) {
            $day = (int)$dayKey;
            if ($day < 1 || $day > 7) continue;

            // Обрабатываем разные форматы расписания
            if (is_array($daySchedule)) {
                // Формат 3: [{"time": "17:00", "room": 1, "subject": "Мат."}, ...]
                if (isset($daySchedule[0]) && is_array($daySchedule[0])) {
                    foreach ($daySchedule as $slot) {
                        $time = substr($slot['time'] ?? '00:00', 0, 5);
                        $room = (int)($slot['room'] ?? 1);
                        $subject = $slot['subject'] ?? 'Мат.';

                        $key = "{$day}_{$time}_{$room}";
                        if (!isset($scheduleGrid[$key])) {
                            $scheduleGrid[$key] = [
                                'day' => $day,
                                'time' => $time,
                                'room' => $room,
                                'subject' => $subject,
                                'students' => []
                            ];
                        }
                        $scheduleGrid[$key]['students'][] = [
                            'id' => $student['id'],
                            'name' => $student['name'],
                            'class' => $student['class'],
                            'tier' => $student['tier'] ?? 'C',
                            'teacher_id' => $student['teacher_id'],
                            'teacher_name' => $student['teacher_name']
                        ];
                    }
                }
                // Формат 1: {"time": "17:00", "room": 1}
                elseif (isset($daySchedule['time'])) {
                    $time = substr($daySchedule['time'], 0, 5);
                    $room = (int)($daySchedule['room'] ?? 1);
                    $subject = $daySchedule['subject'] ?? 'Мат.';

                    $key = "{$day}_{$time}_{$room}";
                    if (!isset($scheduleGrid[$key])) {
                        $scheduleGrid[$key] = [
                            'day' => $day,
                            'time' => $time,
                            'room' => $room,
                            'subject' => $subject,
                            'students' => []
                        ];
                    }
                    $scheduleGrid[$key]['students'][] = [
                        'id' => $student['id'],
                        'name' => $student['name'],
                        'class' => $student['class'],
                        'tier' => $student['tier'] ?? 'C',
                        'teacher_id' => $student['teacher_id'],
                        'teacher_name' => $student['teacher_name']
                    ];
                }
            }
            // Формат 2: "17:00"
            elseif (is_string($daySchedule)) {
                $time = substr($daySchedule, 0, 5);
                $room = 1;

                $key = "{$day}_{$time}_{$room}";
                if (!isset($scheduleGrid[$key])) {
                    $scheduleGrid[$key] = [
                        'day' => $day,
                        'time' => $time,
                        'room' => $room,
                        'subject' => 'Математика',
                        'students' => []
                    ];
                }
                $scheduleGrid[$key]['students'][] = [
                    'id' => $student['id'],
                    'name' => $student['name'],
                    'class' => $student['class'],
                    'tier' => $student['tier'] ?? 'C',
                    'teacher_id' => $student['teacher_id'],
                    'teacher_name' => $student['teacher_name']
                ];
            }
        }
    }

    jsonSuccess([
        'schedule' => $scheduleGrid,
        'student_count' => count($students)
    ]);
}
