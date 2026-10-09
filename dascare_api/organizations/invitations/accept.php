<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/realtime.php';
require_once __DIR__ . '/../../reusables/password_helpers.php';

header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed.']); exit;
}
$data = json_decode(file_get_contents('php://input'), true);
$token = trim((string) ($data['token'] ?? ''));
$password = (string) ($data['password'] ?? '');
$confirmation = (string) ($data['password_confirmation'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/i', $token)) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Invalid invitation token.']); exit; }
if ($password !== $confirmation) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Passwords do not match.']); exit; }

try {
    $pdo->beginTransaction();
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT i.*, o.name AS organization_name, o.status AS organization_status, o.primary_admin_user_id,
               r.role_name
        FROM organization_invitations i
        INNER JOIN organizations o ON o.id = i.organization_id AND o.deleted_at IS NULL
        INNER JOIN org_roles r ON r.id = i.role_id AND r.organization_id = i.organization_id AND r.deleted_at IS NULL
        WHERE i.token_hash = ?
        FOR UPDATE
    ");
    $stmt->execute([$hash]);
    $invite = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invite || $invite['status'] !== 'pending') {
        $pdo->rollBack(); http_response_code(409); echo json_encode(['success'=>false,'message'=>'This invitation is no longer available.']); exit;
    }
    if (strtotime($invite['expires_at']) < time()) {
        $pdo->prepare("UPDATE organization_invitations SET status='expired' WHERE id=?")->execute([$invite['id']]);
        $pdo->commit(); http_response_code(410); echo json_encode(['success'=>false,'message'=>'This invitation has expired.']); exit;
    }
    if ($invite['organization_status'] !== 'active') {
        $pdo->rollBack(); http_response_code(409); echo json_encode(['success'=>false,'message'=>'This organization is not currently active.']); exit;
    }

    $strengthError = passwordStrengthError($password, [$invite['email'], $invite['first_name'], $invite['last_name']]);
    if ($strengthError !== null) {
        $pdo->rollBack(); http_response_code(422); echo json_encode(['success'=>false,'message'=>$strengthError]); exit;
    }

    $exists = $pdo->prepare('SELECT id FROM users WHERE (email = ? OR phone = ?) AND deleted_at IS NULL LIMIT 1');
    $exists->execute([$invite['email'], $invite['phone']]);
    if ($exists->fetchColumn()) {
        $pdo->rollBack(); http_response_code(409); echo json_encode(['success'=>false,'message'=>'A DASCARE account already uses this email or phone number. Ask the organization administrator for help.']); exit;
    }

    $createUser = $pdo->prepare("
        INSERT INTO users (first_name, last_name, email, phone, password_hash, account_status, email_verified_at)
        VALUES (?, ?, ?, ?, ?, 'active', NOW())
    ");
    $createUser->execute([$invite['first_name'], $invite['last_name'], $invite['email'], $invite['phone'], password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, 'organization_operational_user')")->execute([$userId]);
    $pdo->prepare('INSERT INTO user_security_tokens (user_id) VALUES (?)')->execute([$userId]);

    $member = $pdo->prepare("
        INSERT INTO organization_members
            (organization_id, user_id, employee_code, is_default_password, invited_by_user_id, membership_status, joined_at)
        VALUES (?, ?, NULLIF(?, ''), 0, ?, 'active', CURRENT_DATE)
    ");
    $member->execute([$invite['organization_id'], $userId, $invite['employee_code'] ?? '', $invite['invited_by_user_id']]);
    $memberId = (int) $pdo->lastInsertId();
    if (trim((string) ($invite['employee_code'] ?? '')) === '') {
        $pdo->prepare('UPDATE organization_members SET employee_code = ? WHERE id = ?')->execute(['STAFF-' . str_pad((string) $memberId, 4, '0', STR_PAD_LEFT), $memberId]);
    }

    $pdo->prepare("
        INSERT INTO personnel_profiles (organization_member_id, license_number, certification_details, availability_status)
        VALUES (?, NULLIF(?, ''), NULLIF(?, ''), 'off_duty')
    ")->execute([$memberId, $invite['license_number'] ?? '', $invite['certification_details'] ?? '']);
    $pdo->prepare('INSERT INTO org_member_roles (organization_member_id, role_id, assigned_by) VALUES (?, ?, ?)')
        ->execute([$memberId, $invite['role_id'], $invite['invited_by_user_id']]);
    $pdo->prepare("UPDATE organization_invitations SET status='accepted', accepted_at=NOW() WHERE id=?")->execute([$invite['id']]);

    $notifyUsers = array_unique(array_filter([(int) $invite['invited_by_user_id'], (int) ($invite['primary_admin_user_id'] ?? 0)]));
    $notify = $pdo->prepare("
        INSERT INTO notifications (user_id, notification_type, title, message, related_type, related_id, dedup_key)
        VALUES (?, 'organization_member_joined', 'Employee Joined Organization', ?, 'organization_member', ?, ?)
        ON DUPLICATE KEY UPDATE message=VALUES(message), read_at=NULL, created_at=CURRENT_TIMESTAMP
    ");
    $memberName = trim($invite['first_name'] . ' ' . $invite['last_name']);
    foreach ($notifyUsers as $notifyUser) {
        $notify->execute([$notifyUser, $memberName . ' accepted the invitation and joined as ' . $invite['role_name'] . '.', $memberId, 'member_join_' . $memberId . '_' . $notifyUser]);
    }
    realtimeNotifyUsers($pdo, $notifyUsers);

    $audit = $pdo->prepare("
        INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, new_values, ip_address, user_agent)
        VALUES (?, ?, 'organization.member_joined', 'organization_member', ?, ?, ?, ?)
    ");
    $audit->execute([$userId, $invite['organization_id'], $memberId, json_encode(['role'=>$invite['role_name'],'invitation_id'=>(int)$invite['id']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $_SERVER['REMOTE_ADDR'] ?? null, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),0,255)]);

    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'Your DASCARE organization account is ready. You can now sign in.','organization_name'=>$invite['organization_name'],'role_name'=>$invite['role_name']]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Invitation acceptance failed: ' . $e->getMessage());
    http_response_code(500); echo json_encode(['success'=>false,'message'=>'Unable to activate the organization account.']);
}
