<?php
require '../cors.php';
header("Content-Type: application/json");

require __DIR__ . '/../db/db.php';
require __DIR__ . '/../reusables/rate_limit.php';

// ==================================================
// INPUT
// Frontend posts only { otp } — the user is identified via the
// pending-2FA marker set by login.php, not by email in the body.
// ==================================================
$data = json_decode(file_get_contents("php://input"), true);
$otp  = trim($data['otp'] ?? '');

if ($otp === '') {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit;
}

// ==================================================
// CHECK PENDING 2FA STATE
// ==================================================
if (
    empty($_SESSION['pending_2fa_user_id']) ||
    empty($_SESSION['pending_2fa_expires_at']) ||
    $_SESSION['pending_2fa_expires_at'] < time()
) {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_expires_at']);
    echo json_encode([
        "success" => false,
        "message" => "Your login session expired. Please log in again."
    ]);
    exit;
}

$userId = (int) $_SESSION['pending_2fa_user_id'];

// ==================================================
// RATE LIMIT — per pending user, 5 attempts per 10 minutes
// ==================================================
checkRateLimit($pdo, 'login_2fa_verify', (string) $userId);

// ==================================================
// FETCH OTP DATA
// ==================================================
$stmt = $pdo->prepare("
    SELECT login_otp, login_otp_expires_at
    FROM user_security_tokens
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$tokenRow = $stmt->fetch();

if (!$tokenRow || !$tokenRow['login_otp']) {
    recordAttempt($pdo, 'login_2fa_verify', (string) $userId);
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit;
}

// ==================================================
// CHECK EXPIRY
// ==================================================
if (!$tokenRow['login_otp_expires_at'] || strtotime($tokenRow['login_otp_expires_at']) < time()) {
    echo json_encode(["success" => false, "message" => "OTP expired"]);
    exit;
}

// ==================================================
// VERIFY OTP
// ==================================================
if (!password_verify($otp, $tokenRow['login_otp'])) {
    recordAttempt($pdo, 'login_2fa_verify', (string) $userId);
    echo json_encode(["success" => false, "message" => "Invalid OTP"]);
    exit;
}

// ==================================================
// OTP CORRECT — clear attempts + clear stored OTP
// ==================================================
clearAttempts($pdo, 'login_2fa_verify', (string) $userId);

$pdo->prepare("
    UPDATE user_security_tokens
    SET login_otp = NULL, login_otp_expires_at = NULL
    WHERE user_id = ?
")->execute([$userId]);

// ==================================================
// FETCH FULL USER RECORD (same shape as login.php)
// ==================================================
$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.phone,
        u.email,
        u.account_status,
        ur.role
    FROM users u
    LEFT JOIN user_roles ur
        ON ur.user_id = u.id
    WHERE u.id = ?
      AND u.deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || $user['account_status'] === 'suspended' || $user['account_status'] === 'disabled') {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_expires_at']);
    echo json_encode(["success" => false, "message" => "Unable to complete login"]);
    exit;
}

// ==================================================
// ESTABLISH REAL SESSION
// ==================================================
unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_expires_at']);
session_regenerate_id(true);

$_SESSION['user_id']    = $user['id'];
$_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_phone'] = $user['phone'];
$_SESSION['user_level'] = $user['role'];

$pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

echo json_encode([
    "success" => true,
    "user"    => [
        "id"    => $_SESSION['user_id'],
        "name"  => $_SESSION['user_name'],
        "email" => $_SESSION['user_email'],
        "phone" => $_SESSION['user_phone'],
        "level" => $_SESSION['user_level']
    ]
]);
