<?php
require_once __DIR__ . '/realtime.php';
/**
 * DASCARE incident attention / overdue synchronization.
 *
 * Emergency records are never deleted just because they are old. Instead,
 * unresolved instant requests that have not progressed beyond the pre-assignment
 * stages are persistently flagged so they cannot silently sit in the normal queue.
 */

function incidentAttentionSetting(PDO $pdo, string $key, int $fallback): int
{
    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $raw = $stmt->fetchColumn();
    if ($raw === false) return $fallback;
    $decoded = json_decode((string) $raw, true);
    $value = is_numeric($decoded) ? (int) $decoded : (is_numeric($raw) ? (int) $raw : $fallback);
    return max(1, $value);
}

function syncIncidentAttentionFlags(PDO $pdo, ?int $requestId = null): void
{
    $overdueMinutes = incidentAttentionSetting($pdo, 'platform.incident_stale_minutes', 15);
    $criticalMinutes = incidentAttentionSetting($pdo, 'platform.incident_critical_stale_minutes', max(60, $overdueMinutes * 4));
    if ($criticalMinutes <= $overdueMinutes) $criticalMinutes = max($overdueMinutes + 15, $overdueMinutes * 2);

    $idSql = $requestId !== null && $requestId > 0 ? ' AND er.id = ' . (int) $requestId : '';

    // Record the transition once before updating the row. This gives the audit
    // viewer a durable history without changing the emergency lifecycle status.
    $pdo->exec("\n        INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)\n        SELECT NULL, NULL, 'incident_overdue_flagged', 'emergency_request', er.id,\n               JSON_OBJECT('attention_level', er.attention_level),\n               JSON_OBJECT('attention_level', 'overdue', 'submitted_at', er.submitted_at),\n               NULL, 'DASCARE incident attention monitor'\n        FROM emergency_requests er\n        WHERE er.request_mode = 'instant'\n          AND er.status IN ('submitted','validating','verified')\n          AND er.attention_level = 'normal'\n          AND er.submitted_at <= DATE_SUB(NOW(), INTERVAL {$overdueMinutes} MINUTE)\n          {$idSql}\n    ");

    $pdo->exec("\n        INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)\n        SELECT NULL, NULL, 'incident_critical_overdue', 'emergency_request', er.id,\n               JSON_OBJECT('attention_level', er.attention_level),\n               JSON_OBJECT('attention_level', 'critical_overdue', 'submitted_at', er.submitted_at),\n               NULL, 'DASCARE incident attention monitor'\n        FROM emergency_requests er\n        WHERE er.request_mode = 'instant'\n          AND er.status IN ('submitted','validating','verified')\n          AND er.attention_level <> 'critical_overdue'\n          AND er.submitted_at <= DATE_SUB(NOW(), INTERVAL {$criticalMinutes} MINUTE)\n          {$idSql}\n    ");

    // Notify Platform Executive accounts once at each escalation level.
    $pdo->exec("\n        INSERT INTO notifications (user_id, notification_type, title, message, related_type, related_id, dedup_key)\n        SELECT ur.user_id, 'incident_escalation', 'Emergency Request Overdue',\n               CONCAT(er.reference_number, ' has remained unresolved for more than {$overdueMinutes} minutes and requires operational review.'),\n               'emergency_request', er.id, CONCAT('incident-overdue-', er.id)\n        FROM emergency_requests er\n        JOIN user_roles ur ON ur.role = 'platform_executive_admin'\n        JOIN users u ON u.id = ur.user_id AND u.deleted_at IS NULL AND u.account_status = 'active'\n        WHERE er.request_mode = 'instant'\n          AND er.status IN ('submitted','validating','verified')\n          AND er.attention_level = 'normal'\n          AND er.submitted_at <= DATE_SUB(NOW(), INTERVAL {$overdueMinutes} MINUTE)\n          {$idSql}\n        ON DUPLICATE KEY UPDATE title = VALUES(title), message = VALUES(message)\n    ");

    $pdo->exec("\n        INSERT INTO notifications (user_id, notification_type, title, message, related_type, related_id, dedup_key)\n        SELECT ur.user_id, 'incident_escalation', 'Critical Unresolved Emergency',\n               CONCAT(er.reference_number, ' has remained unresolved for more than {$criticalMinutes} minutes. Immediate review is required.'),\n               'emergency_request', er.id, CONCAT('incident-critical-overdue-', er.id)\n        FROM emergency_requests er\n        JOIN user_roles ur ON ur.role = 'platform_executive_admin'\n        JOIN users u ON u.id = ur.user_id AND u.deleted_at IS NULL AND u.account_status = 'active'\n        WHERE er.request_mode = 'instant'\n          AND er.status IN ('submitted','validating','verified')\n          AND er.attention_level <> 'critical_overdue'\n          AND er.submitted_at <= DATE_SUB(NOW(), INTERVAL {$criticalMinutes} MINUTE)\n          {$idSql}\n        ON DUPLICATE KEY UPDATE title = VALUES(title), message = VALUES(message)\n    ");

    // Which requests are about to change flag, so open screens can be told.
    $changing = $pdo->query("
        SELECT er.id FROM emergency_requests er
        WHERE er.request_mode = 'instant' AND er.status IN ('submitted','validating','verified') {$idSql}
          AND er.attention_level <> CASE
                WHEN er.submitted_at <= DATE_SUB(NOW(), INTERVAL {$criticalMinutes} MINUTE) THEN 'critical_overdue'
                WHEN er.submitted_at <= DATE_SUB(NOW(), INTERVAL {$overdueMinutes} MINUTE) THEN 'overdue'
                ELSE 'normal' END
        UNION
        SELECT er.id FROM emergency_requests er
        WHERE er.attention_level <> 'normal' AND er.status NOT IN ('submitted','validating','verified') {$idSql}
    ")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($changing as $changedId) realtimeRequestChanged($pdo, (int) $changedId, 'request.attention');

    $pdo->exec("\n        UPDATE emergency_requests er\n        SET er.attention_level = CASE\n              WHEN er.submitted_at <= DATE_SUB(NOW(), INTERVAL {$criticalMinutes} MINUTE) THEN 'critical_overdue'\n              WHEN er.submitted_at <= DATE_SUB(NOW(), INTERVAL {$overdueMinutes} MINUTE) THEN 'overdue'\n              ELSE 'normal'\n            END,\n            er.attention_flagged_at = CASE\n              WHEN er.submitted_at <= DATE_SUB(NOW(), INTERVAL {$overdueMinutes} MINUTE) THEN COALESCE(er.attention_flagged_at, NOW())\n              ELSE NULL\n            END,\n            er.attention_reason = CASE\n              WHEN er.submitted_at <= DATE_SUB(NOW(), INTERVAL {$criticalMinutes} MINUTE)\n                THEN 'Emergency request has remained unresolved beyond the critical escalation threshold.'\n              WHEN er.submitted_at <= DATE_SUB(NOW(), INTERVAL {$overdueMinutes} MINUTE)\n                THEN 'Emergency request has remained unresolved beyond the configured escalation threshold.'\n              ELSE NULL\n            END\n        WHERE er.request_mode = 'instant'\n          AND er.status IN ('submitted','validating','verified')\n          {$idSql}\n    ");

    // Once dispatch actually progresses (or the request becomes terminal), it
    // should no longer carry a current overdue flag. Historical audit entries stay.
    $pdo->exec("\n        UPDATE emergency_requests er\n        SET er.attention_level = 'normal', er.attention_flagged_at = NULL, er.attention_reason = NULL\n        WHERE er.attention_level <> 'normal'\n          AND er.status NOT IN ('submitted','validating','verified')\n          {$idSql}\n    ");
}
