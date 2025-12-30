<?php
/**
 * Просмотр логов webhook для отладки MacroDroid
 */

require_once __DIR__ . '/config/db.php';

$webhookLogFile = __DIR__ . '/logs/webhook_debug.log';
$emailLogFile = __DIR__ . '/logs/email_parser.log';
$webhookLogs = '';
$emailLogs = '';

// Очистка логов
if (isset($_POST['clear_webhook'])) {
    if (file_exists($webhookLogFile)) {
        file_put_contents($webhookLogFile, '');
    }
    header('Location: webhook_logs.php?cleared=webhook');
    exit;
}
if (isset($_POST['clear_email'])) {
    if (file_exists($emailLogFile)) {
        file_put_contents($emailLogFile, '');
    }
    header('Location: webhook_logs.php?cleared=email');
    exit;
}

// Запуск проверки почты
$emailCheckOutput = '';
if (isset($_POST['check_email'])) {
    $output = [];
    $returnCode = 0;
    exec('php ' . __DIR__ . '/cron/check_email.php 2>&1', $output, $returnCode);
    $emailCheckOutput = implode("\n", $output);
    if (empty($emailCheckOutput)) {
        $emailCheckOutput = "(exec вернул пустой результат, код: $returnCode)";
    }
}

// Чтение логов webhook
if (file_exists($webhookLogFile)) {
    $webhookLogs = file_get_contents($webhookLogFile);
    if (empty($webhookLogs)) {
        $webhookLogs = '(Логи пусты)';
    }
} else {
    $webhookLogs = '(Файл логов не существует)';
}

// Чтение логов email
if (file_exists($emailLogFile)) {
    $emailLogs = file_get_contents($emailLogFile);
    if (empty($emailLogs)) {
        $emailLogs = '(Логи пусты)';
    }
} else {
    $emailLogs = '(Файл логов не существует)';
}

// Статус настроек Gmail
$gmailConfigured = !empty(getSetting('gmail_user', '')) && !empty(getSetting('gmail_app_password', ''));

// Получаем токен для отображения URL
$token = getSetting('automate_api_token', '');
if (empty($token)) {
    $token = bin2hex(random_bytes(16));
    setSetting('automate_api_token', $token);
}

