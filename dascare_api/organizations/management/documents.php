<?php
// Organization → Documents & Credentials: the organization's current documents
// (with expiry state, replacement status and history), what its type still
// requires, and ambulance credential dates.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_guard.php';
require_once __DIR__ . '/../../reusables/organization_documents.php';
header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'org_settings.org_documents.read');
$orgId = (int) $ctx['organization_id'];

try {
    orgDocSyncExpiryNotices($pdo);
    $warn = orgDocWarnDays($pdo);
    $type = (string) $pdo->query('SELECT organization_type FROM organizations WHERE id = ' . $orgId)->fetchColumn();

    $s = $pdo->prepare("SELECT d.id, d.doc_type, d.original_name, d.mime_type, d.file_size, d.expires_at, d.status, d.rejection_reason, d.reviewed_at, d.created_at, d.replaces_document_id, d.superseded_at,
                               CONCAT_WS(' ', u.first_name, u.last_name) AS reviewer_name, CONCAT_WS(' ', up.first_name, up.last_name) AS uploaded_by
                        FROM organization_documents d
                        LEFT JOIN users u ON u.id = d.reviewed_by
                        LEFT JOIN users up ON up.id = d.uploaded_by_user_id
                        WHERE d.organization_id = ? ORDER BY d.created_at DESC, d.id DESC");
    $s->execute([$orgId]);
    $all = $s->fetchAll(PDO::FETCH_ASSOC);

    $current = [];
    $history = [];
    foreach ($all as $d) {
        $d['id'] = (int) $d['id'];
        $d['replaces_document_id'] = $d['replaces_document_id'] !== null ? (int) $d['replaces_document_id'] : null;
        $d += orgDocExpiryState($d['expires_at'], $warn);
        if ($d['superseded_at'] === null) $current[] = $d; else $history[] = $d;
    }

    $reqList = ORGANIZATION_DOCUMENT_REQUIREMENTS[$type] ?? [];
    $have = [];
    foreach ($current as $d) if (in_array($d['status'], ['approved', 'pending'], true)) $have[$d['doc_type']] = true;
    $requirements = array_map(static fn($r) => ['key' => $r['key'], 'label' => $r['label'], 'hint' => $r['hint'] ?? '', 'required' => !empty($r['required']), 'missing' => empty($have[$r['key']])], $reqList);

    $stats = ['documents' => count($current), 'approved' => 0, 'pending' => 0, 'expiring' => 0, 'expired' => 0, 'credential_alerts' => 0];
    foreach ($current as $d) {
        if ($d['status'] === 'approved') $stats['approved']++;
        if ($d['status'] === 'pending') $stats['pending']++;
        if ($d['status'] === 'approved' && $d['state'] === 'expiring') $stats['expiring']++;
        if ($d['status'] === 'approved' && $d['state'] === 'expired') $stats['expired']++;
    }

    $fleet = $pdo->prepare('SELECT id, unit_code, plate_number, registration_expiry, inspection_expiry, status FROM ambulances WHERE organization_id = ? AND deleted_at IS NULL ORDER BY unit_code');
    $fleet->execute([$orgId]);
    $units = $fleet->fetchAll(PDO::FETCH_ASSOC);
    $today = date('Y-m-d');
    $warnDate = date('Y-m-d', strtotime("+$warn days"));
    foreach ($units as &$u) {
        $alerts = [];
        foreach (['registration_expiry' => 'Registration', 'inspection_expiry' => 'Inspection'] as $k => $label) {
            if (!empty($u[$k]) && $u[$k] <= $warnDate) $alerts[] = ['label' => $label, 'date' => $u[$k], 'expired' => $u[$k] < $today];
        }
        $u['credential_alerts'] = $alerts;
        $stats['credential_alerts'] += count($alerts);
    }
    unset($u);

    $canUpload = $ctx['user_role'] === 'organization_admin' || organizationHasPermission($pdo, $ctx, 'org_settings.org_documents.create');
    echo json_encode(['success' => true, 'documents' => $current, 'history' => $history, 'requirements' => $requirements, 'fleet_credentials' => $units, 'stats' => $stats, 'warn_days' => $warn, 'can_upload' => $canUpload, 'organization_type' => $type]);
} catch (Throwable $e) {
    error_log('Org docs failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to load documents.');
}
