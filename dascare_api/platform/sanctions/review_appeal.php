<?php
// Accept (strike removed, sanction recomputed) or reject a citizen's appeal.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/false_alarm_sanctions.php';
header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') platformJsonError(405, 'Method not allowed.');
$actor = requirePlatformExecutive($pdo, 'citizens.citizens.update');
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$appealId = (int) ($in['appeal_id'] ?? 0);
$decision = (string) ($in['decision'] ?? '');
$note = mb_substr(trim(strip_tags((string) ($in['note'] ?? ''))), 0, 500);
if ($appealId <= 0 || !in_array($decision, ['accept', 'reject'], true)) platformJsonError(422, 'Choose accept or reject.');
if ($decision === 'reject' && mb_strlen($note) < 5) platformJsonError(422, 'Tell the citizen why the appeal was rejected (at least 5 characters).');
try {
    $pdo->beginTransaction();
    $s = $pdo->prepare("SELECT fa.*, fs.status strike_status, er.reference_number FROM false_alarm_appeals fa JOIN false_alarm_strikes fs ON fs.id = fa.strike_id JOIN emergency_requests er ON er.id = fs.emergency_request_id WHERE fa.id = ? FOR UPDATE");
    $s->execute([$appealId]);
    $a = $s->fetch(PDO::FETCH_ASSOC);
    if (!$a) throw new RuntimeException('Appeal not found.');
    if ($a['status'] !== 'pending') throw new RuntimeException('This appeal was already reviewed.');
    $pdo->prepare('UPDATE false_alarm_appeals SET status = ?, reviewed_by_user_id = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?')
        ->execute([$decision === 'accept' ? 'accepted' : 'rejected', $actor, $note ?: null, $appealId]);
    $userId = (int) $a['user_id'];
    $result = null;
    if ($decision === 'accept' && $a['strike_status'] === 'active') {
        $pdo->prepare("UPDATE false_alarm_strikes SET status = 'removed', removed_by_user_id = ?, removed_at = NOW(), removed_reason = ? WHERE id = ?")
            ->execute([$actor, 'Appeal accepted' . ($note ? ': ' . $note : ''), (int) $a['strike_id']]);
        $result = sanctionRecomputeUser($pdo, $userId, false);
    }
    $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent) VALUES (?,?,?,?,?,?,?)')
        ->execute([$actor, 'sanctions.appeal_' . ($decision === 'accept' ? 'accepted' : 'rejected'), 'false_alarm_appeal', $appealId, json_encode(['note' => $note, 'sanction_now' => $result['level'] ?? null]), $_SERVER['REMOTE_ADDR'] ?? null, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
    $msg = $decision === 'accept'
        ? 'Your appeal for ' . $a['reference_number'] . ' was accepted and the false-alarm strike was removed.' . ($note ? ' ' . $note : '')
        : 'Your appeal for ' . $a['reference_number'] . ' was not accepted. ' . $note;
    assignmentNotifyUser($pdo, $userId, 'account_sanction', $decision === 'accept' ? 'Appeal accepted' : 'Appeal not accepted', mb_substr($msg, 0, 500), 'user', $userId, 'sanction-appeal:' . $appealId);
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $decision === 'accept' ? 'Appeal accepted — strike removed.' : 'Appeal rejected.', 'sanction' => $result]);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    platformJsonError(422, $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Appeal review failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to review the appeal.');
}
