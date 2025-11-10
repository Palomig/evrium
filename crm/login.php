<?php
/**
 * Evrium CRM - Login Page
 * Страница авторизации
 */

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

// Если уже авторизован, редирект на панель
if (isLoggedIn()) {
    redirect('/crm/dashboard.php');
}

$error = '';
$success = '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = postParam('username', '');
    $password = postParam('password', '');

    if (empty($username) || empty($password)) {
        $error = 'Заполните все поля';
    } else {
        $result = login($username, $password);

        if ($result['success']) {
            redirect('/crm/dashboard.php');
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Evrium CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #000000;
            --bg-secondary: #1d1d1f;
            --text-light: #f5f5f7;
            --text-secondary: #a1a1a6;
            --accent: #0071e3;
        }

        body {
            background: linear-gradient(135deg, var(--bg-dark) 0%, var(--bg-secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .login-card {
            background: rgba(29, 29, 31, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 48px 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo h1 {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), #30d158);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .logo p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .form-label {
            color: var(--text-light);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: var(--text-light);
            padding: 12px 16px;
            font-size: 16px;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--accent);
            color: var(--text-light);
            box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.2);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        .btn-primary {
            background: var(--accent);
            border: none;
            border-radius: 980px;
            padding: 12px 32px;
            font-size: 16px;
            font-weight: 500;
            width: 100%;
            margin-top: 24px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #0077ed;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 113, 227, 0.3);
        }

        .alert {
            border-radius: 12px;
            border: none;
            margin-bottom: 24px;
        }

        .alert-danger {
            background: rgba(255, 69, 58, 0.15);
            color: #ff453a;
        }

        .alert-success {
            background: rgba(48, 209, 88, 0.15);
            color: #30d158;
        }

        .back-link {
            text-align: center;
            margin-top: 24px;
        }

        .back-link a {
            color: var(--accent);
            text-decoration: none;
            font-size: 14px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <h1>Evrium</h1>
                <p>CRM для репетиторов</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= e($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Имя пользователя</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Введите логин" required autofocus>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Пароль</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Введите пароль" required>
                </div>

                <button type="submit" class="btn btn-primary">Войти</button>
            </form>

            <div class="back-link">
                <a href="/crm.html">← Вернуться на главную</a>
            </div>
        </div>

        <div class="text-center mt-4" style="color: var(--text-secondary); font-size: 12px;">
            <p>Тестовый доступ: admin / admin123</p>
        </div>
    </div>
</body>
</html>
