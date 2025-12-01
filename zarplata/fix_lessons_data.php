<?php
/**
 * Исправление данных уроков и преподавателей
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Проверяем авторизацию
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'assign_formulas':
            $result = assignFormulasToTeachers();
            echo json_encode($result);
            break;
        case 'update_lessons':
            $result = updateLessonsFromTemplates();
            echo json_encode($result);
            break;
        case 'full_fix':
            $result1 = assignFormulasToTeachers();
            $result2 = updateLessonsFromTemplates();

            echo json_encode([
                'success' => true,
                'message' => "Обновлено уроков: " . ($result2['updated'] ?? 0),
                'updated' => $result2['updated'] ?? 0,
                'formulas_assigned' => $result1['updated'] ?? 0,
                'errors' => $result2['errors'] ?? []
            ]);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Назначить формулы преподавателям
 */
function assignFormulasToTeachers() {
    // Станислав Олегович (ID=1) - по умолчанию группа (ID=4)
    // Руслан Романович (ID=2) - тоже группа (ID=4) или можно оставить NULL

    $updated = 0;

    // Обновляем Станислава
    $result1 = dbExecute(
        "UPDATE teachers SET formula_id = 4 WHERE id = 1",
        []
    );
    if ($result1) $updated++;

    // Обновляем Руслана (если нужно)
    $result2 = dbExecute(
        "UPDATE teachers SET formula_id = 4 WHERE id = 2",
        []
    );
    if ($result2) $updated++;

    return [
        'success' => true,
        'message' => "Назначено формул преподавателям",
        'updated' => $updated
    ];
}

/**
 * Обновить уроки из шаблонов
 */
function updateLessonsFromTemplates() {
    // Получаем все уроки с шаблонами
    $lessons = dbQuery("
        SELECT
            li.id as lesson_id,
            li.template_id,
            lt.subject,
            lt.formula_id as template_formula_id,
            lt.expected_students as template_expected,
            t.formula_id as teacher_formula_id
        FROM lessons_instance li
        LEFT JOIN lessons_template lt ON li.template_id = lt.id
        LEFT JOIN teachers t ON li.teacher_id = t.id
        WHERE li.template_id IS NOT NULL
    ", []);

    $updated = 0;
    $errors = [];

    foreach ($lessons as $lesson) {
        // Определяем формулу: сначала из шаблона, потом из преподавателя
        $formulaId = $lesson['template_formula_id'] ?: $lesson['teacher_formula_id'];
        $subject = $lesson['subject'];
        $expectedStudents = $lesson['template_expected'];

        if (!$formulaId) {
            $errors[] = "Урок ID {$lesson['lesson_id']}: нет формулы";
            continue;
        }

        // Обновляем урок
        try {
            dbExecute(
                "UPDATE lessons_instance
                 SET formula_id = ?,
                     subject = COALESCE(subject, ?),
                     expected_students = COALESCE(NULLIF(expected_students, 0), ?)
                 WHERE id = ?",
                [$formulaId, $subject, $expectedStudents, $lesson['lesson_id']]
            );
            $updated++;
        } catch (Exception $e) {
            $errors[] = "Урок ID {$lesson['lesson_id']}: " . $e->getMessage();
        }
    }

    return [
        'success' => true,
        'message' => "Обновлено уроков: $updated",
        'updated' => $updated,
        'errors' => $errors
    ];
}
