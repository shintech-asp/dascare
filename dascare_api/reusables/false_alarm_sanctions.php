<?php
// reusables/false_alarm_sanctions.php
//
// Sanctions for confirmed false alarms (migration 2026_10_22_false_alarm_sanctions.sql).
//
// A request closed as `false_alarm` by an organization or a platform admin
// (reusables/request_close.php) gives its requester a STRIKE:
//   - citizen account → strike on the account; the ladder below decides the
//     sanction from the number of active strikes within `window_days`;
//   - guest → strike on the phone number (and website temporary account);
//     `guest_block_at` strikes within `guest_window_days` block guest requests
//     from that number for `guest_block_days`.
// Every number is a platform setting (`sanctions.false_alarm`, edited on
// Platform → False Alarm Sanctions) — nothing below is hard-wired except the
// defaults and the allowed ranges.
//
// Citizen ladder (counts of active strikes in the window):
//   >= warn_at    → warning (notification + email)
//   >= suspend_at → account suspended for suspend_days (lifts by itself)
//   >= review_at  → suspended until a platform admin lifts it
// A suspended citizen can still send SOS as a guest (subject to the guest
// rules), so a real emergency is never fully blocked.
//
// Only suspensions this file created (users.suspension_source='false_alarm')
// are ever lifted here; a manual suspension or a disabled account is left
// alone. Removing a strike (accepted appeal / admin) only ever de-escalates.
require_once __DIR__ . '/assignment_helpers.php';

const SANCTION_SETTING_KEY = 'sanctions.false_alarm';
const SANCTION_DEFAULTS = [
    'enabled' => true,
    'window_days' => 90,
    'warn_at' => 1,
    'suspend_at' => 2,
    'suspend_days' => 7,
    'review_at' => 3,
    'guest_enabled' => true,
    'guest_window_days' => 30,
    'guest_block_at' => 2,
    'guest_block_days' => 7,
];
// Allowed ranges (inclusive) — the settings endpoint enforces them.
const SANCTION_LIMITS = [
    'window_days' => [7, 365],
    'warn_at' => [1, 10],
    'suspend_at' => [1, 20],
    'suspend_days' => [1, 90],
    'review_at' => [1, 30],
    'guest_window_days' => [1, 365],
    'guest_block_at' => [1, 20],
    'guest_block_days' => [1, 90],
];

/** Current settings: stored values over the defaults, clamped to the limits. */
function sanctionSettings(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([SANCTION_SETTING_KEY]);
    $stored = json_decode((string) $stmt->fetchColumn(), true);
    $s = SANCTION_DEFAULTS;
    if (is_array($stored)) {
        foreach ($s as $k => $v) {
            if (!array_key_exists($k, $stored)) continue;
            $s[$k] = is_bool($v) ? (bool) $stored[$k] : (int) $stored[$k];
        }
    }
    foreach (SANCTION_LIMITS as $k => [$min, $max]) $s[$k] = max($min, min($max, (int) $s[$k]));
    return $s;
}

/** Validate a settings payload from the admin; returns the clean array or throws with a user-facing message. */
function sanctionValidateSettings(array $in): array
{
    $out = [];
    foreach (SANCTION_DEFAULTS as $k => $default) {
        if (!array_key_exists($k, $in)) throw new InvalidArgumentException('Missing setting: ' . $k . '.');
        if (is_bool($default)) { $out[$k] = (bool) $in[$k]; continue; }
        if (!is_numeric($in[$k]) || (int) $in[$k] != $in[$k]) throw new InvalidArgumentException('Settings must be whole numbers.');
        [$min, $max] = SANCTION_LIMITS[$k];
        $v = (int) $in[$k];
        if ($v < $min || $v > $max) throw new InvalidArgumentException(str_replace('_', ' ', ucfirst($k)) . " must be between $min and $max.");
        $out[$k] = $v;
    }
    if (!($out['warn_at'] <= $out['suspend_at'] && $out['suspend_at'] <= $out['review_at'])) {
        throw new InvalidArgumentException('Keep the order: warning ≤ timed suspension ≤ suspension until review.');
    }
    return $out;
}

