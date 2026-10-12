<?php
// organizations/incidents/close.php
// The organization handling an incident (sent/accepted offer or an
// assignment) closes it before completion: cancelled or false alarm, with a
// reason. Releases the unit and crew; see reusables/request_close.php.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_guard.php';
require_once __DIR__ . '/../../reusables/request_close.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');
$ctx = requireOrganizationAccess($pdo, 'incidents.emergency_requests.update');
$org = (int) $ctx['organization_id'];

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$requestId = (int) ($body['request_id'] ?? 0);
$status = (string) ($body['status'] ?? '');
$reason = trim((string) ($body['reason'] ?? ''));
if ($requestId <= 0) organizationJsonError(422, 'A valid incident is required.');
if (!in_array($status, ['cancelled', 'false_alarm'], true)) organizationJsonError(422, 'Choose Cancelled or False alarm.');
if ($reason === '') organizationJsonError(422, 'Please give a reason.');
if (mb_strlen($reason) > 255) organizationJsonError(422, 'Please keep the reason under 255 characters.');

try {
    $scope = $pdo->prepare("
        SELECT o.name
        FROM emergency_requests er
        INNER JOIN organizations o ON o.id = ?
        WHERE er.id = ?
          AND (
            EXISTS (SELECT 1 FROM dispatch_assignments da WHERE da.emergency_request_id = er.id AND da.organization_id = ?)
            OR EXISTS (SELECT 1 FROM incident_offers io WHERE io.emergency_request_id = er.id AND io.organization_id = ? AND io.offer_status IN ('sent','accepted'))
          )
    ");
    $scope->execute([$org, $requestId, $org, $org]);
    $orgName = $scope->fetchColumn();
    if ($orgName === false) organizationJsonError(404, 'Incident not found for this organization.');

    $result = requestClose($pdo, $requestId, $status, $reason, [
        'kind' => 'organization', 'user_id' => (int) $ctx['user_id'], 'organization_id' => $org, 'label' => (string) $orgName,
    ]);
    echo json_encode([
        'success' => true,
        'message' => ($status === 'false_alarm' ? 'Marked as a false alarm.' : 'Incident cancelled.') . ' The unit and crew were released.',
        'result' => $result,
    ]);
} catch (RuntimeException $e) {
    organizationJsonError(409, $e->getMessage());
} catch (Throwable $e) {
    error_log('Organization incident close failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to close this incident.');
}
