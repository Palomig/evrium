<?php
/**
 * External API для управления расписанием (для OpenClaw бота)
 * Авторизация через X-Api-Key заголовок (без session)
 *
 * Endpoints:
 *   GET  ?action=schedule          — полное расписание с учениками
 *   GET  ?action=students          — список всех учеников
 *   GET  ?action=teachers          — список преподавателей
 *   POST ?action=add_student       — добавить ученика в расписание
 *   POST ?action=remove_student    — убрать ученика из расписания
 *   POST ?action=move_student      — переместить ученика (убрать из одного слота, добавить в другой)
 *   POST ?action=add_extra_lesson  — добавить доп. урок (разовый, для доп. выплаты)
 *   POST ?action=add_template      — добавить шаблон урока
 *   POST ?action=delete_template   — удалить шаблон урока
 *   POST ?action=update_student    — обновить данные ученика (расписание, класс и т.д.)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/student_helpers.php';

header('Content-Type: application/json; charset=utf-8');

// ═══════════════════════════════════════════════════════════════════════════
// Авторизация по API-ключу
// ═══════════════════════════════════════════════════════════════════════════

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

if (empty($apiKey)) {
    jsonError('Missing X-Api-Key header', 401);
}

// Ключ: сначала проверяем файл, потом settings в БД
$storedKey = null;
$keyFile = __DIR__ . '/../config/api_key.php';
if (file_exists($keyFile)) {
    $storedKey = require $keyFile;
}
if (!$storedKey) {
    $storedKey = getSetting('external_api_key');
}

if (empty($storedKey) || !hash_equals($storedKey, $apiKey)) {
    jsonError('Invalid API key', 403);
}

// ═══════════════════════════════════════════════════════════════════════════
// Маршрутизация
// ═══════════════════════════════════════════════════════════════════════════

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'schedule':
        handleSchedule();
        break;
    case 'students':
        handleStudents();
        break;
    case 'teachers':
        handleTeachers();
        break;
    case 'add_student':
        handleAddStudent();
        break;
    case 'remove_student':
        handleRemoveStudent();
        break;
    case 'move_student':
        handleMoveStudent();
        break;
    case 'add_extra_lesson':
        handleAddExtraLesson();
        break;
    case 'add_template':
        handleAddTemplate();
        break;
    case 'delete_template':
        handleDeleteTemplate();
        break;
    case 'update_student':
        handleUpdateStudent();
        break;
    default:
        jsonError('Unknown action. Available: schedule, students, teachers, add_student, remove_student, move_student, add_extra_lesson, add_template, delete_template, update_student', 400);
}

// ═══════════════════════════════════════════════════════════════════════════
// READ endpoints
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Получить полное расписание с учениками
 */
function handleSchedule() {
    $templates = dbQuery(
        "SELECT lt.*, COALESCE(t.display_name, t.name) as teacher_name
         FROM lessons_template lt
         LEFT JOIN teachers t ON lt.teacher_id = t.id
         WHERE lt.active = 1
         ORDER BY lt.day_of_week ASC, lt.time_start ASC",
        []
    );

    $dayNames = [1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'];

    $result = [];
    foreach ($templates as $t) {
        // Динамически получаем учеников для этого слота
        $studentsData = getStudentsForLesson(
            $t['teacher_id'],
            $t['day_of_week'],
            substr($t['time_start'], 0, 5)
        );

        $studentNames = array_map(function($s) {
            $name = $s['name'];
            if ($s['class']) $name .= " ({$s['class']} кл.)";
            return $name;
        }, $studentsData['students']);

        $result[] = [
            'id' => (int)$t['id'],
            'day' => (int)$t['day_of_week'],
            'day_name' => $dayNames[$t['day_of_week']] ?? '?',
            'time_start' => substr($t['time_start'], 0, 5),
            'time_end' => substr($t['time_end'], 0, 5),
            'room' => (int)($t['room'] ?? 1),
            'teacher_id' => (int)$t['teacher_id'],
            'teacher_name' => $t['teacher_name'],
            'lesson_type' => $t['lesson_type'],
            'subject' => $t['subject'] ?? null,
            'tier' => $t['tier'] ?? 'C',
            'students' => $studentNames,
            'student_count' => $studentsData['count'],
        ];
    }

    jsonSuccess($result);
}

/**
 * Получить список учеников
 */
function handleStudents() {
    $students = dbQuery(
        "SELECT s.id, s.name, s.class, s.teacher_id, s.schedule, s.active, s.is_sick,
                s.lesson_type, s.tier, s.price_group, s.price_individual,
                COALESCE(t.display_name, t.name) as teacher_name
         FROM students s
         LEFT JOIN teachers t ON s.teacher_id = t.id
         ORDER BY s.active DESC, s.name ASC",
        []
    );

    // Парсим schedule JSON для каждого
    foreach ($students as &$s) {
        if ($s['schedule']) {
            $s['schedule'] = json_decode($s['schedule'], true);
        }
    }

    jsonSuccess($students);
}

/**
 * Получить список преподавателей
 */
function handleTeachers() {
    $teachers = dbQuery(
        "SELECT id, name, COALESCE(display_name, name) as display_name, active
         FROM teachers
         WHERE active = 1
         ORDER BY name ASC",
        []
    );

    jsonSuccess($teachers);
}

// ═══════════════════════════════════════════════════════════════════════════
// WRITE endpoints
// ═══════════════════════════════════════════════════════════════════════════

function getPostData() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!$data) $data = $_POST;
    return $data ?: [];
}

