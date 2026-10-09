<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';

header('Content-Type: application/json');
requirePlatformExecutive($pdo, 'citizens.citizens.read');

$status = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$kyc = strtolower(trim((string) ($_GET['kyc'] ?? 'all')));
$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(50, max(10, (int) ($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;

$allowedStatus = ['pending', 'active', 'suspended', 'disabled'];
$kycMap = ['unverified' => 0, 'pending' => 1, 'approved' => 2, 'rejected' => 3, 'resubmission' => 4];
$where = ["ur.role = 'citizen'", 'u.deleted_at IS NULL'];
$params = [];

if (in_array($status, $allowedStatus, true)) {
    $where[] = 'u.account_status = ?';
    $params[] = $status;
}
if (isset($kycMap[$kyc])) {
    $where[] = 'COALESCE(k.status, 0) = ?';
    $params[] = $kycMap[$kyc];
}
if ($search !== '') {
    $needle = '%' . $search . '%';
    $where[] = "(CONCAT_WS(' ', u.first_name, u.last_name) LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    array_push($params, $needle, $needle, $needle);
}
$whereSql = implode(' AND ', $where);

try {
    $statsStmt = $pdo->query("\n        SELECT\n            COUNT(*) AS total,\n            SUM(u.account_status = 'active') AS active,\n            SUM(u.account_status = 'suspended') AS suspended,\n            SUM(u.account_status = 'disabled') AS disabled,\n            SUM(COALESCE(k.status, 0) = 2) AS verified\n        FROM users u\n        INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.role = 'citizen'\n        LEFT JOIN kyc_verifications k ON k.user_id = u.id\n        WHERE u.deleted_at IS NULL\n    ");
    $rawStats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $stats = [
        'total' => (int) ($rawStats['total'] ?? 0),
        'active' => (int) ($rawStats['active'] ?? 0),
        'suspended' => (int) ($rawStats['suspended'] ?? 0),
        'disabled' => (int) ($rawStats['disabled'] ?? 0),
        'verified' => (int) ($rawStats['verified'] ?? 0),
    ];

    $count = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM users u\n        INNER JOIN user_roles ur ON ur.user_id = u.id\n        LEFT JOIN kyc_verifications k ON k.user_id = u.id\n        WHERE $whereSql\n    ");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $pdo->prepare("\n        SELECT\n            u.id, u.first_name, u.last_name, u.email, u.phone, u.account_status,\n            u.email_verified_at, u.last_login_at, u.created_at,\n            COALESCE(k.status, 0) AS kyc_status, k.submitted_at AS kyc_submitted_at, k.verified_at AS kyc_verified_at,\n            (SELECT COUNT(*) FROM emergency_requests er WHERE er.requester_user_id = u.id) AS request_count,\n            (SELECT COUNT(*) FROM emergency_requests er WHERE er.requester_user_id = u.id AND er.status NOT IN ('completed','cancelled')) AS active_request_count,\n            (SELECT MAX(er.created_at) FROM emergency_requests er WHERE er.requester_user_id = u.id) AS last_request_at\n        FROM users u\n        INNER JOIN user_roles ur ON ur.user_id = u.id\n        LEFT JOIN kyc_verifications k ON k.user_id = u.id\n        WHERE $whereSql\n        ORDER BY u.created_at DESC, u.id DESC\n        LIMIT $perPage OFFSET $offset\n    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['id'] = (int) $item['id'];
        $item['kyc_status'] = (int) $item['kyc_status'];
        $item['request_count'] = (int) $item['request_count'];
        $item['active_request_count'] = (int) $item['active_request_count'];
        $item['name'] = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
        $item['email_verified'] = $item['email_verified_at'] !== null;
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
    error_log('Platform citizen list failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to load citizen accounts.');
}
