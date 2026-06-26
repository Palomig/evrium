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

    // Карта цвет → активный преподаватель (фолбэк для легаси-записей)
    $colorToTeacher = plannerColorTeacherMap();
    $activeTeacherIds = array_values($colorToTeacher);

    // Записи всех блоков этого дня и времени; просроченные временные не учитываем
    $notes = dbQuery(
        "SELECT room, kind, content, color, teacher_id FROM planner_notes
         WHERE day = ? AND time = ?
           AND (temp_until IS NULL OR temp_until >= CURDATE())
         ORDER BY room, kind, position, id",
        [$dayOfWeek, $timeStart]
    );

    // Заголовки блоков: по преподавателю и по кабинету (легаси)
    $titlesByTeacher = [];
    $titlesByRoom = [];
    foreach ($notes as $n) {
        if ($n['kind'] === 'title') {
            if (!empty($n['teacher_id'])) {
                $titlesByTeacher[(int)$n['teacher_id']] = $n;
            }
            $titlesByRoom[(int)$n['room']] = $n;
        }
    }

    $studentsForLesson = [];
    $classLabels = [];
    $lessonSubject = null;

    foreach ($notes as $n) {
        if ($n['kind'] !== 'student' || trim($n['content']) === '') {
            continue;
        }

        // Преподаватель: teacher_id записи → цвет ячейки → цвет блока → c1
        $noteTeacher = (int)($n['teacher_id'] ?? 0);
        if (!$noteTeacher || !in_array($noteTeacher, $activeTeacherIds, true)) {
            $color = (int)$n['color'];
            $legacyTitle = $titlesByRoom[(int)$n['room']] ?? null;
            if (!$color && $legacyTitle) {
                $color = (int)$legacyTitle['color'];
            }
            if (!$color) {
                $color = 1;
            }
            $noteTeacher = $colorToTeacher[$color] ?? 0;
        }
        if ($noteTeacher !== $teacherId) {
            continue;
        }

        $title = $titlesByTeacher[$teacherId] ?? ($titlesByRoom[(int)$n['room']] ?? null);

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
 * Слоты уроков на день из планировщика — только те, где есть ученики.
 *
 * Источник истины тот же, что и getStudentsForLesson(): planner_notes.
 * Атрибуция преподавателя к ученику-записи идентична. Возвращает
 * уникальные пары (преподаватель, время) с количеством учеников.
 *
 * @param int $dayOfWeek День недели (1-7)
 * @return array [['teacher_id'=>int, 'time'=>'HH:MM', 'count'=>int], ...] по времени
 */
function getLessonSlotsForDay($dayOfWeek) {
    $dayOfWeek = (int)$dayOfWeek;

    $colorToTeacher = plannerColorTeacherMap();
    $activeTeacherIds = array_values($colorToTeacher);

    $notes = dbQuery(
        "SELECT room, kind, content, color, teacher_id, time FROM planner_notes
         WHERE day = ?
           AND (temp_until IS NULL OR temp_until >= CURDATE())
         ORDER BY time, room, kind, position, id",
        [$dayOfWeek]
    );

    // Цвета легаси-заголовков по (время, кабинет) для фолбэка
    $titleColorByRoomTime = [];
    foreach ($notes as $n) {
        if ($n['kind'] === 'title') {
            $key = substr($n['time'], 0, 5) . '_' . (int)$n['room'];
            $titleColorByRoomTime[$key] = (int)$n['color'];
        }
    }

    $slots = []; // "time|teacher" => count
    foreach ($notes as $n) {
        if ($n['kind'] !== 'student' || trim($n['content']) === '') {
            continue;
        }
        $time = substr($n['time'], 0, 5);

        // Преподаватель: teacher_id записи → цвет ячейки → цвет блока → c1
        $noteTeacher = (int)($n['teacher_id'] ?? 0);
        if (!$noteTeacher || !in_array($noteTeacher, $activeTeacherIds, true)) {
            $color = (int)$n['color'];
            if (!$color) {
                $color = $titleColorByRoomTime[$time . '_' . (int)$n['room']] ?? 0;
            }
            if (!$color) {
                $color = 1;
            }
            $noteTeacher = $colorToTeacher[$color] ?? 0;
        }
        if (!$noteTeacher) {
            continue;
        }

        $key = $time . '|' . $noteTeacher;
        $slots[$key] = ($slots[$key] ?? 0) + 1;
    }

    $result = [];
    foreach ($slots as $key => $count) {
        list($time, $teacherId) = explode('|', $key, 2);
        $result[] = [
            'teacher_id' => (int)$teacherId,
            'time'       => $time,
            'count'      => $count,
        ];
    }

    usort($result, fn($a, $b) => strcmp($a['time'], $b['time']) ?: ($a['teacher_id'] <=> $b['teacher_id']));
    return $result;
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
