<?php
require_once __DIR__ . '/realtime.php';

/**
 * DASCARE Phase 7 dispatch/DSS foundation.
 *
 * This service deliberately stops before ambulance/crew assignment. It ranks
 * dispatchable organizations/resources and manages one timed organization
 * offer at a time. Phase 8 converts an accepted offer into a locked resource
 * assignment transaction.
 */

function dssJsonSetting(PDO $pdo, string $key, mixed $fallback): mixed
{
    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $raw = $stmt->fetchColumn();
    if ($raw === false) return $fallback;
    $decoded = json_decode((string) $raw, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
}

function dssWeights(PDO $pdo): array
{
    $fallback = ['distance' => 0.40, 'availability' => 0.30, 'capability' => 0.20, 'workload' => 0.10];
    $value = dssJsonSetting($pdo, 'dispatch.dss.weights', $fallback);
    if (!is_array($value)) return $fallback;
    $weights = [];
    foreach ($fallback as $key => $default) {
        $weights[$key] = max(0.0, (float) ($value[$key] ?? $default));
    }
    $sum = array_sum($weights);
    if ($sum <= 0) return $fallback;
    foreach ($weights as $key => $weight) $weights[$key] = $weight / $sum;
    return $weights;
}

function dssOfferTimeoutSeconds(PDO $pdo): int
{
    $value = dssJsonSetting($pdo, 'dispatch.offer_timeout_seconds', 90);
    $seconds = (int) $value;
    return max(30, min(300, $seconds ?: 90));
}

function dssHaversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earth = 6371.0;
    $latDelta = deg2rad($lat2 - $lat1);
    $lonDelta = deg2rad($lon2 - $lon1);
    $a = sin($latDelta / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;
    return $earth * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
}

function dssCapabilityScore(string $ambulanceType, string $severity, string $categoryCode): float
{
    $severity = strtolower($severity);
    $categoryCode = strtolower($categoryCode);
    $base = match ($ambulanceType) {
        'advanced_life_support' => 1.00,
        'rescue_unit' => 0.82,
        'basic_life_support' => 0.78,
        'patient_transport' => 0.48,
        default => 0.50,
    };

    if (in_array($severity, ['critical', 'high'], true)) {
        $base += match ($ambulanceType) {
            'advanced_life_support' => 0.00,
            'rescue_unit' => -0.05,
            'basic_life_support' => -0.18,
            'patient_transport' => -0.38,
            default => -0.25,
        };
    }

    if (in_array($categoryCode, ['fire_related', 'disaster', 'trauma', 'road_accident'], true) && $ambulanceType === 'rescue_unit') {
        $base += 0.15;
    }
    if (in_array($categoryCode, ['cardiac', 'maternal', 'medical'], true) && $ambulanceType === 'advanced_life_support') {
        $base += 0.08;
    }

    return max(0.10, min(1.00, $base));
}

function dssLoadRequest(PDO $pdo, int $requestId, bool $forUpdate = false): array
{
    $sql = "
        SELECT er.*, ec.code AS category_code, ec.name AS category_name
        FROM emergency_requests er
        INNER JOIN emergency_categories ec ON ec.id = er.emergency_category_id
        WHERE er.id = ?
        LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) throw new RuntimeException('Emergency request not found.');
    return $request;
}

