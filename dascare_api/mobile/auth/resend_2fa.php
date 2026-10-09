<?php
// mobile/auth/resend_2fa.php
// Email a fresh 2FA code during app login. Same limit as the web's
// auth/resend_login_otp.php (3 per 10 min). POST JSON {challenge}.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/rate_limit.php';
require_once __DIR__ . '/../../reusables/mobile_auth.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') mobileJson(405, ['success' => false, 'message' => 'Method not allowed.']);

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$row = mobileFindToken($pdo, trim((string) ($data['challenge'] ?? '')), 'login_2fa');
if (!$row) {
    mobileJson(401, ['success' => false, 'code' => 'CHALLENGE_EXPIRED', 'message' => 'Your login session expired. Please log in again.']);
}
$userId = (int) $row['id'];

checkRateLimit($pdo, 'login_2fa_resend', (string) $userId);
if (!mobileSend2faCode($pdo, $row)) {
    mobileJson(502, ['success' => false, 'message' => 'Failed to send verification code. Please try again.']);
}
recordAttempt($pdo, 'login_2fa_resend', (string) $userId);
// Give the user the full 10 minutes again for the new code.
$pdo->prepare('UPDATE api_tokens SET expires_at = DATE_ADD(NOW(), INTERVAL ' . MOBILE_2FA_TTL_SECONDS . ' SECOND) WHERE id = ?')
    ->execute([(int) $row['token_id']]);

mobileJson(200, ['success' => true, 'message' => 'A new verification code was sent to your email']);
