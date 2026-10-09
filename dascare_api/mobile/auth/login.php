<?php
// mobile/auth/login.php
// Android app login. Same checks and rate limits as auth/login.php (the web
// login), but instead of a cookie session it returns a bearer token, and it
// only lets CITIZENS in — staff/admin accounts are told to use the web
// portal. 2FA accounts get a short-lived challenge token instead of the
// web's $_SESSION['pending_2fa_*'] marker; finish with verify_2fa.php.
//
// POST JSON {email, password, device_name?}
//   → {success, token, user}                       (no 2FA)
//   → {success, requires_2fa: true, challenge}      (2FA on — code emailed)
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/rate_limit.php';
require_once __DIR__ . '/../../reusables/email_helper.php';
require_once __DIR__ . '/../../reusables/mobile_auth.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') mobileJson(405, ['success' => false, 'message' => 'Method not allowed.']);

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$email = trim((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');
$deviceName = trim((string) ($data['device_name'] ?? '')) ?: null;

if ($email === '' || $password === '') {
    mobileJson(422, ['success' => false, 'message' => 'Please enter your email and password.']);
}

$ip = getClientIp();
// Shares the web's 'login' counters, so the app is not a way around them.
checkRateLimit($pdo, 'login', $ip);
checkRateLimit($pdo, 'login', $email);

$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.phone, u.email, u.password_hash,
           u.email_verified_at, u.account_status, u.has_two_factor, ur.role
    FROM users u
    LEFT JOIN user_roles ur ON ur.user_id = u.id
    WHERE u.email = ? AND u.deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    recordAttempt($pdo, 'login', $ip);
    recordAttempt($pdo, 'login', $email);
    mobileJson(401, ['success' => false, 'message' => 'Invalid email or password']);
}
if ($user['account_status'] === 'suspended') {
    mobileJson(403, ['success' => false, 'code' => 'ACCOUNT_SUSPENDED', 'message' => 'Your account has been suspended. Please contact support for assistance.']);
}
if ($user['account_status'] === 'disabled') {
    mobileJson(403, ['success' => false, 'code' => 'ACCOUNT_DEACTIVATED', 'message' => 'Your account has been deactivated. Please contact support for assistance.']);
}
if ($user['email_verified_at'] === null) {
    mobileJson(403, ['success' => false, 'code' => 'EMAIL_NOT_VERIFIED', 'message' => 'Please verify your email first']);
}
if (!password_verify($password, $user['password_hash'])) {
    recordAttempt($pdo, 'login', $ip);
    recordAttempt($pdo, 'login', $email);
    mobileJson(401, ['success' => false, 'message' => 'Invalid email or password']);
}

clearAttempts($pdo, 'login', $ip);
clearAttempts($pdo, 'login', $email);

// Citizens only. Checked after the password so this reveals nothing to
// someone who doesn't already know the account's password.
if ($user['role'] !== 'citizen') {
    mobileJson(403, [
        'success' => false,
        'code' => 'STAFF_USE_WEB',
        'message' => 'This app is for people requesting help. Staff and administrators, please sign in on the DASCARE web portal.',
    ]);
}
if ($user['account_status'] !== 'active') {
    mobileJson(403, ['success' => false, 'code' => 'ACCOUNT_INACTIVE', 'message' => 'Your account is not active yet.']);
}

if ((int) $user['has_two_factor'] === 1) {
    if (!mobileSend2faCode($pdo, $user)) {
        mobileJson(502, ['success' => false, 'message' => 'Failed to send verification code. Please try again.']);
    }
    $challenge = mobileIssueToken($pdo, (int) $user['id'], 'login_2fa', $deviceName);
    mobileJson(200, [
        'success' => true,
        'requires_2fa' => true,
        'challenge' => $challenge,
        'message' => 'Verification code sent to your email',
    ]);
}

$token = mobileIssueToken($pdo, (int) $user['id'], 'access', $deviceName);
$pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

mobileJson(200, ['success' => true, 'token' => $token, 'user' => mobileUserPayload($user)]);