function sanctionNormalizePhone(?string $phone): string
{
    return preg_replace('/[\s-]/', '', (string) $phone);
}

/**
 * Give the requester of a request just closed as false alarm a strike and
 * apply the sanction. Call INSIDE the closing transaction (notifications are
 * queued; the email goes out at shutdown). Returns what was applied.
 */
function sanctionIssueStrike(PDO $pdo, int $requestId, ?int $orgId, ?int $actorUserId, string $reason): array
{
    $s = sanctionSettings($pdo);
    if (!$s['enabled']) return ['applied' => 'disabled'];
    $hasTemp = sanctionHasTemporaryAccounts($pdo);
    $stmt = $pdo->prepare('SELECT id, reference_number, source, requester_user_id, requester_phone' . ($hasTemp ? ', temporary_account_id' : ', NULL AS temporary_account_id') . ' FROM emergency_requests WHERE id = ?');
    $stmt->execute([$requestId]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return ['applied' => 'none'];

    $userId = !empty($r['requester_user_id']) ? (int) $r['requester_user_id'] : null;
    $phone = $userId ? null : (sanctionNormalizePhone($r['requester_phone']) ?: null);
    $tempId = $userId ? null : (!empty($r['temporary_account_id']) ? (int) $r['temporary_account_id'] : null);
    $ins = $pdo->prepare('INSERT IGNORE INTO false_alarm_strikes (emergency_request_id, user_id, phone, temporary_account_id, organization_id, issued_by_user_id, reason) VALUES (?,?,?,?,?,?,?)');
    $ins->execute([$requestId, $userId, $phone, $tempId, $orgId, $actorUserId, mb_substr($reason, 0, 255) ?: null]);
    if ($ins->rowCount() === 0) return ['applied' => 'none']; // already struck
    $strikeId = (int) $pdo->lastInsertId();

    if ($userId) {
        $result = sanctionRecomputeUser($pdo, $userId, true);
        $applied = $result['level'];
        sanctionNotifyCitizen($pdo, $userId, $r['reference_number'], $result, $s);
    } else {
        $block = ($phone || $tempId) ? sanctionGuestBlock($pdo, $phone, $tempId) : null;
        $applied = $block ? 'guest_block' : 'none';
    }
    $pdo->prepare('UPDATE false_alarm_strikes SET sanction_applied = ? WHERE id = ?')->execute([$applied, $strikeId]);
    $pdo->prepare("INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, new_values, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$actorUserId, $orgId, 'sanctions.false_alarm_strike', 'false_alarm_strike', $strikeId,
            json_encode(['request_id' => $requestId, 'user_id' => $userId, 'guest' => $userId === null, 'sanction' => $applied], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
    return ['applied' => $applied, 'strike_id' => $strikeId];
}

function sanctionHasTemporaryAccounts(PDO $pdo): bool
{
    static $has = null;
    if ($has === null) {
        try { $pdo->query('SELECT temporary_account_id FROM emergency_requests LIMIT 0'); $has = true; } catch (Throwable $e) { $has = false; }
    }
    return $has;
}

/** Active strikes on a citizen account inside the window, newest first. */
function sanctionActiveUserStrikes(PDO $pdo, int $userId, int $windowDays): array
{
    $stmt = $pdo->prepare("SELECT id, created_at FROM false_alarm_strikes WHERE user_id = ? AND status = 'active' AND created_at >= NOW() - INTERVAL ? DAY ORDER BY created_at DESC");
    $stmt->execute([$userId, $windowDays]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Bring a citizen's suspension in line with their active strikes.
 * $escalate=false (strike removed) only ever lifts or shortens.
 * Returns ['level' => none|warning|suspended|review, 'count' => n, 'until' => ?datetime].
 */
function sanctionRecomputeUser(PDO $pdo, int $userId, bool $escalate): array
{
    $s = sanctionSettings($pdo);
    $strikes = sanctionActiveUserStrikes($pdo, $userId, $s['window_days']);
    $count = count($strikes);
    $level = 'none';
    if ($count >= $s['warn_at']) $level = 'warning';
    if ($count >= $s['suspend_at']) $level = 'suspended';
    if ($count >= $s['review_at']) $level = 'review';

    $u = $pdo->prepare('SELECT account_status, suspended_until, suspension_source FROM users WHERE id = ? FOR UPDATE');
    $u->execute([$userId]);
    $user = $u->fetch(PDO::FETCH_ASSOC);
    if (!$user) return ['level' => $level, 'count' => $count, 'until' => null];
    $ours = $user['account_status'] === 'suspended' && $user['suspension_source'] === 'false_alarm';
    $free = $user['account_status'] === 'active';
    // A manual suspension or a disabled / pending account is never touched here.
    if (!$ours && !($escalate && $free)) return ['level' => $level, 'count' => $count, 'until' => null];

    // Timed suspension runs from the newest strike.
    $timedUntil = null;
    if ($level === 'suspended') {
        $q = $pdo->prepare('SELECT IF(? + INTERVAL ? DAY > NOW(), ? + INTERVAL ? DAY, NULL)');
        $q->execute([$strikes[0]['created_at'], $s['suspend_days'], $strikes[0]['created_at'], $s['suspend_days']]);
        $timedUntil = $q->fetchColumn() ?: null;
        if ($timedUntil === null) $level = 'warning'; // that suspension has already run out
    }
    if (!$escalate && $ours) {
        // De-escalation never lengthens: an indefinite suspension may become
        // timed, a timed one may only get shorter.
        if ($level === 'review') return ['level' => $user['suspended_until'] === null ? 'review' : 'suspended', 'count' => $count, 'until' => $user['suspended_until']];
        if ($level === 'suspended' && $user['suspended_until'] !== null && strtotime($user['suspended_until']) < strtotime($timedUntil)) $timedUntil = $user['suspended_until'];
    }
    $until = null;
    if ($level === 'review') {
        $pdo->prepare("UPDATE users SET account_status = 'suspended', suspended_until = NULL, suspension_source = 'false_alarm' WHERE id = ?")->execute([$userId]);
    } elseif ($level === 'suspended') {
        $pdo->prepare("UPDATE users SET account_status = 'suspended', suspended_until = ?, suspension_source = 'false_alarm' WHERE id = ?")->execute([$timedUntil, $userId]);
        $until = $timedUntil;
    } elseif ($ours) {
        sanctionLiftUser($pdo, $userId);
    }
    return ['level' => $level, 'count' => $count, 'until' => $until];
}

function sanctionLiftUser(PDO $pdo, int $userId): void
{
    $pdo->prepare("UPDATE users SET account_status = 'active', suspended_until = NULL, suspension_source = NULL WHERE id = ? AND suspension_source = 'false_alarm'")->execute([$userId]);
}

/** A timed false-alarm suspension that has run out → active again. Call before login status checks. */
function sanctionAutoLift(PDO $pdo, int $userId): void
{
    try {
        $pdo->prepare("UPDATE users SET account_status = 'active', suspended_until = NULL, suspension_source = NULL WHERE id = ? AND account_status = 'suspended' AND suspension_source = 'false_alarm' AND suspended_until IS NOT NULL AND suspended_until <= NOW()")->execute([$userId]);
    } catch (Throwable $e) { /* migration not run yet */ }
}

/** Is this guest phone / temporary account currently blocked? Returns null or ['until' => datetime, 'message' => string]. */
function sanctionGuestBlock(PDO $pdo, ?string $phone, ?int $tempId): ?array
{
    try { $s = sanctionSettings($pdo); } catch (Throwable $e) { return null; }
    if (!$s['enabled'] || !$s['guest_enabled']) return null;
    $where = [];
    $params = [];
    if ($phone) { $where[] = 'phone = ?'; $params[] = $phone; }
    if ($tempId) { $where[] = 'temporary_account_id = ?'; $params[] = $tempId; }
    if (!$where) return null;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) n, MAX(created_at) latest, MAX(created_at) + INTERVAL ? DAY until_at, MAX(created_at) + INTERVAL ? DAY > NOW() blocked
            FROM false_alarm_strikes WHERE status = 'active' AND user_id IS NULL AND created_at >= NOW() - INTERVAL ? DAY AND (" . implode(' OR ', $where) . ')');
        $stmt->execute(array_merge([$s['guest_block_days'], $s['guest_block_days'], $s['guest_window_days']], $params));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return null;
    }
    if (!$row || (int) $row['n'] < $s['guest_block_at'] || (int) $row['blocked'] !== 1) return null;
    return [
        'until' => $row['until_at'],
        'message' => 'Guest requests from this number are paused until ' . date('M j, Y g:i A', strtotime($row['until_at'])) . ' because of repeated false alarms. If this is a real emergency, call 911 now, or log in to your DASCARE account.',
    ];
}

function sanctionNotifyCitizen(PDO $pdo, int $userId, string $reference, array $result, array $s): void
{
    $count = $result['count'];
    $strikesText = $count . ' false-alarm ' . ($count === 1 ? 'strike' : 'strikes') . ' in the last ' . $s['window_days'] . ' days';
    switch ($result['level']) {
        case 'review':
            $title = 'Account suspended — false alarms';
            $msg = "Your request $reference was confirmed as a false alarm ($strikesText). Your account is suspended until a DASCARE administrator reviews it. You can appeal from the login screen. In a real emergency you can still send SOS as a guest or call 911.";
            break;
        case 'suspended':
            $title = 'Account suspended for ' . $s['suspend_days'] . ' days';
            $msg = "Your request $reference was confirmed as a false alarm ($strikesText). Your account is suspended until " . date('M j, Y g:i A', strtotime($result['until'])) . '. You can appeal from the login screen. In a real emergency you can still send SOS as a guest or call 911.';
            break;
        case 'warning':
            $next = $s['suspend_at'] - $count;
            $title = 'Warning: false alarm';
            $msg = "Your request $reference was confirmed as a false alarm ($strikesText)." . ($next > 0 ? " $next more and your account will be suspended for {$s['suspend_days']} days." : '') . ' If this was a mistake, appeal it under Settings → False alarm record (in the app: Profile → False alarm record).';
            break;
        default:
            return;
    }
    assignmentNotifyUser($pdo, $userId, 'account_sanction', $title, mb_substr($msg, 0, 500), 'user', $userId, 'false-alarm-sanction:' . $userId . ':' . $reference);

    // Email at shutdown (after commit; a mail failure never affects the close).
    $u = $pdo->prepare('SELECT email, first_name FROM users WHERE id = ?');
    $u->execute([$userId]);
    $user = $u->fetch(PDO::FETCH_ASSOC);
    if (!$user || !$user['email']) return;
    register_shutdown_function(function () use ($user, $title, $msg) {
        try {
            require_once __DIR__ . '/email_helper.php';
            $body = render_email_template('Account notice', htmlspecialchars($title), '<p>Hi ' . htmlspecialchars($user['first_name'] ?: 'there') . ',</p><p>' . htmlspecialchars($msg) . '</p>', 'False alarms send ambulances away from people who need them.');
            send_email($user['email'], 'DASCARE: ' . $title, $body, $user['first_name'] ?: null);
        } catch (Throwable $e) {
            error_log('Sanction email failed: ' . $e->getMessage());
        }
    });
}

/** A citizen's record: strikes (with appeal state), current suspension and the rules, for the citizen's own screens. */
function sanctionUserRecord(PDO $pdo, int $userId): array
{
    $s = sanctionSettings($pdo);
    $stmt = $pdo->prepare("SELECT fs.id, fs.emergency_request_id, er.reference_number, fs.reason, fs.status, fs.created_at, fs.removed_reason,
            fs.created_at >= NOW() - INTERVAL ? DAY AS counts, o.name AS organization_name,
            fa.id AS appeal_id, fa.status AS appeal_status, fa.review_note AS appeal_note, fa.created_at AS appealed_at
        FROM false_alarm_strikes fs
        JOIN emergency_requests er ON er.id = fs.emergency_request_id
        LEFT JOIN organizations o ON o.id = fs.organization_id
        LEFT JOIN false_alarm_appeals fa ON fa.id = (SELECT MAX(id) FROM false_alarm_appeals WHERE strike_id = fs.id)
        WHERE fs.user_id = ? ORDER BY fs.created_at DESC");
    $stmt->execute([$s['window_days'], $userId]);
    $strikes = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $strikes[] = [
            'id' => (int) $r['id'], 'request_id' => (int) $r['emergency_request_id'], 'reference_number' => $r['reference_number'],
            'reason' => $r['reason'], 'status' => $r['status'], 'created_at' => $r['created_at'], 'counts' => $r['status'] === 'active' && (int) $r['counts'] === 1,
            'closed_by' => $r['organization_name'] ?: 'a DASCARE administrator', 'removed_reason' => $r['removed_reason'],
            'appeal' => $r['appeal_id'] ? ['id' => (int) $r['appeal_id'], 'status' => $r['appeal_status'], 'note' => $r['appeal_note'], 'created_at' => $r['appealed_at']] : null,
            'can_appeal' => $r['status'] === 'active' && !$r['appeal_id'], // one appeal per strike
        ];
    }
    $u = $pdo->prepare('SELECT account_status, suspended_until, suspension_source FROM users WHERE id = ?');
    $u->execute([$userId]);
    $user = $u->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'strikes' => $strikes,
        'active_count' => count(array_filter($strikes, fn ($x) => $x['counts'])),
        'suspension' => ($user['account_status'] ?? '') === 'suspended' && ($user['suspension_source'] ?? '') === 'false_alarm'
            ? ['until' => $user['suspended_until'], 'until_review' => $user['suspended_until'] === null] : null,
        'rules' => ['window_days' => $s['window_days'], 'warn_at' => $s['warn_at'], 'suspend_at' => $s['suspend_at'], 'suspend_days' => $s['suspend_days'], 'review_at' => $s['review_at']],
    ];
}

/** 30-minute key for a citizen suspended for false alarms (password already checked) to see their record and appeal. */
function sanctionIssueAccessToken(PDO $pdo, int $userId): string
{
    $token = bin2hex(random_bytes(24));
    $pdo->prepare('DELETE FROM sanction_access_tokens WHERE expires_at < NOW()')->execute();
    $pdo->prepare('INSERT INTO sanction_access_tokens (token_hash, user_id, expires_at) VALUES (?, ?, NOW() + INTERVAL 30 MINUTE)')->execute([hash('sha256', $token), $userId]);
    return $token;
}

function sanctionUserFromAccessToken(PDO $pdo, string $token): ?int
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) return null;
    $stmt = $pdo->prepare('SELECT user_id FROM sanction_access_tokens WHERE token_hash = ? AND expires_at > NOW()');
    $stmt->execute([hash('sha256', $token)]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

/**
 * Login refused because of a false-alarm suspension: the response carries
 * the citizen's record and an appeal key instead of a session.
 */
function sanctionLoginRefusal(PDO $pdo, int $userId): ?array
{
    try {
        $u = $pdo->prepare('SELECT suspended_until, suspension_source FROM users WHERE id = ?');
        $u->execute([$userId]);
        $row = $u->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return null; }
    if (!$row || $row['suspension_source'] !== 'false_alarm') return null;
    $until = $row['suspended_until'];
    return [
        'success' => false,
        'code' => 'FALSE_ALARM_SUSPENDED',
        'message' => $until
            ? 'Your account is suspended until ' . date('M j, Y g:i A', strtotime($until)) . ' because of repeated false alarms.'
            : 'Your account is suspended because of repeated false alarms until a DASCARE administrator reviews it.',
        'sanction' => sanctionUserRecord($pdo, $userId),
        'sanctionToken' => sanctionIssueAccessToken($pdo, $userId),
    ];
}

/** Citizen behind a request: the signed-in citizen, or a valid suspension key (X-Sanction-Token header / token param). */
function sanctionRequestUser(PDO $pdo): ?int
{
    $token = (string) ($_SERVER['HTTP_X_SANCTION_TOKEN'] ?? ($_GET['token'] ?? ''));
    if ($token !== '') return sanctionUserFromAccessToken($pdo, $token);
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) return null;
    $r = $pdo->prepare("SELECT 1 FROM user_roles WHERE user_id = ? AND role = 'citizen'");
    $r->execute([$userId]);
    return $r->fetchColumn() ? $userId : null;
}
