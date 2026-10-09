<?php
// reusables/guest_request_limit.php
//
// Guest abuse handling for emergency requests. Deliberately a SOFT limit,
// not a block:
//
//   - Requests 1–3 from a given phone number in a rolling 24h window go
//     through completely normally.
//   - Every request after that still gets created and dispatched exactly
//     the same way — it's just marked with guest_verification_flag so a
//     dispatcher knows to double-check it before treating it as routine.
//   - A phone number with a history of *confirmed* false alarms (a
//     dispatcher/organization actually marking a past request
//     status='false_alarm', not just "over the count") gets a stronger
//     flag on new guest requests, still without blocking a submission.
//
// There is no code path in here that rejects a guest request. A genuine
// emergency is never turned away for being the 4th one today from the
// same number — the cost of a false negative there is much higher than
// the cost of a dispatcher spending 20 extra seconds double-checking a
// flagged report. Thresholds are both plain constants below, not baked
// into logic, so they're easy to tune without touching the functions —
// and worth revisiting with actual usage data / dispatcher feedback
// rather than treating these numbers as final.
//
// Keyed on phone number (normalized the same way create.php already
// normalizes requester_phone), not IP — a phone number is a much more
// stable proxy for "the same person" than an IP address, which is the
// distinction that matters for a soft-review threshold like this one.
// The separate per-IP checkRateLimit() call in create.php is unrelated:
// that's flood/bot protection, this is a human-facing usage pattern.

// ------------------------------------------------------------------
// Thresholds — tune here. Nothing else in this file hardcodes a number.
// ------------------------------------------------------------------

// Requests from the same phone number, per rolling 24h window, before
// further ones get flagged for dispatcher verification (not blocked).
const GUEST_DAILY_SOFT_LIMIT = 3;
const GUEST_DAILY_WINDOW_SECONDS = 86400;

// Confirmed false alarms (status = 'false_alarm', set by a dispatcher/
// org after the fact — never by this file) from the same phone number,
// within this lookback window, before new guest requests from that
// number get the stronger review flag.
const GUEST_FALSE_ALARM_REVIEW_THRESHOLD = 2;
const GUEST_FALSE_ALARM_LOOKBACK_DAYS = 30;

// Flag values written to emergency_requests.guest_verification_flag.
const GUEST_FLAG_DAILY_THRESHOLD = 'daily_threshold_exceeded';
const GUEST_FLAG_FALSE_ALARM_HISTORY = 'repeat_false_alarm_history';

const GUEST_PHONE_RATE_ACTION_KEY = 'guest_emergency_request_phone';

function guestPhoneIdentifierHash(string $normalizedPhone): string
{
    return hash('sha256', GUEST_PHONE_RATE_ACTION_KEY . ':' . $normalizedPhone);
}

/**
 * How many guest requests this phone number has made in the current
 * rolling window, WITHOUT consuming/incrementing. Read-only — safe to
 * call purely to decide what to show/flag before writing anything.
 * Reuses the same `rate_limits` table create.php's anti-spam throttle
 * already uses (action_key/identifier_hash/attempts/expires_at), just
 * under its own action_key so the two never collide.
 */
