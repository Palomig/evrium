<?php
/**
 * API для управления учениками
 * Система учёта зарплаты преподавателей
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
    case 'list':
        handleList();
        break;
    case 'get':
        handleGet();
        break;
    case 'add':
        handleAdd();
        break;
    case 'update':
        handleUpdate();
        break;
    case 'delete':
        handleDelete();
        break;
    case 'toggle_active':
        handleToggleActive();
        break;
    default:
        jsonError('Неизвестное действие', 400);
}

/**
 * Получить список всех учеников
 */
function handleList() {
    $students = dbQuery(
        "SELECT * FROM students ORDER BY active DESC, name ASC",
        []
    );

    jsonSuccess($students);
}

/**
 * Получить одного ученика
 */
function handleGet() {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID ученика', 400);
    }

    $student = dbQueryOne(
        "SELECT * FROM students WHERE id = ?",
        [$id]
    );

    if (!$student) {
        jsonError('Ученик не найден', 404);
    }

    jsonSuccess($student);
}

/**
 * Добавить нового ученика
 */
function handleAdd() {
    // Получаем данные из POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    // Валидация обязательных полей
    $name = trim($data['name'] ?? '');

    if (empty($name)) {
        jsonError('ФИО ученика обязательно', 400);
    }

    if (mb_strlen($name) > 100) {
        jsonError('ФИО слишком длинное (максимум 100 символов)', 400);
    }

    // Преподаватель (обязательно)
    $teacherId = filter_var($data['teacher_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$teacherId) {
        jsonError('Выберите преподавателя', 400);
    }

    // Проверяем существование преподавателя
    $teacher = dbQueryOne("SELECT id FROM teachers WHERE id = ? AND active = 1", [$teacherId]);
    if (!$teacher) {
        jsonError('Преподаватель не найден или неактивен', 404);
    }

    // Остальные поля
    $parentName = trim($data['parent_name'] ?? '');
    $notes = trim($data['notes'] ?? '');

    // Мессенджеры ученика
    $studentTelegram = trim($data['student_telegram'] ?? '');
    $studentWhatsapp = trim($data['student_whatsapp'] ?? '');

    // Мессенджеры родителя
    $parentTelegram = trim($data['parent_telegram'] ?? '');
    $parentWhatsapp = trim($data['parent_whatsapp'] ?? '');

    // Класс (может быть NULL)
    $class = null;
    if (isset($data['class']) && $data['class'] !== '') {
        $class = filter_var($data['class'], FILTER_VALIDATE_INT);
        if ($class === false) {
            jsonError('Неверный формат класса', 400);
        }
    }

    // Тир (уровень ученика)
    $tier = $data['tier'] ?? 'C';
    if (!in_array($tier, ['S', 'A', 'B', 'C', 'D'])) {
        jsonError('Неверный тир (должен быть S, A, B, C или D)', 400);
    }

    // Тип занятия
    $lessonType = $data['lesson_type'] ?? 'group';
    if (!in_array($lessonType, ['group', 'individual'])) {
        jsonError('Неверный тип занятия', 400);
    }

    // Цены
    $priceGroup = filter_var($data['price_group'] ?? 5000, FILTER_VALIDATE_INT);
    $priceIndividual = filter_var($data['price_individual'] ?? 1500, FILTER_VALIDATE_INT);
    if ($priceGroup === false || $priceGroup < 0) {
        jsonError('Неверная цена для групповых занятий', 400);
    }
    if ($priceIndividual === false || $priceIndividual < 0) {
        jsonError('Неверная цена для индивидуальных занятий', 400);
    }

    // Типы оплаты
    $paymentTypeGroup = $data['payment_type_group'] ?? 'monthly';
    $paymentTypeIndividual = $data['payment_type_individual'] ?? 'per_lesson';
    if (!in_array($paymentTypeGroup, ['monthly', 'per_lesson'])) {
        jsonError('Неверный тип оплаты для групповых занятий', 400);
    }
    if (!in_array($paymentTypeIndividual, ['monthly', 'per_lesson'])) {
        jsonError('Неверный тип оплаты для индивидуальных занятий', 400);
    }

    // Расписание (JSON)
    $schedule = $data['schedule'] ?? null;

    // Создаем ученика
    try {
        // Пробуем вставить с новыми полями
        try {
            $studentId = dbExecute(
                "INSERT INTO students (name, teacher_id, tier, student_telegram, student_whatsapp, parent_name, parent_telegram, parent_whatsapp, class, lesson_type, price_group, price_individual, payment_type_group, payment_type_individual, schedule, notes, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                [$name, $teacherId, $tier, $studentTelegram ?: null, $studentWhatsapp ?: null, $parentName ?: null, $parentTelegram ?: null, $parentWhatsapp ?: null, $class, $lessonType, $priceGroup, $priceIndividual, $paymentTypeGroup, $paymentTypeIndividual, $schedule, $notes ?: null]
            );
        } catch (PDOException $e) {
            // Если новых полей еще нет в базе, используем минимальный набор
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                $studentId = dbExecute(
                    "INSERT INTO students (name, teacher_id, tier, class, notes, active)
                     VALUES (?, ?, ?, ?, ?, 1)",
                    [$name, $teacherId, $tier, $class, $notes ?: null]
                );
            } else {
                throw $e;
            }
        }

        if ($studentId) {
            // Логируем создание
            logAudit('student_created', 'student', $studentId, null, [
                'name' => $name,
                'class' => $class,
                'lesson_type' => $lessonType,
                'tier' => $tier
            ], 'Создан новый ученик');

            // Автоматически добавляем в расписание
            if ($schedule) {
                $scheduleData = json_decode($schedule, true);
                if ($scheduleData && is_array($scheduleData)) {
                    foreach ($scheduleData as $dayOfWeek => $time) {
                        if ($time && is_numeric($dayOfWeek)) {
                            // Разбираем время на start и end (предполагаем 1.5 часа урок)
                            $timeStart = $time;
                            $timeEnd = date('H:i', strtotime($time) + 5400); // +1.5 часа

                            // Получаем текущий список учеников для этого шаблона (если есть)
                            $existingTemplate = dbQueryOne(
                                "SELECT id, students FROM lessons_template
                                 WHERE teacher_id = ? AND day_of_week = ? AND time_start = ? AND lesson_type = ? AND tier = ? AND active = 1",
                                [$teacherId, $dayOfWeek, $timeStart, $lessonType, $tier]
                            );

                            if ($existingTemplate) {
                                // Шаблон уже существует - добавляем ученика в список
                                $studentsList = $existingTemplate['students'] ? json_decode($existingTemplate['students'], true) : [];
                                if (!in_array($name, $studentsList)) {
                                    $studentsList[] = $name;
                                    dbExecute(
                                        "UPDATE lessons_template SET students = ?, updated_at = NOW() WHERE id = ?",
                                        [json_encode($studentsList), $existingTemplate['id']]
                                    );
                                }
                            } else {
                                // Создаем новый шаблон
                                dbExecute(
                                    "INSERT INTO lessons_template (teacher_id, day_of_week, time_start, time_end, lesson_type, tier, expected_students, students, active)
                                     VALUES (?, ?, ?, ?, ?, ?, 1, ?, 1)",
                                    [$teacherId, $dayOfWeek, $timeStart, $timeEnd, $lessonType, $tier, json_encode([$name])]
                                );
                            }
                        }
                    }
                }
            }

            // Возвращаем созданного ученика
            $student = dbQueryOne("SELECT * FROM students WHERE id = ?", [$studentId]);
            jsonSuccess($student);
        } else {
            jsonError('Не удалось создать ученика', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to create student: " . $e->getMessage());
        jsonError('Ошибка при создании ученика', 500);
    }
}

/**
 * Обновить данные ученика
 */
function handleUpdate() {
    // Получаем данные из POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID ученика', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM students WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Ученик не найден', 404);
    }

    // Валидация
    $name = trim($data['name'] ?? '');

    if (empty($name)) {
        jsonError('ФИО ученика обязательно', 400);
    }

    if (mb_strlen($name) > 100) {
        jsonError('ФИО слишком длинное (максимум 100 символов)', 400);
    }

    // Преподаватель (обязательно)
    $teacherId = filter_var($data['teacher_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$teacherId) {
        jsonError('Выберите преподавателя', 400);
    }

    // Проверяем существование преподавателя
    $teacher = dbQueryOne("SELECT id FROM teachers WHERE id = ? AND active = 1", [$teacherId]);
    if (!$teacher) {
        jsonError('Преподаватель не найден или неактивен', 404);
    }

    // Остальные поля
    $parentName = trim($data['parent_name'] ?? '');
    $notes = trim($data['notes'] ?? '');

    // Мессенджеры ученика
    $studentTelegram = trim($data['student_telegram'] ?? '');
    $studentWhatsapp = trim($data['student_whatsapp'] ?? '');

    // Мессенджеры родителя
    $parentTelegram = trim($data['parent_telegram'] ?? '');
    $parentWhatsapp = trim($data['parent_whatsapp'] ?? '');

    // Класс (может быть NULL)
    $class = null;
    if (isset($data['class']) && $data['class'] !== '') {
        $class = filter_var($data['class'], FILTER_VALIDATE_INT);
        if ($class === false) {
            jsonError('Неверный формат класса', 400);
        }
    }

    // Тир (уровень ученика)
    $tier = $data['tier'] ?? 'C';
    if (!in_array($tier, ['S', 'A', 'B', 'C', 'D'])) {
        jsonError('Неверный тир (должен быть S, A, B, C или D)', 400);
    }

    // Тип занятия
    $lessonType = $data['lesson_type'] ?? 'group';
    if (!in_array($lessonType, ['group', 'individual'])) {
        jsonError('Неверный тип занятия', 400);
    }

    // Цены
    $priceGroup = filter_var($data['price_group'] ?? 5000, FILTER_VALIDATE_INT);
    $priceIndividual = filter_var($data['price_individual'] ?? 1500, FILTER_VALIDATE_INT);
    if ($priceGroup === false || $priceGroup < 0) {
        jsonError('Неверная цена для групповых занятий', 400);
    }
    if ($priceIndividual === false || $priceIndividual < 0) {
        jsonError('Неверная цена для индивидуальных занятий', 400);
    }

    // Типы оплаты
    $paymentTypeGroup = $data['payment_type_group'] ?? 'monthly';
    $paymentTypeIndividual = $data['payment_type_individual'] ?? 'per_lesson';
    if (!in_array($paymentTypeGroup, ['monthly', 'per_lesson'])) {
        jsonError('Неверный тип оплаты для групповых занятий', 400);
    }
    if (!in_array($paymentTypeIndividual, ['monthly', 'per_lesson'])) {
        jsonError('Неверный тип оплаты для индивидуальных занятий', 400);
    }

    // Расписание (JSON)
    $schedule = $data['schedule'] ?? null;

    // Обновляем ученика
    try {
        // Пробуем обновить с новыми полями
        try {
            $result = dbExecute(
                "UPDATE students
                 SET name = ?, teacher_id = ?, tier = ?, student_telegram = ?, student_whatsapp = ?, parent_name = ?, parent_telegram = ?, parent_whatsapp = ?, class = ?, lesson_type = ?, price_group = ?, price_individual = ?, payment_type_group = ?, payment_type_individual = ?, schedule = ?, notes = ?, updated_at = NOW()
                 WHERE id = ?",
                [$name, $teacherId, $tier, $studentTelegram ?: null, $studentWhatsapp ?: null, $parentName ?: null, $parentTelegram ?: null, $parentWhatsapp ?: null, $class, $lessonType, $priceGroup, $priceIndividual, $paymentTypeGroup, $paymentTypeIndividual, $schedule, $notes ?: null, $id]
            );
        } catch (PDOException $e) {
            // Если новых полей еще нет в базе, используем минимальный набор
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                $result = dbExecute(
                    "UPDATE students
                     SET name = ?, teacher_id = ?, tier = ?, class = ?, notes = ?, updated_at = NOW()
                     WHERE id = ?",
                    [$name, $teacherId, $tier, $class, $notes ?: null, $id]
                );
            } else {
                throw $e;
            }
        }

        if ($result !== false) {
            // Логируем изменение
            logAudit('student_updated', 'student', $id, $existing, [
                'name' => $name,
                'teacher_id' => $teacherId,
                'tier' => $tier,
                'class' => $class,
                'lesson_type' => $lessonType
            ], 'Обновлены данные ученика');

            // Возвращаем обновленного ученика
            $student = dbQueryOne("SELECT * FROM students WHERE id = ?", [$id]);
            jsonSuccess($student);
        } else {
            jsonError('Не удалось обновить ученика', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to update student: " . $e->getMessage());
        jsonError('Ошибка при обновлении ученика', 500);
    }
}

/**
 * Удалить ученика (на самом деле деактивировать)
 */
function handleDelete() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID ученика', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM students WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Ученик не найден', 404);
    }

    // Проверяем, есть ли связанные данные (посещаемость)
    $attendanceCount = dbQueryOne(
        "SELECT COUNT(*) as count FROM attendance_log WHERE student_id = ?",
        [$id]
    );

    if ($attendanceCount['count'] > 0) {
        // Если есть записи посещаемости - деактивируем, а не удаляем
        $result = dbExecute(
            "UPDATE students SET active = 0, updated_at = NOW() WHERE id = ?",
            [$id]
        );

        if ($result !== false) {
            logAudit('student_deactivated', 'student', $id, $existing, ['active' => 0], 'Ученик деактивирован');
            jsonSuccess(['message' => 'Ученик деактивирован (есть связанные записи посещаемости)']);
        } else {
            jsonError('Не удалось деактивировать ученика', 500);
        }
    } else {
        // Если нет записей - можно удалить
        $result = dbExecute("DELETE FROM students WHERE id = ?", [$id]);

        if ($result) {
            logAudit('student_deleted', 'student', $id, $existing, null, 'Ученик удалён');
            jsonSuccess(['message' => 'Ученик удалён']);
        } else {
            jsonError('Не удалось удалить ученика', 500);
        }
    }
}

/**
 * Переключить активность ученика
 */
function handleToggleActive() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID ученика', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM students WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Ученик не найден', 404);
    }

    // Переключаем активность
    $newActive = $existing['active'] ? 0 : 1;
    $result = dbExecute(
        "UPDATE students SET active = ?, updated_at = NOW() WHERE id = ?",
        [$newActive, $id]
    );

    if ($result !== false) {
        logAudit(
            $newActive ? 'student_activated' : 'student_deactivated',
            'student',
            $id,
            ['active' => $existing['active']],
            ['active' => $newActive],
            $newActive ? 'Ученик активирован' : 'Ученик деактивирован'
        );

        $student = dbQueryOne("SELECT * FROM students WHERE id = ?", [$id]);
        jsonSuccess($student);
    } else {
        jsonError('Не удалось изменить статус ученика', 500);
    }
}
