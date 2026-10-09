<?php

/**
 * DASCARE duplicate-request detection ("dedup DSS").
 *
 * When several people report the same emergency, each report would otherwise
 * get its own DSS cycle and its own ambulance. Before DSS runs for a new
 * instant request, this looks for an active, un-merged request nearby
 * (radius) that was submitted recently (window, measured from that earlier
 * report). If the resulting cluster reaches min_requests, the new report is
 * linked to the earliest one (merged_into_request_id, status = duplicate)
 * and DSS is skipped for it.
 *
 * Safety valve: any merge can be reversed (citizen "not my emergency",
 * platform admin, or the org handling the primary) via dedupUnmergeRequest(),
 * which puts the report back to submitted and starts its own DSS cycle.
 *
 * Settings live in system_settings (edited at Technical → Configuration):
 *   dispatch.dedup.enabled, dispatch.dedup.radius_meters,
 *   dispatch.dedup.window_seconds, dispatch.dedup.min_requests
 */

require_once __DIR__ . '/dispatch_dss.php';

const DEDUP_CLOSED_STATUSES = ['completed', 'cancelled', 'rejected', 'duplicate', 'false_alarm'];
const DEDUP_DEFAULTS = ['enabled' => true, 'radius_meters' => 150, 'window_seconds' => 300, 'min_requests' => 2];
// Allowed ranges — shared with technical/configuration/index.php validation.
const DEDUP_LIMITS = [
    'radius_meters' => [25, 2000],
    'window_seconds' => [60, 3600],
    'min_requests' => [2, 20],
];
// Placeholder create.php stores when the requester typed nothing.
const DEDUP_EMPTY_DESCRIPTION = 'EMERGENCY REQUEST. No details provided — confirm with requester on contact.';

