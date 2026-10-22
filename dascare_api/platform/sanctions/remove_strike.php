<?php
// Remove a strike (wrong call) without an appeal; the sanction is recomputed (only ever lighter).
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/false_alarm_sanctions.php';
header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') platformJsonError(405, 'Method not allowed.');
$actor = requirePlatformExecutive($pdo, 'citizens.citizens.update');
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$strikeId = (int) ($in['strike_id'] ?? 0);
$reason = mb_substr(trim(strip_tags((string) ($in['reason'] ?? ''))), 0, 500);
if ($strikeId <= 0) platformJsonError(422, 'Choose a strike.');
if (mb_strlen($reason) < 5) platformJsonError(422, 'Give a reason (at least 5 characters).');
try {
    $pdo->beginTransaction();
    $s = $pdo->prepare("SELECT fs.*, er.reference_number FROM false_alarm_strikes fs JOIN emergency_requests er ON er.id = fs.emergency_request_id WHERE fs.id = ? FOR UPDATE");
    $s->execute([$strikeId]);
    $st = $s->fetch(PDO::FETCH_ASSOC);
    if (!$st) throw new RuntimeException('Strike not found.');
    if ($st['status'] !== 'active') throw new RuntimeException('This strike was already removed.');
    $pdo->prepare("UPDATE false_alarm_strikes SET status = 'removed', removed_by_user_id = ?, removed_at = NOW(), removed_reason = ? WHERE id = ?")->execute([$actor, $reason, $strikeId]);
    // A pending appeal on it is settled by this.
    $pdo->prepare("UPDATE false_alarm_appeals SET status = 'accepted', reviewed_by_user_id = ?, reviewed_at = NOW(), review_note = ? WHERE strike_id = ? AND status = 'pending'")->execute([$actor, 'Strike removed: ' . $reason, $strikeId]);
    $result = null;
    if ($st['user_id']) {
        $result = sanctionRecomputeUser($pdo, (int) $st['user_id'], false);
        assignmentNotifyUser($pdo, (int) $st['user_id'], 'account_sanction', 'False-alarm strike removed', mb_substr('The false-alarm strike on ' . $st['reference_number'] . ' was removed by a DASCARE administrator. ' . $reason, 0, 500), 'user', (int) $st['user_id'], 'sanction-removed:' . $strikeId);
    }
    $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent) VALUES (?,?,?,?,?,?,?)')
        ->execute([$actor, 'sanctions.strike_removed', 'false_alarm_strike', $strikeId, json_encode(['reason' => $reason, 'sanction_now' => $result['level'] ?? null]), $_SERVER['REMOTE_ADDR'] ?? null, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Strike removed.', 'sanction' => $result]);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    platformJsonError(422, $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Strike removal failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to remove the strike.');
}
