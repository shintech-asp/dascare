<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';

header('Content-Type: application/json');
requirePlatformExecutive($pdo, 'citizens.kyc_verifications.read');

$statusMap = [
    'unverified' => 0,
    'pending' => 1,
    'approved' => 2,
    'rejected' => 3,
    'resubmission' => 4,
];

$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(50, max(10, (int) ($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;

$where = ["ur.role = 'citizen'", 'u.deleted_at IS NULL'];
$params = [];

if ($statusFilter !== 'all' && array_key_exists($statusFilter, $statusMap)) {
    $where[] = 'k.status = ?';
    $params[] = $statusMap[$statusFilter];
}

if ($search !== '') {
    $where[] = "(CONCAT_WS(' ', u.first_name, u.last_name) LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR k.id_type LIKE ?)";
    $needle = '%' . $search . '%';
    array_push($params, $needle, $needle, $needle, $needle);
}

$whereSql = implode(' AND ', $where);

try {
    $statsStmt = $pdo->query("\n        SELECT\n            SUM(k.status = 1) AS pending,\n            SUM(k.status = 2) AS approved,\n            SUM(k.status = 3) AS rejected,\n            SUM(k.status = 4) AS resubmission,\n            COUNT(*) AS total\n        FROM kyc_verifications k\n        INNER JOIN users u ON u.id = k.user_id AND u.deleted_at IS NULL\n        INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.role = 'citizen'\n    ");
    $rawStats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $stats = [
        'pending' => (int) ($rawStats['pending'] ?? 0),
        'approved' => (int) ($rawStats['approved'] ?? 0),
        'rejected' => (int) ($rawStats['rejected'] ?? 0),
        'resubmission' => (int) ($rawStats['resubmission'] ?? 0),
        'total' => (int) ($rawStats['total'] ?? 0),
    ];

    $countSql = "\n        SELECT COUNT(*)\n        FROM kyc_verifications k\n        INNER JOIN users u ON u.id = k.user_id\n        INNER JOIN user_roles ur ON ur.user_id = u.id\n        WHERE $whereSql\n    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $filteredTotal = (int) $countStmt->fetchColumn();

    $listSql = "\n        SELECT\n            k.user_id, k.status, k.id_type, k.phone_number, k.barangay, k.city,\n            k.submitted_at, k.updated_at, k.verified_at, k.last_rejected_at,\n            k.rejection_count, k.verification_note,\n            u.first_name, u.last_name, u.email, u.phone AS account_phone,\n            reviewer.first_name AS reviewer_first_name,\n            reviewer.last_name AS reviewer_last_name\n        FROM kyc_verifications k\n        INNER JOIN users u ON u.id = k.user_id\n        INNER JOIN user_roles ur ON ur.user_id = u.id\n        LEFT JOIN users reviewer ON reviewer.id = k.verified_by\n        WHERE $whereSql\n        ORDER BY\n            CASE k.status WHEN 1 THEN 0 WHEN 4 THEN 1 WHEN 3 THEN 2 WHEN 2 THEN 3 ELSE 4 END,\n            COALESCE(k.submitted_at, k.created_at) DESC\n        LIMIT $perPage OFFSET $offset\n    ";
    $listStmt = $pdo->prepare($listSql);
    $listStmt->execute($params);
    $items = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['user_id'] = (int) $item['user_id'];
        $item['status'] = (int) $item['status'];
        $item['rejection_count'] = (int) $item['rejection_count'];
        $item['applicant_name'] = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
        $item['reviewer_name'] = trim(($item['reviewer_first_name'] ?? '') . ' ' . ($item['reviewer_last_name'] ?? '')) ?: null;
        unset($item['first_name'], $item['last_name'], $item['reviewer_first_name'], $item['reviewer_last_name']);
    }
    unset($item);

    echo json_encode([
        'success' => true,
        'items' => $items,
        'stats' => $stats,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $filteredTotal,
            'pages' => max(1, (int) ceil($filteredTotal / $perPage)),
        ],
    ]);
} catch (Throwable $e) {
    error_log('Platform KYC list failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to load citizen verification submissions.');
}
