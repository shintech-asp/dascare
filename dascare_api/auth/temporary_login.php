<?php
// auth/temporary_login.php
// "Log in with your request key" — signs this browser into a guest's
// temporary account (reusables/temporary_accounts.php). Rate-limited per IP
// like the normal login, with one generic error so keys can't be probed.
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/rate_limit.php';
require_once __DIR__ . '/../reusables/temporary_accounts.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$key = (string) ($data['key'] ?? '');
$ip = getClientIp();

checkRateLimit($pdo, 'temporary_login', $ip);
tempCleanupExpired($pdo);

$account = tempFindByKey($pdo, $key);
if (!$account) {
    recordAttempt($pdo, 'temporary_login', $ip);
    echo json_encode(['success' => false, 'message' => 'That key didn\'t work. Check it and try again — temporary accounts are removed some days after the request ends.']);
    exit;
}
clearAttempts($pdo, 'temporary_login', $ip);

// A signed-in account (citizen or staff) is replaced by the temporary one.
foreach (['user_id', 'user_name', 'user_email', 'user_phone', 'user_level', 'user_status', 'user_kyc_status', 'organization', 'platform'] as $k) unset($_SESSION[$k]);
tempSignIn($pdo, (int) $account['id']);
$pdo->prepare('UPDATE temporary_accounts SET last_login_at = NOW() WHERE id = ?')->execute([(int) $account['id']]);

echo json_encode(['success' => true, 'temporary' => tempSessionInfo($pdo, $account)]);