/**
 * Добавить ученика в расписание
 *
 * POST JSON:
 * {
 *   "student_name": "Иван Петров",
 *   "teacher_id": 5,
 *   "day": 1,
 *   "time": "17:00",
 *   "room": 1,
 *   "class": 8,
 *   "subject": "Мат."
 * }
 *
 * Если ученик не существует — создаёт нового.
 * Если существует — добавляет слот в его schedule.
 */
function handleAddStudent() {
    $data = getPostData();

    $studentName = trim($data['student_name'] ?? '');
    $teacherId = filter_var($data['teacher_id'] ?? 0, FILTER_VALIDATE_INT);
    $day = filter_var($data['day'] ?? 0, FILTER_VALIDATE_INT);
    $time = trim($data['time'] ?? '');
    $room = filter_var($data['room'] ?? 1, FILTER_VALIDATE_INT);
    $class = isset($data['class']) ? filter_var($data['class'], FILTER_VALIDATE_INT) : null;
    $subject = trim($data['subject'] ?? 'Мат.');

    if (!$studentName) jsonError('student_name is required', 400);
    if (!$teacherId) jsonError('teacher_id is required', 400);
    if ($day < 1 || $day > 7) jsonError('day must be 1-7', 400);
    if (!$time || !preg_match('/^\d{2}:\d{2}$/', $time)) jsonError('time must be HH:MM', 400);

    // Ищем или создаём ученика
    $student = dbQueryOne(
        "SELECT * FROM students WHERE name = ? AND active = 1",
        [$studentName]
    );

    if (!$student) {
        // Создаём нового ученика
        $schedule = json_encode([$day => [['time' => $time, 'teacher_id' => $teacherId, 'room' => $room, 'subject' => $subject]]]);
        $studentId = dbExecute(
            "INSERT INTO students (name, teacher_id, class, schedule, active) VALUES (?, ?, ?, ?, 1)",
            [$studentName, $teacherId, $class, $schedule]
        );
        $action = 'created';
    } else {
        // Добавляем слот в существующее расписание
        $studentId = $student['id'];
        $schedule = $student['schedule'] ? json_decode($student['schedule'], true) : [];

        if (!isset($schedule[$day])) {
            $schedule[$day] = [];
        }

        // Проверяем, нет ли уже такого слота
        $exists = false;
        foreach ($schedule[$day] as $slot) {
            if ($slot['time'] === $time && (int)($slot['teacher_id'] ?? 0) === $teacherId) {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            jsonSuccess(['message' => 'Ученик уже есть в этом слоте', 'student_id' => $studentId]);
            return;
        }

        $schedule[$day][] = ['time' => $time, 'teacher_id' => $teacherId, 'room' => $room, 'subject' => $subject];

        dbExecute(
            "UPDATE students SET schedule = ?, updated_at = NOW() WHERE id = ?",
            [json_encode($schedule), $studentId]
        );
        $action = 'slot_added';
    }

    // Убедимся, что шаблон урока существует
    ensureTemplate($teacherId, $day, $time, $room);

    logExternalAudit('student_schedule_add', 'student', $studentId, [
        'student_name' => $studentName,
        'day' => $day,
        'time' => $time,
        'teacher_id' => $teacherId,
    ]);

    jsonSuccess([
        'message' => $action === 'created'
            ? "Ученик '$studentName' создан и добавлен в расписание"
            : "Ученик '$studentName' добавлен в слот $day/$time",
        'student_id' => $studentId,
        'action' => $action,
    ]);
}

/**
 * Убрать ученика из расписания (из конкретного слота или полностью)
 *
 * POST JSON:
 * {
 *   "student_name": "Иван Петров",
 *   "day": 1,       // optional — если не указано, убирает из ВСЕХ слотов
 *   "time": "17:00"  // optional
 * }
 */
function handleRemoveStudent() {
    $data = getPostData();

    $studentName = trim($data['student_name'] ?? '');
    $day = isset($data['day']) ? filter_var($data['day'], FILTER_VALIDATE_INT) : null;
    $time = isset($data['time']) ? trim($data['time']) : null;

    if (!$studentName) jsonError('student_name is required', 400);

    $student = dbQueryOne(
        "SELECT * FROM students WHERE name = ? AND active = 1",
        [$studentName]
    );

    if (!$student) {
        jsonError("Ученик '$studentName' не найден", 404);
    }

    $schedule = $student['schedule'] ? json_decode($student['schedule'], true) : [];

    if ($day === null) {
        // Убираем полностью — очищаем расписание
        $schedule = [];
        $msg = "Ученик '$studentName' убран из всего расписания";
    } else {
        if (!isset($schedule[$day])) {
            jsonError("У ученика '$studentName' нет уроков в день $day", 400);
        }

        if ($time !== null) {
            // Убираем конкретный слот
            $schedule[$day] = array_values(array_filter($schedule[$day], function($slot) use ($time) {
                return $slot['time'] !== $time;
            }));
            if (empty($schedule[$day])) {
                unset($schedule[$day]);
            }
            $msg = "Ученик '$studentName' убран из слота день=$day время=$time";
        } else {
            // Убираем весь день
            unset($schedule[$day]);
            $msg = "Ученик '$studentName' убран из дня $day";
        }
    }

    $scheduleJson = !empty($schedule) ? json_encode($schedule) : null;

    dbExecute(
        "UPDATE students SET schedule = ?, updated_at = NOW() WHERE id = ?",
        [$scheduleJson, $student['id']]
    );

    logExternalAudit('student_schedule_remove', 'student', $student['id'], [
        'student_name' => $studentName,
        'day' => $day,
        'time' => $time,
    ]);

    jsonSuccess(['message' => $msg, 'student_id' => $student['id']]);
}

/**
 * Переместить ученика из одного слота в другой
 *
 * POST JSON:
 * {
 *   "student_name": "Иван Петров",
 *   "from_day": 1,
 *   "from_time": "17:00",
 *   "to_day": 3,
 *   "to_time": "18:00",
 *   "to_teacher_id": 5,  // optional, default = тот же
 *   "to_room": 1          // optional
 * }
 */
function handleMoveStudent() {
    $data = getPostData();

    $studentName = trim($data['student_name'] ?? '');
    $fromDay = filter_var($data['from_day'] ?? 0, FILTER_VALIDATE_INT);
    $fromTime = trim($data['from_time'] ?? '');
    $toDay = filter_var($data['to_day'] ?? 0, FILTER_VALIDATE_INT);
    $toTime = trim($data['to_time'] ?? '');
    $toTeacherId = isset($data['to_teacher_id']) ? filter_var($data['to_teacher_id'], FILTER_VALIDATE_INT) : null;
    $toRoom = isset($data['to_room']) ? filter_var($data['to_room'], FILTER_VALIDATE_INT) : 1;

    if (!$studentName) jsonError('student_name is required', 400);
    if (!$fromDay || !$fromTime) jsonError('from_day and from_time are required', 400);
    if (!$toDay || !$toTime) jsonError('to_day and to_time are required', 400);

    $student = dbQueryOne(
        "SELECT * FROM students WHERE name = ? AND active = 1",
        [$studentName]
    );

    if (!$student) {
        jsonError("Ученик '$studentName' не найден", 404);
    }

    $schedule = $student['schedule'] ? json_decode($student['schedule'], true) : [];

    // Находим и удаляем старый слот
    $oldSlot = null;
    if (isset($schedule[$fromDay])) {
        foreach ($schedule[$fromDay] as $i => $slot) {
            if ($slot['time'] === $fromTime) {
                $oldSlot = $slot;
                array_splice($schedule[$fromDay], $i, 1);
                if (empty($schedule[$fromDay])) unset($schedule[$fromDay]);
                break;
            }
        }
    }

    if (!$oldSlot) {
        jsonError("Слот день=$fromDay время=$fromTime не найден у ученика '$studentName'", 404);
    }

    // Добавляем новый слот
    $teacherId = $toTeacherId ?: ($oldSlot['teacher_id'] ?? $student['teacher_id']);
    $subject = $oldSlot['subject'] ?? 'Мат.';

    if (!isset($schedule[$toDay])) {
        $schedule[$toDay] = [];
    }

    $schedule[$toDay][] = [
        'time' => $toTime,
        'teacher_id' => $teacherId,
        'room' => $toRoom,
        'subject' => $subject,
    ];

    dbExecute(
        "UPDATE students SET schedule = ?, updated_at = NOW() WHERE id = ?",
        [json_encode($schedule), $student['id']]
    );

    ensureTemplate($teacherId, $toDay, $toTime, $toRoom);

    logExternalAudit('student_schedule_move', 'student', $student['id'], [
        'student_name' => $studentName,
        'from' => "$fromDay/$fromTime",
        'to' => "$toDay/$toTime",
    ]);

    jsonSuccess([
        'message' => "Ученик '$studentName' перемещён: день $fromDay $fromTime → день $toDay $toTime",
        'student_id' => $student['id'],
    ]);
}

/**
 * Добавить доп. урок (разовый) — создаёт lesson_instance для доп. выплаты
 *
 * POST JSON:
 * {
 *   "teacher_id": 5,
 *   "date": "2026-03-15",
 *   "time_start": "17:00",
 *   "time_end": "18:00",       // optional, default +1h
 *   "actual_students": 4,
 *   "subject": "Математика",
 *   "notes": "Доп. урок по просьбе родителей"
 * }
 */
function handleAddExtraLesson() {
    $data = getPostData();

    $teacherId = filter_var($data['teacher_id'] ?? 0, FILTER_VALIDATE_INT);
    $date = trim($data['date'] ?? '');
    $timeStart = trim($data['time_start'] ?? '');
    $timeEnd = trim($data['time_end'] ?? '');
    $actualStudents = filter_var($data['actual_students'] ?? 1, FILTER_VALIDATE_INT);
    $subject = trim($data['subject'] ?? 'Математика');
    $notes = trim($data['notes'] ?? 'Доп. урок (добавлен через бота)');

    if (!$teacherId) jsonError('teacher_id is required', 400);
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) jsonError('date must be YYYY-MM-DD', 400);
    if (!$timeStart) jsonError('time_start is required', 400);

    if (!$timeEnd) {
        $hour = intval(substr($timeStart, 0, 2));
        $timeEnd = sprintf('%02d:%02d', $hour + 1, intval(substr($timeStart, 3, 2)));
    }

    // Проверяем, что такого урока ещё нет
    $existing = dbQueryOne(
        "SELECT id FROM lessons_instance WHERE teacher_id = ? AND lesson_date = ? AND time_start = ?",
        [$teacherId, $date, $timeStart]
    );

    if ($existing) {
        jsonError("Урок уже существует: teacher=$teacherId, date=$date, time=$timeStart (id={$existing['id']})", 409);
    }

    // Ищем формулу преподавателя
    $teacher = dbQueryOne("SELECT * FROM teachers WHERE id = ? AND active = 1", [$teacherId]);
    if (!$teacher) {
        jsonError("Преподаватель с id=$teacherId не найден", 404);
    }

    $formulaId = $teacher['formula_id'] ?? null;

    // Создаём lesson_instance с status=completed
    $lessonId = dbExecute(
        "INSERT INTO lessons_instance
         (template_id, teacher_id, lesson_date, time_start, time_end,
          lesson_type, subject, expected_students, actual_students, formula_id, status, notes)
         VALUES (NULL, ?, ?, ?, ?, 'group', ?, ?, ?, ?, 'completed', ?)",
        [$teacherId, $date, $timeStart, $timeEnd, $subject, $actualStudents, $actualStudents, $formulaId, $notes]
    );

    if (!$lessonId) {
        jsonError('Не удалось создать урок', 500);
    }

    // Считаем выплату если есть формула
    $paymentAmount = 0;
    if ($formulaId) {
        $formula = dbQueryOne("SELECT * FROM payment_formulas WHERE id = ?", [$formulaId]);
        if ($formula) {
            $paymentAmount = calculatePayment($formula, $actualStudents);
        }
    }

    // Создаём выплату
    if ($paymentAmount > 0) {
        dbExecute(
            "INSERT INTO payments (teacher_id, lesson_instance_id, amount, payment_type, status, notes)
             VALUES (?, ?, ?, 'lesson', 'pending', ?)",
            [$teacherId, $lessonId, $paymentAmount, $notes]
        );
    }

    logExternalAudit('extra_lesson_added', 'lesson', $lessonId, [
        'teacher_id' => $teacherId,
        'date' => $date,
        'time' => $timeStart,
        'students' => $actualStudents,
        'payment' => $paymentAmount,
    ]);

    jsonSuccess([
        'message' => "Доп. урок создан: $date $timeStart, $actualStudents уч., выплата: {$paymentAmount}₽",
        'lesson_id' => $lessonId,
        'payment_amount' => $paymentAmount,
    ]);
}

