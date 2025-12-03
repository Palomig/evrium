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
        foreach ($schedule as $key => $entry) {
            if (is_array($entry)) {
                // Формат 1: массив объектов
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
            } else {
                // Формат 2: объект {"1": "17:00"}
                if ((int)$key == $dayOfWeek && substr($entry, 0, 5) == $timeStart) {
                    $hasThisLesson = true;
                    break;
                }
            }
        }

        if ($hasThisLesson) {
            $studentName = $student['name'];
            if ($student['class']) {
                $studentName .= " ({$student['class']} кл.)";
                $studentClasses[] = (int)$student['class'];
            }
            $studentsForLesson[] = $studentName;
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
