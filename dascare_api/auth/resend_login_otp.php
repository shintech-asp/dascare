<?php
require '../cors.php';
header("Content-Type: application/json");

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../db/db.php';
require __DIR__ . '/../reusables/rate_limit.php';
require __DIR__ . '/../reusables/email_helper.php';

// ==================================================
// CHECK PENDING 2FA STATE
// Frontend posts {} — the user is identified via the pending-2FA
// marker set by login.php.
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
// RATE LIMIT — 3 resends per 10 minutes per pending user
// ==================================================
checkRateLimit($pdo, 'login_2fa_resend', (string) $userId);

// ==================================================
// FETCH USER EMAIL
// ==================================================
$stmt = $pdo->prepare("SELECT email, has_two_factor FROM users WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || (int) $user['has_two_factor'] !== 1) {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_expires_at']);
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit;
}

// ==================================================
// GENERATE NEW OTP
// ==================================================
$otp       = random_int(100000, 999999);
$hashedOtp = password_hash((string) $otp, PASSWORD_DEFAULT);
$otpExpiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$pdo->prepare("
    UPDATE user_security_tokens
    SET login_otp = ?, login_otp_expires_at = ?
    WHERE user_id = ?
")->execute([$hashedOtp, $otpExpiry, $userId]);

// Keep the pending window alive alongside the new code
$_SESSION['pending_2fa_expires_at'] = strtotime('+10 minutes');

// ==================================================
// SEND OTP EMAIL
// ==================================================
$body = render_email_template(
    'New Code Requested',
    "Here's your new<br><span style='color:#c0392b;'>verification code</span>",
    "<div style='font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.8; color:#6b7280; margin:0 0 28px 0;'>
        You requested a new login verification code. Use the code below to complete sign-in.
     </div>" . render_otp_card((string) $otp),
    "<strong style='color:#0f203a;'>This code expires in 10 minutes.</strong><br>If you did not request this code, you can safely ignore this email."
);

$result = send_email($user['email'], 'Your New Login Verification Code – DASCARE', $body);

if (!$result['success']) {
    echo json_encode(["success" => false, "message" => "Failed to send OTP email"]);
    exit;
}

// Record the resend attempt AFTER successful send
recordAttempt($pdo, 'login_2fa_resend', (string) $userId);

echo json_encode([
    "success" => true,
    "message" => "A new login verification code has been sent to your email"
]);
