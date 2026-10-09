<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
require_once __DIR__ . '/../../reusables/organization_role_templates.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'rbac.roles.read');

try {
    ensureStarterOrganizationRoles($pdo, $ctx['organization_id'], $ctx['user_id']);
    $assignableIds = ($ctx['user_role'] === 'organization_admin') ? null : organizationPermissionIdsForMember($pdo, $ctx['organization_member_id']);

    $permStmt = $pdo->query("\n        SELECT p.id, p.resource, p.action, m.module_key, m.module_name\n        FROM rbac_permissions p\n        INNER JOIN rbac_modules m ON m.id = p.module_id AND m.is_active = 1\n        ORDER BY m.id, p.resource, FIELD(p.action,'read','create','update','approve','delete'), p.id\n    ");
    $permissions = $permStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($permissions as &$permission) {
        $permission['id'] = (int) $permission['id'];
        $permission['key'] = $permission['module_key'] . '.' . $permission['resource'] . '.' . $permission['action'];
        $permission['assignable'] = $assignableIds === null || in_array($permission['id'], $assignableIds, true);
    }
    unset($permission);

    $roleStmt = $pdo->prepare("\n        SELECT r.id, r.role_name, r.description, r.is_system, r.created_at,\n               GROUP_CONCAT(DISTINCT orp.permission_id ORDER BY orp.permission_id SEPARATOR ',') AS permission_ids,\n               COUNT(DISTINCT omr.organization_member_id) AS member_count\n        FROM org_roles r\n        LEFT JOIN org_role_permissions orp ON orp.role_id = r.id\n        LEFT JOIN org_member_roles omr ON omr.role_id = r.id\n        WHERE r.organization_id = ? AND r.deleted_at IS NULL\n        GROUP BY r.id\n        ORDER BY r.is_system DESC, r.role_name\n    ");
    $roleStmt->execute([$ctx['organization_id']]);
    $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($roles as &$role) {
        $role['id'] = (int) $role['id'];
        $role['is_system'] = (bool) $role['is_system'];
        $role['member_count'] = (int) $role['member_count'];
        $role['permission_ids'] = $role['permission_ids'] ? array_map('intval', explode(',', $role['permission_ids'])) : [];
    }
    unset($role);

    echo json_encode(['success' => true, 'roles' => $roles, 'permissions' => $permissions]);
} catch (Throwable $e) {
    error_log('Organization access catalog failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to load roles and permissions.');
}
