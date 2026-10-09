<?php
// organizations/incidents/unmerge.php
// The organization handling an incident (accepted offer or assignment) can
// unlink a duplicate report that turns out to be a different emergency —
// e.g. the crew on scene finds the second report describes another patient.
// The report becomes its own incident and gets its own DSS cycle.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_guard.php';
require_once __DIR__ . '/../../reusables/dispatch_dedup.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');
$ctx = requireOrganizationAccess($pdo, 'incidents.emergency_requests.update');
$org = (int) $ctx['organization_id'];

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$requestId = (int) ($body['request_id'] ?? 0);
if ($requestId <= 0) organizationJsonError(422, 'A valid report is required.');
$reason = trim((string) ($body['reason'] ?? ''));
if (mb_strlen($reason) > 255) organizationJsonError(422, 'Please keep the reason under 255 characters.');

try {
    // The report must be linked to an incident this org is actually handling.
    $scope = $pdo->prepare("
        SELECT o.name
        FROM emergency_requests er
        INNER JOIN organizations o ON o.id = ?
        WHERE er.id = ?
          AND er.merged_into_request_id IS NOT NULL
          AND (
            EXISTS (SELECT 1 FROM dispatch_assignments da WHERE da.emergency_request_id = er.merged_into_request_id AND da.organization_id = ?)
            OR EXISTS (SELECT 1 FROM incident_offers io WHERE io.emergency_request_id = er.merged_into_request_id AND io.organization_id = ? AND io.offer_status = 'accepted')
          )
    ");
    $scope->execute([$org, $requestId, $org, $org]);
    $orgName = $scope->fetchColumn();
    if ($orgName === false) organizationJsonError(404, 'Linked report not found for an incident this organization handles.');

    $result = dedupUnmergeRequest($pdo, $requestId, (int) $ctx['user_id'], $org, (string) $orgName, $reason);
    echo json_encode([
        'success' => true,
        'message' => 'Report unlinked. It is now a separate incident and is being screened for a unit.',
        'result' => $result,
    ]);
} catch (RuntimeException $e) {
    organizationJsonError(409, $e->getMessage());
} catch (Throwable $e) {
    error_log('Org unmerge failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to unlink this report.');
}
