<?php
// citizen/guest_status.php
//
// Public GET endpoint for the hero CTA's "X of 3 used today" badge,
// fetched before the guest has typed anything (no phone number known
// yet at that point). Deliberately IP-based and explicitly indicative —
// see the note in reusables/guest_request_limit.php: the actual soft
// limit + flagging in create.php is phone-keyed and computed at submit
// time, this is just a reasonable heads-up shown ahead of that.
require '../cors.php';
require_once __DIR__ . '/../db/db.php';               // exposes PDO as $pdo
require_once __DIR__ . '/../reusables/rate_limit.php'; // getClientIp()
require_once __DIR__ . '/../reusables/guest_request_limit.php';

header('Content-Type: application/json');

$ip = getClientIp();

try {
    $used = getGuestIpRequestCount($pdo, $ip);
    echo json_encode([
        'success'    => true,
        'limit'      => GUEST_DAILY_SOFT_LIMIT,
        'used'       => $used,
        'indicative' => true, // this device/IP only — the real per-phone count is authoritative at submit time
    ]);
} catch (Throwable $e) {
    error_log('Guest status error: ' . $e->getMessage());
    // Fail open — don't let a transient DB hiccup block the CTA from
    // rendering; create.php's own logic is still what actually matters.
    http_response_code(200);
    echo json_encode([
        'success'    => true,
        'limit'      => GUEST_DAILY_SOFT_LIMIT,
        'used'       => 0,
        'indicative' => true,
    ]);
}