function getGuestPhoneRequestCount(PDO $pdo, string $normalizedPhone): int
{
    $hash = guestPhoneIdentifierHash($normalizedPhone);

    $stmt = $pdo->prepare("
        SELECT attempts, expires_at
        FROM rate_limits
        WHERE action_key = ? AND identifier_hash = ?
    ");
    $stmt->execute([GUEST_PHONE_RATE_ACTION_KEY, $hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || $row['expires_at'] === null || strtotime($row['expires_at']) <= time()) {
        return 0;
    }

    return (int) $row['attempts'];
}

/**
 * Record this request against the phone number's rolling window and
 * return the count INCLUDING this one (i.e. its ordinal position today —
 * 1st, 2nd, 3rd, ...). Call once per successful guest submission, same
 * "record on success" placement as create.php's existing recordAttempt().
 */
function consumeGuestPhoneRequest(PDO $pdo, string $normalizedPhone): int
{
    $hash = guestPhoneIdentifierHash($normalizedPhone);
    $expiresAt = date('Y-m-d H:i:s', time() + GUEST_DAILY_WINDOW_SECONDS);

    $stmt = $pdo->prepare("
        INSERT INTO rate_limits (action_key, identifier_hash, attempts, expires_at)
        VALUES (:action_key, :hash, 1, :expires_at)
        ON DUPLICATE KEY UPDATE
            attempts = IF(expires_at IS NULL OR expires_at <= NOW(), 1, attempts + 1),
            expires_at = IF(expires_at IS NULL OR expires_at <= NOW(), :expires_at2, expires_at)
    ");
    $stmt->execute([
        ':action_key'  => GUEST_PHONE_RATE_ACTION_KEY,
        ':hash'        => $hash,
        ':expires_at'  => $expiresAt,
        ':expires_at2' => $expiresAt,
    ]);

    return getGuestPhoneRequestCount($pdo, $normalizedPhone);
}

/**
 * Confirmed false alarms from this phone number within the lookback
 * window — deliberately reads emergency_requests.status directly rather
 * than any guest-only counter, since this is about dispatcher-confirmed
 * outcomes, not raw submission volume.
 */
function getGuestFalseAlarmCount(PDO $pdo, string $normalizedPhone): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM emergency_requests
        WHERE requester_phone = ?
          AND source = 'guest'
          AND status = 'false_alarm'
          AND submitted_at >= (NOW() - INTERVAL ? DAY)
    ");
    $stmt->execute([$normalizedPhone, GUEST_FALSE_ALARM_LOOKBACK_DAYS]);
    return (int) $stmt->fetchColumn();
}

/**
 * Decide the flag (if any) for a new guest request from this phone
 * number. Never returns anything that should block submission — callers
 * always proceed to create the request regardless of the result.
 *
 * $ordinalToday should be the count AFTER this request (i.e. what
 * consumeGuestPhoneRequest() just returned), so the very 3rd request of
 * the day is still unflagged and the 4th is the first flagged one.
 */
function determineGuestVerificationFlag(int $ordinalToday, int $falseAlarmCount): ?string
{
    if ($falseAlarmCount >= GUEST_FALSE_ALARM_REVIEW_THRESHOLD) {
        return GUEST_FLAG_FALSE_ALARM_HISTORY;
    }
    if ($ordinalToday > GUEST_DAILY_SOFT_LIMIT) {
        return GUEST_FLAG_DAILY_THRESHOLD;
    }
    return null;
}

// ------------------------------------------------------------------
// IP-based INDICATIVE counter — display only, never used to flag or
// gate anything. The real threshold logic above is phone-keyed, but a
// guest hasn't typed a phone number yet when they first land on the
// page, so there's nothing phone-keyed to show them at that point. This
// gives the hero CTA a reasonable "X of 3 used today on this device"
// heads-up before that, tracked separately so it never collides with or
// influences the authoritative phone-keyed count above.
// ------------------------------------------------------------------
const GUEST_IP_RATE_ACTION_KEY = 'guest_emergency_request_ip';

function guestIpIdentifierHash(string $ip): string
{
    return hash('sha256', GUEST_IP_RATE_ACTION_KEY . ':' . $ip);
}

function getGuestIpRequestCount(PDO $pdo, string $ip): int
{
    $hash = guestIpIdentifierHash($ip);
    $stmt = $pdo->prepare("
        SELECT attempts, expires_at FROM rate_limits
        WHERE action_key = ? AND identifier_hash = ?
    ");
    $stmt->execute([GUEST_IP_RATE_ACTION_KEY, $hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || $row['expires_at'] === null || strtotime($row['expires_at']) <= time()) {
        return 0;
    }
    return (int) $row['attempts'];
}

function consumeGuestIpRequest(PDO $pdo, string $ip): int
{
    $hash = guestIpIdentifierHash($ip);
    $expiresAt = date('Y-m-d H:i:s', time() + GUEST_DAILY_WINDOW_SECONDS);

    $stmt = $pdo->prepare("
        INSERT INTO rate_limits (action_key, identifier_hash, attempts, expires_at)
        VALUES (:action_key, :hash, 1, :expires_at)
        ON DUPLICATE KEY UPDATE
            attempts = IF(expires_at IS NULL OR expires_at <= NOW(), 1, attempts + 1),
            expires_at = IF(expires_at IS NULL OR expires_at <= NOW(), :expires_at2, expires_at)
    ");
    $stmt->execute([
        ':action_key'  => GUEST_IP_RATE_ACTION_KEY,
        ':hash'        => $hash,
        ':expires_at'  => $expiresAt,
        ':expires_at2' => $expiresAt,
    ]);

    return getGuestIpRequestCount($pdo, $ip);
}
