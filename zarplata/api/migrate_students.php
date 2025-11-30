<?php
/**
 * API для миграции учеников в новый формат "Имя (класс кл.)"
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonError('Требуется авторизация', 401);
}

$user = getCurrentUser();
if ($user['role'] !== 'owner') {
    jsonError('Недостаточно прав. Только владелец может запускать миграцию.', 403);
}

try {
    // Получаем все активные шаблоны уроков
    $templates = dbQuery(
        "SELECT id, students FROM lessons_template WHERE active = 1 AND students IS NOT NULL",
        []
    );

    $updated = 0;
    $skipped = 0;
    $errors = [];
    $details = [];

    foreach ($templates as $template) {
        $studentsJson = $template['students'];
        $students = json_decode($studentsJson, true);

        if (!is_array($students) || empty($students)) {
            continue;
        }

        $needsUpdate = false;
        $updatedStudents = [];

        foreach ($students as $studentName) {
            // Проверяем, есть ли уже класс в скобках
            if (preg_match('/\((\d+)\s*кл\.\)/', $studentName)) {
                // Уже в новом формате
                $updatedStudents[] = $studentName;
            } else {
                // Старый формат - нужно найти ученика в БД
                $foundStudents = dbQuery(
                    "SELECT id, name, class FROM students WHERE name = ? AND active = 1 ORDER BY class",
                    [$studentName]
                );

                if (count($foundStudents) === 1) {
                    // Найден ровно один ученик - обновляем формат
                    $student = $foundStudents[0];
                    if ($student['class']) {
                        $updatedStudents[] = $student['name'] . ' (' . $student['class'] . ' кл.)';
                        $needsUpdate = true;
                        $details[] = "Template {$template['id']}: '{$studentName}' -> '{$student['name']} ({$student['class']} кл.)'";
                    } else {
                        // У ученика нет класса в БД
                        $updatedStudents[] = $studentName;
                        $errors[] = "Template {$template['id']}: ученик '{$studentName}' не имеет класса в БД";
                    }
                } elseif (count($foundStudents) > 1) {
                    // Найдено несколько учеников с таким именем - неоднозначность
                    $classes = array_map(function($s) { return $s['class']; }, $foundStudents);
                    $updatedStudents[] = $studentName;
                    $errors[] = "Template {$template['id']}: несколько учеников с именем '{$studentName}' (классы: " . implode(', ', $classes) . "). Требуется ручное исправление.";
                } else {
                    // Ученик не найден в БД
                    $updatedStudents[] = $studentName;
                    $errors[] = "Template {$template['id']}: ученик '{$studentName}' не найден в БД";
                }
            }
        }

        if ($needsUpdate) {
            // Обновляем запись
            $newStudentsJson = json_encode($updatedStudents, JSON_UNESCAPED_UNICODE);
            dbExecute(
                "UPDATE lessons_template SET students = ? WHERE id = ?",
                [$newStudentsJson, $template['id']]
            );
            $updated++;

            // Логируем в аудит
            logAudit(
                'students_migrated',
                'template',
                $template['id'],
                $studentsJson,
                $newStudentsJson,
                "Миграция учеников в новый формат"
            );
        } else {
            $skipped++;
        }
    }

    jsonSuccess([
        'message' => 'Миграция завершена',
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'details' => $details
    ]);

} catch (Exception $e) {
    error_log("Migration error: " . $e->getMessage());
    jsonError('Ошибка миграции: ' . $e->getMessage(), 500);
}
