<?php
// Platform → False Alarm Sanctions: appeals, strikes, suspended citizens, settings.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/false_alarm_sanctions.php';
header('Content-Type: application/json');
requirePlatformExecutive($pdo, 'citizens.citizens.read');
try {
    $s = sanctionSettings($pdo);
    $appeals = $pdo->query("SELECT fa.id, fa.strike_id, fa.message, fa.status, fa.review_note, fa.created_at, fa.reviewed_at,
            fs.user_id, er.reference_number, fs.emergency_request_id request_id, fs.reason strike_reason, fs.created_at strike_at, o.name closed_by,
            CONCAT_WS(' ', u.first_name, u.last_name) citizen_name, u.email citizen_email, CONCAT_WS(' ', rv.first_name, rv.last_name) reviewed_by
        FROM false_alarm_appeals fa
        JOIN false_alarm_strikes fs ON fs.id = fa.strike_id
        JOIN emergency_requests er ON er.id = fs.emergency_request_id
        JOIN users u ON u.id = fa.user_id
        LEFT JOIN organizations o ON o.id = fs.organization_id
        LEFT JOIN users rv ON rv.id = fa.reviewed_by_user_id
        ORDER BY fa.status = 'pending' DESC, fa.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare("SELECT fs.id, fs.user_id, fs.phone, fs.temporary_account_id, fs.reason, fs.status, fs.sanction_applied, fs.created_at, fs.removed_reason,
            fs.created_at >= NOW() - INTERVAL ? DAY AS in_window, er.reference_number, fs.emergency_request_id request_id, o.name closed_by,
            CONCAT_WS(' ', u.first_name, u.last_name) citizen_name, u.email citizen_email, u.account_status, u.suspended_until, u.suspension_source
        FROM false_alarm_strikes fs
        JOIN emergency_requests er ON er.id = fs.emergency_request_id
        LEFT JOIN users u ON u.id = fs.user_id
        LEFT JOIN organizations o ON o.id = fs.organization_id
        ORDER BY fs.created_at DESC LIMIT 200");
    $st->execute([$s['window_days']]);
    $strikes = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($strikes as &$r) {
        $r['guest'] = $r['user_id'] === null;
        // Mask guest numbers: 0917•••4567
        if ($r['phone']) $r['phone'] = mb_substr($r['phone'], 0, 4) . '•••' . mb_substr($r['phone'], -4);
        $r['in_window'] = (bool) $r['in_window'];
    }
    unset($r);
    $suspended = $pdo->query("SELECT u.id, CONCAT_WS(' ', u.first_name, u.last_name) name, u.email, u.suspended_until,
            (SELECT COUNT(*) FROM false_alarm_strikes fs WHERE fs.user_id = u.id AND fs.status = 'active') strikes
        FROM users u WHERE u.account_status = 'suspended' AND u.suspension_source = 'false_alarm' AND u.deleted_at IS NULL
        ORDER BY u.suspended_until IS NULL DESC, u.suspended_until")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'settings' => $s, 'limits' => SANCTION_LIMITS, 'appeals' => $appeals, 'strikes' => $strikes, 'suspended' => $suspended,
        'pending_appeals' => count(array_filter($appeals, fn ($a) => $a['status'] === 'pending'))]);
} catch (Throwable $e) {
    error_log('Sanctions list failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to load false alarm sanctions.');
}