/**
 * Добавить шаблон урока
 *
 * POST JSON:
 * {
 *   "teacher_id": 5,
 *   "day": 1,
 *   "time_start": "17:00",
 *   "time_end": "18:00",       // optional, default +1h
 *   "room": 1,
 *   "lesson_type": "group",
 *   "subject": "Математика",
 *   "expected_students": 6
 * }
 */
function handleAddTemplate() {
    $data = getPostData();

    $teacherId = filter_var($data['teacher_id'] ?? 0, FILTER_VALIDATE_INT);
    $day = filter_var($data['day'] ?? 0, FILTER_VALIDATE_INT);
    $timeStart = trim($data['time_start'] ?? '');
    $timeEnd = trim($data['time_end'] ?? '');
    $room = filter_var($data['room'] ?? 1, FILTER_VALIDATE_INT);
    $lessonType = $data['lesson_type'] ?? 'group';
    $subject = trim($data['subject'] ?? '');
    $expectedStudents = filter_var($data['expected_students'] ?? 6, FILTER_VALIDATE_INT);

    if (!$teacherId) jsonError('teacher_id is required', 400);
    if ($day < 1 || $day > 7) jsonError('day must be 1-7', 400);
    if (!$timeStart) jsonError('time_start is required', 400);

    if (!$timeEnd) {
        $hour = intval(substr($timeStart, 0, 2));
        $timeEnd = sprintf('%02d:%02d', $hour + 1, intval(substr($timeStart, 3, 2)));
    }

    // Проверяем дубликат
    $existing = dbQueryOne(
        "SELECT id FROM lessons_template WHERE teacher_id = ? AND day_of_week = ? AND time_start = ? AND room = ? AND active = 1",
        [$teacherId, $day, $timeStart, $room]
    );

    if ($existing) {
        jsonError("Шаблон уже существует: teacher=$teacherId, day=$day, time=$timeStart, room=$room (id={$existing['id']})", 409);
    }

    $templateId = dbExecute(
        "INSERT INTO lessons_template (teacher_id, day_of_week, room, time_start, time_end, lesson_type, subject, expected_students, active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)",
        [$teacherId, $day, $room, $timeStart, $timeEnd, $lessonType, $subject ?: null, $expectedStudents]
    );

    logExternalAudit('template_created_external', 'template', $templateId, [
        'teacher_id' => $teacherId,
        'day' => $day,
        'time' => $timeStart,
    ]);

    jsonSuccess([
        'message' => "Шаблон создан: день $day, $timeStart, каб. $room",
        'template_id' => $templateId,
    ]);
}

