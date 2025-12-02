<?php
/**
 * API для синхронизации количества студентов
 * Обновляет expected_students на основе реального количества в JSON
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Требуем авторизацию
if (!isLoggedIn()) {
    jsonError('Требуется авторизация', 401);
}

// Выполняем синхронизацию
$results = [
    'total' => 0,
    'updated' => 0,
    'skipped' => 0,
    'errors' => 0,
    'details' => []
];

try {
    // Получить все активные шаблоны
    $templates = dbQuery(
        "SELECT id, teacher_id, day_of_week, time_start, subject, expected_students, students
         FROM lessons_template
         WHERE active = 1
         ORDER BY day_of_week ASC, time_start ASC",
        []
    );

    $results['total'] = count($templates);

    $days = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

    foreach ($templates as $template) {
        $templateId = $template['id'];
        $expectedStudents = (int)$template['expected_students'];
        $studentsJson = $template['students'];

        // Парсим JSON
        $students = [];
        if ($studentsJson) {
            $studentsData = json_decode($studentsJson, true);
            if (is_array($studentsData)) {
                $students = $studentsData;
            }
        }

        $realCount = count($students);
        $dayName = $days[$template['day_of_week']] ?? '?';
        $timeStart = substr($template['time_start'], 0, 5);
        $subject = $template['subject'] ?: '(без предмета)';

        $detail = [
            'id' => $templateId,
            'day' => $dayName,
            'time' => $timeStart,
            'subject' => $subject,
            'expected' => $expectedStudents,
            'real' => $realCount,
            'students' => $students,
            'updated' => false,
            'error' => null
        ];

        // Если количество не совпадает - обновляем
        if ($expectedStudents !== $realCount) {
            try {
                $result = dbExecute(
                    "UPDATE lessons_template SET expected_students = ? WHERE id = ?",
                    [$realCount, $templateId]
                );

                if ($result !== false) {
                    $detail['updated'] = true;
                    $results['updated']++;
                } else {
                    $detail['error'] = 'Не удалось обновить запись';
                    $results['errors']++;
                }
            } catch (Exception $e) {
                $detail['error'] = $e->getMessage();
                $results['errors']++;
            }
        } else {
            $results['skipped']++;
        }

        $results['details'][] = $detail;
    }

    // Логируем в аудит
    logAudit(
        'students_sync',
        'template',
        null,
        null,
        [
            'total' => $results['total'],
            'updated' => $results['updated'],
            'skipped' => $results['skipped'],
            'errors' => $results['errors']
        ],
        "Синхронизация количества студентов: обновлено {$results['updated']} из {$results['total']}"
    );

    jsonSuccess($results);

} catch (Exception $e) {
    jsonError('Ошибка синхронизации: ' . $e->getMessage(), 500);
}
