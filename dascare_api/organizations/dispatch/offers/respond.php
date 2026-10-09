<?php
require_once __DIR__ . '/../../../cors.php';
require_once __DIR__ . '/../../../db/db.php';
require_once __DIR__ . '/../../../reusables/organization_guard.php';
require_once __DIR__ . '/../../../reusables/dispatch_dss.php';
require_once __DIR__ . '/../../../reusables/push.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') organizationJsonError(405, 'Method not allowed.');
$ctx = requireOrganizationAccess($pdo, 'dispatch.dispatch_assignments.approve');
$body = json_decode(file_get_contents('php://input'), true);
$offerId = (int) ($body['offer_id'] ?? 0);
$decision = strtolower(trim((string)($body['decision'] ?? '')));
$note = trim(strip_tags((string)($body['note'] ?? '')));
if ($offerId <= 0 || !in_array($decision, ['accept','decline'], true)) organizationJsonError(422, 'Invalid offer response.');
if ($decision === 'decline' && $note === '') organizationJsonError(422, 'Add a short reason for declining this incident.');
if (mb_strlen($note) > 500) organizationJsonError(422, 'Response note is too long.');

try {
    $pdo->beginTransaction();
    dssSyncExpiredOffers($pdo);
    $stmt = $pdo->prepare("
        SELECT io.*, er.status AS incident_status, er.requester_user_id, er.reference_number, er.requester_name
        FROM incident_offers io
        INNER JOIN emergency_requests er ON er.id = io.emergency_request_id
        WHERE io.id = ? AND io.organization_id = ?
        LIMIT 1 FOR UPDATE
    ");
    $stmt->execute([$offerId, (int)$ctx['organization_id']]);
    $offer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$offer) throw new RuntimeException('Incident offer not found.');
    if ($offer['offer_status'] !== 'sent') throw new RuntimeException('This offer is no longer awaiting a response.');
    if (strtotime($offer['expires_at']) <= time()) throw new RuntimeException('This offer has expired. Refresh the queue for the next update.');

    if ($decision === 'accept') {
        $pdo->prepare("UPDATE incident_offers SET offer_status='accepted', responded_at=NOW(), responded_by_user_id=?, response_note=? WHERE id=?")
            ->execute([(int)$ctx['user_id'], $note ?: 'Accepted by organization dispatcher.', $offerId]);
        $pdo->prepare("UPDATE incident_offers SET offer_status='cancelled', responded_at=NOW(), response_note='Another organization accepted the incident.' WHERE emergency_request_id=? AND id<>? AND offer_status='sent'")
            ->execute([(int)$offer['emergency_request_id'], $offerId]);
        $pdo->prepare("UPDATE dss_recommendation_runs SET run_status='accepted' WHERE id=?")->execute([(int)$offer['dss_run_id']]);

        $oldStatus = $offer['incident_status'];
        if (in_array($oldStatus, ['submitted','validating'], true)) {
            $pdo->prepare("UPDATE emergency_requests SET status='verified', verified_at=NOW() WHERE id=?")->execute([(int)$offer['emergency_request_id']]);
            $pdo->prepare("INSERT INTO emergency_request_status_logs (emergency_request_id, old_status, new_status, changed_by_user_id, notes) VALUES (?, ?, 'verified', ?, ?)")
                ->execute([(int)$offer['emergency_request_id'], $oldStatus, (int)$ctx['user_id'], 'Rescue organization accepted the incident offer. Resource assignment is next.']);
        }

        if (!empty($offer['requester_user_id'])) {
            $notify = $pdo->prepare("INSERT INTO notifications (user_id, notification_type, title, message, related_type, related_id, dedup_key) VALUES (?, 'dispatch_update', 'Rescue Organization Accepted', ?, 'emergency_request', ?, ?) ON DUPLICATE KEY UPDATE message=VALUES(message), read_at=NULL, created_at=CURRENT_TIMESTAMP");
            $notify->execute([(int)$offer['requester_user_id'], 'A rescue organization accepted ' . $offer['reference_number'] . ' and is preparing an ambulance and crew.', (int)$offer['emergency_request_id'], 'incident-accepted:' . $offer['emergency_request_id']]);
        }
        // DASCARE app (no-op without Firebase): the requester's phone, plus
        // guest phones and linked (dedup) reports following this incident.
        $pushBody = 'A rescue organization accepted ' . $offer['reference_number'] . ' and is preparing an ambulance and crew.';
        if (!empty($offer['requester_user_id'])) {
            pushQueueUser($pdo, (int)$offer['requester_user_id'], 'Rescue Organization Accepted', $pushBody, ['type' => 'dispatch_update', 'related_type' => 'emergency_request', 'related_id' => (int)$offer['emergency_request_id']], 'incident-accepted:' . $offer['emergency_request_id']);
        }
        pushQueueRequestFollowers($pdo, (int)$offer['emergency_request_id'], 'Rescue Organization Accepted', $pushBody);
        $message = 'Incident accepted. Continue to Resource Assignment to choose the ambulance and crew.';
    } else {
        $pdo->prepare("UPDATE incident_offers SET offer_status='declined', responded_at=NOW(), responded_by_user_id=?, response_note=? WHERE id=?")
            ->execute([(int)$ctx['user_id'], $note, $offerId]);
        dssEscalateNextOffer($pdo, (int)$offer['emergency_request_id']);
        $message = 'Incident declined. DASCARE escalated the offer to the next eligible organization when available.';
    }

    $audit = $pdo->prepare("INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, 'incident_offer', ?, ?, ?, ?, ?)");
    $audit->execute([
        (int)$ctx['user_id'], (int)$ctx['organization_id'], 'dispatch.offer_' . ($decision === 'accept' ? 'accepted' : 'declined'), $offerId,
        json_encode(['offer_status'=>'sent']), json_encode(['offer_status'=>$decision === 'accept' ? 'accepted' : 'declined','note'=>$note]),
        $_SERVER['REMOTE_ADDR'] ?? null, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,255)
    ]);
    realtimeRequestChanged($pdo, (int)$offer['emergency_request_id'], $decision === 'accept' ? 'offer.accepted' : 'offer.declined', [], [(int)$ctx['organization_id']]);
    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>$message]);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    organizationJsonError(409, $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Offer response failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to respond to this incident offer.');
}
