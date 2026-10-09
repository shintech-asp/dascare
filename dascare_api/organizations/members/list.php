<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_guard.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'hr.members.read');

try {
    $pdo->prepare("UPDATE organization_invitations SET status = 'expired' WHERE organization_id = ? AND status = 'pending' AND expires_at < NOW()")->execute([$ctx['organization_id']]);

    $stmt = $pdo->prepare("
        SELECT om.id, om.employee_code, om.membership_status, om.joined_at, om.created_at,
               u.id AS user_id, u.first_name, u.last_name, u.email, u.phone, u.account_status,
               ur.role AS account_role,
               pp.license_number, pp.certification_details, pp.availability_status,
               GROUP_CONCAT(DISTINCT r.role_name ORDER BY r.role_name SEPARATOR '||') AS role_names
        FROM organization_members om
        INNER JOIN users u ON u.id = om.user_id AND u.deleted_at IS NULL
        LEFT JOIN user_roles ur ON ur.user_id = u.id
        LEFT JOIN personnel_profiles pp ON pp.organization_member_id = om.id
        LEFT JOIN org_member_roles omr ON omr.organization_member_id = om.id
        LEFT JOIN org_roles r ON r.id = omr.role_id AND r.deleted_at IS NULL
        WHERE om.organization_id = ? AND om.deleted_at IS NULL
        GROUP BY om.id
        ORDER BY (ur.role = 'organization_admin') DESC, u.first_name, u.last_name
    ");
    $stmt->execute([$ctx['organization_id']]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($members as &$member) {
        $member['id'] = (int) $member['id'];
        $member['user_id'] = (int) $member['user_id'];
        $member['role_names'] = $member['role_names'] ? explode('||', $member['role_names']) : [];
    }
    unset($member);

    $inviteStmt = $pdo->prepare("
        SELECT i.id, i.first_name, i.last_name, i.email, i.phone, i.employee_code,
               i.status, i.expires_at, i.created_at, r.role_name
        FROM organization_invitations i
        INNER JOIN org_roles r ON r.id = i.role_id
        WHERE i.organization_id = ? AND i.status = 'pending'
        ORDER BY i.created_at DESC
    ");
    $inviteStmt->execute([$ctx['organization_id']]);
    $invitations = $inviteStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($invitations as &$invite) $invite['id'] = (int) $invite['id'];
    unset($invite);

    echo json_encode([
        'success' => true,
        'members' => $members,
        'pending_invitations' => $invitations,
        'stats' => [
            'members' => count($members),
            'active' => count(array_filter($members, fn($m) => $m['membership_status'] === 'active')),
            'available' => count(array_filter($members, fn($m) => ($m['availability_status'] ?? '') === 'available')),
            'pending_invites' => count($invitations),
        ],
    ]);
} catch (Throwable $e) {
    error_log('Organization members list failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to load organization personnel.');
}
