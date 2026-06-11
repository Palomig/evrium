<?php
/**
 * Хелперы для получения учеников урока.
 *
 * ⭐ ЕДИНЫЙ ИСТОЧНИК: расписание-планировщик (таблица planner_notes).
 * Ячейки — свободный текст; преподаватель определяется по цвету ячейки
 * (палитра планировщика: colorIndex = (teacher_id % 8) ?: 8), фолбэк —
 * цвет заголовка блока, затем 1 (teal, цвет по умолчанию в планировщике).
 * Класс и предмет берутся из заголовка блока: «9 Мат.», «8-9 Физика».
 */

/**
 * Получить учеников урока по преподавателю, дню и времени (все кабинеты).
 *
 * @param int $teacherId ID преподавателя
 * @param int $dayOfWeek День недели (1-7)
 * @param string $timeStart Время начала урока (HH:MM)
 * @return array ['students' => [['name','class'],...], 'count' => N, 'classes' => 'X, Y', 'subject' => 'Математика']
 */
function getStudentsForLesson($teacherId, $dayOfWeek, $timeStart) {
    $teacherId = (int)$teacherId;
    $dayOfWeek = (int)$dayOfWeek;
    $timeStart = substr($timeStart, 0, 5); // "17:00:00" -> "17:00"

    // Карта цвет → активный преподаватель (как в легенде планировщика)
    $colorToTeacher = plannerColorTeacherMap();

    // Записи всех блоков этого дня и времени; просроченные временные не учитываем
    $notes = dbQuery(
        "SELECT room, kind, content, color FROM planner_notes
         WHERE day = ? AND time = ?
           AND (temp_until IS NULL OR temp_until >= CURDATE())
         ORDER BY room, kind, position, id",
        [$dayOfWeek, $timeStart]
    );

    // Заголовки блоков по кабинетам
    $titles = [];
    foreach ($notes as $n) {
        if ($n['kind'] === 'title') {
            $titles[(int)$n['room']] = $n;
        }
    }

    $studentsForLesson = [];
    $classLabels = [];
    $lessonSubject = null;

    foreach ($notes as $n) {
        if ($n['kind'] !== 'student' || trim($n['content']) === '') {
            continue;
        }

        $room = (int)$n['room'];
        $title = $titles[$room] ?? null;

        // Преподаватель ячейки: цвет ячейки → цвет заголовка блока → 1
        $color = (int)$n['color'];
        if (!$color && $title) {
            $color = (int)$title['color'];
        }
        if (!$color) {
            $color = 1;
        }
        if (($colorToTeacher[$color] ?? null) !== $teacherId) {
            continue;
        }

        // Класс и предмет из заголовка блока
        $classLabel = null;
        $blockSubject = null;
        if ($title && preg_match('/^\s*(\d+(?:-\d+)?)?\s*(.*)$/u', trim($title['content']), $m)) {
            $classLabel = ($m[1] ?? '') !== '' ? $m[1] : null;
            $blockSubject = trim($m[2] ?? '') !== '' ? trim($m[2]) : null;
        }

        $studentsForLesson[] = [
            'name' => trim($n['content']),
            'class' => ($classLabel !== null && ctype_digit($classLabel)) ? (int)$classLabel : null
        ];

        if ($classLabel !== null) {
            $classLabels[] = $classLabel;
        }
        if ($blockSubject && !$lessonSubject) {
            $lessonSubject = $blockSubject;
        }
    }

    $classLabels = array_unique($classLabels);
    sort($classLabels, SORT_NATURAL);

    // Сокращённый предмет → полное название
    $subjectMap = [
        'Мат.' => 'Математика',
        'Физ.' => 'Физика',
        'Инф.' => 'Информатика'
    ];
    $fullSubject = $lessonSubject ? ($subjectMap[$lessonSubject] ?? $lessonSubject) : null;

    return [
        'students' => $studentsForLesson,
        'count' => count($studentsForLesson),
        'classes' => implode(', ', $classLabels),
        'subject' => $fullSubject
    ];
}

/**
 * Карта «цвет планировщика → ID активного преподавателя».
 * colorIndex = (teacher_id % 8) ?: 8 — тот же расчёт, что в planner.php.
 */
function plannerColorTeacherMap() {
    $map = [];
    $teachers = dbQuery("SELECT id FROM teachers WHERE active = 1", []);
    foreach ($teachers as $t) {
        $map[(((int)$t['id']) % 8) ?: 8] = (int)$t['id'];
    }
    return $map;
}
