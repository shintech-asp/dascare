<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_guard.php';
require_once __DIR__ . '/../../reusables/organization_role_templates.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'hr.members.read');

try {
    ensureStarterOrganizationRoles($pdo, $ctx['organization_id'], $ctx['user_id']);
    $stmt = $pdo->prepare("
        SELECT r.id, r.role_name, r.description, r.is_system,
               COUNT(DISTINCT orp.permission_id) AS permission_count,
               COUNT(DISTINCT omr.organization_member_id) AS member_count
        FROM org_roles r
        LEFT JOIN org_role_permissions orp ON orp.role_id = r.id
        LEFT JOIN org_member_roles omr ON omr.role_id = r.id
        WHERE r.organization_id = ? AND r.deleted_at IS NULL
        GROUP BY r.id
        ORDER BY r.is_system DESC, r.role_name
    ");
    $stmt->execute([$ctx['organization_id']]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($roles as &$role) {
        $role['id'] = (int) $role['id'];
        $role['is_system'] = (bool) $role['is_system'];
        $role['permission_count'] = (int) $role['permission_count'];
        $role['member_count'] = (int) $role['member_count'];
    }
    unset($role);
    echo json_encode(['success' => true, 'roles' => $roles]);
} catch (Throwable $e) {
    error_log('Organization roles list failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to load organization roles.');
}
