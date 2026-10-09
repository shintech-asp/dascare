<?php
require '../cors.php';
header("Content-Type: application/json");

require __DIR__ . '/../db/db.php';
require __DIR__ . '/../reusables/rate_limit.php';

// ==================================================
// INPUT
// ==================================================
$data    = json_decode(file_get_contents("php://input"), true);
$email   = trim($data['email'] ?? '');
$otp     = trim($data['otp'] ?? '');
$context = trim($data['context'] ?? ''); // 'register' | 'forgot'

if ($email === '' || $otp === '' || $context === '') {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit;
}

if (!in_array($context, ['register', 'forgot'])) {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit;
}

// ==================================================
// RATE LIMIT — per email, 5 attempts per 10 minutes
// ==================================================
checkRateLimit($pdo, 'otp_verify', $email);

// ==================================================
// CONTEXT RULES
// email_verified is a timestamp (email_verified_at) here rather
// than Likhavite's boolean flag, so the SQL condition differs
// slightly even though the logic is the same.
// ==================================================
$verifiedCondition = $context === 'register'
    ? 'email_verified_at IS NULL'
    : 'email_verified_at IS NOT NULL';

$otpColumn        = $context === 'register' ? 'email_otp'            : 'reset_otp';
$otpExpiresColumn = $context === 'register' ? 'email_otp_expires_at' : 'reset_otp_expires_at';

// ==================================================
// FETCH USER
// ==================================================
$stmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE email = ?
      AND $verifiedCondition
      AND deleted_at IS NULL
");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    recordAttempt($pdo, 'otp_verify', $email);
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit;
}

// ==================================================
// FETCH OTP DATA
// ==================================================
$stmt = $pdo->prepare("
    SELECT $otpColumn AS otp_hash, $otpExpiresColumn AS otp_expires_at
    FROM user_security_tokens
    WHERE user_id = ?
");
$stmt->execute([$user['id']]);
$tokenRow = $stmt->fetch();

if (!$tokenRow || !$tokenRow['otp_hash']) {
    recordAttempt($pdo, 'otp_verify', $email);
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit;
}

// ==================================================
// CHECK EXPIRY
// ==================================================
if (!$tokenRow['otp_expires_at'] || strtotime($tokenRow['otp_expires_at']) < time()) {
    echo json_encode(["success" => false, "message" => "OTP expired"]);
    exit;
}

// ==================================================
// VERIFY OTP
// ==================================================
if (!password_verify($otp, $tokenRow['otp_hash'])) {
    recordAttempt($pdo, 'otp_verify', $email);
    echo json_encode(["success" => false, "message" => "Invalid OTP"]);
    exit;
}

// ==================================================
// OTP CORRECT — clear attempts + clear OTP
// ==================================================
clearAttempts($pdo, 'otp_verify', $email);

$stmt = $pdo->prepare("
    UPDATE user_security_tokens
    SET $otpColumn = NULL, $otpExpiresColumn = NULL
    WHERE user_id = ?
");
$stmt->execute([$user['id']]);

// ==================================================
// MARK EMAIL VERIFIED + ACTIVATE ACCOUNT (REGISTER ONLY)
// ==================================================
if ($context === 'register') {
    $stmt = $pdo->prepare("
        UPDATE users
        SET email_verified_at = NOW(), account_status = 'active'
        WHERE id = ?
    ");
    $stmt->execute([$user['id']]);
}

echo json_encode(["success" => true]);
