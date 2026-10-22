<?php
// A photo attached to an emergency request, for an organization handling it.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_guard.php';
require_once __DIR__ . '/../../reusables/incident_report.php';
$ctx = requireOrganizationAccess($pdo, 'incidents.emergency_requests.read');
$media = incidentReportMediaRow($pdo, (int) ($_GET['id'] ?? 0));
$orgId = (int) $ctx['organization_id'];
if (!$media || !(incidentReportOrgCanSee($pdo, $orgId, (int) $media['emergency_request_id']))) organizationJsonError(404, 'Photo not found.');
incidentReportStreamMedia($media);
