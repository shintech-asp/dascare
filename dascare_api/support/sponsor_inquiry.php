<?php
// "Become a sponsor" form. The Platform admin reviews every inquiry; nothing is shown publicly until approved.
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/support.php';
require_once __DIR__ . '/../reusables/rate_limit.php';
require_once __DIR__ . '/../reusables/input_validation.php';
header('Content-Type: application/json');

function inqFail(int $code, string $message): void { http_response_code($code); echo json_encode(['success' => false, 'message' => $message]); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') inqFail(405, 'Method not allowed.');
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) inqFail(422, 'Invalid request.');
checkRateLimit($pdo, 'sponsor_inquiry', getClientIp());

$t = static fn($k, $max) => mb_substr(trim(strip_tags((string) ($in[$k] ?? ''))), 0, $max);
$org = $t('organization_name', 120); $contact = $t('contact_name', 120); $email = strtolower($t('email', 190)); $phone = $t('phone', 30);
$category = $t('category', 40); $site = $t('website_url', 255); $msg = $t('message', 1000);
if (mb_strlen($org) < 2 || !inputLooksLikeText($org, 2)) inqFail(422, 'Enter your organization name.');
if (!inputIsPersonName($contact)) inqFail(422, 'Enter the contact person\'s name (letters only).');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) inqFail(422, 'Enter a valid email address.');
if ($phone !== '' && !preg_match('/^(?:\+63|0)9\d{9}$/', $phone)) inqFail(422, 'Enter a valid Philippine mobile number, or leave it blank.');
if (!isset(SPONSOR_CATEGORIES[$category])) inqFail(422, 'Choose the category that fits your organization.');
if ($site !== '' && !preg_match('#^https://[^\s]+\.[^\s]+$#i', $site)) inqFail(422, 'The website must start with https://');
if ($msg !== '' && !inputLooksLikeText($msg, 8)) inqFail(422, 'The message must be readable text.');
if (empty($in['agreed_policy'])) inqFail(422, 'Please confirm you have read the sponsor policy.');

try {
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO sponsor_inquiries (organization_name, contact_name, email, phone, category, website_url, message) VALUES (?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), NULLIF(?, ''))")
        ->execute([$org, $contact, $email, $phone, $category, $site, $msg]);
    $id = (int) $pdo->lastInsertId();
    recordAttempt($pdo, 'sponsor_inquiry', getClientIp());
    $admins = $pdo->query("SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.role = 'platform_executive_admin' WHERE u.deleted_at IS NULL AND u.account_status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    $notify = $pdo->prepare("INSERT INTO notifications (user_id, notification_type, title, message, related_type, related_id, dedup_key) VALUES (?, 'sponsor_inquiry', 'New sponsor inquiry', ?, 'sponsor_inquiry', ?, ?) ON DUPLICATE KEY UPDATE message = VALUES(message), read_at = NULL, created_at = CURRENT_TIMESTAMP");
    foreach ($admins as $uid) $notify->execute([(int) $uid, $org . ' wants to sponsor DASCARE.', $id, 'sponsor_inquiry:' . $id . ':' . $uid]);
    $pdo->commit();
    require_once __DIR__ . '/../reusables/realtime.php';
    realtimeNotifyUsers($pdo, array_map('intval', $admins));
    echo json_encode(['success' => true, 'message' => 'Thank you! The DASCARE team will contact you at ' . $email . '.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Sponsor inquiry failed: ' . $e->getMessage());
    inqFail(500, 'Unable to send your inquiry. Please try again.');
}