/**
 * Удалить шаблон урока (soft delete)
 *
 * POST JSON: { "id": 42 }
 */
function handleDeleteTemplate() {
    $data = getPostData();
    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id) jsonError('id is required', 400);

    $template = dbQueryOne("SELECT * FROM lessons_template WHERE id = ? AND active = 1", [$id]);
    if (!$template) {
        jsonError("Шаблон id=$id не найден", 404);
    }

    dbExecute("UPDATE lessons_template SET active = 0, updated_at = NOW() WHERE id = ?", [$id]);

    logExternalAudit('template_deleted_external', 'template', $id, [
        'day' => $template['day_of_week'],
        'time' => $template['time_start'],
    ]);

    jsonSuccess(['message' => "Шаблон id=$id удалён"]);
}

/**
 * Обновить данные ученика
 *
 * POST JSON:
 * {
 *   "student_name": "Иван Петров",  // или "student_id": 42
 *   "class": 9,                      // optional
 *   "schedule": { "1": [...], ... }   // optional — полная замена расписания
 *   "active": 1                       // optional
 * }
 */
function handleUpdateStudent() {
    $data = getPostData();

    $studentId = isset($data['student_id']) ? filter_var($data['student_id'], FILTER_VALIDATE_INT) : null;
    $studentName = trim($data['student_name'] ?? '');

    if (!$studentId && !$studentName) {
        jsonError('student_id or student_name is required', 400);
    }

    if ($studentId) {
        $student = dbQueryOne("SELECT * FROM students WHERE id = ?", [$studentId]);
    } else {
        $student = dbQueryOne("SELECT * FROM students WHERE name = ? AND active = 1", [$studentName]);
    }

    if (!$student) {
        jsonError('Ученик не найден', 404);
    }

    $updates = [];
    $params = [];

    if (isset($data['class'])) {
        $updates[] = 'class = ?';
        $params[] = filter_var($data['class'], FILTER_VALIDATE_INT) ?: null;
    }

    if (isset($data['schedule'])) {
        $updates[] = 'schedule = ?';
        $params[] = is_string($data['schedule']) ? $data['schedule'] : json_encode($data['schedule']);
    }

    if (isset($data['active'])) {
        $updates[] = 'active = ?';
        $params[] = $data['active'] ? 1 : 0;
    }

    if (isset($data['name'])) {
        $updates[] = 'name = ?';
        $params[] = trim($data['name']);
    }

    if (empty($updates)) {
        jsonError('No fields to update', 400);
    }

    $updates[] = 'updated_at = NOW()';
    $params[] = $student['id'];

    dbExecute(
        "UPDATE students SET " . implode(', ', $updates) . " WHERE id = ?",
        $params
    );

    logExternalAudit('student_updated_external', 'student', $student['id'], array_keys($data));

    $updated = dbQueryOne("SELECT * FROM students WHERE id = ?", [$student['id']]);
    if ($updated['schedule']) {
        $updated['schedule'] = json_decode($updated['schedule'], true);
    }

    jsonSuccess($updated);
}