function dssBuildCandidates(PDO $pdo, array $request, array $weights): array
{
    $unitStmt = $pdo->query("
        SELECT
            a.id AS ambulance_id, a.organization_id, a.unit_code, a.ambulance_type,
            a.last_latitude, a.last_longitude,
            o.name AS organization_name, o.organization_type,
            o.latitude AS organization_latitude, o.longitude AS organization_longitude
        FROM ambulances a
        INNER JOIN organizations o ON o.id = a.organization_id
        INNER JOIN ambulance_readiness_checks rc ON rc.id = (
            SELECT rc2.id FROM ambulance_readiness_checks rc2
            WHERE rc2.ambulance_id = a.id
            ORDER BY rc2.checked_at DESC, rc2.id DESC LIMIT 1
        )
        WHERE a.deleted_at IS NULL
          AND o.deleted_at IS NULL
          AND o.status = 'active'
          AND a.status = 'available'
          AND rc.overall_status = 'ready'
          AND (a.registration_expiry IS NULL OR a.registration_expiry >= CURDATE())
          AND (a.inspection_expiry IS NULL OR a.inspection_expiry >= CURDATE())
          AND NOT EXISTS (
              SELECT 1 FROM ambulance_maintenance_records am
              WHERE am.ambulance_id = a.id AND am.status = 'in_progress'
          )
        ORDER BY a.organization_id, a.id
    ");
    $units = $unitStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$units) return [];

    $areaStmt = $pdo->query('SELECT organization_id, barangay FROM organization_service_areas');
    $areas = [];
    foreach ($areaStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $areas[(int) $row['organization_id']][] = mb_strtolower(trim((string) $row['barangay']));
    }

    $workStmt = $pdo->query("
        SELECT organization_id, COUNT(*) AS active_count
        FROM dispatch_assignments
        WHERE assignment_status IN ('assigned','acknowledged','responding','on_scene','transporting')
        GROUP BY organization_id
    ");
    $workload = [];
    foreach ($workStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $workload[(int) $row['organization_id']] = (int) $row['active_count'];

    $acceptedStmt = $pdo->query("
        SELECT io.organization_id, COUNT(*) AS active_count
        FROM incident_offers io
        INNER JOIN emergency_requests er ON er.id = io.emergency_request_id
        WHERE io.offer_status = 'accepted'
          AND er.status NOT IN ('completed','cancelled','rejected','duplicate','false_alarm')
        GROUP BY io.organization_id
    ");
    foreach ($acceptedStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $orgId = (int) $row['organization_id'];
        $workload[$orgId] = ($workload[$orgId] ?? 0) + (int) $row['active_count'];
    }

    $byOrg = [];
    foreach ($units as $unit) {
        $orgId = (int) $unit['organization_id'];
        $targetBarangay = mb_strtolower(trim((string) ($request['barangay'] ?? '')));
        $orgAreas = $areas[$orgId] ?? [];
        if ($targetBarangay !== '' && $unit['organization_type'] !== 'city_rescue' && $orgAreas && !in_array($targetBarangay, $orgAreas, true)) {
            continue;
        }

        $lat = $unit['last_latitude'] !== null ? (float) $unit['last_latitude'] : (float) ($unit['organization_latitude'] ?? 0);
        $lon = $unit['last_longitude'] !== null ? (float) $unit['last_longitude'] : (float) ($unit['organization_longitude'] ?? 0);
        if (!$lat || !$lon) continue;

        $distanceKm = dssHaversineKm((float) $request['latitude'], (float) $request['longitude'], $lat, $lon);
        $distanceScore = max(0.0, 1.0 - min($distanceKm, 20.0) / 20.0);
        $capabilityScore = dssCapabilityScore((string) $unit['ambulance_type'], (string) $request['severity'], (string) $request['category_code']);

        if (!isset($byOrg[$orgId])) {
            $byOrg[$orgId] = [
                'organization_id' => $orgId,
                'organization_name' => $unit['organization_name'],
                'eligible_units' => [],
            ];
        }
        $byOrg[$orgId]['eligible_units'][] = [
            'ambulance_id' => (int) $unit['ambulance_id'],
            'unit_code' => $unit['unit_code'],
            'ambulance_type' => $unit['ambulance_type'],
            'distance_km' => $distanceKm,
            'distance_score' => $distanceScore,
            'capability_score' => $capabilityScore,
        ];
    }

    $candidates = [];
    foreach ($byOrg as $orgId => $org) {
        $availableCount = count($org['eligible_units']);
        $availabilityScore = min(1.0, 0.75 + 0.125 * max(0, $availableCount - 1));
        $activeWorkload = $workload[$orgId] ?? 0;
        $workloadScore = 1.0 / (1.0 + $activeWorkload);

        $best = null;
        $bestScore = -1;
        foreach ($org['eligible_units'] as $unit) {
            $score =
                ($unit['distance_score'] * $weights['distance']) +
                ($availabilityScore * $weights['availability']) +
                ($unit['capability_score'] * $weights['capability']) +
                ($workloadScore * $weights['workload']);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $unit;
            }
        }
        if (!$best) continue;

        $total = round($bestScore * 100, 2);
        $explanation = sprintf(
            '%s is %.1f km away with %d ready unit%s; recommended unit %s (%s). Active workload: %d.',
            $org['organization_name'],
            $best['distance_km'],
            $availableCount,
            $availableCount === 1 ? '' : 's',
            $best['unit_code'],
            str_replace('_', ' ', $best['ambulance_type']),
            $activeWorkload
        );
        $candidates[] = [
            'organization_id' => $orgId,
            'organization_name' => $org['organization_name'],
            'recommended_ambulance_id' => $best['ambulance_id'],
            'recommended_unit_code' => $best['unit_code'],
            'distance_km' => round($best['distance_km'], 2),
            'distance_score' => round($best['distance_score'] * 100, 2),
            'availability_score' => round($availabilityScore * 100, 2),
            'capability_score' => round($best['capability_score'] * 100, 2),
            'workload_score' => round($workloadScore * 100, 2),
            'total_score' => $total,
            'explanation' => $explanation,
        ];
    }

    usort($candidates, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
    foreach ($candidates as $index => &$candidate) $candidate['rank_position'] = $index + 1;
    unset($candidate);
    return $candidates;
}

function dssNotifyOrganization(PDO $pdo, int $organizationId, int $offerId, array $request, int $timeoutSeconds): void
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id
        FROM organization_members om
        INNER JOIN users u ON u.id = om.user_id AND u.deleted_at IS NULL AND u.account_status = 'active'
        INNER JOIN user_roles ur ON ur.user_id = u.id
        LEFT JOIN org_member_roles omr ON omr.organization_member_id = om.id
        LEFT JOIN org_roles r ON r.id = omr.role_id AND r.deleted_at IS NULL
        LEFT JOIN org_role_permissions orp ON orp.role_id = r.id
        LEFT JOIN rbac_permissions p ON p.id = orp.permission_id
        LEFT JOIN rbac_modules m ON m.id = p.module_id
        WHERE om.organization_id = ?
          AND om.deleted_at IS NULL
          AND om.membership_status = 'active'
          AND (
            ur.role = 'organization_admin'
            OR CONCAT(m.module_key, '.', p.resource, '.', p.action) IN ('dispatch.dispatch_assignments.read','dispatch.dispatch_assignments.approve')
          )
    ");
    $stmt->execute([$organizationId]);
    $userIds = array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    if (!$userIds) return;

    $title = 'New Incident Offer';
    $message = sprintf('%s • %s emergency in %s. Respond within %d seconds.', $request['reference_number'], ucfirst($request['severity']), $request['barangay'] ?: 'Dasmariñas', $timeoutSeconds);
    $notify = $pdo->prepare("
        INSERT INTO notifications (user_id, notification_type, title, message, related_type, related_id, dedup_key)
        VALUES (?, 'incident_offer', ?, ?, 'incident_offer', ?, ?)
        ON DUPLICATE KEY UPDATE title = VALUES(title), message = VALUES(message), read_at = NULL, created_at = CURRENT_TIMESTAMP
    ");
    foreach ($userIds as $userId) {
        $notify->execute([$userId, $title, $message, $offerId, 'incident_offer:' . $offerId . ':user:' . $userId]);
    }
    realtimeNotifyUsers($pdo, $userIds);
}

function dssCreateOfferForRecommendation(PDO $pdo, array $recommendation, array $request): array
{
    $timeout = dssOfferTimeoutSeconds($pdo);

    // Keep offered_at, expires_at, and timeout reconciliation on the SAME
    // database clock. The old implementation calculated expires_at with
    // PHP time() but later compared it against MySQL NOW(). On machines
    // where PHP was UTC while MySQL/XAMPP used Asia/Manila, a brand-new
    // 90-second offer was stored roughly eight hours in the past and was
    // immediately marked timed_out. $timeout is clamped to 30..300 above,
    // so interpolating the integer into INTERVAL is safe.
    $stmt = $pdo->prepare("
        INSERT INTO incident_offers
            (emergency_request_id, dss_run_id, recommendation_id, organization_id, offer_status, offered_at, expires_at)
        VALUES (?, ?, ?, ?, 'sent', NOW(), DATE_ADD(NOW(), INTERVAL {$timeout} SECOND))
    ");
    $stmt->execute([
        (int) $request['id'],
        (int) $recommendation['run_id'],
        (int) $recommendation['id'],
        (int) $recommendation['organization_id'],
    ]);
    $offerId = (int) $pdo->lastInsertId();

    // Return the exact timestamp MySQL wrote instead of reconstructing it
    // with PHP's potentially different timezone.
    $expiresStmt = $pdo->prepare('SELECT expires_at FROM incident_offers WHERE id = ? LIMIT 1');
    $expiresStmt->execute([$offerId]);
    $expiresAt = (string) ($expiresStmt->fetchColumn() ?: '');
    dssNotifyOrganization($pdo, (int) $recommendation['organization_id'], $offerId, $request, $timeout);
    realtimeRequestChanged($pdo, (int) $request['id'], 'offer.created', ['organization_id' => (int) $recommendation['organization_id']]);
    return ['id' => $offerId, 'expires_at' => $expiresAt, 'organization_id' => (int) $recommendation['organization_id']];
}

function dssEscalateNextOffer(PDO $pdo, int $requestId): ?array
{
    $request = dssLoadRequest($pdo, $requestId, true);
    $accepted = $pdo->prepare("SELECT 1 FROM incident_offers WHERE emergency_request_id = ? AND offer_status = 'accepted' LIMIT 1");
    $accepted->execute([$requestId]);
    if ($accepted->fetchColumn()) return null;

    $runStmt = $pdo->prepare("SELECT id FROM dss_recommendation_runs WHERE emergency_request_id = ? AND run_status = 'active' ORDER BY id DESC LIMIT 1");
    $runStmt->execute([$requestId]);
    $runId = (int) ($runStmt->fetchColumn() ?: 0);
    if (!$runId) return null;

    $next = $pdo->prepare("
        SELECT dr.*
        FROM dss_recommendations dr
        WHERE dr.run_id = ?
          AND NOT EXISTS (
            SELECT 1 FROM incident_offers io
            WHERE io.dss_run_id = dr.run_id AND io.recommendation_id = dr.id
          )
        ORDER BY dr.rank_position ASC
        LIMIT 1
        FOR UPDATE
    ");
    $next->execute([$runId]);
    $recommendation = $next->fetch(PDO::FETCH_ASSOC);
    if (!$recommendation) {
        $pdo->prepare("UPDATE dss_recommendation_runs SET run_status = 'exhausted' WHERE id = ? AND run_status = 'active'")->execute([$runId]);
        return null;
    }
    return dssCreateOfferForRecommendation($pdo, $recommendation, $request);
}

function dssSyncExpiredOffers(PDO $pdo, ?int $requestId = null): void
{
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $sql = "SELECT id, emergency_request_id, organization_id FROM incident_offers WHERE offer_status = 'sent' AND expires_at <= NOW()";
        $params = [];
        if ($requestId !== null) { $sql .= ' AND emergency_request_id = ?'; $params[] = $requestId; }
        $sql .= ' FOR UPDATE';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $requests = [];
        $update = $pdo->prepare("UPDATE incident_offers SET offer_status = 'timed_out', responded_at = NOW(), response_note = 'Offer window expired.' WHERE id = ? AND offer_status = 'sent'");
        foreach ($rows as $row) {
            $update->execute([(int) $row['id']]);
            $requests[(int) $row['emergency_request_id']] = true;
            realtimeRequestChanged($pdo, (int) $row['emergency_request_id'], 'offer.expired', [], [(int) $row['organization_id']]);
        }
        foreach (array_keys($requests) as $id) dssEscalateNextOffer($pdo, $id);
        if ($ownTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function dssGenerateRecommendations(PDO $pdo, int $requestId, ?int $generatedByUserId = null): array
{
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $request = dssLoadRequest($pdo, $requestId, true);
        if ($request['request_mode'] !== 'instant') throw new RuntimeException('DSS incident offers are currently limited to immediate emergency requests.');
        if (in_array($request['status'], ['assigned','acknowledged','responding','on_scene','transporting','completed','cancelled','rejected','duplicate','false_alarm'], true)) {
            throw new RuntimeException('This request is no longer eligible for a new DSS offer cycle.');
        }
        $acceptedStmt = $pdo->prepare("SELECT 1 FROM incident_offers WHERE emergency_request_id = ? AND offer_status = 'accepted' LIMIT 1");
        $acceptedStmt->execute([$requestId]);
        if ($acceptedStmt->fetchColumn()) throw new RuntimeException('An organization has already accepted this incident.');

        $supersededOrgs = $pdo->prepare("SELECT organization_id FROM incident_offers WHERE emergency_request_id = ? AND offer_status = 'sent'");
        $supersededOrgs->execute([$requestId]);
        realtimeRequestChanged($pdo, $requestId, 'request.updated', [], array_map('intval', $supersededOrgs->fetchAll(PDO::FETCH_COLUMN)));
        $pdo->prepare("UPDATE incident_offers SET offer_status = 'cancelled', responded_at = NOW(), response_note = 'Superseded by a new DSS run.' WHERE emergency_request_id = ? AND offer_status = 'sent'")->execute([$requestId]);
        $pdo->prepare("UPDATE dss_recommendation_runs SET run_status = 'superseded' WHERE emergency_request_id = ? AND run_status IN ('active','exhausted')")->execute([$requestId]);

        $weights = dssWeights($pdo);
        $candidates = dssBuildCandidates($pdo, $request, $weights);
        $runStmt = $pdo->prepare("INSERT INTO dss_recommendation_runs (emergency_request_id, generated_by_user_id, weights_json, candidate_count, run_status) VALUES (?, ?, ?, ?, ?)");
        $runStmt->execute([$requestId, $generatedByUserId ?: null, json_encode($weights), count($candidates), $candidates ? 'active' : 'exhausted']);
        $runId = (int) $pdo->lastInsertId();

        $insert = $pdo->prepare("
            INSERT INTO dss_recommendations
                (run_id, emergency_request_id, organization_id, recommended_ambulance_id, rank_position,
                 distance_km, distance_score, availability_score, capability_score, workload_score, total_score, explanation)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stored = [];
        foreach ($candidates as $candidate) {
            $insert->execute([
                $runId, $requestId, $candidate['organization_id'], $candidate['recommended_ambulance_id'], $candidate['rank_position'],
                $candidate['distance_km'], $candidate['distance_score'], $candidate['availability_score'], $candidate['capability_score'],
                $candidate['workload_score'], $candidate['total_score'], $candidate['explanation'],
            ]);
            $candidate['id'] = (int) $pdo->lastInsertId();
            $candidate['run_id'] = $runId;
            $stored[] = $candidate;
        }

        if ($request['status'] === 'submitted') {
            $pdo->prepare("UPDATE emergency_requests SET status = 'validating' WHERE id = ? AND status = 'submitted'")->execute([$requestId]);
            $pdo->prepare("INSERT INTO emergency_request_status_logs (emergency_request_id, old_status, new_status, changed_by_user_id, notes) VALUES (?, 'submitted', 'validating', ?, 'DSS resource screening started.')")
                ->execute([$requestId, $generatedByUserId ?: null]);
        }

        $offer = null;
        if ($stored) $offer = dssCreateOfferForRecommendation($pdo, $stored[0], $request);

        if ($generatedByUserId) {
            $audit = $pdo->prepare("INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, new_values, ip_address, user_agent) VALUES (?, NULL, 'dispatch.dss_generated', 'emergency_request', ?, ?, ?, ?)");
            $audit->execute([
                $generatedByUserId, $requestId,
                json_encode(['run_id' => $runId, 'candidate_count' => count($stored), 'top_offer' => $offer], JSON_UNESCAPED_SLASHES),
                $_SERVER['REMOTE_ADDR'] ?? null,
                mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        }

        if ($ownTransaction) $pdo->commit();
        return ['run_id' => $runId, 'candidates' => $stored, 'offer' => $offer];
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
