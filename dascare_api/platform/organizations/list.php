<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';

header('Content-Type: application/json');
requirePlatformExecutive($pdo, 'organizations.organizations.read');

$allowedStatuses = ['pending', 'revision_requested', 'approved', 'rejected'];
$status = strtolower(trim((string) ($_GET['status'] ?? 'pending')));
$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(50, max(10, (int) ($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;

$where = ['o.deleted_at IS NULL', 'o.application_reference IS NOT NULL'];
$params = [];

if ($status !== 'all' && in_array($status, $allowedStatuses, true)) {
    $where[] = 'o.application_status = ?';
    $params[] = $status;
}

if ($search !== '') {
    $where[] = "(o.name LIKE ? OR o.application_reference LIKE ? OR o.email LIKE ? OR o.phone LIKE ? OR CONCAT_WS(' ', admin.first_name, admin.last_name) LIKE ? OR admin.email LIKE ?)";
    $needle = '%' . $search . '%';
    array_push($params, $needle, $needle, $needle, $needle, $needle, $needle);
}

$whereSql = implode(' AND ', $where);

try {
    $statsStmt = $pdo->query("\n        SELECT\n            SUM(application_status = 'pending') AS pending,\n            SUM(application_status = 'revision_requested') AS revision_requested,\n            SUM(application_status = 'approved') AS approved,\n            SUM(application_status = 'rejected') AS rejected,\n            COUNT(*) AS total\n        FROM organizations\n        WHERE deleted_at IS NULL\n          AND application_reference IS NOT NULL\n    ");
    $rawStats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $stats = [
        'pending' => (int) ($rawStats['pending'] ?? 0),
        'revision_requested' => (int) ($rawStats['revision_requested'] ?? 0),
        'approved' => (int) ($rawStats['approved'] ?? 0),
        'rejected' => (int) ($rawStats['rejected'] ?? 0),
        'total' => (int) ($rawStats['total'] ?? 0),
    ];

    $countStmt = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM organizations o\n        LEFT JOIN users admin ON admin.id = o.primary_admin_user_id\n        WHERE $whereSql\n    ");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("\n        SELECT\n            o.id, o.application_reference, o.name, o.organization_type,\n            o.registration_number, o.email, o.phone, o.address_line,\n            o.status, o.application_status, o.application_submitted_at,\n            o.application_reviewed_at, o.verified_at, o.last_rejected_at,\n            o.rejection_count, o.verification_note,\n            admin.first_name AS admin_first_name, admin.last_name AS admin_last_name,\n            admin.email AS admin_email, admin.phone AS admin_phone,\n            reviewer.first_name AS reviewer_first_name, reviewer.last_name AS reviewer_last_name,\n            (SELECT COUNT(*) FROM organization_documents d WHERE d.organization_id = o.id) AS document_count,\n            (SELECT COUNT(*) FROM organization_service_areas sa WHERE sa.organization_id = o.id) AS service_area_count\n        FROM organizations o\n        LEFT JOIN users admin ON admin.id = o.primary_admin_user_id\n        LEFT JOIN users reviewer ON reviewer.id = o.application_reviewed_by\n        WHERE $whereSql\n        ORDER BY\n            CASE o.application_status\n                WHEN 'pending' THEN 0\n                WHEN 'revision_requested' THEN 1\n                WHEN 'rejected' THEN 2\n                WHEN 'approved' THEN 3\n                ELSE 4\n            END,\n            COALESCE(o.application_submitted_at, o.created_at) DESC\n        LIMIT $perPage OFFSET $offset\n    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['id'] = (int) $item['id'];
        $item['rejection_count'] = (int) $item['rejection_count'];
        $item['document_count'] = (int) $item['document_count'];
        $item['service_area_count'] = (int) $item['service_area_count'];
        $item['admin_name'] = trim(($item['admin_first_name'] ?? '') . ' ' . ($item['admin_last_name'] ?? '')) ?: null;
        $item['reviewer_name'] = trim(($item['reviewer_first_name'] ?? '') . ' ' . ($item['reviewer_last_name'] ?? '')) ?: null;
        unset($item['admin_first_name'], $item['admin_last_name'], $item['reviewer_first_name'], $item['reviewer_last_name']);
    }
    unset($item);

    echo json_encode([
        'success' => true,
        'items' => $items,
        'stats' => $stats,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ],
    ]);
} catch (Throwable $e) {
    error_log('Platform organization list failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to load organization applications.');
}
