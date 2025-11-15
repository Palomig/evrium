<?php
/**
 * Страница входа в систему
 * Система учёта зарплаты преподавателей
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

// Если уже авторизован, редирект на главную
if (isLoggedIn()) {
    redirect('/zarplata/');
}

$error = null;

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Пожалуйста, заполните все поля';
    } else {
        if (login($username, $password)) {
            redirect('/zarplata/');
        } else {
            $error = 'Неверное имя пользователя или пароль';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему — Учёт зарплаты</title>

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Montserrat Font -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Material Dark Theme CSS -->
    <link rel="stylesheet" href="/zarplata/assets/css/material-dark.css">

    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            animation: slideUp 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--md-primary), var(--md-secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: var(--elevation-3);
        }

        .login-icon .material-icons {
            font-size: 36px;
            color: var(--md-on-primary);
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 400;
            margin-bottom: 8px;
        }

        .login-subtitle {
            font-size: 0.875rem;
            color: var(--text-medium-emphasis);
        }

        .footer-text {
            text-align: center;
            margin-top: 24px;
            font-size: 0.75rem;
            color: var(--text-disabled);
        }

        .error-shake {
            animation: shake 0.4s cubic-bezier(0.36, 0.07, 0.19, 0.97);
        }

        @keyframes shake {
            10%, 90% { transform: translateX(-2px); }
            20%, 80% { transform: translateX(4px); }
            30%, 50%, 70% { transform: translateX(-6px); }
            40%, 60% { transform: translateX(6px); }
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-medium-emphasis);
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }

        .password-toggle:hover {
            color: var(--text-high-emphasis);
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon .material-icons {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-medium-emphasis);
            font-size: 20px;
        }

        .input-with-icon input {
            padding-left: 44px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <span class="material-icons">account_balance_wallet</span>
                </div>
                <h1 class="login-title">Учёт зарплаты</h1>
                <p class="login-subtitle">Система управления выплатами преподавателям</p>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-error error-shake">
                            <span class="material-icons">error_outline</span>
                            <span><?= e($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="/zarplata/login.php" autocomplete="off">
                        <div class="form-group">
                            <label class="form-label" for="username">
                                <span class="material-icons" style="font-size: 16px; vertical-align: middle;">person</span>
                                Имя пользователя
                            </label>
                            <div class="input-with-icon">
                                <span class="material-icons">person_outline</span>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="username"
                                    name="username"
                                    placeholder="Введите имя пользователя"
                                    value="<?= e($_POST['username'] ?? '') ?>"
                                    required
                                    autofocus
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password">
                                <span class="material-icons" style="font-size: 16px; vertical-align: middle;">lock</span>
                                Пароль
                            </label>
                            <div class="input-with-icon">
                                <span class="material-icons">lock_outline</span>
                                <input
                                    type="password"
                                    class="form-control"
                                    id="password"
                                    name="password"
                                    placeholder="Введите пароль"
                                    required
                                >
                                <button type="button" class="password-toggle" onclick="togglePassword()">
                                    <span class="material-icons" id="password-icon">visibility</span>
                                </button>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <button type="submit" class="btn btn-primary btn-large btn-block">
                                <span class="material-icons" style="margin-right: 8px; font-size: 20px;">login</span>
                                Войти
                            </button>
                        </div>
                    </form>

                    <div class="text-center">
                        <p class="text-disabled" style="font-size: 0.75rem;">
                            Для доступа к системе используйте учётные данные администратора
                        </p>
                    </div>
                </div>
            </div>

            <div class="footer-text">
                <p>© <?= date('Y') ?> Система учёта зарплаты преподавателей</p>
                <p style="margin-top: 4px;">Powered by Evrium</p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.textContent = 'visibility_off';
            } else {
                passwordInput.type = 'password';
                passwordIcon.textContent = 'visibility';
            }
        }

        // Убрать анимацию ошибки после завершения
        document.addEventListener('DOMContentLoaded', () => {
            const errorAlert = document.querySelector('.error-shake');
            if (errorAlert) {
                setTimeout(() => {
                    errorAlert.classList.remove('error-shake');
                }, 400);
            }
        });
    </script>
</body>
</html>
