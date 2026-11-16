<?php
// Voluntary support for DASCARE: donations (PayMongo Checkout) with admin-set fundraising
// goals, and sponsors. Citizens never pay for emergency features and no sponsor/donation
// prompt appears in any emergency screen.
//
// PayMongo keys live in config/paymongo.json (gitignored):
//   {"secret_key": "sk_test_...", "webhook_secret": "whsk_..."}
// Webhook to register in the PayMongo dashboard: https://<site>/api/support/webhook.php
// (event: checkout_session.payment.paid).
require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/upload_paths.php';

const SUPPORT_MIN_AMOUNT_LIMIT = [20, 1000];          // PayMongo's minimum is ₱20
const SUPPORT_MAX_AMOUNT = 100000;                    // pesos per donation
const SUPPORT_METHODS = ['gcash' => 'GCash', 'paymaya' => 'Maya', 'card' => 'Credit / debit card', 'grab_pay' => 'GrabPay', 'qrph' => 'QR Ph'];
const SPONSOR_CATEGORIES = [
    'healthcare' => 'Hospital / clinic / healthcare provider',
    'pharmacy' => 'Pharmacy / medical supplies',
    'insurance' => 'Health insurance / HMO',
    'safety_training' => 'First-aid / safety training',
    'education' => 'School / university',
    'local_business' => 'Local business',
    'community' => 'Community / civic organization',
    'government' => 'Government office',
];

const SUPPORT_SETTINGS_DEFAULT = [
    'enabled' => true,
    'recipient' => 'DASCARE project team',
    'intro' => 'DASCARE is free for everyone who needs an ambulance. Voluntary donations pay for the servers, maps, live updates and SMS that keep it running.',
    'breakdown' => [['label' => 'Hosting and domain', 'percent' => 30], ['label' => 'Maps and live updates', 'percent' => 30], ['label' => 'SMS and email', 'percent' => 15], ['label' => 'Maintenance and support', 'percent' => 25]],
    'presets' => [50, 100, 200, 500],
    'min_amount' => 20,
    'methods' => ['gcash', 'paymaya', 'card', 'qrph'],
    'show_wall' => true,
    'thanks' => 'Thank you for helping keep emergency response free for everyone.',
];

function supportSettings(PDO $pdo): array
{
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'support.page'");
    $stmt->execute();
    $saved = json_decode((string) $stmt->fetchColumn(), true);
    return is_array($saved) ? array_replace(SUPPORT_SETTINGS_DEFAULT, $saved) : SUPPORT_SETTINGS_DEFAULT;
}

