<?php
/**
 * ==================================================
 * RATE LIMITING
 * --------------------------------------------------
 * Built against DASCARE's existing `rate_limits` table:
 *   action_key (varchar80), identifier_hash (char64, unique
 *   together with action_key), attempts (int), expires_at (datetime)
 *
 * This is an upsert-per-window design (one row per action+identifier),
 * NOT one row per attempt — so it matches the UNIQUE KEY already on
 * (action_key, identifier_hash) in dascare.sql. Identifiers (emails,
 * IPs, user ids) are hashed before storage so the table never holds
 * raw PII.
 *
 * Usage from an endpoint:
 *
 *     require __DIR__ . '/../reusables/rate_limit.php';
 *     checkRateLimit($pdo, 'login', $email);   // exits w/ 429 JSON if blocked
 *     ...
 *     recordAttempt($pdo, 'login', $email);    // call on failure
 *     ...
 *     clearAttempts($pdo, 'login', $email);    // call on success
 * ================================================== */

// ==================================================
// PER-ACTION LIMITS
// [max attempts, window in seconds]. Add new action keys here as
// new endpoints need them — checkRateLimit() falls back to a safe
// default if a key isn't listed.
// ==================================================
const RATE_LIMIT_RULES = [
    'login'             => [8, 900],   // 8 attempts / 15 min (checked per-IP and per-email)
    'otp_verify'         => [5, 600],   // 5 attempts / 10 min
    'otp_resend'          => [3, 600],   // 3 attempts / 10 min
    'login_2fa_verify'    => [5, 600],
    'login_2fa_resend'    => [3, 600],
    'forgot_password'      => [3, 600],  // guards OTP-email spam on the forgot flow itself
    'register'              => [6, 3600], // 6 signups / hour per IP — throttles mass fake-account creation
    'emergency_request'    => [10, 1800], // 10 requests / 30 min (checked per-user AND per-IP) — generous enough for a genuinely bad night, tight enough to stop a dispatcher-flooding script
];

const RATE_LIMIT_DEFAULT = [5, 600];

/**
 * Best-effort real client IP behind a proxy/load balancer.
 */
function getClientIp(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $value = $_SERVER[$key];
            // X-Forwarded-For can be a comma-separated chain; take the first hop.
            if (strpos($value, ',') !== false) {
                $value = trim(explode(',', $value)[0]);
            }
            if (filter_var($value, FILTER_VALIDATE_IP)) {
                return $value;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function rateLimitHash(string $actionKey, string $identifier): string
{
    return hash('sha256', $actionKey . '|' . strtolower($identifier));
}

/**
 * Halts the request with a 429 JSON response if the caller is
 * currently blocked. Does NOT record an attempt by itself —
 * call recordAttempt() separately on actual failures so successful
 * first tries don't burn the budget.
 */
function checkRateLimit(PDO $pdo, string $actionKey, string $identifier): void
{
    [$maxAttempts, ] = RATE_LIMIT_RULES[$actionKey] ?? RATE_LIMIT_DEFAULT;
    $hash = rateLimitHash($actionKey, $identifier);

    $stmt = $pdo->prepare("
        SELECT attempts, expires_at
        FROM rate_limits
        WHERE action_key = ? AND identifier_hash = ?
    ");
    $stmt->execute([$actionKey, $hash]);
    $row = $stmt->fetch();

    if (!$row) {
        return; // no history — fine
    }

    // Expired window — treat as fresh, nothing to block on.
    if (strtotime($row['expires_at']) < time()) {
        return;
    }

    if ((int) $row['attempts'] >= $maxAttempts) {
        $retryAfter = max(0, strtotime($row['expires_at']) - time());
        http_response_code(429);
        header("Retry-After: {$retryAfter}");
        echo json_encode([
            "success" => false,
            "message" => "Too many attempts. Please try again later.",
            "retry_after_seconds" => $retryAfter,
        ]);
        exit;
    }
}

/**
 * Records a failed/consumed attempt. Resets the counter if the
 * previous window already expired.
 */
function recordAttempt(PDO $pdo, string $actionKey, string $identifier): void
{
    [, $windowSeconds] = RATE_LIMIT_RULES[$actionKey] ?? RATE_LIMIT_DEFAULT;
    $hash = rateLimitHash($actionKey, $identifier);
    $now       = time();
    $nowStr    = date('Y-m-d H:i:s', $now);
    $expiresAt = date('Y-m-d H:i:s', $now + $windowSeconds);

    // Compare against PHP's own clock ($nowStr), not MySQL's NOW() —
    // expires_at was written using PHP's clock, and if the DB
    // connection's session time_zone differs from PHP's timezone,
    // "expires_at < NOW()" can look true immediately, silently
    // resetting attempts back to 1 on every call.
    $pdo->prepare("
        INSERT INTO rate_limits (action_key, identifier_hash, attempts, expires_at)
        VALUES (?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE
            attempts   = IF(expires_at < ?, 1, attempts + 1),
            expires_at = IF(expires_at < ?, ?, expires_at)
    ")->execute([$actionKey, $hash, $expiresAt, $nowStr, $nowStr, $expiresAt]);
}

/**
 * Clears the counter entirely — call on success so a legitimate
 * user isn't left one bad attempt away from being blocked.
 */
function clearAttempts(PDO $pdo, string $actionKey, string $identifier): void
{
    $hash = rateLimitHash($actionKey, $identifier);
    $pdo->prepare("
        DELETE FROM rate_limits WHERE action_key = ? AND identifier_hash = ?
    ")->execute([$actionKey, $hash]);
}