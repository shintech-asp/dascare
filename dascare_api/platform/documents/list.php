<?php
// Platform → Document Management: every current organization document with its
// review status and expiry state. Filters: status (pending|approved|rejected),
// expiry (expiring|expired), search (organization / document name), organization_id.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/organization_documents.php';
header('Content-Type: application/json');
requirePlatformExecutive($pdo, 'organizations.org_documents.read');

try {
    orgDocSyncExpiryNotices($pdo);
    $warn = orgDocWarnDays($pdo);
    $status = (string) ($_GET['status'] ?? '');
    $expiry = (string) ($_GET['expiry'] ?? '');
    $search = trim((string) ($_GET['search'] ?? ''));
    $orgFilter = (int) ($_GET['organization_id'] ?? 0);

    $where = ['o.deleted_at IS NULL', 'd.superseded_at IS NULL'];
    $args = [];
    if (in_array($status, ['pending', 'approved', 'rejected'], true)) { $where[] = 'd.status = ?'; $args[] = $status; }
    if ($expiry === 'expired') $where[] = "d.status = 'approved' AND d.expires_at IS NOT NULL AND d.expires_at < CURDATE()";
    if ($expiry === 'expiring') { $where[] = "d.status = 'approved' AND d.expires_at IS NOT NULL AND d.expires_at >= CURDATE() AND d.expires_at <= DATE_ADD(CURDATE(), INTERVAL ? DAY)"; $args[] = $warn; }
    if ($orgFilter > 0) { $where[] = 'd.organization_id = ?'; $args[] = $orgFilter; }
    if ($search !== '') { $where[] = '(o.name LIKE ? OR d.original_name LIKE ? OR d.doc_type LIKE ?)'; $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_substr($search, 0, 80)) . '%'; array_push($args, $like, $like, $like); }

    $stmt = $pdo->prepare("SELECT d.id, d.organization_id, o.name AS organization_name, o.organization_type, o.status AS organization_status,
                                  d.doc_type, d.original_name, d.mime_type, d.file_size, d.expires_at, d.status, d.rejection_reason, d.reviewed_at, d.created_at, d.replaces_document_id,
                                  CONCAT_WS(' ', ru.first_name, ru.last_name) AS reviewer_name
                           FROM organization_documents d
                           INNER JOIN organizations o ON o.id = d.organization_id
                           LEFT JOIN users ru ON ru.id = d.reviewed_by
                           WHERE " . implode(' AND ', $where) . "
                           ORDER BY FIELD(d.status, 'pending', 'rejected', 'approved'), d.expires_at IS NULL, d.expires_at, d.created_at DESC
                           LIMIT 300");
    $stmt->execute($args);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$i) {
        $i['id'] = (int) $i['id'];
        $i['organization_id'] = (int) $i['organization_id'];
        $i['is_replacement'] = $i['replaces_document_id'] !== null;
        $i['can_review'] = $i['status'] === 'pending' && $i['organization_status'] === 'active';
        $i += orgDocExpiryState($i['expires_at'], $warn);
    }
    unset($i);

    $stats = $pdo->query("SELECT
            SUM(d.status = 'pending') AS pending,
            SUM(d.status = 'rejected') AS rejected,
            SUM(d.status = 'approved' AND d.expires_at IS NOT NULL AND d.expires_at < CURDATE()) AS expired,
            SUM(d.status = 'approved' AND d.expires_at IS NOT NULL AND d.expires_at >= CURDATE() AND d.expires_at <= DATE_ADD(CURDATE(), INTERVAL $warn DAY)) AS expiring,
            COUNT(*) AS total
        FROM organization_documents d INNER JOIN organizations o ON o.id = d.organization_id AND o.deleted_at IS NULL
        WHERE d.superseded_at IS NULL")->fetch(PDO::FETCH_ASSOC);
    foreach ($stats as $k => $v) $stats[$k] = (int) $v;

    echo json_encode(['success' => true, 'items' => $items, 'stats' => $stats, 'warn_days' => $warn]);
} catch (Throwable $e) {
    error_log('Platform documents list failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to load documents.');
}
