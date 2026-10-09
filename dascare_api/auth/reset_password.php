<?php
require '../cors.php';
header("Content-Type: application/json");

require __DIR__ . '/../db/db.php';
require __DIR__ . '/../reusables/password_helpers.php';

// ==================================================
// INPUT
// ==================================================
$data     = json_decode(file_get_contents("php://input"), true);
$email    = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if ($email === '' || $password === '') {
    echo json_encode(["success" => false, "message" => "Missing fields"]);
    exit;
}

// ==================================================
// PASSWORD VALIDATION
// Shared with register.php / any future account-settings screen
// via reusables/password_helpers.php.
// ==================================================
$strengthError = passwordStrengthError($password, [$email]);
if ($strengthError !== null) {
    echo json_encode(["success" => false, "message" => $strengthError]);
    exit;
}

// ==================================================
// FIND USER
// This endpoint should only ever run after verify_otp.php (context
// 'forgot') already confirmed the reset code — it doesn't re-check
// the OTP itself, matching the Likhavite version's split.
// ==================================================
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(["success" => false, "message" => "Email not found"]);
    exit;
}

// ==================================================
// UPDATE PASSWORD
// ==================================================
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    UPDATE users
    SET password_hash = ?
    WHERE id = ?
");
$stmt->execute([$hashedPassword, $user['id']]);

// ==================================================
// CLEAR RESET OTP (user_security_tokens)
// ==================================================
$stmt = $pdo->prepare("
    UPDATE user_security_tokens
    SET reset_otp = NULL, reset_otp_expires_at = NULL
    WHERE user_id = ?
");
$stmt->execute([$user['id']]);

echo json_encode([
    "success" => true,
    "message" => "Password reset successful"
]);
