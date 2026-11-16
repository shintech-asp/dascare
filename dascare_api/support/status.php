<?php
// Where a donation stands (the donor lands on /support?ref=… after paying). When it is still
// pending we ask PayMongo directly, so it works even before the webhook arrives.
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/support.php';
header('Content-Type: application/json');

$ref = trim((string) ($_GET['ref'] ?? ''));
if (!preg_match('/^DON-[A-Z0-9]{10}$/', $ref)) { http_response_code(422); echo json_encode(['success' => false, 'message' => 'Invalid reference.']); exit; }
try {
    $s = $pdo->prepare('SELECT * FROM donations WHERE reference = ?');
    $s->execute([$ref]);
    $d = $s->fetch(PDO::FETCH_ASSOC);
    if (!$d) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Donation not found.']); exit; }
    if ($d['status'] === 'pending') {
        try { supportRefreshFromPaymongo($pdo, $d); } catch (Throwable $e) { error_log('Donation refresh failed: ' . $e->getMessage()); }
        $s->execute([$ref]);
        $d = $s->fetch(PDO::FETCH_ASSOC);
    }
    echo json_encode(['success' => true, 'status' => $d['status'], 'reference' => $d['reference'], 'amount_centavos' => (int) $d['amount_centavos'], 'method' => $d['method'], 'paid_at' => $d['paid_at']]);
} catch (Throwable $e) {
    error_log('Donation status failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to check the donation.']);
}
