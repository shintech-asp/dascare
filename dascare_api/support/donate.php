<?php
// Starts a donation: records it as pending and returns a PayMongo Checkout URL.
// POST {amount (pesos), campaign_id?, donor_name?, donor_email?, anonymous?, show_on_wall?, message?}
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/support.php';
require_once __DIR__ . '/../reusables/rate_limit.php';
require_once __DIR__ . '/../reusables/input_validation.php';
header('Content-Type: application/json');

function donateFail(int $code, string $message): void { http_response_code($code); echo json_encode(['success' => false, 'message' => $message]); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') donateFail(405, 'Method not allowed.');
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) donateFail(422, 'Invalid request.');

$settings = supportSettings($pdo);
if (!$settings['enabled']) donateFail(503, 'Donations are paused right now.');
checkRateLimit($pdo, 'support_donate', getClientIp());
if (paymongoConfig() === null && !isset($GLOBALS['DASCARE_PAYMONGO_FETCH'])) donateFail(503, 'Online donations are not set up yet.');

$amount = $in['amount'] ?? null;
if (!is_numeric($amount) || $amount != round((float) $amount, 2)) donateFail(422, 'Enter the amount in pesos.');
$amount = round((float) $amount, 2);
if ($amount < $settings['min_amount'] || $amount > SUPPORT_MAX_AMOUNT) donateFail(422, 'Donations are from ₱' . $settings['min_amount'] . ' to ₱' . number_format(SUPPORT_MAX_AMOUNT) . '.');

$name = mb_substr(trim(strip_tags((string) ($in['donor_name'] ?? ''))), 0, 120);
if ($name !== '' && !inputIsPersonName($name)) donateFail(422, 'Name can only contain letters, spaces, apostrophes, periods and hyphens.');
$email = strtolower(trim((string) ($in['donor_email'] ?? '')));
if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190)) donateFail(422, 'Enter a valid email address, or leave it blank.');
$message = mb_substr(trim(strip_tags((string) ($in['message'] ?? ''))), 0, 200);
if ($message !== '' && !inputLooksLikeText($message, 3)) donateFail(422, 'The message must be readable text.');
$anonymous = !empty($in['anonymous']) || $name === '';
$wall = !$anonymous && !empty($in['show_on_wall']) && $settings['show_wall'];

try {
    $campaignId = null;
    $open = supportOpenCampaigns($pdo);
    $wanted = (int) ($in['campaign_id'] ?? 0);
    foreach ($open as $c) if ($c['id'] === $wanted) $campaignId = $c['id'];
    if ($campaignId === null && $open) $campaignId = $open[0]['id'];

    $ins = $pdo->prepare("INSERT INTO donations (reference, campaign_id, amount_centavos, status, source, donor_name, donor_email, is_anonymous, show_on_wall, message) VALUES (?, ?, ?, 'pending', 'paymongo', ?, ?, ?, ?, ?)");
    $ref = supportNewReference();
    $ins->execute([$ref, $campaignId, (int) round($amount * 100), $name !== '' ? $name : null, $email !== '' ? $email : null, $anonymous ? 1 : 0, $wall ? 1 : 0, $message !== '' ? $message : null]);
    $donation = ['id' => (int) $pdo->lastInsertId(), 'reference' => $ref, 'amount_centavos' => (int) round($amount * 100)];
    recordAttempt($pdo, 'support_donate', getClientIp());

    try {
        $url = supportCreateCheckout($pdo, $donation, $settings['methods'], supportReturnBase());
    } catch (RuntimeException $e) {
        $pdo->prepare("UPDATE donations SET status = 'failed', admin_note = ? WHERE id = ?")->execute([mb_substr($e->getMessage(), 0, 250), $donation['id']]);
        donateFail(503, $e->getMessage());
    }
    echo json_encode(['success' => true, 'reference' => $ref, 'checkout_url' => $url]);
} catch (Throwable $e) {
    error_log('Donate failed: ' . $e->getMessage());
    donateFail(500, 'Unable to start the donation. Please try again.');
}
