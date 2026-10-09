<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/fleet_helpers.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'fleet.ambulances.delete');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');
$body = fleetBody();
$ambulanceId = (int) ($body['id'] ?? 0);
$reason = trim(strip_tags((string) ($body['reason'] ?? '')));
if (mb_strlen($reason) < 5) organizationJsonError(422, 'Provide a reason for archiving this ambulance.');

try {
    $pdo->beginTransaction();
    $unit = fleetOwnedAmbulance($pdo, (int) $ctx['organization_id'], $ambulanceId, true);
    if (in_array($unit['status'], ['reserved','dispatched','on_scene','transporting','returning'], true)) {
        $pdo->rollBack();
        organizationJsonError(409, 'An ambulance assigned to an active mission cannot be archived.');
    }
    $pdo->prepare("UPDATE ambulances SET status='offline', deleted_at=NOW() WHERE id=? AND organization_id=?")->execute([$ambulanceId,$ctx['organization_id']]);
    fleetStatusLog($pdo, $ambulanceId, $unit['status'], 'offline', (int) $ctx['user_id'], 'Archived: ' . $reason);
    fleetAudit($pdo, $ctx, 'fleet.ambulance_archived', 'ambulance', $ambulanceId, $unit, ['deleted'=>true,'reason'=>$reason]);
    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'Ambulance archived from the active fleet.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (http_response_code() >= 400) exit;
    error_log('Fleet archive failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to archive the ambulance.');
}
