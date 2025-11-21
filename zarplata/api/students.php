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

    try {
        $student = dbQueryOne(
            "SELECT * FROM students WHERE id = ?",
            [$id]
        );

        if (!$student) {
            jsonError('Ученик не найден', 404);
        }

        // Убеждаемся, что schedule - это валидный JSON или NULL
        if (isset($student['schedule']) && $student['schedule']) {
            $testDecode = json_decode($student['schedule'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Student {$id} has invalid schedule JSON: {$student['schedule']}");
                $student['schedule'] = null;
            }
        }

        jsonSuccess($student);
    } catch (Exception $e) {
        error_log("Error in handleGet for student {$id}: " . $e->getMessage());
        jsonError('Ошибка при загрузке ученика: ' . $e->getMessage(), 500);
    }
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

    // Примечание: teacher_id теперь указывается в расписании для каждого урока,
    // но для обратной совместимости оставляем поле в базе (поле NOT NULL в БД)
    $teacherId = null;
    if (isset($data['teacher_id']) && $data['teacher_id']) {
        $teacherId = filter_var($data['teacher_id'], FILTER_VALIDATE_INT);
    }

    // Если teacher_id не указан, пытаемся извлечь из расписания
    if (!$teacherId && isset($data['schedule']) && $data['schedule']) {
        $scheduleData = json_decode($data['schedule'], true);
        if ($scheduleData && is_array($scheduleData)) {
            foreach ($scheduleData as $day => $lessons) {
                if (is_array($lessons)) {
                    foreach ($lessons as $lesson) {
                        if (isset($lesson['teacher_id']) && $lesson['teacher_id']) {
                            $teacherId = intval($lesson['teacher_id']);
                            break 2;  // Выходим из обоих циклов
                        }
                    }
                }
            }
        }
    }

    // Если всё ещё NULL, берём первого активного преподавателя
    if (!$teacherId) {
        $firstTeacher = dbQueryOne("SELECT id FROM teachers WHERE active = 1 ORDER BY id LIMIT 1");
        if ($firstTeacher) {
            $teacherId = $firstTeacher['id'];
        } else {
            jsonError('Не найдено активных преподавателей в системе', 400);
        }
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
                error_log("Processing schedule for new student '$name': " . json_encode($scheduleData));

                if ($scheduleData && is_array($scheduleData)) {
                    foreach ($scheduleData as $dayOfWeek => $lessons) {
                        if (!is_numeric($dayOfWeek)) continue;

                        error_log("Processing day $dayOfWeek, lessons: " . json_encode($lessons));

                        // Новый формат: lessons - это массив объектов { time, teacher_id }
                        if (is_array($lessons)) {
                            error_log("Day $dayOfWeek has " . count($lessons) . " lessons");

                            foreach ($lessons as $lessonIndex => $lesson) {
                                error_log("Processing lesson index $lessonIndex: " . json_encode($lesson));

                                // Проверяем формат
                                if (!is_array($lesson) || !isset($lesson['time']) || !isset($lesson['teacher_id'])) {
                                    // Старый формат или некорректные данные - пропускаем
                                    error_log("Skipping lesson $lessonIndex - invalid format or missing fields");
                                    continue;
                                }

                                $timeStart = $lesson['time'];
                                $lessonTeacherId = filter_var($lesson['teacher_id'], FILTER_VALIDATE_INT);

                                if (!$lessonTeacherId || !$timeStart) {
                                    error_log("Skipping lesson $lessonIndex - invalid teacher_id ($lessonTeacherId) or time ($timeStart)");
                                    continue;
                                }

                                error_log("Valid lesson: day=$dayOfWeek, time=$timeStart, teacher_id=$lessonTeacherId, lesson_type=$lessonType");

                                // Разбираем время на start и end (предполагаем 1 час урок)
                                $timeEnd = date('H:i', strtotime($timeStart) + 3600); // +1 час

                                // Получаем текущий список учеников для этого шаблона (если есть)
                                // Тир ученика НЕ влияет на выбор группы - группируем по времени, типу и преподавателю
                                $existingTemplate = dbQueryOne(
                                    "SELECT id, students, tier, expected_students FROM lessons_template
                                     WHERE teacher_id = ? AND day_of_week = ? AND time_start = ? AND lesson_type = ? AND active = 1",
                                    [$lessonTeacherId, $dayOfWeek, $timeStart, $lessonType]
                                );

                                if ($existingTemplate) {
                                    // Шаблон уже существует - добавляем ученика в список
                                    $studentsList = $existingTemplate['students'] ? json_decode($existingTemplate['students'], true) : [];
                                    if (!in_array($name, $studentsList)) {
                                        $studentsList[] = $name;
                                        // НЕ меняем expected_students - он остается 6 (или каким был установлен)
                                        dbExecute(
                                            "UPDATE lessons_template SET students = ?, updated_at = NOW() WHERE id = ?",
                                            [json_encode($studentsList), $existingTemplate['id']]
                                        );
                                        error_log("Added student '$name' (tier $tier) to existing template ID {$existingTemplate['id']} for teacher $lessonTeacherId, expected_students remains {$existingTemplate['expected_students']}, now has " . count($studentsList) . " students");
                                    }
                                } else {
                                    // Создаем новый шаблон
                                    // Для групповых занятий - 6 мест, для индивидуальных - 1 место
                                    $expectedStudents = ($lessonType === 'group') ? 6 : 1;
                                    // Tier группы по умолчанию 'C', не зависит от tier ученика
                                    dbExecute(
                                        "INSERT INTO lessons_template (teacher_id, day_of_week, time_start, time_end, lesson_type, tier, expected_students, students, active)
                                         VALUES (?, ?, ?, ?, ?, 'C', ?, ?, 1)",
                                        [$lessonTeacherId, $dayOfWeek, $timeStart, $timeEnd, $lessonType, $expectedStudents, json_encode([$name])]
                                    );
                                    error_log("Created new template for teacher $lessonTeacherId, day $dayOfWeek, time $timeStart, type $lessonType with student '$name' (tier $tier), expected_students=$expectedStudents");
                                }
                            }
                        } else {
                            // Старый формат: lessons это просто время (строка) - обратная совместимость
                            if ($teacherId && $lessons) {
                                $timeStart = $lessons;
                                $timeEnd = date('H:i', strtotime($timeStart) + 3600);

                                $existingTemplate = dbQueryOne(
                                    "SELECT id, students, tier, expected_students FROM lessons_template
                                     WHERE teacher_id = ? AND day_of_week = ? AND time_start = ? AND lesson_type = ? AND active = 1",
                                    [$teacherId, $dayOfWeek, $timeStart, $lessonType]
                                );

                                if ($existingTemplate) {
                                    $studentsList = $existingTemplate['students'] ? json_decode($existingTemplate['students'], true) : [];
                                    if (!in_array($name, $studentsList)) {
                                        $studentsList[] = $name;
                                        dbExecute(
                                            "UPDATE lessons_template SET students = ?, updated_at = NOW() WHERE id = ?",
                                            [json_encode($studentsList), $existingTemplate['id']]
                                        );
                                    }
                                } else {
                                    $expectedStudents = ($lessonType === 'group') ? 6 : 1;
                                    dbExecute(
                                        "INSERT INTO lessons_template (teacher_id, day_of_week, time_start, time_end, lesson_type, tier, expected_students, students, active)
                                         VALUES (?, ?, ?, ?, ?, 'C', ?, ?, 1)",
                                        [$teacherId, $dayOfWeek, $timeStart, $timeEnd, $lessonType, $expectedStudents, json_encode([$name])]
                                    );
                                }
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

    // Примечание: teacher_id теперь указывается в расписании для каждого урока,
    // но для обратной совместимости оставляем поле в базе (поле NOT NULL в БД)
    $teacherId = null;
    if (isset($data['teacher_id']) && $data['teacher_id']) {
        $teacherId = filter_var($data['teacher_id'], FILTER_VALIDATE_INT);
    }

    // Если teacher_id не указан, пытаемся извлечь из расписания
    if (!$teacherId && isset($data['schedule']) && $data['schedule']) {
        $scheduleData = json_decode($data['schedule'], true);
        if ($scheduleData && is_array($scheduleData)) {
            foreach ($scheduleData as $day => $lessons) {
                if (is_array($lessons)) {
                    foreach ($lessons as $lesson) {
                        if (isset($lesson['teacher_id']) && $lesson['teacher_id']) {
                            $teacherId = intval($lesson['teacher_id']);
                            break 2;  // Выходим из обоих циклов
                        }
                    }
                }
            }
        }
    }

    // Если всё ещё NULL, берём первого активного преподавателя
    if (!$teacherId) {
        $firstTeacher = dbQueryOne("SELECT id FROM teachers WHERE active = 1 ORDER BY id LIMIT 1");
        if ($firstTeacher) {
            $teacherId = $firstTeacher['id'];
        } else {
            jsonError('Не найдено активных преподавателей в системе', 400);
        }
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

            // ВАЖНО: Синхронизируем расписание с шаблонами
            // Если имя ученика изменилось, обновляем его во всех шаблонах
            $oldName = $existing['name'];
            if ($oldName !== $name) {
                error_log("Student name changed from '$oldName' to '$name', updating templates");
                // Обновляем имя во всех шаблонах
                $templates = dbQuery("SELECT id, students FROM lessons_template WHERE active = 1");
                foreach ($templates as $template) {
                    if (!$template['students']) continue;

                    try {
                        $studentsList = json_decode($template['students'], true);
                        if (!is_array($studentsList)) continue;

                        $index = array_search($oldName, $studentsList);
                        if ($index !== false) {
                            $studentsList[$index] = $name;
                            dbExecute(
                                "UPDATE lessons_template SET students = ?, updated_at = NOW() WHERE id = ?",
                                [json_encode($studentsList), $template['id']]
                            );
                            error_log("Updated student name in template ID {$template['id']}");
                        }
                    } catch (Exception $e) {
                        error_log("Failed to update student name in template ID {$template['id']}: " . $e->getMessage());
                    }
                }
            }

            // Синхронизируем расписание
            // 1. Удаляем ученика из всех шаблонов (где он был раньше)
            // 2. Добавляем в новые шаблоны (согласно новому расписанию)

            // Удаляем из всех шаблонов
            removeStudentFromTemplates($name);

            // Добавляем в новые шаблоны согласно расписанию
            if ($schedule) {
                $scheduleData = json_decode($schedule, true);
                error_log("Syncing schedule for updated student '$name': " . json_encode($scheduleData));

                if ($scheduleData && is_array($scheduleData)) {
                    foreach ($scheduleData as $dayOfWeek => $lessons) {
                        if (!is_numeric($dayOfWeek)) continue;

                        if (is_array($lessons)) {
                            foreach ($lessons as $lessonIndex => $lesson) {
                                if (!is_array($lesson) || !isset($lesson['time']) || !isset($lesson['teacher_id'])) {
                                    continue;
                                }

                                $timeStart = $lesson['time'];
                                $lessonTeacherId = filter_var($lesson['teacher_id'], FILTER_VALIDATE_INT);

                                if (!$lessonTeacherId || !$timeStart) continue;

                                $timeEnd = date('H:i', strtotime($timeStart) + 3600);

                                // Ищем или создаём шаблон
                                $existingTemplate = dbQueryOne(
                                    "SELECT id, students FROM lessons_template
                                     WHERE teacher_id = ? AND day_of_week = ? AND time_start = ? AND lesson_type = ? AND active = 1",
                                    [$lessonTeacherId, $dayOfWeek, $timeStart, $lessonType]
                                );

                                if ($existingTemplate) {
                                    // Добавляем в существующий шаблон
                                    $studentsList = $existingTemplate['students'] ? json_decode($existingTemplate['students'], true) : [];
                                    if (!in_array($name, $studentsList)) {
                                        $studentsList[] = $name;
                                        dbExecute(
                                            "UPDATE lessons_template SET students = ?, updated_at = NOW() WHERE id = ?",
                                            [json_encode($studentsList), $existingTemplate['id']]
                                        );
                                        error_log("Added updated student '$name' to existing template ID {$existingTemplate['id']}");
                                    }
                                } else {
                                    // Создаём новый шаблон
                                    $expectedStudents = ($lessonType === 'group') ? 6 : 1;
                                    dbExecute(
                                        "INSERT INTO lessons_template (teacher_id, day_of_week, time_start, time_end, lesson_type, tier, expected_students, students, active)
                                         VALUES (?, ?, ?, ?, ?, 'C', ?, ?, 1)",
                                        [$lessonTeacherId, $dayOfWeek, $timeStart, $timeEnd, $lessonType, $expectedStudents, json_encode([$name])]
                                    );
                                    error_log("Created new template for updated student '$name', teacher $lessonTeacherId, day $dayOfWeek, time $timeStart");
                                }
                            }
                        }
                    }
                }
            }

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
 * Удалить ученика из всех шаблонов уроков
 */
function removeStudentFromTemplates($studentName) {
    // Получаем все шаблоны
    $templates = dbQuery("SELECT id, students FROM lessons_template WHERE active = 1");

    $removedCount = 0;
    $deletedTemplates = 0;

    foreach ($templates as $template) {
        if (!$template['students']) continue;

        // Парсим список учеников
        try {
            $studentsList = json_decode($template['students'], true);
            if (!is_array($studentsList)) continue;

            // Проверяем, есть ли этот ученик в списке
            $index = array_search($studentName, $studentsList);
            if ($index !== false) {
                // Удаляем ученика из списка
                array_splice($studentsList, $index, 1);
                $removedCount++;

                if (empty($studentsList)) {
                    // Если учеников не осталось, удаляем весь шаблон
                    dbExecute("DELETE FROM lessons_template WHERE id = ?", [$template['id']]);
                    $deletedTemplates++;
                    error_log("Deleted empty template ID {$template['id']} after removing student '{$studentName}'");
                } else {
                    // Обновляем список учеников
                    dbExecute(
                        "UPDATE lessons_template SET students = ?, updated_at = NOW() WHERE id = ?",
                        [json_encode($studentsList), $template['id']]
                    );
                    error_log("Removed student '{$studentName}' from template ID {$template['id']}, {count($studentsList)} students remaining");
                }
            }
        } catch (Exception $e) {
            error_log("Failed to process template ID {$template['id']}: " . $e->getMessage());
        }
    }

    error_log("Removed student '{$studentName}' from {$removedCount} templates, deleted {$deletedTemplates} empty templates");
    return ['removed_from' => $removedCount, 'deleted_templates' => $deletedTemplates];
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

    // Удаляем ученика из всех шаблонов уроков
    $templateStats = removeStudentFromTemplates($existing['name']);

    if ($attendanceCount['count'] > 0) {
        // Если есть записи посещаемости - деактивируем, а не удаляем
        $result = dbExecute(
            "UPDATE students SET active = 0, updated_at = NOW() WHERE id = ?",
            [$id]
        );

        if ($result !== false) {
            logAudit('student_deactivated', 'student', $id, $existing, ['active' => 0], 'Ученик деактивирован');
            jsonSuccess([
                'message' => 'Ученик деактивирован (есть связанные записи посещаемости)',
                'removed_from_templates' => $templateStats['removed_from'],
                'deleted_templates' => $templateStats['deleted_templates']
            ]);
        } else {
            jsonError('Не удалось деактивировать ученика', 500);
        }
    } else {
        // Если нет записей - можно удалить
        $result = dbExecute("DELETE FROM students WHERE id = ?", [$id]);

        if ($result) {
            logAudit('student_deleted', 'student', $id, $existing, null, 'Ученик удалён');
            jsonSuccess([
                'message' => 'Ученик удалён',
                'removed_from_templates' => $templateStats['removed_from'],
                'deleted_templates' => $templateStats['deleted_templates']
            ]);
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

    // Если деактивируем ученика, удаляем его из шаблонов уроков
    $templateStats = null;
    if ($newActive == 0) {
        $templateStats = removeStudentFromTemplates($existing['name']);
    }

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

        // Добавляем информацию о шаблонах, если деактивировали
        if ($templateStats) {
            $student['removed_from_templates'] = $templateStats['removed_from'];
            $student['deleted_templates'] = $templateStats['deleted_templates'];
        }

        jsonSuccess($student);
    } else {
        jsonError('Не удалось изменить статус ученика', 500);
    }
}
