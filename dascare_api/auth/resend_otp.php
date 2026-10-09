<?php
require '../cors.php';
header("Content-Type: application/json");

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../db/db.php';
require __DIR__ . '/../reusables/rate_limit.php';
require __DIR__ . '/../reusables/email_helper.php';

// ==================================================
// READ INPUT
// ==================================================
$data  = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');

if ($email === '') {
    echo json_encode(["success" => false, "message" => "Email is required"]);
    exit;
}

// ==================================================
// RATE LIMIT — 3 resends per 10 minutes per email
// ==================================================
checkRateLimit($pdo, 'otp_resend', $email);

// ==================================================
// CHECK USER
// ==================================================
$stmt = $pdo->prepare("
    SELECT id, email_verified_at FROM users WHERE email = ? AND deleted_at IS NULL
");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // Record attempt anyway to prevent email enumeration via timing
    recordAttempt($pdo, 'otp_resend', $email);
    echo json_encode(["success" => false, "message" => "User not found"]);
    exit;
}

if ($user['email_verified_at'] !== null) {
    echo json_encode(["success" => false, "message" => "Email already verified"]);
    exit;
}

// ==================================================
// GENERATE NEW OTP
// ==================================================
$otp        = random_int(100000, 999999);
$hashedOtp  = password_hash((string) $otp, PASSWORD_DEFAULT);
$otpExpiry  = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// ==================================================
// UPSERT INTO user_security_tokens (register flow)
// ==================================================
$tokenStmt = $pdo->prepare("SELECT id FROM user_security_tokens WHERE user_id = ?");
$tokenStmt->execute([$user['id']]);
$tokenRow = $tokenStmt->fetch();

if ($tokenRow) {
    $pdo->prepare("
        UPDATE user_security_tokens
        SET email_otp = ?, email_otp_expires_at = ?
        WHERE user_id = ?
    ")->execute([$hashedOtp, $otpExpiry, $user['id']]);
} else {
    $pdo->prepare("
        INSERT INTO user_security_tokens (user_id, email_otp, email_otp_expires_at)
        VALUES (?, ?, ?)
    ")->execute([$user['id'], $hashedOtp, $otpExpiry]);
}

// ==================================================
// SEND OTP EMAIL
// ==================================================
$body = render_email_template(
    'New Code Requested',
    "Here's your new<br><span style='color:#c0392b;'>verification code</span>",
    "<div style='font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.8; color:#6b7280; margin:0 0 28px 0;'>
        You requested a new verification code. Please use the code below to confirm your email address and complete your DASCARE account registration.
     </div>" . render_otp_card((string) $otp),
    "<strong style='color:#0f203a;'>This code expires in 10 minutes.</strong><br>If you did not request this code, you can safely ignore this email."
);

$result = send_email($email, 'Your New Verification Code – DASCARE', $body);

if (!$result['success']) {
    echo json_encode(["success" => false, "message" => "Failed to send OTP email"]);
    exit;
}

// Record the resend attempt AFTER successful send
recordAttempt($pdo, 'otp_resend', $email);

echo json_encode([
    "success" => true,
    "message" => "A new OTP has been sent to your email"
]);
