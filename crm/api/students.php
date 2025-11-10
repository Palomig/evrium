<?php
/**
 * Evrium CRM - Students API
 * API для работы с учениками
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Получение метода запроса
$method = $_SERVER['REQUEST_METHOD'];

// Проверка API токена
$token = getBearerToken();
if ($token) {
    $user = validateAPIToken($token);
    if (!$user) {
        jsonError('Неверный или истекший токен', 401);
    }
    // Установка пользователя в сессию для дальнейшего использования
    $_SESSION['api_user'] = $user;
} else {
    // Проверка обычной сессии
    requireLogin();
    $user = [
        'id' => getCurrentUserId(),
        'role' => getCurrentUserRole()
    ];
}

// Маршрутизация
$action = getParam('action', 'list');

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            getStudentsList($user);
        } elseif ($action === 'get') {
            getStudent($user);
        } elseif ($action === 'stats') {
            getStudentStats($user);
        } else {
            jsonError('Неизвестное действие');
        }
        break;

    case 'POST':
        if ($action === 'add') {
            addStudent($user);
        } elseif ($action === 'update') {
            updateStudent($user);
        } elseif ($action === 'delete') {
            deleteStudent($user);
        } else {
            jsonError('Неизвестное действие');
        }
        break;

    default:
        jsonError('Метод не поддерживается', 405);
}

/**
 * Получить список учеников
 */
