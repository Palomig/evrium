<?php
/**
 * Helper функции для работы со студентами
 */

/**
 * ⭐ Получить список студентов для шаблона урока
 * Динамически читает из таблицы students на основе их расписания
 *
 * @param int $teacherId ID преподавателя
 * @param int $dayOfWeek День недели (1-7)
 * @param string $timeStart Время начала урока (HH:MM)
 * @return array ['students' => [...], 'count' => N, 'classes' => 'X, Y, Z']
 */
function getStudentsForLesson($teacherId, $dayOfWeek, $timeStart) {
    // Убираем секунды если есть
    $timeStart = substr($timeStart, 0, 5); // "17:00:00" -> "17:00"

    // Получаем всех активных студентов этого преподавателя
    $allStudents = dbQuery(
        "SELECT id, name, class, schedule
         FROM students
         WHERE active = 1 AND teacher_id = ?",
        [$teacherId]
    );

    $studentsForLesson = [];
    $studentClasses = [];

    foreach ($allStudents as $student) {
        if (!$student['schedule']) continue;

        $schedule = json_decode($student['schedule'], true);
        if (!is_array($schedule)) continue;

        $hasThisLesson = false;

        // Проверяем формат расписания
        // Формат 1: [{"day": "Monday", "time": "17:00"}, ...]
        // Формат 2: {"1": "17:00", "3": "19:00"}
        // Формат 3: {"1": [{"time": "17:00", "room": 1}], "3": [...]} ⭐ НОВЫЙ ФОРМАТ
        foreach ($schedule as $key => $entry) {
            if (is_array($entry)) {
                // Проверяем, это массив объектов (Формат 3) или один объект (Формат 1)?
                if (isset($entry[0]) && is_array($entry[0])) {
                    // ⭐ Формат 3: {"1": [{"time": "17:00", "room": 1}, ...]}
                    // $key - это день недели, $entry - массив объектов с time/room
                    if ((int)$key == $dayOfWeek) {
                        foreach ($entry as $timeSlot) {
                            if (isset($timeSlot['time']) && substr($timeSlot['time'], 0, 5) == $timeStart) {
                                $hasThisLesson = true;
                                break 2; // Выходим из обоих циклов
                            }
                        }
                    }
                } else {
                    // Формат 1: массив объектов с полем day
                    $entryDay = $entry['day'] ?? null;
                    $entryTime = $entry['time'] ?? null;

                    // Преобразуем день из названия в номер
                    $dayMap = [
                        'Monday' => 1, 'Пн' => 1, 'понедельник' => 1,
                        'Tuesday' => 2, 'Вт' => 2, 'вторник' => 2,
                        'Wednesday' => 3, 'Ср' => 3, 'среда' => 3,
                        'Thursday' => 4, 'Чт' => 4, 'четверг' => 4,
                        'Friday' => 5, 'Пт' => 5, 'пятница' => 5,
                        'Saturday' => 6, 'Сб' => 6, 'суббота' => 6,
                        'Sunday' => 7, 'Вс' => 7, 'воскресенье' => 7
                    ];

                    $entryDayNum = $dayMap[$entryDay] ?? (int)$entryDay;

                    if ($entryDayNum == $dayOfWeek && substr($entryTime, 0, 5) == $timeStart) {
                        $hasThisLesson = true;
                        break;
                    }
                }
            } else {
                // Формат 2: объект {"1": "17:00"}
                if ((int)$key == $dayOfWeek && substr($entry, 0, 5) == $timeStart) {
                    $hasThisLesson = true;
                    break;
                }
            }
        }

        if ($hasThisLesson) {
            // Добавляем студента как объект с полями name и class
            $studentsForLesson[] = [
                'name' => $student['name'],
                'class' => $student['class'] ? (int)$student['class'] : null
            ];

            // Собираем уникальные классы для строки
            if ($student['class']) {
                $studentClasses[] = (int)$student['class'];
            }
        }
    }

    // Формируем строку с классами
    $studentClasses = array_unique($studentClasses);
    sort($studentClasses);
    $classesStr = !empty($studentClasses) ? implode(', ', $studentClasses) : '';

    return [
        'students' => $studentsForLesson,
        'count' => count($studentsForLesson),
        'classes' => $classesStr
    ];
}
