<?php
/**
 * API: Сохранить посещаемость + доп. учеников и пересчитать выплату.
 *
 * POST body:
 *   {
 *     "lesson_instance_id": int,
 *     "extra_group": int (>=0, default 0),
 *     "extra_individual": int (>=0, default 0)
 *   }
 *
 * Логика:
 *   - actual_students считается из attendance_log (сколько отмечено attended=1)
 *   - base = calculatePayment(formula, actual_students)
 *   - extras = extra_group*200 + extra_individual*900
 *   - total = base + extras
 *   - Если actual_students == 0 и extras == 0 → status='cancelled', payment cancelled
 *     Иначе → status='completed', payment upsert.
 *   - Upsert payments чтобы переиспользовать ряд от триггера или предыдущего вызова.
 *
 * Доступно только после time_start урока (сравнение по серверу).
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/helpers.php';

header('Content-Type: application/json');

requireAuth();
$user = getCurrentUser();

const EXTRA_GROUP_RATE = 200;
const EXTRA_INDIVIDUAL_RATE = 900;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$lessonId = (int)($body['lesson_instance_id'] ?? 0);
$extraGroup = max(0, (int)($body['extra_group'] ?? 0));
$extraIndividual = max(0, (int)($body['extra_individual'] ?? 0));

if (!$lessonId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing lesson_instance_id']);
    exit;
}

$lesson = dbQueryOne("SELECT * FROM lessons_instance WHERE id = ?", [$lessonId]);
if (!$lesson) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Урок не найден']);
    exit;
}

// Разрешаем только после старта урока
$startTs = strtotime($lesson['lesson_date'] . ' ' . $lesson['time_start']);
if ($startTs === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Некорректная дата/время урока']);
    exit;
}
if (time() < $startTs) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Урок ещё не начался']);
    exit;
}

$teacher = dbQueryOne("SELECT * FROM teachers WHERE id = ?", [$lesson['teacher_id']]);
if (!$teacher) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Преподаватель не найден']);
    exit;
}

// Считаем actual_students из attendance_log (источник истины — клик по плиткам)
$countRow = dbQueryOne(
    "SELECT COUNT(*) AS cnt FROM attendance_log
     WHERE lesson_instance_id = ? AND attended = 1",
    [$lessonId]
);
$actualStudents = (int)($countRow['cnt'] ?? 0);

$totalStudents = $actualStudents + $extraGroup + $extraIndividual;
$isCancelled = ($totalStudents === 0);
$newStatus = $isCancelled ? 'cancelled' : 'completed';

// Формула: individual если итого ровно 1 (и это из основных или индивид. экстра),
// иначе group. Правило: если в итоге >1 ученика (с учётом экстра) — это group.
$lessonType = ($actualStudents + $extraGroup + $extraIndividual) > 1 ? 'group' : 'individual';

// Базовая формула: используем ту, что уже привязана к уроку, иначе по типу у учителя
$formulaId = $lesson['formula_id']
    ?: ($lessonType === 'individual' ? ($teacher['formula_id_individual'] ?? null) : ($teacher['formula_id_group'] ?? null))
    ?: ($teacher['formula_id'] ?? null);

$formula = $formulaId
    ? dbQueryOne("SELECT * FROM payment_formulas WHERE id = ? AND active = 1", [$formulaId])
    : null;

$baseAmount = 0;
if (!$isCancelled && $formula) {
    $baseAmount = calculatePayment($formula, $actualStudents);
}
$extrasAmount = $extraGroup * EXTRA_GROUP_RATE + $extraIndividual * EXTRA_INDIVIDUAL_RATE;
$totalAmount = $isCancelled ? 0 : ($baseAmount + $extrasAmount);

// Обновляем lessons_instance (status + счётчики + экстра)
dbExecute(
    "UPDATE lessons_instance
     SET status = ?, actual_students = ?,
         extra_group_count = ?, extra_individual_count = ?,
         updated_at = NOW()
     WHERE id = ?",
    [$newStatus, $actualStudents, $extraGroup, $extraIndividual, $lessonId]
);

// Upsert выплаты
if ($isCancelled) {
    cancelPaymentForLesson($lessonId);
    $paymentId = null;
} else {
    $teacherForPayment = $lesson['substitute_teacher_id'] ?: $lesson['teacher_id'];
    $calcMethod = sprintf(
        'Посещаемость: %d, доп.группа: %d, доп.индив: %d',
        $actualStudents, $extraGroup, $extraIndividual
    );
    $notes = sprintf(
        'База %d ₽ + доп. %d ₽ (group×%d + indiv×%d)',
        $baseAmount, $extrasAmount, $extraGroup, $extraIndividual
    );
    $paymentId = upsertPaymentForLesson(
        (int)$teacherForPayment,
        $lessonId,
        $totalAmount,
        $calcMethod,
        $notes
    );
}

logAudit('attendance_submitted', 'lesson', $lessonId, null, [
    'actual_students' => $actualStudents,
    'extra_group' => $extraGroup,
    'extra_individual' => $extraIndividual,
    'status' => $newStatus,
    'amount' => $totalAmount,
    'payment_id' => $paymentId,
], 'Посещаемость сохранена через PWA');

echo json_encode([
    'success' => true,
    'status' => $newStatus,
    'actual_students' => $actualStudents,
    'extra_group_count' => $extraGroup,
    'extra_individual_count' => $extraIndividual,
    'base_amount' => $baseAmount,
    'extras_amount' => $extrasAmount,
    'total_amount' => $totalAmount,
    'payment_id' => $paymentId,
]);
