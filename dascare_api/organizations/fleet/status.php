<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/fleet_helpers.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'fleet.ambulance_status.update');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');
$body = fleetBody();
$ambulanceId = (int) ($body['id'] ?? 0);
$newStatus = trim((string) ($body['status'] ?? ''));
$reason = trim(strip_tags((string) ($body['reason'] ?? '')));
if (!in_array($newStatus, ['available','maintenance','offline'], true)) organizationJsonError(422, 'Fleet staff can only set Available, Maintenance, or Offline. Mission states are controlled by dispatch.');
if ($reason !== '' && mb_strlen($reason) > 500) organizationJsonError(422, 'Status reason is too long.');

try {
    $pdo->beginTransaction();
    $unit = fleetOwnedAmbulance($pdo, (int) $ctx['organization_id'], $ambulanceId, true);
    $oldStatus = $unit['status'];
    if ($oldStatus === $newStatus) {
        $pdo->rollBack();
        echo json_encode(['success'=>true,'message'=>'Ambulance status is already up to date.']);
        exit;
    }
    // 'returning' is the post-mission state a completed mission leaves the unit
    // in — it's *meant* to be cleared from Fleet by returning the unit to
    // service, so it is NOT treated as an active mission here. The genuinely
    // in-progress states (still controlled by dispatch) stay blocked.
    if (in_array($oldStatus, ['reserved','dispatched','on_scene','transporting'], true)) {
        $pdo->rollBack();
        organizationJsonError(409, 'This ambulance is tied to an active mission and cannot be changed from Fleet.');
    }
    if ($newStatus === 'available') {
        $readiness = fleetLatestReadiness($pdo, $ambulanceId);
        if (!$readiness || $readiness['overall_status'] !== 'ready') {
            $pdo->rollBack();
            organizationJsonError(409, 'Run and pass a readiness check before marking this ambulance available.');
        }
        $credentialState = fleetCredentialState($unit);
        if (!$credentialState['valid']) {
            $pdo->rollBack();
            organizationJsonError(409, 'Expired registration or inspection credentials prevent this ambulance from becoming available.');
        }
        if (fleetHasOpenMaintenance($pdo, $ambulanceId)) {
            $pdo->rollBack();
            organizationJsonError(409, 'Complete the in-progress maintenance record before marking this ambulance available.');
        }
    }
    if ($newStatus !== 'available' && $reason === '') organizationJsonError(422, 'Provide a short reason for taking this ambulance out of service.');

    $pdo->prepare('UPDATE ambulances SET status = ? WHERE id = ? AND organization_id = ?')->execute([$newStatus,$ambulanceId,$ctx['organization_id']]);
    fleetStatusLog($pdo, $ambulanceId, $oldStatus, $newStatus, (int) $ctx['user_id'], $reason ?: 'Readiness confirmed.');
    fleetAudit($pdo, $ctx, 'fleet.ambulance_status_changed', 'ambulance', $ambulanceId, ['status'=>$oldStatus], ['status'=>$newStatus,'reason'=>$reason ?: null]);
    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'Ambulance status updated.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (http_response_code() >= 400) exit;
    error_log('Fleet status failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to update ambulance status.');
}
