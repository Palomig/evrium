<?php
/**
 * Диагностика расчета зарплаты
 * https://эвриум.рф/zarplata/bot/check_payment.php?lesson_id=12
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

echo "<h1>Диагностика расчета зарплаты</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #1a1a1a; color: #fff; }
    pre { background: #2a2a2a; padding: 15px; border-radius: 8px; }
    .success { color: #4caf50; }
    .error { color: #f44336; }
    .warning { color: #ff9800; }
</style>";

$lessonId = filter_var($_GET['lesson_id'] ?? 12, FILTER_VALIDATE_INT);

echo "<pre>";
echo "=== Диагностика урока ID: $lessonId ===\n\n";

// Получаем урок
$lesson = dbQueryOne("SELECT * FROM lessons_template WHERE id = ?", [$lessonId]);

if (!$lesson) {
    echo "<span class='error'>❌ Урок не найден</span>\n";
    exit;
}

echo "<span class='success'>✅ Урок найден</span>\n";
echo "ID: {$lesson['id']}\n";
echo "Преподаватель ID: {$lesson['teacher_id']}\n";
echo "Предмет: " . ($lesson['subject'] ?: '-') . "\n";
echo "Ожидается учеников: {$lesson['expected_students']}\n";
echo "Formula ID (урока): " . ($lesson['formula_id'] ?: 'НЕТ') . "\n\n";

// Получаем преподавателя
$teacher = dbQueryOne("SELECT * FROM teachers WHERE id = ?", [$lesson['teacher_id']]);

if (!$teacher) {
    echo "<span class='error'>❌ Преподаватель не найден</span>\n";
    exit;
}

echo "<span class='success'>✅ Преподаватель найден</span>\n";
echo "Имя: {$teacher['name']}\n";
echo "Formula ID (преподавателя): " . ($teacher['formula_id'] ?: 'НЕТ') . "\n\n";

// Определяем какую формулу использовать
$formulaId = $lesson['formula_id'] ?? $teacher['formula_id'] ?? null;

echo "=== Выбор формулы ===\n";
if ($formulaId) {
    echo "<span class='success'>✅ Formula ID для расчета: $formulaId</span>\n\n";
} else {
    echo "<span class='error'>❌ НЕТ ФОРМУЛЫ! Ни у урока, ни у преподавателя!</span>\n";
    echo "\nРешение: Зайдите на https://эвриум.рф/zarplata/formulas.php и создайте формулу\n";
    echo "Затем назначьте её преподавателю на https://эвриум.рф/zarplata/teachers.php\n";
    exit;
}

// Получаем формулу
$formula = dbQueryOne("SELECT * FROM payment_formulas WHERE id = ? AND active = 1", [$formulaId]);

if (!$formula) {
    echo "<span class='error'>❌ Формула ID $formulaId не найдена или неактивна</span>\n";
    exit;
}

echo "<span class='success'>✅ Формула найдена</span>\n";
echo "Название: {$formula['name']}\n";
echo "Тип: {$formula['type']}\n";

switch ($formula['type']) {
    case 'min_plus_per':
        echo "Минимальная выплата: {$formula['min_payment']} ₽\n";
        echo "За каждого ученика: {$formula['per_student']} ₽\n";
        echo "Порог (threshold): {$formula['threshold']}\n";
        break;
    case 'fixed':
        echo "Фиксированная сумма: {$formula['fixed_amount']} ₽\n";
        break;
    case 'expression':
        echo "Выражение: {$formula['expression']}\n";
        break;
}

echo "\n=== Тестовые расчеты ===\n";

// Проверяем функцию calculatePayment
if (!function_exists('calculatePayment')) {
    echo "<span class='error'>❌ Функция calculatePayment() не существует!</span>\n";
    exit;
}

echo "<span class='success'>✅ Функция calculatePayment() существует</span>\n\n";

// Тестируем разные количества учеников
$testCounts = [0, 1, 2, 3, 4, 5, 6];

echo "Тестовые расчеты для разного количества учеников:\n\n";

foreach ($testCounts as $count) {
    $amount = calculatePayment($lesson, $teacher, $count);
    echo "  Пришло {$count} учеников → Зарплата: <strong>{$amount} ₽</strong>\n";

    if ($formula['type'] === 'min_plus_per') {
        $threshold = $formula['threshold'] ?? 2;
        $minPayment = $formula['min_payment'] ?? 0;
        $perStudent = $formula['per_student'] ?? 0;
        $expected = $minPayment + (max(0, $count - $threshold) * $perStudent);
        echo "    Расчет: {$minPayment} + max(0, {$count} - {$threshold}) * {$perStudent} = {$expected} ₽\n";
        if ($amount != $expected) {
            echo "    <span class='error'>⚠️ Ожидалось {$expected} ₽, получено {$amount} ₽</span>\n";
        }
    }
    echo "\n";
}

echo "\n=== Последние выплаты по этому уроку ===\n";

$payments = dbQuery(
    "SELECT * FROM payments WHERE lesson_template_id = ? ORDER BY created_at DESC LIMIT 5",
    [$lessonId]
);

if (empty($payments)) {
    echo "Выплат пока нет\n";
} else {
    foreach ($payments as $p) {
        echo "ID: {$p['id']}, Сумма: {$p['amount']} ₽, Статус: {$p['status']}, Создано: {$p['created_at']}\n";
        echo "  Метод расчета: " . ($p['calculation_method'] ?: '-') . "\n";
    }
}

echo "</pre>";

echo "<hr>";
echo "<p><a href='quick_test.php'>← Назад к тестированию</a></p>";
