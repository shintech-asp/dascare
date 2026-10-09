<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/incident_attention.php';
header('Content-Type: application/json');
requirePlatformExecutive($pdo,'oversight.emergency_requests.read');

try {
    syncIncidentAttentionFlags($pdo);

    $items = $pdo->query("
        SELECT
            er.id, er.reference_number, er.severity, er.status, er.attention_level,
            er.attention_flagged_at, er.attention_reason,
            TIMESTAMPDIFF(MINUTE, er.submitted_at, NOW()) AS age_minutes,
            er.address_text, er.barangay, er.submitted_at,
            ec.name AS category_name,
            (SELECT COUNT(*) FROM incident_offers io
             WHERE io.emergency_request_id=er.id
               AND io.offer_status IN ('declined','timed_out')) AS failed_offers,
            (SELECT MAX(io.expires_at) FROM incident_offers io
             WHERE io.emergency_request_id=er.id) AS last_offer_at,
            (SELECT COUNT(*) FROM dss_recommendations dr
             WHERE dr.emergency_request_id=er.id) AS candidate_count
        FROM emergency_requests er
        LEFT JOIN emergency_categories ec ON ec.id=er.emergency_category_id
        WHERE er.status NOT IN ('completed','cancelled','rejected','duplicate','false_alarm')
          AND (
            er.attention_level IN ('overdue','critical_overdue')
            OR NOT EXISTS (
                SELECT 1 FROM incident_offers io
                WHERE io.emergency_request_id=er.id
                  AND io.offer_status IN ('sent','accepted')
            )
            OR (
                SELECT COUNT(*) FROM incident_offers io
                WHERE io.emergency_request_id=er.id
                  AND io.offer_status IN ('declined','timed_out')
            ) >= 2
          )
        ORDER BY
          FIELD(er.attention_level,'critical_overdue','overdue','normal'),
          (er.severity='critical') DESC,
          er.submitted_at ASC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'=>true,
        'items'=>$items,
        'stats'=>[
            'total'=>count($items),
            'critical'=>count(array_filter($items,fn($x)=>$x['severity']==='critical')),
            'multiple_failures'=>count(array_filter($items,fn($x)=>(int)$x['failed_offers']>=2)),
            'overdue'=>count(array_filter($items,fn($x)=>in_array($x['attention_level'],['overdue','critical_overdue'],true))),
            'critical_overdue'=>count(array_filter($items,fn($x)=>$x['attention_level']==='critical_overdue')),
        ]
    ]);
} catch(Throwable $e) {
    error_log('Platform escalations failed: '.$e->getMessage());
    platformJsonError(500,'Unable to load incident escalations.');
}
