<?php
// platform/incidents/monitor.php
// Live monitoring board for City-wide Incidents: every active incident with
// where it stands (waiting / offered / accepted / en route / on scene /
// transporting / no unit), its offer countdown, the assigned unit's last GPS
// fix and ETA, plus city-wide counters and fleet status. The page re-fetches
// this on every platform live event and moves ambulance pins straight from
// `ambulance.location` events on each request channel.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/dispatch_dss.php';
require_once __DIR__ . '/../../reusables/incident_attention.php';

header('Content-Type: application/json');
requirePlatformExecutive($pdo, 'oversight.emergency_requests.read');

try {
    // Same lazy clock as the incident list: expire offers, raise overdue flags.
    dssSyncExpiredOffers($pdo);
    syncIncidentAttentionFlags($pdo);

    $rows = $pdo->query("
        SELECT
            er.id, er.reference_number, er.status, er.severity, er.barangay, er.address_text, er.landmark,
            er.latitude, er.longitude, er.submitted_at, er.attention_level,
            TIMESTAMPDIFF(SECOND, er.submitted_at, NOW()) AS age_seconds,
            ec.name AS category_name,
            (SELECT COUNT(*) FROM emergency_requests c WHERE c.merged_into_request_id = er.id AND c.status = 'duplicate') AS linked_count,
            dr.run_status AS dss_status, dr.candidate_count,
            io.id AS offer_id, o.name AS offer_organization,
            TIMESTAMPDIFF(SECOND, NOW(), io.expires_at) AS offer_seconds_left,
            acc_org.name AS accepted_organization,
            da.id AS assignment_id, da.assignment_status, a.unit_code, a.id AS ambulance_id, da_org.name AS unit_organization,
            a.last_latitude AS ambulance_latitude, a.last_longitude AS ambulance_longitude,
            TIMESTAMPDIFF(SECOND, a.last_location_at, NOW()) AS location_age_seconds,
            mr.duration_s AS eta_seconds, mr.distance_m AS eta_distance_m, mr.provider AS eta_provider,
            TIMESTAMPDIFF(SECOND, mr.computed_at, NOW()) AS eta_age_seconds, mr.destination_kind
        FROM emergency_requests er
        JOIN emergency_categories ec ON ec.id = er.emergency_category_id
        LEFT JOIN dss_recommendation_runs dr ON dr.id = (
            SELECT dr2.id FROM dss_recommendation_runs dr2 WHERE dr2.emergency_request_id = er.id ORDER BY dr2.id DESC LIMIT 1)
        LEFT JOIN incident_offers io ON io.id = (
            SELECT io2.id FROM incident_offers io2 WHERE io2.emergency_request_id = er.id AND io2.offer_status = 'sent' ORDER BY io2.id DESC LIMIT 1)
        LEFT JOIN organizations o ON o.id = io.organization_id
        LEFT JOIN incident_offers acc ON acc.id = (
            SELECT io3.id FROM incident_offers io3 WHERE io3.emergency_request_id = er.id AND io3.offer_status = 'accepted' ORDER BY io3.id DESC LIMIT 1)
        LEFT JOIN organizations acc_org ON acc_org.id = acc.organization_id
        LEFT JOIN dispatch_assignments da ON da.emergency_request_id = er.id
            AND da.assignment_status IN ('assigned','acknowledged','responding','on_scene','transporting')
        LEFT JOIN ambulances a ON a.id = da.ambulance_id
        LEFT JOIN organizations da_org ON da_org.id = da.organization_id
        LEFT JOIN mission_routes mr ON mr.dispatch_assignment_id = da.id
        WHERE er.request_mode = 'instant'
          AND er.status NOT IN ('completed','cancelled','rejected','duplicate','false_alarm')
        ORDER BY FIELD(er.attention_level, 'critical_overdue', 'overdue', 'normal'), er.submitted_at ASC
        LIMIT 200
    ")->fetchAll(PDO::FETCH_ASSOC);

    $counts = ['active' => 0, 'waiting' => 0, 'offered' => 0, 'accepted' => 0, 'en_route' => 0, 'on_scene' => 0, 'transporting' => 0, 'no_unit' => 0, 'overdue' => 0, 'linked_reports' => 0];
    $incidents = [];
    foreach ($rows as $r) {
        $assignment = $r['assignment_status'];
        if ($assignment === 'on_scene') $phase = 'on_scene';
        elseif ($assignment === 'transporting') $phase = 'transporting';
        elseif ($assignment !== null) $phase = 'en_route';
        elseif ($r['accepted_organization'] !== null) $phase = 'accepted';
        elseif ($r['offer_id'] !== null) $phase = 'offered';
        // Screened but nobody to send it to: the case that needs a human now.
        elseif ($r['dss_status'] !== null && ($r['dss_status'] === 'exhausted' || (int) $r['candidate_count'] === 0)) $phase = 'no_unit';
        else $phase = 'waiting';

        $counts['active']++;
        $counts[$phase]++;
        if ($r['attention_level'] !== 'normal') $counts['overdue']++;
        $counts['linked_reports'] += (int) $r['linked_count'];

        $hasGps = $r['ambulance_latitude'] !== null && $r['ambulance_longitude'] !== null;
        $incidents[] = [
            'id' => (int) $r['id'],
            'reference_number' => $r['reference_number'],
            'status' => $r['status'],
            'phase' => $phase,
            'severity' => $r['severity'],
            'category_name' => $r['category_name'],
            'barangay' => $r['barangay'],
            'address_text' => $r['address_text'],
            'landmark' => $r['landmark'],
            'latitude' => (float) $r['latitude'],
            'longitude' => (float) $r['longitude'],
            'submitted_at' => $r['submitted_at'],
            'age_seconds' => max(0, (int) $r['age_seconds']),
            'attention_level' => $r['attention_level'],
            'linked_count' => (int) $r['linked_count'],
            'offer' => $r['offer_id'] === null ? null : [
                'organization' => $r['offer_organization'],
                'seconds_left' => max(0, (int) $r['offer_seconds_left']),
            ],
            'accepted_organization' => $r['accepted_organization'],
            'unit' => $r['assignment_id'] === null ? null : [
                'assignment_id' => (int) $r['assignment_id'],
                'assignment_status' => $assignment,
                'ambulance_id' => (int) $r['ambulance_id'],
                'unit_code' => $r['unit_code'],
                'organization' => $r['unit_organization'],
                'latitude' => $hasGps ? (float) $r['ambulance_latitude'] : null,
                'longitude' => $hasGps ? (float) $r['ambulance_longitude'] : null,
                'location_age_seconds' => $r['location_age_seconds'] === null ? null : max(0, (int) $r['location_age_seconds']),
                'eta' => $r['eta_seconds'] === null ? null : [
                    'seconds' => (int) $r['eta_seconds'],
                    'distance_m' => (int) $r['eta_distance_m'],
                    'provider' => $r['eta_provider'],
                    'age_seconds' => max(0, (int) $r['eta_age_seconds']),
                    'destination' => $r['destination_kind'],
                ],
            ],
        ];
    }

    // Fleet across active organizations.
    $fleet = ['available' => 0, 'on_mission' => 0, 'returning' => 0, 'unavailable' => 0, 'total' => 0];
    $fleetRows = $pdo->query("
        SELECT a.status, COUNT(*) AS n
        FROM ambulances a JOIN organizations o ON o.id = a.organization_id
        WHERE a.deleted_at IS NULL AND o.deleted_at IS NULL AND o.status = 'active'
        GROUP BY a.status
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fleetRows as $f) {
        $n = (int) $f['n'];
        $fleet['total'] += $n;
        if ($f['status'] === 'available') $fleet['available'] += $n;
        elseif (in_array($f['status'], ['reserved', 'dispatched', 'on_scene', 'transporting'], true)) $fleet['on_mission'] += $n;
        elseif ($f['status'] === 'returning') $fleet['returning'] += $n;
        else $fleet['unavailable'] += $n;
    }

    echo json_encode(['success' => true, 'incidents' => $incidents, 'counts' => $counts, 'fleet' => $fleet]);
} catch (Throwable $e) {
    error_log('Incident monitor failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to load the live monitor.');
}
