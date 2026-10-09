<?php

function organizationJsonError(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

/**
 * Resolve an authenticated organization member directly from the database.
 * Organization admins are treated as owners and have full tenant access.
 * Organization operational users must possess the requested permission.
 */
function requireOrganizationAccess(PDO $pdo, ?string $permission = null, bool $requireActiveOrganization = true): array
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) organizationJsonError(401, 'Authentication required.');

    $stmt = $pdo->prepare("
        SELECT
            u.id AS user_id, u.account_status,
            ur.role AS user_role,
            om.id AS organization_member_id, om.organization_id, om.membership_status,
            o.name AS organization_name, o.status AS organization_status,
            o.application_status
        FROM users u
        INNER JOIN user_roles ur ON ur.user_id = u.id
        INNER JOIN organization_members om ON om.user_id = u.id AND om.deleted_at IS NULL
        INNER JOIN organizations o ON o.id = om.organization_id AND o.deleted_at IS NULL
        WHERE u.id = ? AND u.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $context = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$context || $context['account_status'] !== 'active') {
        organizationJsonError(403, 'This account is not active.');
    }
    if (!in_array($context['user_role'], ['organization_admin', 'organization_operational_user'], true)) {
        organizationJsonError(403, 'Organization access is required.');
    }
    if (!in_array($context['membership_status'], ['active', 'invited'], true)) {
        organizationJsonError(403, 'Your organization membership is not active.');
    }
    if ($requireActiveOrganization && $context['organization_status'] !== 'active') {
        organizationJsonError(403, 'This organization is not active.');
    }

    if ($permission !== null && $context['user_role'] !== 'organization_admin') {
        $permStmt = $pdo->prepare("
            SELECT 1
            FROM org_member_roles omr
            INNER JOIN org_roles r ON r.id = omr.role_id AND r.deleted_at IS NULL
            INNER JOIN org_role_permissions orp ON orp.role_id = r.id
            INNER JOIN rbac_permissions p ON p.id = orp.permission_id
            INNER JOIN rbac_modules m ON m.id = p.module_id
            WHERE omr.organization_member_id = ?
              AND r.organization_id = ?
              AND CONCAT(m.module_key, '.', p.resource, '.', p.action) = ?
            LIMIT 1
        ");
        $permStmt->execute([
            (int) $context['organization_member_id'],
            (int) $context['organization_id'],
            $permission,
        ]);
        if (!$permStmt->fetchColumn()) {
            organizationJsonError(403, 'You do not have permission to perform this action.');
        }
    }

    $context['user_id'] = (int) $context['user_id'];
    $context['organization_member_id'] = (int) $context['organization_member_id'];
    $context['organization_id'] = (int) $context['organization_id'];
    return $context;
}
