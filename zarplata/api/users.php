<?php
/**
 * API управления пользователями панели (только админ/владелец).
 * Создание аккаунтов преподавателей, сброс пароля, доступ к дашборду.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonError('Требуется авторизация', 401);
}
if (!isAdmin()) {
    jsonError('Доступ запрещён', 403);
}

ensureUsersRolesSchema();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        handleListUsers();
        break;
    case 'create':
        handleCreateUser();
        break;
    case 'update':
        handleUpdateUser();
        break;
    case 'reset_password':
        handleResetPassword();
        break;
    default:
        jsonError('Неизвестное действие', 400);
}

function handleListUsers() {
    $users = dbQuery(
        "SELECT u.id, u.username, u.name, u.role, u.active, u.teacher_id, u.can_dashboard,
                COALESCE(t.display_name, t.name) AS teacher_name
         FROM users u
         LEFT JOIN teachers t ON u.teacher_id = t.id
         ORDER BY u.role, u.id",
        []
    );
    $teachers = dbQuery(
        "SELECT id, COALESCE(display_name, name) AS name FROM teachers WHERE active = 1 ORDER BY id",
        []
    );
    jsonSuccess(['users' => $users, 'teachers' => $teachers, 'self_id' => getCurrentUserId()]);
}

function handleCreateUser() {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    $username = trim((string)($data['username'] ?? ''));
    $name = trim((string)($data['name'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $role = ($data['role'] ?? 'teacher') === 'admin' ? 'admin' : 'teacher';
    $teacherId = (int)($data['teacher_id'] ?? 0) ?: null;

    if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
        jsonError('Логин: 3–50 символов, латиница/цифры/._-', 400);
    }
    if ($name === '') {
        jsonError('Укажите имя', 400);
    }
    if (mb_strlen($password) < 8) {
        jsonError('Пароль не короче 8 символов', 400);
    }
    if ($role === 'teacher') {
        if (!$teacherId) {
            jsonError('Привяжите аккаунт к преподавателю', 400);
        }
        $t = dbQueryOne("SELECT id FROM teachers WHERE id = ? AND active = 1", [$teacherId]);
        if (!$t) {
            jsonError('Преподаватель не найден', 400);
        }
    } else {
        $teacherId = null;
    }

    $userId = createUser($username, $password, $name, $role);
    if (!$userId) {
        jsonError('Логин уже занят', 400);
    }

    if ($teacherId) {
        dbExecute("UPDATE users SET teacher_id = ? WHERE id = ?", [$teacherId, $userId]);
    }

    jsonSuccess(['id' => $userId]);
}

function handleUpdateUser() {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $userId = (int)($data['id'] ?? 0);

    if (!$userId) {
        jsonError('Не указан пользователь', 400);
    }

    $target = dbQueryOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$target) {
        jsonError('Пользователь не найден', 404);
    }

    // Себя нельзя деактивировать или лишить роли
    if ($userId === getCurrentUserId()) {
        if (array_key_exists('active', $data) && !(int)$data['active']) {
            jsonError('Нельзя деактивировать собственный аккаунт', 400);
        }
        if (isset($data['role']) && $data['role'] === 'teacher') {
            jsonError('Нельзя понизить собственную роль', 400);
        }
    }
    // Владельца не трогаем (кроме can_dashboard самим владельцем)
    if ($target['role'] === 'owner' && !isOwner() && (isset($data['role']) || array_key_exists('active', $data))) {
        jsonError('Изменять владельца может только владелец', 403);
    }

    $updates = [];
    $params = [];

    if (array_key_exists('can_dashboard', $data)) {
        $updates[] = 'can_dashboard = ?';
        $params[] = $data['can_dashboard'] === null ? null : (int)(bool)$data['can_dashboard'];
    }
    if (array_key_exists('active', $data)) {
        $updates[] = 'active = ?';
        $params[] = (int)(bool)$data['active'];
    }
    if (isset($data['teacher_id'])) {
        $tid = (int)$data['teacher_id'] ?: null;
        if ($tid) {
            $t = dbQueryOne("SELECT id FROM teachers WHERE id = ? AND active = 1", [$tid]);
            if (!$t) {
                jsonError('Преподаватель не найден', 400);
            }
        }
        $updates[] = 'teacher_id = ?';
        $params[] = $tid;
    }
    if (isset($data['role']) && in_array($data['role'], ['admin', 'teacher'], true)) {
        $updates[] = 'role = ?';
        $params[] = $data['role'];
    }

    if (empty($updates)) {
        jsonError('Нечего обновлять', 400);
    }

    $params[] = $userId;
    dbExecute("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?", $params);

    logAudit('user_updated', 'user', $userId, null, $data, 'Обновление пользователя');
    jsonSuccess(['id' => $userId]);
}

function handleResetPassword() {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $userId = (int)($data['id'] ?? 0);
    $password = (string)($data['password'] ?? '');

    if (!$userId) {
        jsonError('Не указан пользователь', 400);
    }
    if (mb_strlen($password) < 8) {
        jsonError('Пароль не короче 8 символов', 400);
    }

    $target = dbQueryOne("SELECT id, role FROM users WHERE id = ?", [$userId]);
    if (!$target) {
        jsonError('Пользователь не найден', 404);
    }
    if ($target['role'] === 'owner' && !isOwner() && $userId !== getCurrentUserId()) {
        jsonError('Сбросить пароль владельца может только владелец', 403);
    }

    changePassword($userId, $password);
    jsonSuccess(['id' => $userId]);
}
