<?php
/**
 * API for OGE Geometry Prototypes
 *
 * Endpoints:
 * GET    ?action=list         - Get all prototypes
 * GET    ?action=get&id=X     - Get single prototype
 * POST   ?action=add          - Add new prototype
 * POST   ?action=update       - Update prototype
 * POST   ?action=delete       - Delete prototype
 * POST   ?action=reorder      - Reorder prototypes
 * POST   ?action=save_all     - Save all prototypes (bulk)
 * POST   ?action=reset        - Reset to default data
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/db.php';

// JSON helpers
function jsonSuccess($data = null): void {
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Get JSON body for POST requests
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?: $_POST;

try {
    switch ($action) {
        case 'list':
            handleList();
            break;
        case 'get':
            handleGet();
            break;
        case 'add':
            handleAdd($data);
            break;
        case 'update':
            handleUpdate($data);
            break;
        case 'delete':
            handleDelete($data);
            break;
        case 'reorder':
            handleReorder($data);
            break;
        case 'save_all':
            handleSaveAll($data);
            break;
        case 'reset':
            handleReset();
            break;
        default:
            jsonError('Unknown action: ' . $action, 400);
    }
} catch (PDOException $e) {
    jsonError('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    jsonError($e->getMessage(), 400);
}

/**
 * GET: List all prototypes
 */
function handleList(): void {
    $prototypes = dbQuery("
        SELECT id, num, type, method, image, sort_order, created_at, updated_at
        FROM prototypes
        ORDER BY sort_order ASC, id ASC
    ");

    jsonSuccess($prototypes);
}

/**
 * GET: Get single prototype
 */
function handleGet(): void {
    $id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$id) {
        jsonError('Invalid ID');
    }

    $prototype = dbQueryOne("SELECT * FROM prototypes WHERE id = ?", [$id]);
    if (!$prototype) {
        jsonError('Prototype not found', 404);
    }

    jsonSuccess($prototype);
}

/**
 * POST: Add new prototype
 */
function handleAdd(array $data): void {
    $num = trim($data['num'] ?? '');
    $type = trim($data['type'] ?? '');
    $method = trim($data['method'] ?? '');
    $image = $data['image'] ?? '';

    // Get max sort_order
    $maxOrder = dbQueryOne("SELECT MAX(sort_order) as max_order FROM prototypes");
    $sortOrder = ($maxOrder['max_order'] ?? 0) + 1;

    $id = dbExecute("
        INSERT INTO prototypes (num, type, method, image, sort_order)
        VALUES (?, ?, ?, ?, ?)
    ", [$num, $type, $method, $image, $sortOrder]);

    $prototype = dbQueryOne("SELECT * FROM prototypes WHERE id = ?", [$id]);
    jsonSuccess($prototype);
}

/**
 * POST: Update prototype
 */
function handleUpdate(array $data): void {
    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$id) {
        jsonError('Invalid ID');
    }

    // Check exists
    $existing = dbQueryOne("SELECT id FROM prototypes WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Prototype not found', 404);
    }

    $num = trim($data['num'] ?? '');
    $type = trim($data['type'] ?? '');
    $method = trim($data['method'] ?? '');
    $image = $data['image'] ?? '';

    dbExecute("
        UPDATE prototypes
        SET num = ?, type = ?, method = ?, image = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ", [$num, $type, $method, $image, $id]);

    $prototype = dbQueryOne("SELECT * FROM prototypes WHERE id = ?", [$id]);
    jsonSuccess($prototype);
}

/**
 * POST: Delete prototype
 */
function handleDelete(array $data): void {
    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$id) {
        jsonError('Invalid ID');
    }

    $affected = dbExecute("DELETE FROM prototypes WHERE id = ?", [$id]);
    if ($affected === 0) {
        jsonError('Prototype not found', 404);
    }

    jsonSuccess(['deleted' => $id]);
}

/**
 * POST: Reorder prototypes
 */
function handleReorder(array $data): void {
    $order = $data['order'] ?? [];
    if (!is_array($order)) {
        jsonError('Invalid order data');
    }

    $pdo = getDB();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("UPDATE prototypes SET sort_order = ? WHERE id = ?");
        foreach ($order as $index => $id) {
            $stmt->execute([$index, $id]);
        }
        $pdo->commit();
        jsonSuccess(['reordered' => count($order)]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * POST: Save all prototypes (bulk replace)
 */
function handleSaveAll(array $data): void {
    $prototypes = $data['prototypes'] ?? [];
    if (!is_array($prototypes)) {
        jsonError('Invalid prototypes data');
    }

    $pdo = getDB();
    $pdo->beginTransaction();

    try {
        // Delete all existing
        $pdo->exec("DELETE FROM prototypes");

        // Insert new ones
        $stmt = $pdo->prepare("
            INSERT INTO prototypes (num, type, method, image, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($prototypes as $index => $row) {
            $stmt->execute([
                trim($row['num'] ?? ''),
                trim($row['type'] ?? ''),
                trim($row['method'] ?? ''),
                $row['image'] ?? '',
                $index
            ]);
        }

        $pdo->commit();
        jsonSuccess(['saved' => count($prototypes)]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * POST: Reset to default data
 */
function handleReset(): void {
    $pdo = getDB();

    // Delete all
    $pdo->exec("DELETE FROM prototypes");

    // Re-seed
    seedDefaultData($pdo);

    // Return new list
    handleList();
}
