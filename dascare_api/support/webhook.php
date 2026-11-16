<?php
// PayMongo webhook (event: checkout_session.payment.paid). Register
//   https://<site>/api/support/webhook.php
// in the PayMongo dashboard and put its signing secret in config/paymongo.json ("webhook_secret").
// Not browser-facing, so no CORS/session here.
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/support.php';
header('Content-Type: application/json');

$raw = (string) file_get_contents('php://input');
$cfg = paymongoConfig();
if (!$cfg || empty($cfg['webhook_secret'])) { http_response_code(503); echo json_encode(['success' => false]); exit; }
if (!paymongoVerifySignature($raw, (string) ($_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? ''), $cfg['webhook_secret'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }

try {
    $event = json_decode($raw, true);
    if (($event['data']['attributes']['type'] ?? '') === 'checkout_session.payment.paid') {
        $session = $event['data']['attributes']['data'] ?? [];
        $payment = ($session['attributes']['payments'][0] ?? []);
        $pa = $payment['attributes'] ?? [];
        supportMarkPaid($pdo, (string) ($session['id'] ?? ''), $payment['id'] ?? null, $pa['source']['type'] ?? null, (int) ($pa['amount'] ?? 0), isset($pa['paid_at']) ? date('Y-m-d H:i:s', (int) $pa['paid_at']) : null);
    }
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('PayMongo webhook failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false]);
}
