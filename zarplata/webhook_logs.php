<?php
/**
 * Просмотр логов webhook для отладки MacroDroid
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

session_start();

// Требуем авторизацию
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$logFile = __DIR__ . '/logs/webhook_debug.log';
$logs = '';
$error = '';

// Очистка логов
if (isset($_POST['clear'])) {
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }
    header('Location: webhook_logs.php?cleared=1');
    exit;
}

// Чтение логов
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    if (empty($logs)) {
        $logs = '(Логи пусты - ещё не было запросов к webhook)';
    }
} else {
    $logs = '(Файл логов не существует - ещё не было запросов к webhook)';
}

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

        <div class="card">
            <h2>📡 Webhook URL для MacroDroid</h2>
            <div class="url-box" id="webhookUrl"><?= htmlspecialchars($webhookUrl) ?></div>
            <button class="btn btn-primary" onclick="copyUrl()">
                <span class="material-icons">content_copy</span>
                Копировать URL
            </button>
            <p class="hint">Используй этот URL в MacroDroid → HTTP-запрос (POST)</p>
        </div>

        <div class="card">
            <h2>📝 Тело запроса для MacroDroid</h2>
            <div class="url-box" id="bodyTemplate">{"notification": "[not_title] [not_text]"}</div>
            <button class="btn btn-secondary" onclick="copyBody()">
                <span class="material-icons">content_copy</span>
                Копировать
            </button>
            <p class="hint">Вставь в "Тело сообщения" и установи тип: application/json</p>
        </div>

        <div class="actions">
            <button class="btn btn-primary" onclick="location.reload()">
                <span class="material-icons">refresh</span>
                Обновить
            </button>
            <form method="POST" style="display: inline;">
                <button type="submit" name="clear" class="btn btn-danger" onclick="return confirm('Очистить все логи?')">
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
            <h2>📋 Входящие запросы</h2>
            <div class="logs"><?= htmlspecialchars($logs) ?></div>
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
