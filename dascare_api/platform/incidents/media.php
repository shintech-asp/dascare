<?php
// A photo attached to an emergency request, for platform oversight.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/incident_report.php';
requirePlatformExecutive($pdo, 'oversight.emergency_requests.read');
$media = incidentReportMediaRow($pdo, (int) ($_GET['id'] ?? 0));
if (!$media) platformJsonError(404, 'Photo not found.');
incidentReportStreamMedia($media);
