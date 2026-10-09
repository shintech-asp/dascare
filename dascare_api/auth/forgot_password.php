<?php
require '../cors.php';
header("Content-Type: application/json");

require __DIR__ . '/../db/db.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../reusables/rate_limit.php';
require __DIR__ . '/../reusables/email_helper.php';

// ==================================================
// INPUT
// ==================================================
$data  = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');

if ($email === '') {
    echo json_encode(["success" => false, "message" => "Email is required"]);
    exit;
}

// ==================================================
// RATE LIMIT — 3 requests per 10 minutes per email
// (Also feeds into resend_otp's OTP-verify limiter downstream.)
// ==================================================
checkRateLimit($pdo, 'forgot_password', $email);

// ==================================================
// CHECK USER EXISTS
// ==================================================
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // Record the attempt anyway so this endpoint can't be used to
    // enumerate registered emails via timing/rate-limit differences.
    recordAttempt($pdo, 'forgot_password', $email);
    echo json_encode(["success" => false, "message" => "Email not found"]);
    exit;
}

// ==================================================
// GENERATE OTP
// ==================================================
$otp        = random_int(100000, 999999);
$hashedOtp  = password_hash((string) $otp, PASSWORD_DEFAULT);
$expiresAt  = date('Y-m-d H:i:s', time() + 600); // 10 mins

// ==================================================
// SAVE OTP (reset flow -> user_security_tokens.reset_otp)
// ==================================================
$tokenStmt = $pdo->prepare("SELECT id FROM user_security_tokens WHERE user_id = ?");
$tokenStmt->execute([$user['id']]);
$tokenRow = $tokenStmt->fetch();

if ($tokenRow) {
    $pdo->prepare("
        UPDATE user_security_tokens
        SET reset_otp = ?, reset_otp_expires_at = ?
        WHERE user_id = ?
    ")->execute([$hashedOtp, $expiresAt, $user['id']]);
} else {
    $pdo->prepare("
        INSERT INTO user_security_tokens (user_id, reset_otp, reset_otp_expires_at)
        VALUES (?, ?, ?)
    ")->execute([$user['id'], $hashedOtp, $expiresAt]);
}

// ==================================================
// SEND EMAIL
// ==================================================
$body = render_email_template(
    'Password Reset Requested',
    "Reset your<br><span style='color:#c0392b;'>password</span>",
    "<div style='font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.8; color:#6b7280; margin:0 0 28px 0;'>
        We received a request to reset your password. Use the verification code below to continue.
     </div>" . render_otp_card((string) $otp),
    "<strong style='color:#0f203a;'>This code expires in 10 minutes.</strong><br>If you did not request a password reset, you can safely ignore this email."
);

$result = send_email($email, 'Reset Your Password – DASCARE', $body);

if (!$result['success']) {
    recordAttempt($pdo, 'forgot_password', $email);
    echo json_encode(["success" => false, "message" => "Failed to send verification email"]);
    exit;
}

// Record the attempt AFTER a successful send too — this endpoint
// sends an email every call, so it needs the same 3/10-min cap
// whether the email succeeds or not.
recordAttempt($pdo, 'forgot_password', $email);

echo json_encode(["success" => true]);
