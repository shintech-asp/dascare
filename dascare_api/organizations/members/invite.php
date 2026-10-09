<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
require_once __DIR__ . '/../../reusables/organization_role_templates.php';
require_once __DIR__ . '/../../reusables/email_helper.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'hr.members.create');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) organizationJsonError(422, 'Invalid request body.');

$firstName = trim(strip_tags((string) ($data['first_name'] ?? '')));
$lastName = trim(strip_tags((string) ($data['last_name'] ?? '')));
$email = strtolower(trim((string) ($data['email'] ?? '')));
$phone = preg_replace('/\s+/', '', trim((string) ($data['phone'] ?? '')));
$roleId = (int) ($data['role_id'] ?? 0);
$employeeCode = trim(strip_tags((string) ($data['employee_code'] ?? '')));
$licenseNumber = trim(strip_tags((string) ($data['license_number'] ?? '')));
$certificationDetails = trim(strip_tags((string) ($data['certification_details'] ?? '')));

if ($firstName === '' || mb_strlen($firstName) > 80) organizationJsonError(422, 'Enter the employee first name.');
if ($lastName === '' || mb_strlen($lastName) > 80) organizationJsonError(422, 'Enter the employee last name.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) organizationJsonError(422, 'Enter a valid employee email address.');
if (!preg_match('/^(?:\+63|0)9\d{9}$/', $phone)) organizationJsonError(422, 'Enter a valid Philippine mobile number.');
if ($roleId <= 0) organizationJsonError(422, 'Choose an organization role.');
if (mb_strlen($employeeCode) > 50 || mb_strlen($licenseNumber) > 100 || mb_strlen($certificationDetails) > 2000) organizationJsonError(422, 'One or more personnel fields are too long.');

try {
    ensureStarterOrganizationRoles($pdo, $ctx['organization_id'], $ctx['user_id']);

    $roleStmt = $pdo->prepare('SELECT id, role_name FROM org_roles WHERE id = ? AND organization_id = ? AND deleted_at IS NULL LIMIT 1');
    $roleStmt->execute([$roleId, $ctx['organization_id']]);
    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
    if (!$role) organizationJsonError(422, 'Choose a valid role from this organization.');

    if ($ctx['user_role'] !== 'organization_admin') {
        $rolePermStmt = $pdo->prepare('SELECT permission_id FROM org_role_permissions WHERE role_id = ?');
        $rolePermStmt->execute([$roleId]);
        $targetPermissionIds = array_map('intval', $rolePermStmt->fetchAll(PDO::FETCH_COLUMN));
        $ownPermissionIds = organizationPermissionIdsForMember($pdo, (int) $ctx['organization_member_id']);
        if (array_diff($targetPermissionIds, $ownPermissionIds)) {
            organizationJsonError(403, 'You cannot invite an employee into a role containing permissions you do not possess.');
        }
    }

    $existing = $pdo->prepare('SELECT id FROM users WHERE (email = ? OR phone = ?) AND deleted_at IS NULL LIMIT 1');
    $existing->execute([$email, $phone]);
    if ($existing->fetchColumn()) {
        organizationJsonError(409, 'That email address or phone number already belongs to a DASCARE account. Existing-account invitations will be added in a later phase.');
    }

    $pdo->beginTransaction();

    // Replace an older pending invite to the same email instead of creating duplicates.
    $pdo->prepare("UPDATE organization_invitations SET status = 'cancelled' WHERE organization_id = ? AND email = ? AND status = 'pending'")
        ->execute([$ctx['organization_id'], $email]);

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+72 hours'));

    $insert = $pdo->prepare("
        INSERT INTO organization_invitations
            (organization_id, role_id, invited_by_user_id, first_name, last_name, email, phone,
             employee_code, license_number, certification_details, token_hash, status, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?, 'pending', ?)
    ");
    $insert->execute([
        $ctx['organization_id'], $roleId, $ctx['user_id'], $firstName, $lastName, $email, $phone,
        $employeeCode, $licenseNumber, $certificationDetails, $tokenHash, $expiresAt,
    ]);
    $invitationId = (int) $pdo->lastInsertId();

    $audit = $pdo->prepare("
        INSERT INTO audit_logs
            (user_id, organization_id, action, entity_type, entity_id, new_values, ip_address, user_agent)
        VALUES (?, ?, 'organization.member_invited', 'organization_invitation', ?, ?, ?, ?)
    ");
    $audit->execute([
        $ctx['user_id'], $ctx['organization_id'], $invitationId,
        json_encode(['email'=>$email,'name'=>$firstName.' '.$lastName,'role'=>$role['role_name'],'expires_at'=>$expiresAt], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $_SERVER['REMOTE_ADDR'] ?? null,
        mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    $pdo->commit();

    $requestOrigin = filter_var((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), FILTER_VALIDATE_URL) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
    $frontendBase = rtrim((string) (getenv('DASCARE_FRONTEND_URL') ?: ($requestOrigin ?: 'http://localhost:5173')), '/');
    $inviteUrl = $frontendBase . '/join-organization?token=' . urlencode($rawToken);
    $safeOrgName = htmlspecialchars((string) $ctx['organization_name'], ENT_QUOTES, 'UTF-8');
    $safeRole = htmlspecialchars((string) $role['role_name'], ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');

    $body = render_email_template(
        'DASCARE Organization Invitation',
        "Join <span style='color:#c0392b;'>{$safeOrgName}</span>",
        "<div style='font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.8;color:#6b7280;'>Hi {$safeName}, you have been invited to join <strong>{$safeOrgName}</strong> as <strong>{$safeRole}</strong>. Use the button below to activate your staff account and create your password.</div>
         <div style='text-align:center;margin:28px 0;'><a href='{$safeUrl}' style='display:inline-block;background:#1976D2;color:#fff;text-decoration:none;font-weight:700;padding:13px 22px;border-radius:12px;'>Accept Organization Invitation</a></div>
         <div style='font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.7;color:#8a94a3;word-break:break-all;'>{$safeUrl}</div>",
        '<strong>This invitation expires in 72 hours.</strong> If you do not recognize this organization, ignore this email.'
    );
    $mail = send_email($email, 'Organization Invitation – DASCARE', $body, $firstName . ' ' . $lastName);

    echo json_encode([
        'success' => true,
        'message' => $mail['success'] ? 'Invitation sent to the employee.' : 'Invitation created, but the email could not be sent. You can copy the invite link instead.',
        'invitation_id' => $invitationId,
        'invite_url' => $inviteUrl,
        'email_sent' => (bool) $mail['success'],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Organization member invite failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to create the employee invitation.');
}