// ═══════════════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Убедиться, что шаблон урока существует для данного слота
 */
function ensureTemplate($teacherId, $day, $time, $room = 1) {
    $existing = dbQueryOne(
        "SELECT id FROM lessons_template WHERE teacher_id = ? AND day_of_week = ? AND time_start = ? AND room = ? AND active = 1",
        [$teacherId, $day, $time, $room]
    );

    if (!$existing) {
        $hour = intval(substr($time, 0, 2));
        $timeEnd = sprintf('%02d:%02d', $hour + 1, intval(substr($time, 3, 2)));

        dbExecute(
            "INSERT INTO lessons_template (teacher_id, day_of_week, room, time_start, time_end, lesson_type, expected_students, active)
             VALUES (?, ?, ?, ?, ?, 'group', 6, 1)",
            [$teacherId, $day, $room, $time, $timeEnd]
        );
    }
}

/**
 * Логирование действий внешнего API
 */
function logExternalAudit($action, $entityType, $entityId, $details) {
    try {
        dbExecute(
            "INSERT INTO audit_log (action_type, entity_type, entity_id, user_id, new_value, notes)
             VALUES (?, ?, ?, NULL, ?, 'external_api')",
            [$action, $entityType, $entityId, json_encode($details)]
        );
    } catch (Exception $e) {
        error_log("[External API] Audit log error: " . $e->getMessage());
    }
}
