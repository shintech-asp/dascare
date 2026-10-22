<?php
// The requester's report for responders: witness or self, description, photos, medical snapshot.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_guard.php';
require_once __DIR__ . '/../../reusables/incident_report.php';
header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'incidents.emergency_requests.read');
$requestId = (int) ($_GET['request_id'] ?? 0);
if ($requestId <= 0 || !incidentReportOrgCanSee($pdo, (int) $ctx['organization_id'], $requestId)) organizationJsonError(404, 'Incident not found for this organization.');
try {
    echo json_encode(['success' => true, 'report' => incidentReportData($pdo, $requestId)]);
} catch (Throwable $e) {
    error_log('Incident report failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to load the report.');
}
