<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/dispatch_dss.php';
require_once __DIR__ . '/../../reusables/incident_attention.php';
require_once __DIR__ . '/../../reusables/dispatch_dedup.php';

header('Content-Type: application/json');
requirePlatformExecutive($pdo, 'oversight.emergency_requests.read');

$status = strtolower(trim((string) ($_GET['status'] ?? 'active')));
$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(50, max(10, (int) ($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;

try {
    dssSyncExpiredOffers($pdo);
    syncIncidentAttentionFlags($pdo);

    $where = ["er.request_mode = 'instant'"];
    $params = [];
    if ($status === 'active') {
        $where[] = "er.status NOT IN ('completed','cancelled','rejected','duplicate','false_alarm')";
    } elseif ($status !== 'all') {
        $allowed = ['submitted','validating','verified','assigned','acknowledged','responding','on_scene','transporting','completed','cancelled','rejected','duplicate','false_alarm'];
        if (in_array($status, $allowed, true)) { $where[] = 'er.status = ?'; $params[] = $status; }
    }
    if ($search !== '') {
        $needle = '%' . $search . '%';
        $where[] = "(er.reference_number LIKE ? OR er.requester_name LIKE ? OR er.address_text LIKE ? OR er.barangay LIKE ?)";
        array_push($params, $needle, $needle, $needle, $needle);
    }
    $whereSql = implode(' AND ', $where);

    $count = $pdo->prepare("SELECT COUNT(*) FROM emergency_requests er WHERE $whereSql");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT
            er.id, er.reference_number, er.requester_name, er.requester_phone, er.source,
            er.severity, er.description, er.address_text, er.barangay, er.landmark,
            er.latitude, er.longitude, er.status, er.attention_level, er.attention_flagged_at, er.attention_reason,
            TIMESTAMPDIFF(MINUTE, er.submitted_at, NOW()) AS age_minutes, er.submitted_at,
            ec.name AS category_name,
            dr.id AS dss_run_id, dr.candidate_count, dr.run_status AS dss_status, dr.created_at AS dss_generated_at,
            io.id AS current_offer_id, io.offer_status AS current_offer_status, io.expires_at AS current_offer_expires_at,
            io.organization_id AS current_offer_organization_id, o.name AS current_offer_organization_name,
            accepted.id AS accepted_offer_id, accepted.organization_id AS accepted_organization_id,
            accepted_org.name AS accepted_organization_name,
            er.merged_into_request_id, merged.reference_number AS merged_into_reference
        FROM emergency_requests er
        INNER JOIN emergency_categories ec ON ec.id = er.emergency_category_id
        LEFT JOIN dss_recommendation_runs dr ON dr.id = (
            SELECT dr2.id FROM dss_recommendation_runs dr2
            WHERE dr2.emergency_request_id = er.id ORDER BY dr2.id DESC LIMIT 1
        )
        LEFT JOIN incident_offers io ON io.id = (
            SELECT io2.id FROM incident_offers io2
            WHERE io2.emergency_request_id = er.id AND io2.offer_status = 'sent'
            ORDER BY io2.id DESC LIMIT 1
        )
        LEFT JOIN organizations o ON o.id = io.organization_id
        LEFT JOIN incident_offers accepted ON accepted.id = (
            SELECT io3.id FROM incident_offers io3
            WHERE io3.emergency_request_id = er.id AND io3.offer_status = 'accepted'
            ORDER BY io3.id DESC LIMIT 1
        )
        LEFT JOIN organizations accepted_org ON accepted_org.id = accepted.organization_id
        LEFT JOIN emergency_requests merged ON merged.id = er.merged_into_request_id
        WHERE $whereSql
        ORDER BY FIELD(er.severity, 'critical','high','moderate','low'), er.submitted_at ASC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        foreach (['id','dss_run_id','candidate_count','current_offer_id','current_offer_organization_id','accepted_offer_id','accepted_organization_id','merged_into_request_id'] as $key) {
            $item[$key] = $item[$key] !== null ? (int) $item[$key] : null;
        }
        $item['latitude'] = (float) $item['latitude'];
        $item['longitude'] = (float) $item['longitude'];
    }
    unset($item);
    // How many duplicate reports dedup linked to each incident on this page.
    $linkedCounts = dedupLinkedCounts($pdo, array_column($items, 'id'));
    foreach ($items as &$item) $item['linked_count'] = $linkedCounts[$item['id']] ?? 0;
    unset($item);

    $statsStmt = $pdo->query("
        SELECT
            COUNT(*) AS total_active,
            SUM(status = 'submitted') AS submitted,
            SUM(status = 'validating') AS validating,
            SUM(status = 'verified') AS accepted,
            SUM(status IN ('assigned','acknowledged','responding','on_scene','transporting')) AS assigned,
            SUM(attention_level = 'overdue') AS overdue,
            SUM(attention_level = 'critical_overdue') AS critical_overdue
        FROM emergency_requests
        WHERE request_mode = 'instant' AND status NOT IN ('completed','cancelled','rejected','duplicate','false_alarm')
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach ($stats as $key => $value) $stats[$key] = (int) $value;

    echo json_encode(['success'=>true,'items'=>$items,'stats'=>$stats,'pagination'=>[
        'page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>max(1,(int)ceil($total/$perPage))
    ]]);
} catch (Throwable $e) {
    error_log('Platform incident list failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to load city-wide incidents.');
}