$webhookUrl = 'https://эвриум.рф/zarplata/api/incoming_payments.php?action=webhook&token=' . $token;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Логи Webhook - Zarplata</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Montserrat', sans-serif;
            background: #121212;
            color: #e0e0e0;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #BB86FC;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card {
            background: #1e1e1e;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .card h2 {
            color: #03DAC6;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .url-box {
            background: #2d2d2d;
            border-radius: 8px;
            padding: 15px;
            font-family: monospace;
            font-size: 13px;
            word-break: break-all;
            margin-bottom: 10px;
            border: 1px solid #333;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #BB86FC;
            color: #121212;
        }
        .btn-primary:hover {
            background: #ce9ffc;
        }
        .btn-danger {
            background: #CF6679;
            color: #121212;
        }
        .btn-danger:hover {
            background: #e57c8e;
        }
        .btn-secondary {
            background: #333;
            color: #e0e0e0;
        }
        .btn-secondary:hover {
            background: #444;
        }
        .logs {
            background: #0d0d0d;
            border-radius: 8px;
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #333;
            line-height: 1.6;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: rgba(3, 218, 198, 0.15);
            border: 1px solid #03DAC6;
            color: #03DAC6;
        }
        .back-link {
            color: #BB86FC;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 20px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .hint {
            color: #888;
            font-size: 13px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="student_payments.php" class="back-link">
            <span class="material-icons">arrow_back</span>
            Назад к платежам
        </a>

        <h1>
            <span class="material-icons">bug_report</span>
            Логи Webhook
        </h1>

        <?php if (isset($_GET['cleared'])): ?>
        <div class="alert alert-success">
            <span class="material-icons" style="vertical-align: middle;">check_circle</span>
            Логи очищены
        </div>
        <?php endif; ?>

        <?php if (!empty($emailCheckOutput)): ?>
        <div class="card" style="border: 2px solid #BB86FC;">
            <h2>🔍 Результат проверки почты</h2>
            <div class="logs" style="max-height: 400px;"><?= htmlspecialchars($emailCheckOutput) ?></div>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['email_checked'])): ?>
        <div class="alert alert-success">
            <span class="material-icons" style="vertical-align: middle;">check_circle</span>
            Проверка почты выполнена
        </div>
        <?php endif; ?>

        <!-- Email парсинг (основной метод) -->
        <div class="card" style="border: 2px solid #03DAC6;">
            <h2>📧 Парсинг Email (рекомендуется)</h2>
            <?php if ($gmailConfigured): ?>
                <div class="alert alert-success" style="margin-bottom: 15px;">
                    <span class="material-icons" style="vertical-align: middle;">check_circle</span>
                    Gmail настроен: <?= htmlspecialchars(getSetting('gmail_user', '')) ?>
                </div>
            <?php else: ?>
                <div class="alert" style="background: rgba(207, 102, 121, 0.15); border: 1px solid #CF6679; color: #CF6679; margin-bottom: 15px;">
                    <span class="material-icons" style="vertical-align: middle;">warning</span>
                    Gmail не настроен. Выполни SQL из migrations/add_gmail_settings.sql
                </div>
            <?php endif; ?>

            <div class="actions" style="margin-bottom: 0;">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="check_email" class="btn btn-primary" <?= $gmailConfigured ? '' : 'disabled' ?>>
                        <span class="material-icons">mail</span>
                        Проверить почту сейчас
                    </button>
                </form>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="clear_email" class="btn btn-danger" onclick="return confirm('Очистить логи email?')">
                        <span class="material-icons">delete</span>
                        Очистить логи
                    </button>
                </form>
            </div>
            <p class="hint">Notification Forwarder → Email → Сервер парсит письма каждые 5 минут</p>
        </div>

        <div class="card">
            <h2>📋 Логи парсинга Email</h2>
            <div class="logs" style="max-height: 300px;"><?= htmlspecialchars($emailLogs) ?></div>
        </div>

        <hr style="border-color: #333; margin: 30px 0;">

        <!-- Webhook (альтернативный метод) -->
        <div class="card">
            <h2>📡 Webhook URL (альтернатива)</h2>
            <div class="url-box" id="webhookUrl"><?= htmlspecialchars($webhookUrl) ?></div>
            <button class="btn btn-secondary" onclick="copyUrl()">
                <span class="material-icons">content_copy</span>
                Копировать URL
            </button>
            <p class="hint">Для MacroDroid/Android приложения</p>
        </div>

        <div class="actions">
            <button class="btn btn-primary" onclick="location.reload()">
                <span class="material-icons">refresh</span>
                Обновить
            </button>
            <form method="POST" style="display: inline;">
                <button type="submit" name="clear_webhook" class="btn btn-danger" onclick="return confirm('Очистить логи webhook?')">
                    <span class="material-icons">delete</span>
                    Очистить логи
                </button>
            </form>
            <button class="btn btn-secondary" onclick="testWebhook()">
                <span class="material-icons">send</span>
                Тестовый запрос
            </button>
        </div>

        <div class="card">
            <h2>📋 Логи Webhook</h2>
            <div class="logs" style="max-height: 300px;"><?= htmlspecialchars($webhookLogs) ?></div>
        </div>
    </div>

    <script>
        function copyUrl() {
            navigator.clipboard.writeText(document.getElementById('webhookUrl').textContent);
            alert('URL скопирован!');
        }

        function copyBody() {
            navigator.clipboard.writeText(document.getElementById('bodyTemplate').textContent);
            alert('Шаблон скопирован!');
        }

        async function testWebhook() {
            const url = '<?= htmlspecialchars($webhookUrl) ?>';
            const testData = {
                notification: 'TEST Перевод по СБП от ТЕСТ ТЕСТОВИЧ Тест-Банк +1000 ₽'
            };

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(testData)
                });
                const result = await response.json();
                alert('Ответ сервера:\n' + JSON.stringify(result, null, 2));
                location.reload();
            } catch (error) {
                alert('Ошибка: ' + error.message);
            }
        }

        // Автообновление каждые 5 секунд
        setInterval(() => {
            location.reload();
        }, 10000);
    </script>
</body>
</html>
