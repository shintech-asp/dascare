<?php
// organizations/incidents/linked.php?id=<primary request id>
// Duplicate reports dedup linked to an incident this organization handles,
// with the warning signals the crew/dispatcher uses to spot a wrong merge.
// Scoped like incidents/list.php: the org must have an offer or assignment
// for the incident.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_guard.php';
require_once __DIR__ . '/../../reusables/dispatch_dedup.php';
header('Content-Type: application/json');

$ctx = requireOrganizationAccess($pdo, 'incidents.emergency_requests.read');
$org = (int) $ctx['organization_id'];
$requestId = (int) ($_GET['id'] ?? 0);
if ($requestId <= 0) organizationJsonError(422, 'A valid emergency request is required.');

try {
    $scope = $pdo->prepare("
        SELECT 1 FROM emergency_requests er
        WHERE er.id = ?
          AND (
            EXISTS (SELECT 1 FROM dispatch_assignments da WHERE da.emergency_request_id = er.id AND da.organization_id = ?)
            OR EXISTS (SELECT 1 FROM incident_offers io WHERE io.emergency_request_id = er.id AND io.organization_id = ?)
          )
    ");
    $scope->execute([$requestId, $org, $org]);
    if (!$scope->fetchColumn()) organizationJsonError(404, 'Incident not found for this organization.');

    echo json_encode(['success' => true, 'reports' => dedupLinkedReports($pdo, $requestId)]);
} catch (Throwable $e) {
    error_log('Org linked reports failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to load linked reports.');
}
