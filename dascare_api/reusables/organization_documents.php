<?php
// Document management for organizations (Organization → Documents & Credentials,
// Platform → Document Management). A document is one row in organization_documents;
// replacing it uploads a NEW row (pending, replaces_document_id = old) and the old
// one is retired (superseded_at) only when the platform approves the new one, so an
// organization never drops to "no document" while a replacement is under review.
require_once __DIR__ . '/realtime.php';
require_once __DIR__ . '/organization_requirements.php';

const ORG_DOC_MAX_BYTES = 8 * 1024 * 1024;
const ORG_DOC_ALLOWED = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function orgDocStorageDir(): string
{
    $dir = __DIR__ . '/../storage/organization-documents/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Could not create document storage.');
    return $dir;
}

/** Validated upload info, or throws RuntimeException with a user-facing message. */
function orgDocValidateUpload(array $file): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose a file to upload.');
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('The file could not be uploaded. Try again.');
    if (($file['size'] ?? 0) <= 0 || $file['size'] > ORG_DOC_MAX_BYTES) throw new RuntimeException('The file must be 8MB or smaller.');
    $mime = mime_content_type($file['tmp_name']);
    if (!isset(ORG_DOC_ALLOWED[$mime])) throw new RuntimeException('The file must be a PDF, JPG, PNG or WEBP.');
    if (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false) throw new RuntimeException('That is not a valid image file.');
    $name = trim(preg_replace('/[\x00-\x1f<>]/u', '', (string) ($file['name'] ?? 'document')));
    return ['tmp_name' => $file['tmp_name'], 'original_name' => mb_substr($name !== '' ? $name : 'document', 0, 255), 'mime' => $mime, 'extension' => ORG_DOC_ALLOWED[$mime], 'size' => (int) $file['size']];
}

/** Optional YYYY-MM-DD expiry; throws when malformed or already past. Returns null for blank. */
function orgDocParseExpiry($raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $raw);
    if (!$d || $d->format('Y-m-d') !== $raw) throw new RuntimeException('Enter the expiry date as a valid date.');
    if ($raw < date('Y-m-d')) throw new RuntimeException('That document has already expired — upload a current one.');
    if ($raw > date('Y-m-d', strtotime('+30 years'))) throw new RuntimeException('That expiry date is too far away.');
    return $raw;
}

/** ['state' => none|valid|expiring|expired, 'days_left' => int|null] */
function orgDocExpiryState(?string $expiresAt, int $warnDays = 30): array
{
    if (!$expiresAt) return ['state' => 'none', 'days_left' => null];
    $days = (int) floor((strtotime($expiresAt) - strtotime(date('Y-m-d'))) / 86400);
    return ['state' => $days < 0 ? 'expired' : ($days <= $warnDays ? 'expiring' : 'valid'), 'days_left' => $days];
}

function orgDocWarnDays(PDO $pdo): int
{
    $v = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'platform.compliance_warning_days'")->fetchColumn();
    $n = (int) json_decode((string) $v, true);
    return $n >= 1 && $n <= 365 ? $n : 30;
}

/** Active organization admins' user ids. */
function orgDocAdminIds(PDO $pdo, int $organizationId): array
{
    $s = $pdo->prepare("SELECT DISTINCT om.user_id FROM organization_members om INNER JOIN user_roles ur ON ur.user_id = om.user_id AND ur.role = 'organization_admin' WHERE om.organization_id = ? AND om.deleted_at IS NULL AND om.membership_status = 'active'");
    $s->execute([$organizationId]);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

function orgDocNotify(PDO $pdo, array $userIds, string $type, string $title, string $message, int $documentId, string $dedup): void
{
    if (!$userIds) return;
    $ins = $pdo->prepare("INSERT INTO notifications (user_id, notification_type, title, message, related_type, related_id, dedup_key) VALUES (?, ?, ?, ?, 'organization_document', ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), message = VALUES(message), read_at = NULL, created_at = CURRENT_TIMESTAMP");
    foreach ($userIds as $uid) $ins->execute([$uid, $type, $title, $message, $documentId, $dedup . ':' . $uid]);
}

function orgDocPlatformIds(PDO $pdo): array
{
    return array_map('intval', $pdo->query("SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.role = 'platform_executive_admin' WHERE u.deleted_at IS NULL AND u.account_status = 'active'")->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Lazy expiry reminders (no cron): at most once an hour, tell each organization's
 * admins about current approved documents that expire within the warning window
 * (or already expired), once per document.
 */
function orgDocSyncExpiryNotices(PDO $pdo): void
{
    $key = 'documents.expiry_sync_last_run';
    $last = (int) json_decode((string) $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = '$key'")->fetchColumn(), true);
    if ($last > time() - 3600) return;
    $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, 'Managed automatically.') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$key, (string) time()]);

    $warn = orgDocWarnDays($pdo);
    $rows = $pdo->prepare("SELECT d.id, d.organization_id, d.doc_type, d.expires_at, o.name FROM organization_documents d INNER JOIN organizations o ON o.id = d.organization_id AND o.deleted_at IS NULL AND o.status = 'active' WHERE d.status = 'approved' AND d.superseded_at IS NULL AND d.expires_at IS NOT NULL AND d.expires_at <= DATE_ADD(CURDATE(), INTERVAL ? DAY) AND d.expiry_notice_at IS NULL LIMIT 200");
    $rows->execute([$warn]);
    $mark = $pdo->prepare('UPDATE organization_documents SET expiry_notice_at = NOW() WHERE id = ?');
    $platformNeedsBadge = false;
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $s = orgDocExpiryState($d['expires_at'], $warn);
        $label = ucwords(str_replace('_', ' ', $d['doc_type']));
        $title = $s['state'] === 'expired' ? 'Document expired' : 'Document expiring soon';
        $msg = $s['state'] === 'expired' ? "$label expired on {$d['expires_at']}. Upload a current copy so your organization stays compliant." : "$label expires on {$d['expires_at']} ({$s['days_left']} days). Upload a renewed copy before then.";
        $admins = orgDocAdminIds($pdo, (int) $d['organization_id']);
        orgDocNotify($pdo, $admins, 'document_expiry', $title, $msg, (int) $d['id'], 'doc_expiry:' . $d['id']);
        realtimeNotifyUsers($pdo, $admins);
        $mark->execute([(int) $d['id']]);
    }
}
