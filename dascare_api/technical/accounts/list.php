<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/technical_guard.php';
header('Content-Type: application/json');
requireTechnicalSuperAdmin($pdo);

$search = trim((string)($_GET['search'] ?? ''));
$status = strtolower(trim((string)($_GET['status'] ?? 'all')));
$params = [];
$where = ["ur.role IN ('technical_super_admin','platform_executive_admin')", 'u.deleted_at IS NULL'];
if (in_array($status, ['active','pending','suspended','disabled'], true)) { $where[] = 'u.account_status = ?'; $params[] = $status; }
if ($search !== '') { $n = '%' . $search . '%'; $where[] = "(CONCAT_WS(' ',u.first_name,u.last_name) LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)"; array_push($params, $n, $n, $n); }

try {
    $stmt = $pdo->prepare("\n      SELECT u.id,u.first_name,u.last_name,u.email,u.phone,u.account_status,u.has_two_factor,u.email_verified_at,u.last_login_at,u.created_at,ur.role,\n             GROUP_CONCAT(DISTINCT pr.role_name ORDER BY pr.role_name SEPARATOR '||') AS platform_roles\n      FROM users u\n      INNER JOIN user_roles ur ON ur.user_id=u.id\n      LEFT JOIN platform_account_roles par ON par.user_id=u.id\n      LEFT JOIN platform_roles pr ON pr.id=par.role_id AND pr.deleted_at IS NULL\n      WHERE " . implode(' AND ', $where) . "\n      GROUP BY u.id\n      ORDER BY (ur.role='technical_super_admin') DESC,u.first_name,u.last_name\n    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$i) {
        $i['id']=(int)$i['id']; $i['has_two_factor']=(bool)$i['has_two_factor'];
        $i['name']=trim($i['first_name'].' '.$i['last_name']);
        $i['platform_roles']=$i['platform_roles']?explode('||',$i['platform_roles']):[];
    }
    echo json_encode(['success'=>true,'items'=>$items]);
} catch (Throwable $e) {
    error_log('Technical accounts list failed: '.$e->getMessage());
    technicalJsonError(500,'Unable to load administrative accounts.');
}
