<?php
/**
 * Тестовый скрипт для проверки исправлений Telegram бота
 * Проверяет что все критические исправления работают корректно
 */

require_once __DIR__ . '/config.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Тест исправлений Telegram бота</title>";
echo "<style>
body { font-family: 'Courier New', monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
h1 { color: #bb86fc; }
h2 { color: #03dac6; margin-top: 30px; }
.test { margin: 15px 0; padding: 10px; background: #2d2d2d; border-left: 4px solid #666; }
.pass { border-left-color: #4caf50; }
.fail { border-left-color: #f44336; }
.warn { border-left-color: #ff9800; }
.status { font-weight: bold; }
.pass .status { color: #4caf50; }
.fail .status { color: #f44336; }
.warn .status { color: #ff9800; }
pre { background: #0d0d0d; padding: 10px; overflow-x: auto; border-radius: 4px; }
code { color: #ce9178; }
.summary { margin-top: 30px; padding: 20px; background: #2d2d2d; border-radius: 8px; }
</style></head><body>";

echo "<h1>🔍 Тест исправлений Telegram бота</h1>";
echo "<p>Проверка всех критических исправлений от блокировки за спам</p>";

$results = [];
$passed = 0;
$failed = 0;
$warnings = 0;

// ============================================================================
// ТЕСТ 1: Проверка таблицы telegram_updates
// ============================================================================
echo "<h2>Тест 1: Таблица telegram_updates (защита от дублей)</h2>";

try {
    $tableExists = dbQueryOne("SHOW TABLES LIKE 'telegram_updates'", []);

    if ($tableExists) {
        echo "<div class='test pass'>";
        echo "<div class='status'>✅ PASS</div>";
        echo "<p>Таблица <code>telegram_updates</code> существует</p>";

        // Проверяем структуру
        $columns = dbQuery("DESCRIBE telegram_updates", []);
        echo "<pre>";
        foreach ($columns as $col) {
            echo "{$col['Field']} - {$col['Type']}\n";
        }
        echo "</pre>";

        // Проверяем индексы
        $indexes = dbQuery("SHOW INDEXES FROM telegram_updates", []);
        $hasUniqueIndex = false;
        foreach ($indexes as $idx) {
            if ($idx['Key_name'] === 'idx_update_id' && $idx['Non_unique'] == 0) {
                $hasUniqueIndex = true;
            }
        }

        if ($hasUniqueIndex) {
            echo "<p>✅ UNIQUE индекс на <code>update_id</code> установлен</p>";
        } else {
            echo "<p>⚠️ WARNING: UNIQUE индекс не найден</p>";
            $warnings++;
        }

        echo "</div>";
        $passed++;
    } else {
        echo "<div class='test fail'>";
        echo "<div class='status'>❌ FAIL</div>";
        echo "<p>Таблица <code>telegram_updates</code> НЕ существует!</p>";
        echo "<p><strong>Действие:</strong> Выполнить миграцию <code>zarplata/migrations/add_telegram_updates_table.sql</code></p>";
        echo "</div>";
        $failed++;
    }
} catch (Exception $e) {
    echo "<div class='test fail'>";
    echo "<div class='status'>❌ ERROR</div>";
    echo "<p>Ошибка проверки таблицы: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    $failed++;
}

// ============================================================================
// ТЕСТ 2: Проверка webhook.php (HTTP 200 первым делом)
// ============================================================================
echo "<h2>Тест 2: Webhook возвращает HTTP 200 сразу</h2>";

$webhookContent = file_get_contents(__DIR__ . '/webhook.php');
$lines = explode("\n", $webhookContent);

// Проверяем что HTTP 200 отправляется в первых 15 строках (до require)
$http200Found = false;
$http200Line = 0;
$requireLine = 0;

foreach ($lines as $num => $line) {
    if (stripos($line, 'http_response_code(200)') !== false) {
        $http200Found = true;
        $http200Line = $num + 1;
    }
    if (stripos($line, 'require_once') !== false && $requireLine === 0) {
        $requireLine = $num + 1;
    }
}

if ($http200Found && $http200Line < $requireLine && $http200Line <= 15) {
    echo "<div class='test pass'>";
    echo "<div class='status'>✅ PASS</div>";
    echo "<p><code>http_response_code(200)</code> находится на строке <strong>{$http200Line}</strong></p>";
    echo "<p>Первый <code>require_once</code> на строке <strong>{$requireLine}</strong></p>";
    echo "<p>✅ HTTP 200 отправляется <strong>ДО</strong> загрузки конфигов</p>";
    echo "</div>";
    $passed++;
} else {
    echo "<div class='test fail'>";
    echo "<div class='status'>❌ FAIL</div>";
    echo "<p>HTTP 200 не на своём месте!</p>";
    echo "<p>http_response_code(200) - строка: {$http200Line}</p>";
    echo "<p>require_once - строка: {$requireLine}</p>";
    echo "</div>";
    $failed++;
}

// Проверяем fastcgi_finish_request
if (stripos($webhookContent, 'fastcgi_finish_request') !== false) {
    echo "<div class='test pass'>";
    echo "<div class='status'>✅ PASS</div>";
    echo "<p><code>fastcgi_finish_request()</code> присутствует</p>";
    echo "<p>Соединение с Telegram закрывается немедленно</p>";
    echo "</div>";
    $passed++;
} else {
    echo "<div class='test warn'>";
    echo "<div class='status'>⚠️ WARNING</div>";
    echo "<p><code>fastcgi_finish_request()</code> не найден</p>";
    echo "</div>";
    $warnings++;
}

// ============================================================================
// ТЕСТ 3: Проверка защиты от дублей в webhook.php
// ============================================================================
echo "<h2>Тест 3: Защита от дублей update_id в webhook</h2>";

if (stripos($webhookContent, 'telegram_updates') !== false &&
    stripos($webhookContent, 'INSERT IGNORE') !== false) {
    echo "<div class='test pass'>";
    echo "<div class='status'>✅ PASS</div>";
    echo "<p>Проверка дублей <code>update_id</code> реализована</p>";
    echo "<p>Используется <code>INSERT IGNORE</code> для защиты от race condition</p>";
    echo "</div>";
    $passed++;
} else {
    echo "<div class='test fail'>";
    echo "<div class='status'>❌ FAIL</div>";
    echo "<p>Защита от дублей НЕ реализована в webhook.php</p>";
    echo "</div>";
    $failed++;
}

// ============================================================================
// ТЕСТ 4: Проверка try-catch с Throwable
// ============================================================================
echo "<h2>Тест 4: Глобальный try-catch с Throwable</h2>";

if (stripos($webhookContent, 'catch (Throwable') !== false) {
    echo "<div class='test pass'>";
    echo "<div class='status'>✅ PASS</div>";
    echo "<p><code>catch (Throwable)</code> используется</p>";
    echo "<p>Все ошибки (Exception + Error) будут пойманы</p>";
    echo "</div>";
    $passed++;
} else {
    echo "<div class='test warn'>";
    echo "<div class='status'>⚠️ WARNING</div>";
    echo "<p>Используется <code>catch (Exception)</code> вместо <code>Throwable</code></p>";
    echo "<p>Fatal errors могут не ловиться</p>";
    echo "</div>";
    $warnings++;
}

// ============================================================================
// ТЕСТ 5: Проверка AttendanceHandler.php (fallback на formula_id)
// ============================================================================
echo "<h2>Тест 5: AttendanceHandler с fallback на formula_id</h2>";

$handlerContent = file_get_contents(__DIR__ . '/handlers/AttendanceHandler.php');

if (stripos($handlerContent, 'getFormulaIdForTeacher') !== false) {
    echo "<div class='test pass'>";
    echo "<div class='status'>✅ PASS</div>";
    echo "<p>Функция <code>getFormulaIdForTeacher()</code> реализована</p>";

    if (stripos($handlerContent, 'formula_id_individual') !== false &&
        stripos($handlerContent, 'formula_id_group') !== false &&
        stripos($handlerContent, "teacher['formula_id']") !== false) {
        echo "<p>✅ Проверяет <code>formula_id_individual</code></p>";
        echo "<p>✅ Проверяет <code>formula_id_group</code></p>";
        echo "<p>✅ Fallback на <code>formula_id</code></p>";
    }

    echo "</div>";
    $passed++;
} else {
    echo "<div class='test fail'>";
    echo "<div class='status'>❌ FAIL</div>";
    echo "<p>Функция <code>getFormulaIdForTeacher()</code> НЕ найдена</p>";
    echo "</div>";
    $failed++;
}

// ============================================================================
// ТЕСТ 6: Проверка cron.php (отключение email)
// ============================================================================
echo "<h2>Тест 6: Cron не отправляет email</h2>";

$cronContent = file_get_contents(__DIR__ . '/cron.php');

$hasObStart = stripos($cronContent, 'ob_start()') !== false;
$hasObEndClean = stripos($cronContent, 'ob_end_clean()') !== false;

if ($hasObStart && $hasObEndClean) {
    echo "<div class='test pass'>";
    echo "<div class='status'>✅ PASS</div>";
    echo "<p><code>ob_start()</code> в начале скрипта</p>";
    echo "<p><code>ob_end_clean()</code> в конце скрипта</p>";
    echo "<p>✅ Вывод захватывается и НЕ отправляется на email</p>";
    echo "</div>";
    $passed++;
} else {
    echo "<div class='test fail'>";
    echo "<div class='status'>❌ FAIL</div>";
    if (!$hasObStart) echo "<p>❌ <code>ob_start()</code> не найден</p>";
    if (!$hasObEndClean) echo "<p>❌ <code>ob_end_clean()</code> не найден</p>";
    echo "</div>";
    $failed++;
}

// ============================================================================
// ТЕСТ 7: Проверка cleanup_updates.php
// ============================================================================
echo "<h2>Тест 7: Cleanup script</h2>";

if (file_exists(__DIR__ . '/cleanup_updates.php')) {
    $cleanupContent = file_get_contents(__DIR__ . '/cleanup_updates.php');

    $hasObStart = stripos($cleanupContent, 'ob_start()') !== false;
    $hasObEndClean = stripos($cleanupContent, 'ob_end_clean()') !== false;

    if ($hasObStart && $hasObEndClean) {
        echo "<div class='test pass'>";
        echo "<div class='status'>✅ PASS</div>";
        echo "<p>Скрипт <code>cleanup_updates.php</code> существует</p>";
        echo "<p>✅ Email от cron отключены (ob_start/ob_end_clean)</p>";
        echo "</div>";
        $passed++;
    } else {
        echo "<div class='test warn'>";
        echo "<div class='status'>⚠️ WARNING</div>";
        echo "<p>cleanup_updates.php существует, но может отправлять email</p>";
        echo "</div>";
        $warnings++;
    }
} else {
    echo "<div class='test warn'>";
    echo "<div class='status'>⚠️ WARNING</div>";
    echo "<p>Скрипт <code>cleanup_updates.php</code> не найден</p>";
    echo "</div>";
    $warnings++;
}

// ============================================================================
// ТЕСТ 8: Проверка Webhook Info от Telegram
// ============================================================================
echo "<h2>Тест 8: Telegram Webhook Info</h2>";

try {
    $setting = dbQueryOne("SELECT setting_value FROM settings WHERE setting_key = 'bot_token'", []);
    $token = $setting['setting_value'] ?? '';

    if ($token) {
        $url = "https://api.telegram.org/bot{$token}/getWebhookInfo";
        $response = @file_get_contents($url);

        if ($response) {
            $data = json_decode($response, true);

            if ($data['ok']) {
                $info = $data['result'];

                echo "<div class='test pass'>";
                echo "<div class='status'>✅ PASS</div>";
                echo "<p><strong>Webhook URL:</strong> " . htmlspecialchars($info['url']) . "</p>";
                echo "<p><strong>Pending updates:</strong> {$info['pending_update_count']}</p>";

                if ($info['pending_update_count'] > 10) {
                    echo "<p>⚠️ WARNING: Много pending updates ({$info['pending_update_count']})</p>";
                    echo "<p>Возможно webhook не работает корректно</p>";
                    $warnings++;
                }

                if (isset($info['last_error_message'])) {
                    echo "<p>⚠️ Last error: " . htmlspecialchars($info['last_error_message']) . "</p>";
                    echo "<p>Error date: " . date('Y-m-d H:i:s', $info['last_error_date']) . "</p>";
                    $warnings++;
                } else {
                    echo "<p>✅ Нет ошибок от Telegram</p>";
                    $passed++;
                }

                echo "</div>";
            } else {
                echo "<div class='test fail'>";
                echo "<div class='status'>❌ FAIL</div>";
                echo "<p>Telegram API вернул ошибку</p>";
                echo "</div>";
                $failed++;
            }
        } else {
            echo "<div class='test warn'>";
            echo "<div class='status'>⚠️ WARNING</div>";
            echo "<p>Не удалось получить Webhook Info от Telegram</p>";
            echo "</div>";
            $warnings++;
        }
    } else {
        echo "<div class='test warn'>";
        echo "<div class='status'>⚠️ WARNING</div>";
        echo "<p>Токен бота не найден в settings</p>";
        echo "</div>";
        $warnings++;
    }
} catch (Exception $e) {
    echo "<div class='test warn'>";
    echo "<div class='status'>⚠️ WARNING</div>";
    echo "<p>Ошибка проверки webhook: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    $warnings++;
}

// ============================================================================
// ИТОГОВАЯ СВОДКА
// ============================================================================
$total = $passed + $failed + $warnings;

echo "<div class='summary'>";
echo "<h2>📊 Итоговая сводка</h2>";
echo "<p><strong>Всего тестов:</strong> {$total}</p>";
echo "<p style='color: #4caf50;'><strong>✅ Пройдено:</strong> {$passed}</p>";
echo "<p style='color: #f44336;'><strong>❌ Провалено:</strong> {$failed}</p>";
echo "<p style='color: #ff9800;'><strong>⚠️ Предупреждений:</strong> {$warnings}</p>";

if ($failed === 0 && $warnings === 0) {
    echo "<h3 style='color: #4caf50;'>🎉 ВСЕ ТЕСТЫ ПРОЙДЕНЫ!</h3>";
    echo "<p>Все критические исправления работают корректно.</p>";
    echo "<p>Telegram бот готов к работе без спама и блокировок.</p>";
} elseif ($failed === 0) {
    echo "<h3 style='color: #ff9800;'>⚠️ ЕСТЬ ПРЕДУПРЕЖДЕНИЯ</h3>";
    echo "<p>Критические тесты пройдены, но есть некритичные замечания.</p>";
} else {
    echo "<h3 style='color: #f44336;'>❌ ТРЕБУЮТСЯ ИСПРАВЛЕНИЯ</h3>";
    echo "<p>Некоторые критические тесты не пройдены.</p>";
    echo "<p>Необходимо исправить ошибки перед использованием бота.</p>";
}

echo "</div>";

// Следующие шаги
echo "<h2>📝 Следующие шаги</h2>";
echo "<ol>";

if ($failed > 0) {
    echo "<li><strong>Исправить критические ошибки</strong> (помечены ❌)</li>";
}

echo "<li>Открыть <a href='reset_webhook.php' style='color: #03dac6;'>reset_webhook.php</a> для сброса webhook Telegram</li>";
echo "<li>Отправить боту <code>/start</code> в Telegram</li>";
echo "<li>Проверить работу кнопок посещаемости</li>";
echo "<li>Проверить что email от cron не приходят</li>";
echo "</ol>";

echo "<hr style='margin: 30px 0; border-color: #666;'>";
echo "<p style='text-align: center; color: #888;'>Тест завершён: " . date('Y-m-d H:i:s') . "</p>";

echo "</body></html>";
