<?php
// mobile/auth/verify_2fa.php
// Second step of app login for accounts with 2FA. Same checks as the web's
// auth/verify_login_otp.php (5 tries / 10 min, 10-minute code), keyed by the
// challenge token from mobile/auth/login.php instead of a cookie session.
//
// POST JSON {challenge, otp} → {success, token, user}
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/rate_limit.php';
require_once __DIR__ . '/../../reusables/mobile_auth.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') mobileJson(405, ['success' => false, 'message' => 'Method not allowed.']);

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$challenge = trim((string) ($data['challenge'] ?? ''));
$otp = trim((string) ($data['otp'] ?? ''));
if ($challenge === '' || !preg_match('/^\d{6}$/', $otp)) {
    mobileJson(422, ['success' => false, 'message' => 'Enter the 6-digit code from your email.']);
}

$row = mobileFindToken($pdo, $challenge, 'login_2fa');
if (!$row) {
    mobileJson(401, ['success' => false, 'code' => 'CHALLENGE_EXPIRED', 'message' => 'Your login session expired. Please log in again.']);
}
$userId = (int) $row['id'];

checkRateLimit($pdo, 'login_2fa_verify', (string) $userId);

$stmt = $pdo->prepare('SELECT login_otp, login_otp_expires_at FROM user_security_tokens WHERE user_id = ?');
$stmt->execute([$userId]);
$tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tokenRow || !$tokenRow['login_otp']) {
    recordAttempt($pdo, 'login_2fa_verify', (string) $userId);
    mobileJson(400, ['success' => false, 'message' => 'Invalid request']);
}
if (!$tokenRow['login_otp_expires_at'] || strtotime($tokenRow['login_otp_expires_at']) < time()) {
    mobileJson(400, ['success' => false, 'code' => 'OTP_EXPIRED', 'message' => 'OTP expired']);
}
if (!password_verify($otp, $tokenRow['login_otp'])) {
    recordAttempt($pdo, 'login_2fa_verify', (string) $userId);
    mobileJson(400, ['success' => false, 'message' => 'Invalid OTP']);
}

clearAttempts($pdo, 'login_2fa_verify', (string) $userId);
$pdo->prepare('UPDATE user_security_tokens SET login_otp = NULL, login_otp_expires_at = NULL WHERE user_id = ?')->execute([$userId]);
mobileRevokeToken($pdo, (int) $row['token_id']);

$device = $pdo->prepare('SELECT device_name FROM api_tokens WHERE id = ?');
$device->execute([(int) $row['token_id']]);
$token = mobileIssueToken($pdo, $userId, 'access', $device->fetchColumn() ?: null);
$pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$userId]);

mobileJson(200, ['success' => true, 'token' => $token, 'user' => mobileUserPayload($row)]);
