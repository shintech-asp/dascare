<?php
// Appeal a false-alarm strike (one appeal per strike). Platform admins review
// it on Platform → False Alarm Sanctions; accepting removes the strike.
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/false_alarm_sanctions.php';
header('Content-Type: application/json');
function appealFail(int $code, string $msg): void { http_response_code($code); echo json_encode(['success' => false, 'message' => $msg]); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') appealFail(405, 'Method not allowed.');
$userId = sanctionRequestUser($pdo);
if (!$userId) appealFail(401, 'Please log in again.');
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$strikeId = (int) ($body['strike_id'] ?? 0);
$message = trim(strip_tags((string) ($body['message'] ?? '')));
if ($strikeId <= 0) appealFail(422, 'Choose a strike to appeal.');
if (mb_strlen($message) < 10) appealFail(422, 'Explain what happened in at least 10 characters.');
if (mb_strlen($message) > 1500) appealFail(422, 'Keep your explanation under 1,500 characters.');

try {
    $pdo->beginTransaction();
    $s = $pdo->prepare("SELECT fs.id, er.reference_number FROM false_alarm_strikes fs JOIN emergency_requests er ON er.id = fs.emergency_request_id WHERE fs.id = ? AND fs.user_id = ? AND fs.status = 'active' FOR UPDATE");
    $s->execute([$strikeId, $userId]);
    $strike = $s->fetch(PDO::FETCH_ASSOC);
    if (!$strike) throw new RuntimeException('This strike can no longer be appealed.');
    $e = $pdo->prepare('SELECT 1 FROM false_alarm_appeals WHERE strike_id = ? LIMIT 1');
    $e->execute([$strikeId]);
    if ($e->fetchColumn()) throw new RuntimeException('You already appealed this strike.');
    $pdo->prepare('INSERT INTO false_alarm_appeals (strike_id, user_id, message) VALUES (?, ?, ?)')->execute([$strikeId, $userId, $message]);
    $appealId = (int) $pdo->lastInsertId();
    $n = $pdo->prepare("INSERT INTO notifications (user_id, notification_type, title, message, related_type, related_id, dedup_key)
        SELECT u.id, 'sanction_appeal_pending', 'False alarm appeal', ?, 'false_alarm_appeal', ?, ?
        FROM users u JOIN user_roles ur ON ur.user_id = u.id AND ur.role = 'platform_executive_admin'
        WHERE u.deleted_at IS NULL AND u.account_status = 'active'
        ON DUPLICATE KEY UPDATE title = VALUES(title), message = VALUES(message), read_at = NULL, created_at = CURRENT_TIMESTAMP");
    $n->execute(['A citizen appealed the false-alarm strike on ' . $strike['reference_number'] . '.', $appealId, 'sanction_appeal_' . $appealId]);
    $pdo->commit();
    realtimeNotifyRole($pdo, 'platform_executive_admin');
    echo json_encode(['success' => true, 'message' => 'Appeal sent. A DASCARE administrator will review it.']);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    appealFail(422, $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Sanction appeal failed: ' . $e->getMessage());
    appealFail(500, 'Unable to send your appeal.');
}
