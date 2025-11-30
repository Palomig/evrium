<?php
/**
 * API для обновления ученика в шаблоне расписания
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonError('Требуется авторизация', 401);
}

// Получаем данные
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    jsonError('Неверный формат данных', 400);
}

$templateId = filter_var($data['template_id'] ?? 0, FILTER_VALIDATE_INT);
$studentIndex = filter_var($data['student_index'] ?? -1, FILTER_VALIDATE_INT);
$studentName = trim($data['student_name'] ?? '');
$studentClass = filter_var($data['student_class'] ?? 0, FILTER_VALIDATE_INT);

if (!$templateId) {
    jsonError('ID шаблона не указан', 400);
}

if ($studentIndex < 0) {
    jsonError('Индекс ученика не указан', 400);
}

if (!$studentName) {
    jsonError('Имя ученика не указано', 400);
}

if (!$studentClass) {
    jsonError('Класс ученика не указан', 400);
}

try {
    // Получаем текущий шаблон
    $template = dbQueryOne(
        "SELECT id, students FROM lessons_template WHERE id = ?",
        [$templateId]
    );

    if (!$template) {
        jsonError('Шаблон не найден', 404);
    }

    // Парсим текущий список учеников
    $students = json_decode($template['students'], true);
    if (!is_array($students)) {
        jsonError('Неверный формат списка учеников', 500);
    }

    if (!isset($students[$studentIndex])) {
        jsonError('Ученик с таким индексом не найден', 404);
    }

    // Сохраняем старое значение для аудита
    $oldValue = $students[$studentIndex];

    // Обновляем ученика в формат "Имя (класс кл.)"
    $newValue = $studentName . ' (' . $studentClass . ' кл.)';
    $students[$studentIndex] = $newValue;

    // Сохраняем обновлённый список
    $newStudentsJson = json_encode($students, JSON_UNESCAPED_UNICODE);
    dbExecute(
        "UPDATE lessons_template SET students = ? WHERE id = ?",
        [$newStudentsJson, $templateId]
    );

    // Логируем в аудит
    logAudit(
        'student_updated',
        'template',
        $templateId,
        $oldValue,
        $newValue,
        "Обновлён ученик: '{$oldValue}' -> '{$newValue}'"
    );

    jsonSuccess([
        'message' => 'Ученик успешно обновлён',
        'template_id' => $templateId,
        'student_index' => $studentIndex,
        'old_value' => $oldValue,
        'new_value' => $newValue
    ]);

} catch (Exception $e) {
    error_log("Error updating student: " . $e->getMessage());
    jsonError('Ошибка обновления: ' . $e->getMessage(), 500);
}
