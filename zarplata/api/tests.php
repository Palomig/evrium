<?php
/**
 * API для выполнения тестов
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

// Подключаем функции бота
require_once __DIR__ . '/../bot/config.php';

requireAuth();

header('Content-Type: application/json; charset=utf-8');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$testName = $data['test'] ?? null;

if (!$testName) {
    echo json_encode(['success' => false, 'error' => 'Не указано имя теста']);
    exit;
}

$logs = [];
$testResult = null;

/**
 * Вспомогательная функция для добавления логов
 */
function addLog($message, $type = 'info') {
    global $logs;
    $logs[] = ['message' => $message, 'type' => $type];
}

try {
    switch ($testName) {
        // ==================== ТЕСТЫ БОТА ====================

        case 'bot_attendance_all':
            addLog('Тест: Все ученики пришли на урок');

            // Получаем первого преподавателя
            $teacher = dbQueryOne("SELECT * FROM teachers WHERE active = 1 LIMIT 1");
            if (!$teacher) {
                throw new Exception('Не найдено активных преподавателей');
            }
            addLog("Преподаватель: {$teacher['name']} (ID: {$teacher['id']})", 'info');

            // Получаем первый урок преподавателя
            $lesson = dbQueryOne(
                "SELECT * FROM lessons_template WHERE teacher_id = ? AND active = 1 LIMIT 1",
                [$teacher['id']]
            );

            if (!$lesson) {
                throw new Exception('Не найдено уроков для преподавателя');
            }

            $dayNames = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
            addLog("Урок: {$lesson['subject']} ({$dayNames[$lesson['day_of_week']]} {$lesson['time_start']})", 'info');
            addLog("Ожидается учеников: {$lesson['expected_students']}", 'info');
            addLog("Тип урока: {$lesson['lesson_type']}", 'info');

            // Проверяем наличие формулы
            $formulaField = $lesson['lesson_type'] === 'individual' ? 'formula_id_individual' : 'formula_id_group';
            $formulaId = $teacher[$formulaField];

            if (!$formulaId) {
                addLog("⚠ ПРОБЛЕМА НАЙДЕНА: У преподавателя не указана формула для {$lesson['lesson_type']} уроков!", 'error');
                addLog("Поле {$formulaField} = NULL", 'error');
                addLog("Это объясняет ошибку при выборе 'не все явились'", 'warning');

                // Предлагаем решение
                addLog("РЕШЕНИЕ: Зайдите в Преподаватели → {$teacher['name']} и укажите формулу расчёта", 'warning');

                $testResult = [
                    'status' => 'error',
                    'problem' => 'Не указана формула расчёта',
                    'field' => $formulaField,
                    'teacher_id' => $teacher['id'],
                    'lesson_type' => $lesson['lesson_type']
                ];
                break;
            }

            addLog("Формула ID: {$formulaId}", 'success');

            // Получаем формулу
            $formula = dbQueryOne(
                "SELECT * FROM payment_formulas WHERE id = ? AND active = 1",
                [$formulaId]
            );

            if (!$formula) {
                throw new Exception("Формула {$formulaId} не найдена или неактивна");
            }

            addLog("Формула: {$formula['name']} (тип: {$formula['type']})", 'success');

            // Рассчитываем зарплату
            $payment = calculatePayment($formula, $lesson['expected_students']);
            addLog("Расчёт зарплаты: {$payment} ₽", 'success');

            $testResult = [
                'status' => 'success',
                'teacher' => $teacher['name'],
                'lesson' => $lesson['subject'],
                'students' => $lesson['expected_students'],
                'formula' => $formula['name'],
                'payment' => $payment
            ];
            break;

        case 'bot_attendance_partial':
            addLog('Тест: Не все ученики пришли на урок');

            // Получаем первого преподавателя
            $teacher = dbQueryOne("SELECT * FROM teachers WHERE active = 1 LIMIT 1");
            if (!$teacher) {
                throw new Exception('Не найдено активных преподавателей');
            }
            addLog("Преподаватель: {$teacher['name']} (ID: {$teacher['id']})", 'info');

            // Получаем групповой урок
            $lesson = dbQueryOne(
                "SELECT * FROM lessons_template
                 WHERE teacher_id = ? AND lesson_type = 'group' AND active = 1
                 LIMIT 1",
                [$teacher['id']]
            );

            if (!$lesson) {
                addLog("Групповые уроки не найдены, ищем индивидуальные...", 'warning');
                $lesson = dbQueryOne(
                    "SELECT * FROM lessons_template
                     WHERE teacher_id = ? AND lesson_type = 'individual' AND active = 1
                     LIMIT 1",
                    [$teacher['id']]
                );
            }

            if (!$lesson) {
                throw new Exception('Не найдено уроков для преподавателя');
            }

            addLog("Урок: {$lesson['subject']} (тип: {$lesson['lesson_type']})", 'info');
            addLog("Ожидается учеников: {$lesson['expected_students']}", 'info');

            // Проверяем формулу
            $formulaField = $lesson['lesson_type'] === 'individual' ? 'formula_id_individual' : 'formula_id_group';
            $formulaId = $teacher[$formulaField];

            if (!$formulaId) {
                addLog("✗ ПРОБЛЕМА: Не указана формула для {$lesson['lesson_type']} уроков", 'error');
                addLog("Поле: {$formulaField}", 'error');
                addLog("Значение: NULL", 'error');
                addLog("", 'info');
                addLog("КАК ИСПРАВИТЬ:", 'warning');
                addLog("1. Зайдите в меню 'Формулы оплаты'", 'warning');
                addLog("2. Создайте новую формулу или выберите существующую", 'warning');
                addLog("3. Зайдите в 'Преподаватели' → {$teacher['name']}", 'warning');
                addLog("4. Укажите формулу для групповых/индивидуальных уроков", 'warning');

                $testResult = [
                    'status' => 'error',
                    'problem' => 'Missing formula configuration',
                    'teacher_id' => $teacher['id'],
                    'teacher_name' => $teacher['name'],
                    'lesson_type' => $lesson['lesson_type'],
                    'missing_field' => $formulaField
                ];
                break;
            }

            $formula = dbQueryOne(
                "SELECT * FROM payment_formulas WHERE id = ? AND active = 1",
                [$formulaId]
            );

            if (!$formula) {
                throw new Exception("Формула {$formulaId} не найдена");
            }

            addLog("✓ Формула найдена: {$formula['name']}", 'success');

            // Тестируем разные варианты посещаемости
            addLog("", 'info');
            addLog("Тестирование расчётов:", 'info');

            $testCases = [];
            for ($attended = 0; $attended <= $lesson['expected_students']; $attended++) {
                $payment = calculatePayment($formula, $attended);
                $testCases[] = [
                    'attended' => $attended,
                    'expected' => $lesson['expected_students'],
                    'payment' => $payment
                ];
                addLog("  {$attended} из {$lesson['expected_students']} → {$payment} ₽", 'success');
            }

            $testResult = [
                'status' => 'success',
                'teacher' => $teacher['name'],
                'lesson' => $lesson['subject'],
                'formula' => $formula['name'],
                'test_cases' => $testCases
            ];
            break;

        case 'bot_check_formulas':
            addLog('Проверка настройки формул у всех преподавателей');

            $teachers = dbQuery("SELECT * FROM teachers WHERE active = 1");
            $problems = [];

            foreach ($teachers as $teacher) {
                addLog("", 'info');
                addLog("Преподаватель: {$teacher['name']}", 'info');

                $hasGroup = $teacher['formula_id_group'] !== null;
                $hasIndividual = $teacher['formula_id_individual'] !== null;

                if (!$hasGroup) {
                    addLog("  ⚠ Не указана формула для групповых уроков", 'warning');
                    $problems[] = [
                        'teacher' => $teacher['name'],
                        'problem' => 'Нет формулы для групповых уроков'
                    ];
                } else {
                    addLog("  ✓ Групповые уроки: formula_id = {$teacher['formula_id_group']}", 'success');
                }

                if (!$hasIndividual) {
                    addLog("  ⚠ Не указана формула для индивидуальных уроков", 'warning');
                    $problems[] = [
                        'teacher' => $teacher['name'],
                        'problem' => 'Нет формулы для индивидуальных уроков'
                    ];
                } else {
                    addLog("  ✓ Индивидуальные уроки: formula_id = {$teacher['formula_id_individual']}", 'success');
                }
            }

            addLog("", 'info');
            if (empty($problems)) {
                addLog("✓ Все преподаватели настроены корректно!", 'success');
            } else {
                addLog("✗ Найдено проблем: " . count($problems), 'error');
            }

            $testResult = [
                'status' => empty($problems) ? 'success' : 'warning',
                'checked' => count($teachers),
                'problems' => $problems
            ];
            break;

        // ==================== ТЕСТЫ РАСЧЁТОВ ====================

        case 'payment_calculation':
            addLog('Тест расчёта зарплаты по всем формулам');

            $formulas = dbQuery("SELECT * FROM payment_formulas WHERE active = 1");
            $results = [];

            foreach ($formulas as $formula) {
                addLog("", 'info');
                addLog("Формула: {$formula['name']} (тип: {$formula['type']})", 'info');

                $testValues = [0, 1, 2, 3, 5, 10];
                foreach ($testValues as $students) {
                    $payment = calculatePayment($formula, $students);
                    addLog("  {$students} учеников → {$payment} ₽", 'success');
                    $results[] = [
                        'formula' => $formula['name'],
                        'students' => $students,
                        'payment' => $payment
                    ];
                }
            }

            $testResult = ['status' => 'success', 'calculations' => $results];
            break;

        // ==================== ТЕСТЫ БАЗЫ ДАННЫХ ====================

        case 'db_integrity':
            addLog('Проверка целостности базы данных');

            // Проверяем таблицы
            $tables = ['users', 'teachers', 'students', 'lessons_template', 'payment_formulas', 'payments'];
            foreach ($tables as $table) {
                $count = dbQueryOne("SELECT COUNT(*) as cnt FROM {$table}")['cnt'];
                addLog("  {$table}: {$count} записей", 'success');
            }

            // Проверяем VIEW
            try {
                $stats = dbQueryOne("SELECT COUNT(*) as cnt FROM teacher_stats")['cnt'];
                addLog("  teacher_stats VIEW: {$stats} записей", 'success');
            } catch (Exception $e) {
                addLog("  teacher_stats VIEW: ошибка - {$e->getMessage()}", 'error');
            }

            $testResult = ['status' => 'success'];
            break;

        case 'db_teachers':
            addLog('Проверка данных преподавателей');

            $teachers = dbQuery("SELECT * FROM teachers WHERE active = 1");
            addLog("Активных преподавателей: " . count($teachers), 'info');

            foreach ($teachers as $teacher) {
                addLog("", 'info');
                addLog("ID {$teacher['id']}: {$teacher['name']}", 'info');
                addLog("  Telegram: " . ($teacher['telegram_id'] ?: 'не указан'), 'info');
                addLog("  Формула (группа): " . ($teacher['formula_id_group'] ?: 'НЕ УКАЗАНА'),
                       $teacher['formula_id_group'] ? 'success' : 'warning');
                addLog("  Формула (индивид): " . ($teacher['formula_id_individual'] ?: 'НЕ УКАЗАНА'),
                       $teacher['formula_id_individual'] ? 'success' : 'warning');
            }

            $testResult = ['status' => 'success', 'count' => count($teachers)];
            break;

        case 'db_students':
            addLog('Проверка данных учеников');

            $students = dbQuery("SELECT * FROM students WHERE active = 1");
            addLog("Активных учеников: " . count($students), 'info');

            foreach ($students as $student) {
                addLog("", 'info');
                addLog("ID {$student['id']}: {$student['name']}", 'info');
                addLog("  Класс: {$student['class']}, Тир: {$student['tier']}", 'info');
                addLog("  Тип: {$student['lesson_type']}", 'info');

                $schedule = json_decode($student['schedule'], true);
                if ($schedule) {
                    $dayNames = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
                    $scheduleStr = [];
                    foreach ($schedule as $day => $time) {
                        $scheduleStr[] = "{$dayNames[$day]} {$time}";
                    }
                    addLog("  Расписание: " . implode(', ', $scheduleStr), 'info');
                }
            }

            $testResult = ['status' => 'success', 'count' => count($students)];
            break;

        case 'bot_get_teachers':
            addLog('Получение списка преподавателей с Telegram');

            $teachers = dbQuery(
                "SELECT id, name, telegram_id, telegram_username
                 FROM teachers
                 WHERE active = 1 AND telegram_id IS NOT NULL
                 ORDER BY name"
            );

            addLog('Найдено преподавателей: ' . count($teachers), 'success');

            $testResult = $teachers;
            break;

        case 'bot_send_test_lesson':
            addLog('Отправка тестового урока');

            $teacherId = $data['teacher_id'] ?? 0;
            $lessonType = $data['lesson_type'] ?? 'random';

            if (!$teacherId) {
                throw new Exception('Не указан ID преподавателя');
            }

            // Получаем преподавателя
            $teacher = dbQueryOne(
                "SELECT * FROM teachers WHERE id = ? AND active = 1",
                [$teacherId]
            );

            if (!$teacher) {
                throw new Exception('Преподаватель не найден');
            }

            if (!$teacher['telegram_id']) {
                throw new Exception('У преподавателя не указан Telegram ID');
            }

            addLog("Преподаватель: {$teacher['name']} (Telegram: {$teacher['telegram_id']})", 'info');

            // Получаем урок
            if ($lessonType === 'random') {
                // Берём случайный урок из расписания
                $lesson = dbQueryOne(
                    "SELECT * FROM lessons_template
                     WHERE teacher_id = ? AND active = 1
                     ORDER BY RAND() LIMIT 1",
                    [$teacherId]
                );

                if (!$lesson) {
                    addLog('У преподавателя нет уроков в расписании, создаём фейковый', 'warning');
                    $lessonType = 'mock';
                }
            }

            if ($lessonType === 'mock' || !$lesson) {
                // Создаём фейковый урок для теста
                $lesson = [
                    'id' => 999999,
                    'teacher_id' => $teacherId,
                    'subject' => 'Тестовый урок',
                    'time_start' => date('H:i'),
                    'time_end' => date('H:i', strtotime('+1 hour')),
                    'expected_students' => 6,
                    'lesson_type' => 'group',
                    'room' => 1,
                    'tier' => 'A'
                ];
                addLog('Используется фейковый урок для теста', 'info');
            } else {
                addLog("Используется урок из расписания: {$lesson['subject']}", 'success');
            }

            // Формируем сообщение (копируем логику из cron.php)
            $subject = $lesson['subject'] ? "<b>{$lesson['subject']}</b>" : "<b>Урок</b>";
            $timeStart = date('H:i', strtotime($lesson['time_start']));
            $timeEnd = date('H:i', strtotime($lesson['time_end']));
            $expected = $lesson['expected_students'];
            $room = $lesson['room'] ?? '-';
            $tier = $lesson['tier'] ?? '';

            $message = "📊 <b>🧪 ТЕСТОВОЕ УВЕДОМЛЕНИЕ</b>\n\n";
            $message .= "📚 {$subject}";

            if ($tier) {
                $message .= " [Tier {$tier}]";
            }

            $message .= "\n";
            $message .= "🕐 <b>{$timeStart} - {$timeEnd}</b>\n";

            if ($room) {
                $message .= "🏫 Кабинет {$room}\n";
            }

            $message .= "👥 Ожидалось: <b>{$expected}</b> " . ($expected == 1 ? 'ученик' : ($expected < 5 ? 'ученика' : 'учеников')) . "\n\n";
            $message .= "❓ <b>Все ученики пришли на урок?</b>";

            // Inline кнопки
            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '✅ Все пришли',
                            'callback_data' => "attendance_all_present:{$lesson['id']}"
                        ],
                        [
                            'text' => '❌ Не все явились',
                            'callback_data' => "attendance_some_absent:{$lesson['id']}"
                        ]
                    ]
                ]
            ];

            // Отправляем сообщение
            addLog('Отправка сообщения в Telegram...', 'info');
            $result = sendTelegramMessage($teacher['telegram_id'], $message, $keyboard);

            if ($result && isset($result['ok']) && $result['ok']) {
                addLog('✓ Сообщение успешно отправлено!', 'success');
                addLog('Преподаватель может ответить на уведомление', 'info');
                $testResult = [
                    'status' => 'success',
                    'teacher' => $teacher['name'],
                    'telegram_id' => $teacher['telegram_id'],
                    'lesson' => $lesson['subject'],
                    'lesson_id' => $lesson['id'],
                    'message_id' => $result['result']['message_id'] ?? null
                ];
            } else {
                $errorDesc = isset($result['description']) ? $result['description'] : 'Неизвестная ошибка';
                throw new Exception("Не удалось отправить сообщение: {$errorDesc}");
            }
            break;

        default:
            throw new Exception('Неизвестный тест: ' . $testName);
    }

    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'data' => $testResult
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    addLog('ОШИБКА: ' . $e->getMessage(), 'error');
    addLog('Трассировка: ' . $e->getTraceAsString(), 'error');

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'logs' => $logs
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