function dedupSettings(PDO $pdo): array
{
    $clamp = function (string $key, mixed $value): int {
        [$min, $max] = DEDUP_LIMITS[$key];
        $n = is_numeric($value) ? (int) $value : DEDUP_DEFAULTS[$key];
        return max($min, min($max, $n));
    };
    $enabled = dssJsonSetting($pdo, 'dispatch.dedup.enabled', DEDUP_DEFAULTS['enabled']);
    return [
        'enabled' => filter_var($enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? DEDUP_DEFAULTS['enabled'],
        'radius_meters' => $clamp('radius_meters', dssJsonSetting($pdo, 'dispatch.dedup.radius_meters', DEDUP_DEFAULTS['radius_meters'])),
        'window_seconds' => $clamp('window_seconds', dssJsonSetting($pdo, 'dispatch.dedup.window_seconds', DEDUP_DEFAULTS['window_seconds'])),
        'min_requests' => $clamp('min_requests', dssJsonSetting($pdo, 'dispatch.dedup.min_requests', DEDUP_DEFAULTS['min_requests'])),
    ];
}

/**
 * Run duplicate detection for a freshly committed request. Returns
 * ['merged' => false] or ['merged' => true, 'primary' => [...]].
 *
 * Serialised with a MariaDB named lock so two reports arriving at the same
 * moment can't both miss each other: whichever checks second always sees the
 * first, and the earliest report in a cluster is always the one kept. If the
 * lock can't be taken, dedup is skipped — dispatching twice is the safe
 * failure, never dispatching is not.
 */
function dedupCheckNewRequest(PDO $pdo, int $requestId): array
{
    $settings = dedupSettings($pdo);
    if (!$settings['enabled']) return ['merged' => false];

    $lock = $pdo->query("SELECT GET_LOCK('dascare_dispatch_dedup', 5)")->fetchColumn();
    if ((int) $lock !== 1) {
        error_log('Dedup lock unavailable; skipping duplicate check for request ' . $requestId);
        return ['merged' => false];
    }

    try {
        $self = dssLoadRequest($pdo, $requestId);
        if ($self['request_mode'] !== 'instant' || $self['merged_into_request_id'] !== null || in_array($self['status'], DEDUP_CLOSED_STATUSES, true)) {
            return ['merged' => false];
        }

        $lat = (float) $self['latitude'];
        $lon = (float) $self['longitude'];
        $radiusKm = $settings['radius_meters'] / 1000;
        // Cheap bounding box first (uses no index but trims the haversine
        // loop); exact distance is checked in PHP with dssHaversineKm().
        $latPad = $radiusKm / 111.0;
        $lonPad = $radiusKm / (111.0 * max(0.1, cos(deg2rad($lat))));
        $window = (int) $settings['window_seconds']; // clamped int, safe to interpolate

        // Only reports strictly EARLIER than this one (submitted_at, then id
        // as tie-break) are candidates, so the primary is always the oldest
        // and two same-second reports resolve the same way whichever checks
        // first. The window is measured from each earlier report's own
        // submitted_at, all on the DB clock.
        $stmt = $pdo->prepare("
            SELECT er.id, er.reference_number, er.latitude, er.longitude, er.submitted_at,
                   TIMESTAMPDIFF(SECOND, er.submitted_at, ?) AS gap_seconds
            FROM emergency_requests er
            WHERE er.request_mode = 'instant'
              AND er.merged_into_request_id IS NULL
              AND er.status NOT IN ('completed','cancelled','rejected','duplicate','false_alarm')
              AND er.submitted_at >= DATE_SUB(?, INTERVAL {$window} SECOND)
              AND (er.submitted_at < ? OR (er.submitted_at = ? AND er.id < ?))
              AND er.latitude BETWEEN ? AND ?
              AND er.longitude BETWEEN ? AND ?
            ORDER BY er.submitted_at ASC, er.id ASC
        ");
        $stmt->execute([
            $self['submitted_at'],
            $self['submitted_at'],
            $self['submitted_at'], $self['submitted_at'], $requestId,
            $lat - $latPad, $lat + $latPad, $lon - $lonPad, $lon + $lonPad,
        ]);

        $nearby = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $distanceM = dssHaversineKm($lat, $lon, (float) $row['latitude'], (float) $row['longitude']) * 1000;
            if ($distanceM <= $settings['radius_meters']) {
                $row['distance_m'] = $distanceM;
                $nearby[] = $row;
            }
        }
        if (!$nearby) return ['merged' => false];

        // Earliest report in the cluster is the primary. Rows are ordered
        // by submitted_at, so that's $nearby[0].
        $primary = $nearby[0];

        $childStmt = $pdo->prepare("SELECT COUNT(*) FROM emergency_requests WHERE merged_into_request_id = ? AND status = 'duplicate'");
        $childStmt->execute([(int) $primary['id']]);
        $clusterSize = count($nearby) + (int) $childStmt->fetchColumn() + 1; // + this report
        if ($clusterSize < $settings['min_requests']) return ['merged' => false];

        $distanceM = (int) round($primary['distance_m']);
        $gapSeconds = max(0, (int) $primary['gap_seconds']);

        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare("
                UPDATE emergency_requests
                SET merged_into_request_id = ?, status = 'duplicate'
                WHERE id = ? AND merged_into_request_id IS NULL AND status = ?
            ");
            $upd->execute([(int) $primary['id'], $requestId, $self['status']]);
            if ($upd->rowCount() !== 1) {
                $pdo->rollBack();
                return ['merged' => false];
            }

            $note = sprintf(
                'Linked to %s — same emergency already reported %s away, %s earlier. A unit is handled through that report.',
                $primary['reference_number'],
                $distanceM . ' m',
                dedupHumanSeconds($gapSeconds)
            );
            $pdo->prepare("
                INSERT INTO emergency_request_status_logs (emergency_request_id, old_status, new_status, changed_by_user_id, notes)
                VALUES (?, ?, 'duplicate', NULL, ?)
            ")->execute([$requestId, $self['status'], $note]);

            $pdo->prepare("
                INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, new_values, ip_address, user_agent)
                VALUES (NULL, NULL, 'dispatch.dedup_merged', 'emergency_request', ?, ?, ?, ?)
            ")->execute([
                $requestId,
                json_encode([
                    'merged_into_request_id' => (int) $primary['id'],
                    'distance_m' => $distanceM,
                    'gap_seconds' => $gapSeconds,
                    'cluster_size' => $clusterSize,
                    'settings' => $settings,
                ], JSON_UNESCAPED_SLASHES),
                $_SERVER['REMOTE_ADDR'] ?? null,
                mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        return [
            'merged' => true,
            'primary' => [
                'id' => (int) $primary['id'],
                'reference_number' => $primary['reference_number'],
                'distance_m' => $distanceM,
                'gap_seconds' => $gapSeconds,
            ],
        ];
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('dascare_dispatch_dedup')");
    }
}

/**
 * Reverse a merge: the report becomes its own incident again (status back to
 * submitted) and gets its own DSS cycle. $actor is a short label for the
 * status log ("requester", "platform admin", organization name).
 * Throws RuntimeException with a user-facing message when not allowed.
 */
function dedupUnmergeRequest(PDO $pdo, int $requestId, ?int $userId, ?int $organizationId, string $actor, string $reason = ''): array
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT er.id, er.status, er.merged_into_request_id, er.request_mode, p.reference_number AS primary_reference
            FROM emergency_requests er
            LEFT JOIN emergency_requests p ON p.id = er.merged_into_request_id
            WHERE er.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$request) throw new RuntimeException('Emergency request not found.');
        if ($request['merged_into_request_id'] === null || $request['status'] !== 'duplicate') {
            throw new RuntimeException('This report is not linked to another incident.');
        }

        $pdo->prepare("UPDATE emergency_requests SET merged_into_request_id = NULL, status = 'submitted' WHERE id = ?")
            ->execute([$requestId]);

        $reason = mb_substr(trim($reason), 0, 255);
        $note = 'Unlinked from ' . $request['primary_reference'] . ' by ' . $actor . ' — treated as a separate emergency.'
            . ($reason !== '' ? ' Reason: ' . $reason : '');
        $pdo->prepare("
            INSERT INTO emergency_request_status_logs (emergency_request_id, old_status, new_status, changed_by_user_id, notes)
            VALUES (?, 'duplicate', 'submitted', ?, ?)
        ")->execute([$requestId, $userId, $note]);

        $pdo->prepare("
            INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, 'dispatch.dedup_unmerged', 'emergency_request', ?, ?, ?, ?, ?)
        ")->execute([
            $userId, $organizationId, $requestId,
            json_encode(['merged_into_request_id' => (int) $request['merged_into_request_id'], 'status' => 'duplicate']),
            json_encode(['merged_into_request_id' => null, 'status' => 'submitted', 'actor' => $actor, 'reason' => $reason], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    // Same rule as create.php: the unmerge itself is committed first, and a
    // DSS problem must never undo it.
    $dssStarted = false;
    if ($request['request_mode'] === 'instant') {
        try {
            dssGenerateRecommendations($pdo, $requestId, $userId);
            $dssStarted = true;
        } catch (Throwable $e) {
            error_log('DSS after unmerge failed for request ' . $requestId . ': ' . $e->getMessage());
        }
    }
    return ['id' => $requestId, 'dss_started' => $dssStarted];
}

/**
 * Reports merged into $primaryId, with the signals a reviewer needs to judge
 * whether each merge is right. Warnings are computed on read against the
 * current settings.
 */
function dedupLinkedReports(PDO $pdo, int $primaryId): array
{
    $settings = dedupSettings($pdo);
    $primaryStmt = $pdo->prepare("SELECT id, latitude, longitude, submitted_at, description FROM emergency_requests WHERE id = ?");
    $primaryStmt->execute([$primaryId]);
    $primary = $primaryStmt->fetch(PDO::FETCH_ASSOC);
    if (!$primary) return [];

    $stmt = $pdo->prepare("
        SELECT er.id, er.reference_number, er.source, er.requester_name, er.requester_phone,
               er.description, er.address_text, er.barangay, er.landmark, er.latitude, er.longitude,
               er.submitted_at, TIMESTAMPDIFF(SECOND, ?, er.submitted_at) AS gap_seconds,
               (SELECT COUNT(*) FROM emergency_request_media m WHERE m.emergency_request_id = er.id) AS photo_count
        FROM emergency_requests er
        WHERE er.merged_into_request_id = ? AND er.status = 'duplicate'
        ORDER BY er.submitted_at ASC, er.id ASC
    ");
    $stmt->execute([$primary['submitted_at'], $primaryId]);

    $primaryPatients = dedupPatientCount((string) $primary['description']);
    $primaryDetail = dedupComparableText((string) $primary['description']);
    $reports = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $distanceM = (int) round(dssHaversineKm((float) $primary['latitude'], (float) $primary['longitude'], (float) $row['latitude'], (float) $row['longitude']) * 1000);
        $gap = max(0, (int) $row['gap_seconds']);
        $warnings = [];
        if ($distanceM > 0.7 * $settings['radius_meters']) {
            $warnings[] = ['key' => 'radius_edge', 'label' => 'Near radius edge', 'detail' => "{$distanceM} m apart (radius {$settings['radius_meters']} m)."];
        }
        if ($gap > 0.7 * $settings['window_seconds']) {
            $warnings[] = ['key' => 'late_window', 'label' => 'Late in window', 'detail' => 'Reported ' . dedupHumanSeconds($gap) . ' after the first report.'];
        }
        $patients = dedupPatientCount((string) $row['description']);
        $detail = dedupComparableText((string) $row['description']);
        if ($primaryPatients !== null && $patients !== null && $primaryPatients !== $patients) {
            $warnings[] = ['key' => 'different_details', 'label' => 'Different details', 'detail' => "Reports {$patients} patient(s); the first report says {$primaryPatients}."];
        } elseif ($primaryDetail !== '' && $detail !== '' && $primaryDetail !== $detail) {
            $warnings[] = ['key' => 'different_details', 'label' => 'Different details', 'detail' => 'Both reports describe the situation, and the descriptions differ.'];
        }

        $reports[] = [
            'id' => (int) $row['id'],
            'reference_number' => $row['reference_number'],
            'source' => $row['source'],
            'requester_name' => $row['requester_name'],
            'requester_phone' => $row['requester_phone'],
            'description' => $row['description'],
            'address_text' => $row['address_text'],
            'barangay' => $row['barangay'],
            'landmark' => $row['landmark'],
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
            'submitted_at' => $row['submitted_at'],
            'distance_m' => $distanceM,
            'gap_seconds' => $gap,
            'photo_count' => (int) $row['photo_count'],
            'warnings' => $warnings,
        ];
    }
    return $reports;
}

/** Count of reports linked to each primary id, for list badges. */
function dedupLinkedCounts(PDO $pdo, array $primaryIds): array
{
    $primaryIds = array_values(array_unique(array_filter(array_map('intval', $primaryIds))));
    if (!$primaryIds) return [];
    $ph = implode(',', array_fill(0, count($primaryIds), '?'));
    $stmt = $pdo->prepare("SELECT merged_into_request_id, COUNT(*) AS n FROM emergency_requests WHERE status = 'duplicate' AND merged_into_request_id IN ($ph) GROUP BY merged_into_request_id");
    $stmt->execute($primaryIds);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $out[(int) $row['merged_into_request_id']] = (int) $row['n'];
    return $out;
}

// "Patients involved: 2" is written by EmergencyRequestForm's quick details.
function dedupPatientCount(string $description): ?int
{
    return preg_match('/Patients involved:\s*(\d+)/i', $description, $m) ? (int) $m[1] : null;
}

// Free-text part of a description, normalised for comparison. Placeholders
// and the quick-detail summary line don't count as "describing" anything.
function dedupComparableText(string $description): string
{
    $text = trim($description);
    if ($text === DEDUP_EMPTY_DESCRIPTION) return '';
    $text = preg_replace('/^(Patients involved|Conscious|Breathing):[^\n]*\n*/mi', '', $text);
    return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $text)));
}

function dedupHumanSeconds(int $seconds): string
{
    if ($seconds < 60) return $seconds . ' sec';
    $minutes = intdiv($seconds, 60);
    return $minutes . ' min' . ($seconds % 60 ? ' ' . ($seconds % 60) . ' sec' : '');
}
