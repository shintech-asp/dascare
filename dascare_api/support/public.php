<?php
// Public data for the Support page, the landing supporters strip and the app:
// page text, open fundraising goals with progress, the donor wall, active sponsors.
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/support.php';
header('Content-Type: application/json');

try {
    $s = supportSettings($pdo);
    $wall = [];
    if ($s['show_wall']) {
        $wall = $pdo->query("SELECT donor_name AS name, amount_centavos, paid_at FROM donations WHERE status = 'paid' AND show_on_wall = 1 AND is_anonymous = 0 AND donor_name IS NOT NULL AND donor_name <> '' ORDER BY paid_at DESC LIMIT 24")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($wall as &$w) $w['amount_centavos'] = (int) $w['amount_centavos'];
        unset($w);
    }
    $methods = [];
    foreach ($s['methods'] as $m) if (isset(SUPPORT_METHODS[$m])) $methods[] = ['key' => $m, 'label' => SUPPORT_METHODS[$m]];
    echo json_encode([
        'success' => true,
        'enabled' => (bool) $s['enabled'], 'show_wall' => (bool) $s['show_wall'],
        'online_ready' => paymongoConfig() !== null || isset($GLOBALS['DASCARE_PAYMONGO_FETCH']),
        'recipient' => $s['recipient'], 'intro' => $s['intro'], 'thanks' => $s['thanks'],
        'breakdown' => $s['breakdown'], 'presets' => $s['presets'], 'min_amount' => $s['min_amount'], 'max_amount' => SUPPORT_MAX_AMOUNT,
        'methods' => $methods,
        'campaigns' => supportOpenCampaigns($pdo),
        'wall' => $wall,
        'sponsors' => supportActiveSponsors($pdo),
        'sponsor_categories' => SPONSOR_CATEGORIES,
    ]);
} catch (Throwable $e) {
    error_log('Support public failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load the support page.']);
}