function getStudentsList($user) {
    $teacherId = getParam('teacher_id');
    $status = getParam('status');
    $search = getParam('search', '');
    $page = (int)getParam('page', 1);
    $perPage = (int)getParam('per_page', 20);

    // Построение запроса
    $where = [];
    $params = [];

    // Фильтр по преподавателю
    if ($user['role'] === 'teacher') {
        $where[] = 's.teacher_id = ?';
        $params[] = $user['id'];
    } elseif ($teacherId) {
        $where[] = 's.teacher_id = ?';
        $params[] = $teacherId;
    }

    // Фильтр по статусу
    if ($status) {
        $where[] = 's.status = ?';
        $params[] = $status;
    }

    // Поиск
    if ($search) {
        $where[] = '(s.name LIKE ? OR s.phone LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Подсчет общего количества
    $countQuery = "SELECT COUNT(*) as total FROM students s $whereClause";
    $totalResult = dbQueryOne($countQuery, $params);
    $total = $totalResult['total'];

    // Пагинация
    $pagination = paginate($total, $perPage, $page);

    // Получение студентов
    $query = "
        SELECT
            s.*,
            a.name as teacher_name,
            COUNT(DISTINCT l.id) as total_lessons,
            SUM(CASE WHEN l.homework_done = 1 THEN 1 ELSE 0 END) as homework_completed
        FROM students s
        LEFT JOIN admins a ON s.teacher_id = a.id
        LEFT JOIN lessons l ON s.id = l.student_id
        $whereClause
        GROUP BY s.id
        ORDER BY s.created_at DESC
        LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
    ";

    $students = dbQuery($query, $params);

    jsonSuccess([
        'students' => $students,
        'pagination' => $pagination
    ]);
}

/**
 * Получить ученика по ID
 */
function getStudent($user) {
    $id = (int)getParam('id');

    if (!$id) {
        jsonError('ID ученика не указан');
    }

    $query = "
        SELECT
            s.*,
            a.name as teacher_name,
            a.id as teacher_id
        FROM students s
        LEFT JOIN admins a ON s.teacher_id = a.id
        WHERE s.id = ?
    ";

    $student = dbQueryOne($query, [$id]);

    if (!$student) {
        jsonError('Ученик не найден', 404);
    }

    // Проверка прав доступа
    if (!checkResourceOwner($student['teacher_id'])) {
        jsonError('Доступ запрещен', 403);
    }

    jsonSuccess($student);
}

/**
 * Получить статистику по ученику
 */
function getStudentStats($user) {
    $id = (int)getParam('id');

    if (!$id) {
        jsonError('ID ученика не указан');
    }

    // Проверка доступа
    $student = dbQueryOne("SELECT teacher_id FROM students WHERE id = ?", [$id]);
    if (!$student || !checkResourceOwner($student['teacher_id'])) {
        jsonError('Доступ запрещен', 403);
    }

    // Получение статистики
    $stats = dbQueryOne("
        SELECT
            COUNT(l.id) as total_lessons,
            SUM(CASE WHEN l.homework_done = 1 THEN 1 ELSE 0 END) as homework_completed,
            SUM(CASE WHEN l.homework_given = 1 THEN 1 ELSE 0 END) as homework_given,
            AVG(l.rating) as avg_rating,
            SUM(CASE WHEN l.paid = 1 THEN 1 ELSE 0 END) as paid_lessons,
            SUM(p.amount) as total_paid
        FROM students s
        LEFT JOIN lessons l ON s.id = l.student_id
        LEFT JOIN payments p ON s.id = p.student_id
        WHERE s.id = ?
        GROUP BY s.id
    ", [$id]);

    jsonSuccess($stats);
}

/**
 * Добавить ученика
 */
function addStudent($user) {
    $data = getJsonInput() ?: postParams();

    // Валидация обязательных полей
    $required = ['name', 'class'];
    $validation = validateRequired($data, $required);

    if (!$validation['valid']) {
        jsonError('Не заполнены обязательные поля: ' . implode(', ', $validation['missing']));
    }

    // Определение преподавателя
    $teacherId = $user['role'] === 'superadmin' && isset($data['teacher_id'])
        ? $data['teacher_id']
        : $user['id'];

    // Вставка ученика
    $query = "
        INSERT INTO students (
            teacher_id, name, class, phone, schedule, goal, comment, balance, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $studentId = dbExecute($query, [
        $teacherId,
        $data['name'],
        $data['class'],
        $data['phone'] ?? null,
        $data['schedule'] ?? null,
        $data['goal'] ?? null,
        $data['comment'] ?? null,
        $data['balance'] ?? 0,
        $data['status'] ?? 'ожидает'
    ]);

    if ($studentId) {
        jsonSuccess(['id' => $studentId], 'Ученик успешно добавлен');
    } else {
        jsonError('Ошибка при добавлении ученика', 500);
    }
}

/**
 * Обновить ученика
 */
function updateStudent($user) {
    $data = getJsonInput() ?: postParams();
    $id = (int)($data['id'] ?? 0);

    if (!$id) {
        jsonError('ID ученика не указан');
    }

    // Проверка доступа
    $student = dbQueryOne("SELECT teacher_id FROM students WHERE id = ?", [$id]);
    if (!$student || !checkResourceOwner($student['teacher_id'])) {
        jsonError('Доступ запрещен', 403);
    }

    // Обновление ученика
    $query = "
        UPDATE students SET
            name = ?,
            class = ?,
            phone = ?,
            schedule = ?,
            goal = ?,
            comment = ?,
            balance = ?,
            status = ?
        WHERE id = ?
    ";

    $result = dbExecute($query, [
        $data['name'] ?? $student['name'],
        $data['class'] ?? $student['class'],
        $data['phone'] ?? null,
        $data['schedule'] ?? null,
        $data['goal'] ?? null,
        $data['comment'] ?? null,
        $data['balance'] ?? 0,
        $data['status'] ?? 'ожидает',
        $id
    ]);

    if ($result !== false) {
        jsonSuccess(null, 'Ученик успешно обновлен');
    } else {
        jsonError('Ошибка при обновлении ученика', 500);
    }
}

/**
 * Удалить ученика
 */
function deleteStudent($user) {
    $data = getJsonInput() ?: postParams();
    $id = (int)($data['id'] ?? getParam('id', 0));

    if (!$id) {
        jsonError('ID ученика не указан');
    }

    // Проверка доступа
    $student = dbQueryOne("SELECT teacher_id FROM students WHERE id = ?", [$id]);
    if (!$student || !checkResourceOwner($student['teacher_id'])) {
        jsonError('Доступ запрещен', 403);
    }

    $result = dbExecute("DELETE FROM students WHERE id = ?", [$id]);

    if ($result) {
        jsonSuccess(null, 'Ученик успешно удален');
    } else {
        jsonError('Ошибка при удалении ученика', 500);
    }
}
