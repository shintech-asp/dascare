<?php
// reusables/temporary_accounts.php
// Temporary accounts for website guests (Phase 2).
//
// A guest SOS on the website (citizen/create.php) creates a temporary account
// and signs the browser into it ($_SESSION['temp_account_id']). The guest gets
// a login key (e.g. K7MQ-2XRT-9PHD, ~59 bits, stored SHA-256 hashed) to come
// back from any device via auth/temporary_login.php. Signing in puts the
// account's request ids into $_SESSION['guest_request_ids'], so every
// existing guest path (citizen/detail.php, cancel.php, unmerge.php, live
// updates) works unchanged — a temporary account is a guest with a login.
//
// Completing the account = the normal registration + email OTP; when that
// email is verified (auth/verify_otp.php) the account's requests move to the
// new citizen (requester_user_id) and the temporary account is marked
// converted. Unconverted accounts are deleted N days after their last request
// ends (system_settings guest.temporary_account_retention_days, default 7);
// the emergency records stay, with the guest's name/phone removed.
// The Android app keeps its own guest keys and does not use these.

const TEMP_KEY_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // no 0/O, 1/I/L
const TEMP_KEY_LENGTH = 12;
const TEMP_RETENTION_DEFAULT_DAYS = 7;
const TEMP_RETENTION_LIMITS = [1, 90];
const TEMP_CLEANUP_EVERY_SECONDS = 3600;
const TEMP_FINAL_STATUSES = ['completed', 'cancelled', 'rejected', 'false_alarm'];
// SQL: is request `er` still active? A dedup-linked report (status duplicate)
// follows its incident `p` (LEFT JOINed as p on er.merged_into_request_id).
const TEMP_ACTIVE_SQL = "(CASE WHEN er.id IS NULL THEN 0 WHEN er.status = 'duplicate' THEN COALESCE(p.status, 'completed') ELSE er.status END) NOT IN ('completed','cancelled','rejected','false_alarm', '0')";

/** "k7mq 2xrt-9phd" → "K7MQ2XRT9PHD" (or '' if it can't be a key). */
function tempNormalizeKey(string $key): string
{
    $k = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $key));
    return strlen($k) === TEMP_KEY_LENGTH && strspn($k, TEMP_KEY_ALPHABET) === TEMP_KEY_LENGTH ? $k : '';
}

function tempKeyHash(string $normalized): string
{
    return hash('sha256', 'dascare-temp:' . $normalized);
}

function tempFormatKey(string $normalized): string
{
    return implode('-', str_split($normalized, 4));
}

/** Creates a temporary account. Returns ['id' => int, 'key' => 'XXXX-XXXX-XXXX'] (the only time the key exists in plain text). */
function tempCreateAccount(PDO $pdo, ?string $name, ?string $phone): array
{
    $alphabet = TEMP_KEY_ALPHABET;
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $key = '';
        for ($i = 0; $i < TEMP_KEY_LENGTH; $i++) $key .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        try {
            $pdo->prepare('INSERT INTO temporary_accounts (login_key_hash, key_hint, display_name, phone, last_login_at) VALUES (?, ?, ?, ?, NOW())')
                ->execute([tempKeyHash($key), substr($key, -4), $name !== '' ? $name : null, $phone !== '' ? $phone : null]);
            return ['id' => (int) $pdo->lastInsertId(), 'key' => tempFormatKey($key)];
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e; // duplicate hash: astronomically unlikely, retry
        }
    }
    throw new RuntimeException('Could not create a temporary account key.');
}

