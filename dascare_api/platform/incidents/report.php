<?php
// The requester's report (witness or self, description, photos, medical snapshot) for platform oversight.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/incident_report.php';
header('Content-Type: application/json');
requirePlatformExecutive($pdo, 'oversight.emergency_requests.read');
$report = incidentReportData($pdo, (int) ($_GET['request_id'] ?? 0));
if (!$report) platformJsonError(404, 'Incident not found.');
echo json_encode(['success' => true, 'report' => $report]);