/** Validates and cleans the admin's page settings; throws RuntimeException with a user-facing message. */
function supportCleanSettings(array $in): array
{
    $text = static fn($v, $max) => mb_substr(trim(strip_tags((string) $v)), 0, $max);
    $out = [];
    $out['enabled'] = filter_var($in['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $out['show_wall'] = filter_var($in['show_wall'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $out['recipient'] = $text($in['recipient'] ?? '', 120);
    $out['intro'] = $text($in['intro'] ?? '', 600);
    $out['thanks'] = $text($in['thanks'] ?? '', 300);
    if ($out['recipient'] === '') throw new RuntimeException('Say who receives the donations (shown to donors).');
    if (mb_strlen($out['intro']) < 20) throw new RuntimeException('Write a short intro (at least 20 characters) explaining what donations pay for.');
    $min = $in['min_amount'] ?? null;
    if (!is_numeric($min) || (int) $min != $min || $min < SUPPORT_MIN_AMOUNT_LIMIT[0] || $min > SUPPORT_MIN_AMOUNT_LIMIT[1]) throw new RuntimeException('Minimum donation must be a whole number of pesos from ' . SUPPORT_MIN_AMOUNT_LIMIT[0] . ' to ' . SUPPORT_MIN_AMOUNT_LIMIT[1] . '.');
    $out['min_amount'] = (int) $min;
    $presets = [];
    foreach ((array) ($in['presets'] ?? []) as $p) {
        if (!is_numeric($p) || (int) $p != $p || $p < $out['min_amount'] || $p > SUPPORT_MAX_AMOUNT) throw new RuntimeException('Preset amounts must be whole pesos between the minimum and ' . number_format(SUPPORT_MAX_AMOUNT) . '.');
        $presets[(int) $p] = true;
    }
    $presets = array_keys($presets);
    sort($presets);
    if (count($presets) < 1 || count($presets) > 6) throw new RuntimeException('Give between 1 and 6 preset amounts.');
    $out['presets'] = $presets;
    $methods = array_values(array_unique(array_filter((array) ($in['methods'] ?? []), static fn($m) => isset(SUPPORT_METHODS[$m]))));
    if (!$methods) throw new RuntimeException('Turn on at least one payment method.');
    $out['methods'] = $methods;
    $breakdown = [];
    $sum = 0;
    foreach ((array) ($in['breakdown'] ?? []) as $row) {
        $label = $text($row['label'] ?? '', 60);
        $pct = $row['percent'] ?? null;
        if ($label === '' && ($pct === null || $pct === '')) continue;
        if ($label === '' || !is_numeric($pct) || (int) $pct != $pct || $pct < 1 || $pct > 100) throw new RuntimeException('Each "where the money goes" line needs a name and a whole percent from 1 to 100.');
        $breakdown[] = ['label' => $label, 'percent' => (int) $pct];
        $sum += (int) $pct;
    }
    if (count($breakdown) > 8) throw new RuntimeException('At most 8 lines in "where the money goes".');
    if ($breakdown && $sum !== 100) throw new RuntimeException('The "where the money goes" percentages must add up to 100 (now ' . $sum . ').');
    $out['breakdown'] = $breakdown;
    return $out;
}

/** [from, to] dates (Y-m-d) a campaign's goal counts donations in, on the DB clock. */
function supportCampaignWindow(PDO $pdo, array $c): array
{
    $today = (string) $pdo->query('SELECT CURDATE()')->fetchColumn();
    if ($c['period'] === 'month') return [substr($today, 0, 8) . '01', date('Y-m-t', strtotime($today))];
    if ($c['period'] === 'year') return [substr($today, 0, 4) . '-01-01', substr($today, 0, 4) . '-12-31'];
    return [$c['starts_on'] ?: '1970-01-01', $c['ends_on'] ?: '2999-12-31'];
}

/** Campaign + how much it raised in its current window. */
function supportCampaignProgress(PDO $pdo, array $c): array
{
    [$from, $to] = supportCampaignWindow($pdo, $c);
    $s = $pdo->prepare("SELECT COALESCE(SUM(amount_centavos), 0) AS raised, COUNT(*) AS donors FROM donations WHERE campaign_id = ? AND status = 'paid' AND DATE(paid_at) BETWEEN ? AND ?");
    $s->execute([(int) $c['id'], $from, $to]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    $goal = (int) $c['goal_centavos'];
    $raised = (int) $row['raised'];
    return [
        'id' => (int) $c['id'], 'title' => $c['title'], 'purpose' => $c['purpose'], 'period' => $c['period'],
        'goal_centavos' => $goal, 'raised_centavos' => $raised, 'donors' => (int) $row['donors'],
        'percent' => $goal > 0 ? min(100, (int) floor($raised * 100 / $goal)) : 0,
        'window_from' => $from, 'window_to' => $to,
        'is_active' => (int) $c['is_active'] === 1, 'starts_on' => $c['starts_on'], 'ends_on' => $c['ends_on'],
    ];
}

/** Active campaigns open to donations today. */
function supportOpenCampaigns(PDO $pdo): array
{
    $rows = $pdo->query("SELECT * FROM support_campaigns WHERE is_active = 1 AND (period <> 'custom' OR ((starts_on IS NULL OR starts_on <= CURDATE()) AND (ends_on IS NULL OR ends_on >= CURDATE()))) ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
    return array_map(fn($c) => supportCampaignProgress($pdo, $c), $rows);
}

/** Sponsors that should be shown right now. */
function supportActiveSponsors(PDO $pdo): array
{
    $rows = $pdo->query("SELECT id, name, tagline, website_url, category, tier, logo_file IS NOT NULL AS has_logo FROM sponsors
                         WHERE status = 'active' AND (starts_on IS NULL OR starts_on <= CURDATE()) AND (ends_on IS NULL OR ends_on >= CURDATE())
                         ORDER BY FIELD(tier, 'presenting', 'partner', 'supporter'), sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) { $r['id'] = (int) $r['id']; $r['has_logo'] = (bool) $r['has_logo']; $r['category_label'] = SPONSOR_CATEGORIES[$r['category']] ?? ''; }
    return $rows;
}

function supportNewReference(): string
{
    return 'DON-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
}

// ---------------------------------------------------------------- PayMongo

function paymongoConfig(): ?array
{
    $file = __DIR__ . '/../config/paymongo.json';
    $cfg = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
    return is_array($cfg) && !empty($cfg['secret_key']) ? $cfg : null;
}

/** Calls the PayMongo API. Test hook: $GLOBALS['DASCARE_PAYMONGO_FETCH'] = fn($method, $path, $body) => array. */
function paymongoRequest(string $method, string $path, ?array $body = null): array
{
    if (isset($GLOBALS['DASCARE_PAYMONGO_FETCH'])) return ($GLOBALS['DASCARE_PAYMONGO_FETCH'])($method, $path, $body);
    $cfg = paymongoConfig();
    if (!$cfg) throw new RuntimeException('Online donations are not set up yet.');
    $ch = curl_init('https://api.paymongo.com' . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_USERPWD => $cfg['secret_key'] . ':',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string) $raw, true);
    if ($raw === false || $code >= 400 || !is_array($json)) {
        error_log('PayMongo ' . $method . ' ' . $path . ' failed (' . $code . '): ' . substr((string) $raw, 0, 300));
        throw new RuntimeException('The payment service could not start the donation. Please try again in a moment.');
    }
    return $json;
}

/** The site address PayMongo sends the donor back to: the caller's own origin if CORS-allowed. */
function supportReturnBase(): string
{
    global $allowed_origins;
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '' && is_array($allowed_origins ?? null) && in_array($origin, $allowed_origins, true)) return rtrim($origin, '/');
    $first = (array) appConfig('allowed_origins', []);
    return rtrim((string) ($first[0] ?? 'http://localhost:5173'), '/');
}

/** Creates the Checkout Session for a pending donation; returns the checkout URL. */
function supportCreateCheckout(PDO $pdo, array $donation, array $methods, string $returnBase): string
{
    $ref = $donation['reference'];
    $resp = paymongoRequest('POST', '/v1/checkout_sessions', ['data' => ['attributes' => [
        'line_items' => [['currency' => 'PHP', 'amount' => (int) $donation['amount_centavos'], 'name' => 'Donation to DASCARE', 'quantity' => 1, 'description' => 'Voluntary donation — keeps DASCARE free for everyone']],
        'payment_method_types' => array_values($methods),
        'success_url' => $returnBase . '/support?status=success&ref=' . rawurlencode($ref),
        'cancel_url' => $returnBase . '/support?status=cancelled&ref=' . rawurlencode($ref),
        'description' => 'DASCARE donation ' . $ref,
        'reference_number' => $ref,
        'metadata' => ['donation_reference' => $ref],
        'send_email_receipt' => false, 'show_description' => true, 'show_line_items' => true,
    ]]]);
    $id = (string) ($resp['data']['id'] ?? '');
    $url = (string) ($resp['data']['attributes']['checkout_url'] ?? '');
    if ($id === '' || $url === '') throw new RuntimeException('The payment service did not return a checkout page. Please try again.');
    $pdo->prepare('UPDATE donations SET paymongo_checkout_id = ? WHERE id = ?')->execute([$id, (int) $donation['id']]);
    return $url;
}

/** Marks a donation paid (idempotent). Returns true when this call changed it. */
function supportMarkPaid(PDO $pdo, string $checkoutId, ?string $paymentId, ?string $method, int $paidCentavos, ?string $paidAt): bool
{
    $pdo->beginTransaction();
    try {
        $s = $pdo->prepare('SELECT * FROM donations WHERE paymongo_checkout_id = ? FOR UPDATE');
        $s->execute([$checkoutId]);
        $d = $s->fetch(PDO::FETCH_ASSOC);
        if (!$d || $d['status'] === 'paid' || $d['status'] === 'refunded') { $pdo->rollBack(); return false; }
        if ($paidCentavos > 0 && $paidCentavos !== (int) $d['amount_centavos']) {
            $pdo->prepare("UPDATE donations SET admin_note = ? WHERE id = ?")->execute(['Paid amount ' . $paidCentavos . ' does not match ' . $d['amount_centavos'] . ' — check PayMongo.', (int) $d['id']]);
            $pdo->commit();
            error_log('Donation ' . $d['reference'] . ' amount mismatch');
            return false;
        }
        $when = $paidAt && strtotime($paidAt) ? date('Y-m-d H:i:s', strtotime($paidAt)) : date('Y-m-d H:i:s');
        $pdo->prepare("UPDATE donations SET status = 'paid', method = ?, paymongo_payment_id = ?, paid_at = ? WHERE id = ?")
            ->execute([$method ? mb_substr($method, 0, 40) : null, $paymentId, $when, (int) $d['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    supportSendReceipt($pdo, (int) $d['id']);
    return true;
}

/** Asks PayMongo about a pending donation's checkout session (used when the donor returns). */
function supportRefreshFromPaymongo(PDO $pdo, array $donation): void
{
    if ($donation['status'] !== 'pending' || empty($donation['paymongo_checkout_id'])) return;
    $resp = paymongoRequest('GET', '/v1/checkout_sessions/' . rawurlencode($donation['paymongo_checkout_id']));
    foreach ((array) ($resp['data']['attributes']['payments'] ?? []) as $p) {
        $a = $p['attributes'] ?? [];
        if (($a['status'] ?? '') === 'paid') {
            supportMarkPaid($pdo, $donation['paymongo_checkout_id'], $p['id'] ?? null, $a['source']['type'] ?? null, (int) ($a['amount'] ?? 0), isset($a['paid_at']) ? date('Y-m-d H:i:s', (int) $a['paid_at']) : null);
            return;
        }
    }
}

/** Verifies the Paymongo-Signature header: t=<ts>,te=<test hmac>,li=<live hmac>. */
function paymongoVerifySignature(string $rawBody, string $header, string $secret): bool
{
    $parts = [];
    foreach (explode(',', $header) as $kv) { $p = explode('=', trim($kv), 2); if (count($p) === 2) $parts[$p[0]] = $p[1]; }
    if (empty($parts['t']) || !ctype_digit($parts['t']) || abs(time() - (int) $parts['t']) > 86400) return false;
    $expected = hash_hmac('sha256', $parts['t'] . '.' . $rawBody, $secret);
    foreach (['te', 'li'] as $k) if (!empty($parts[$k]) && hash_equals($expected, $parts[$k])) return true;
    return false;
}

function supportSendReceipt(PDO $pdo, int $donationId): void
{
    try {
        $s = $pdo->prepare("SELECT * FROM donations WHERE id = ? AND status = 'paid' AND receipt_sent_at IS NULL AND donor_email IS NOT NULL AND donor_email <> ''");
        $s->execute([$donationId]);
        $d = $s->fetch(PDO::FETCH_ASSOC);
        if (!$d) return;
        require_once __DIR__ . '/email_helper.php';
        $peso = '₱' . number_format($d['amount_centavos'] / 100, 2);
        $name = $d['donor_name'] ?: 'friend';
        $body = render_email_template('Donation receipt', 'Thank you,<br><span style="color:#c0392b;">' . htmlspecialchars($name) . '</span>', '<p>We received your voluntary donation of <strong>' . $peso . '</strong> to DASCARE (reference <strong>' . htmlspecialchars($d['reference']) . '</strong>).</p><p>It helps keep emergency response free for everyone in Dasmariñas. Please keep this email as your receipt.</p>', 'This is an acknowledgment of a voluntary donation, not an official BIR receipt.');
        $r = send_email($d['donor_email'], 'Your DASCARE donation receipt ' . $d['reference'], $body, $d['donor_name'] ?: null);
        if (!empty($r['success'])) $pdo->prepare('UPDATE donations SET receipt_sent_at = NOW() WHERE id = ?')->execute([$donationId]);
    } catch (Throwable $e) {
        error_log('Donation receipt failed: ' . $e->getMessage());
    }
}