function tempFindByKey(PDO $pdo, string $key): ?array
{
    $normalized = tempNormalizeKey($key);
    if ($normalized === '') return null;
    $stmt = $pdo->prepare("SELECT * FROM temporary_accounts WHERE login_key_hash = ? AND status = 'active' LIMIT 1");
    $stmt->execute([tempKeyHash($normalized)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** The signed-in temporary account (still active), or null. */
function tempCurrentAccount(PDO $pdo): ?array
{
    $id = (int) ($_SESSION['temp_account_id'] ?? 0);
    if ($id <= 0) return null;
    $stmt = $pdo->prepare("SELECT * FROM temporary_accounts WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$account) unset($_SESSION['temp_account_id']); // converted or deleted meanwhile
    return $account;
}

function tempRequestIds(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare('SELECT id FROM emergency_requests WHERE temporary_account_id = ?');
    $stmt->execute([$accountId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Sign this browser into the temporary account (new session id; its requests become this session's guest requests). */
function tempSignIn(PDO $pdo, int $accountId): void
{
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    $_SESSION['temp_account_id'] = $accountId;
    $_SESSION['last_activity'] = time();
    $_SESSION['guest_request_ids'] = array_values(array_unique(array_merge(
        array_map('intval', (array) ($_SESSION['guest_request_ids'] ?? [])),
        tempRequestIds($pdo, $accountId)
    )));
}

function tempLinkRequest(PDO $pdo, int $accountId, int $requestId): void
{
    $pdo->prepare('UPDATE emergency_requests SET temporary_account_id = ? WHERE id = ? AND requester_user_id IS NULL')->execute([$accountId, $requestId]);
    $_SESSION['guest_request_ids'] = array_values(array_unique(array_merge(
        array_map('intval', (array) ($_SESSION['guest_request_ids'] ?? [])), [$requestId]
    )));
}

function tempRetentionDays(PDO $pdo): int
{
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'guest.temporary_account_retention_days' LIMIT 1");
    $stmt->execute();
    $value = json_decode((string) $stmt->fetchColumn(), true);
    [$min, $max] = TEMP_RETENTION_LIMITS;
    return is_numeric($value) && (int) $value >= $min && (int) $value <= $max ? (int) $value : TEMP_RETENTION_DEFAULT_DAYS;
}

/**
 * The account's requests (newest first) for the "My emergency" page.
 * A dedup-linked report shows the status of the incident it follows.
 */
function tempAccountRequests(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare("
        SELECT er.id, er.reference_number, er.status, er.submitted_at, er.updated_at, er.barangay, er.address_text,
               p.reference_number AS merged_into_reference, p.status AS linked_status
        FROM emergency_requests er
        LEFT JOIN emergency_requests p ON p.id = er.merged_into_request_id
        WHERE er.temporary_account_id = ?
        ORDER BY er.submitted_at DESC
    ");
    $stmt->execute([$accountId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $effective = $r['status'] === 'duplicate' && $r['linked_status'] ? $r['linked_status'] : $r['status'];
        $r['effective_status'] = $effective;
        $r['finished'] = in_array($effective, TEMP_FINAL_STATUSES, true);
    }
    unset($r);
    return $rows;
}

/** When the account will be deleted if not completed (null while a request is still active). */
function tempDeletesAt(PDO $pdo, int $accountId): ?string
{
    $stmt = $pdo->prepare("
        SELECT SUM(" . TEMP_ACTIVE_SQL . ") AS active,
               GREATEST(MAX(ta.created_at), COALESCE(MAX(GREATEST(er.updated_at, COALESCE(p.updated_at, er.updated_at))), MAX(ta.created_at))) AS last_activity
        FROM temporary_accounts ta
        LEFT JOIN emergency_requests er ON er.temporary_account_id = ta.id
        LEFT JOIN emergency_requests p ON p.id = er.merged_into_request_id
        WHERE ta.id = ?
    ");
    $stmt->execute([$accountId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int) $row['active'] > 0) return null;
    $at = new DateTime((string) $row['last_activity']);
    $at->modify('+' . tempRetentionDays($pdo) . ' days');
    return $at->format('Y-m-d H:i:s');
}

/**
 * Email verified for a new registration made from a temporary-account
 * session: its requests become the new citizen's, and the temporary account
 * is closed. Returns how many requests moved.
 */
function tempConvertToUser(PDO $pdo, int $userId): int
{
    $account = tempCurrentAccount($pdo);
    if (!$account) return 0;
    $pdo->beginTransaction();
    try {
        $moved = $pdo->prepare('UPDATE emergency_requests SET requester_user_id = ? WHERE temporary_account_id = ? AND requester_user_id IS NULL');
        $moved->execute([$userId, (int) $account['id']]);
        $count = $moved->rowCount();
        $pdo->prepare("UPDATE temporary_accounts SET status = 'converted', converted_user_id = ?, converted_at = NOW() WHERE id = ?")
            ->execute([$userId, (int) $account['id']]);
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent) VALUES (?, 'temporary_account.converted', 'temporary_account', ?, ?, ?, ?)")
            ->execute([$userId, (int) $account['id'], json_encode(['requests_moved' => $count]), $_SERVER['REMOTE_ADDR'] ?? null, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    unset($_SESSION['temp_account_id']);
    return $count;
}

/**
 * Delete expired, unconverted temporary accounts. No cron on this host, so
 * it runs lazily (session.php / temporary login), at most once an hour.
 * Returns how many accounts were deleted (0 when skipped).
 */
function tempCleanupExpired(PDO $pdo, bool $force = false): int
{
    if (!$force) {
        $last = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'guest.temporary_cleanup_last_run'")->fetchColumn();
        if ($last !== false && time() - (int) json_decode((string) $last, true) < TEMP_CLEANUP_EVERY_SECONDS) return 0;
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES ('guest.temporary_cleanup_last_run', ?, 'Internal: last temporary-account clean-up (Unix time).') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
            ->execute([json_encode(time())]);
    }

    $days = tempRetentionDays($pdo);
    $stmt = $pdo->prepare("
        SELECT ta.id
        FROM temporary_accounts ta
        LEFT JOIN emergency_requests er ON er.temporary_account_id = ta.id
        LEFT JOIN emergency_requests p ON p.id = er.merged_into_request_id
        WHERE ta.status = 'active'
        GROUP BY ta.id
        HAVING COALESCE(SUM(" . TEMP_ACTIVE_SQL . "), 0) = 0
           AND GREATEST(MAX(ta.created_at), COALESCE(MAX(GREATEST(er.updated_at, COALESCE(p.updated_at, er.updated_at))), MAX(ta.created_at))) < NOW() - INTERVAL ? DAY
        LIMIT 200
    ");
    $stmt->execute([$days]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    foreach ($ids as $id) {
        $pdo->beginTransaction();
        try {
            // Keep the emergency records; drop the guest's personal details and the link.
            $pdo->prepare("UPDATE emergency_requests SET requester_name = 'Guest requester', requester_phone = '', temporary_account_id = NULL WHERE temporary_account_id = ? AND requester_user_id IS NULL")->execute([$id]);
            $pdo->prepare('DELETE FROM temporary_accounts WHERE id = ? AND status = \'active\'')->execute([$id]);
            $pdo->prepare("INSERT INTO audit_logs (action, entity_type, entity_id, new_values) VALUES ('temporary_account.deleted', 'temporary_account', ?, ?)")
                ->execute([$id, json_encode(['reason' => 'Not completed within ' . $days . ' days of the last request ending.'])]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Temporary account clean-up failed for #' . $id . ': ' . $e->getMessage());
        }
    }
    return count($ids);
}

/** Summary for session.php / the "My emergency" page. */
function tempSessionInfo(PDO $pdo, array $account): array
{
    $requests = tempAccountRequests($pdo, (int) $account['id']);
    return [
        'id' => (int) $account['id'],
        'keyHint' => $account['key_hint'],
        'name' => $account['display_name'],
        'phone' => $account['phone'],
        'createdAt' => $account['created_at'],
        'retentionDays' => tempRetentionDays($pdo),
        'deletesAt' => tempDeletesAt($pdo, (int) $account['id']),
        'requests' => $requests,
        'canComplete' => (bool) array_filter($requests, fn($r) => $r['finished']),
    ];
}
