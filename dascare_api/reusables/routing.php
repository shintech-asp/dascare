<?php
require_once __DIR__ . '/realtime.php';

/**
 * Road route + ETA for an active mission (live updates R5).
 *
 * The ambulance's GPS (organizations/tracking/update.php) and mission status
 * changes call routingRefresh(); the result is stored in mission_routes and
 * published as `route.updated` to the org channel and the request's channel.
 * Screens draw the road line and the ETA from it.
 *
 * Provider: TomTom Routing API with live traffic, when
 * dascare_api/config/tomtom.json holds a key ({"key": "..."}). Without a key,
 * past the daily cap, or when TomTom fails, a straight-line estimate is used
 * (distance x 1.4 road factor at ~30 km/h) and labelled as such.
 *
 * Calls are rationed (free tier ~2,500/day): a stored route is reused until
 * the ambulance has moved ROUTING_RECALC_METERS from where it was computed,
 * it is ROUTING_RECALC_SECONDS old, or the destination changed.
 *
 * Destination: the incident while the unit is assigned / acknowledged /
 * responding; the receiving facility (latest accepted or pending handoff with
 * coordinates) while transporting; none on scene or after.
 */

const ROUTING_CONFIG_FILE = __DIR__ . '/../config/tomtom.json';
const ROUTING_RECALC_METERS = 150;
const ROUTING_RECALC_SECONDS = 60;
const ROUTING_DAILY_LIMIT = 2000;
const ROUTING_HTTP_TIMEOUT = 4;
const ROUTING_ESTIMATE_ROAD_FACTOR = 1.4;
const ROUTING_ESTIMATE_SPEED_KPH = 30;

function routingConfig(): ?array
{
    // Tests can supply a config: $GLOBALS['DASCARE_ROUTING_CONFIG'] = ['key' => '...']
    if (array_key_exists('DASCARE_ROUTING_CONFIG', $GLOBALS)) return $GLOBALS['DASCARE_ROUTING_CONFIG'];
    static $config = false;
    if ($config !== false) return $config;
    $config = null;
    if (!is_readable(ROUTING_CONFIG_FILE)) return null;
    $json = json_decode((string) file_get_contents(ROUTING_CONFIG_FILE), true);
    $key = is_array($json) ? trim((string) ($json['key'] ?? '')) : '';
    if ($key === '' || str_contains($key, 'PASTE-') || !preg_match('/^[A-Za-z0-9]{16,64}$/', $key)) return null;
    $config = ['key' => $key];
    return $config;
}

/** Where this mission's ambulance is heading right now, or null. */
function routingDestination(PDO $pdo, int $assignmentId): ?array
{
    $stmt = $pdo->prepare("
        SELECT da.assignment_status, er.latitude, er.longitude, er.address_text
        FROM dispatch_assignments da
        INNER JOIN emergency_requests er ON er.id = da.emergency_request_id
        WHERE da.id = ?
    ");
    $stmt->execute([$assignmentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    if (in_array($row['assignment_status'], ['assigned', 'acknowledged', 'responding'], true)) {
        if ($row['latitude'] === null || $row['longitude'] === null) return null;
        return ['kind' => 'incident', 'latitude' => (float) $row['latitude'], 'longitude' => (float) $row['longitude'], 'label' => 'Incident location'];
    }
    if ($row['assignment_status'] === 'transporting') {
        $fac = $pdo->prepare("
            SELECT mf.name, mf.latitude, mf.longitude
            FROM patient_handoffs ph
            INNER JOIN medical_facilities mf ON mf.id = ph.medical_facility_id
            WHERE ph.dispatch_assignment_id = ? AND ph.status IN ('accepted', 'pending')
              AND mf.latitude IS NOT NULL AND mf.longitude IS NOT NULL
            ORDER BY ph.status = 'accepted' DESC, ph.id DESC
            LIMIT 1
        ");
        $fac->execute([$assignmentId]);
        $f = $fac->fetch(PDO::FETCH_ASSOC);
        if ($f) return ['kind' => 'facility', 'latitude' => (float) $f['latitude'], 'longitude' => (float) $f['longitude'], 'label' => mb_substr($f['name'], 0, 160)];
    }
    return null;
}

function routingDistanceM(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return 2 * $r * asin(min(1.0, sqrt($a)));
}

/**
 * Recompute (if due) and store the route for an assignment from the given
 * ambulance position. Returns the public route array, or null when there is
 * no destination (and clears any stored route). $published = whether a new
 * route was stored this call (worth announcing).
 */
function routingRefresh(PDO $pdo, int $assignmentId, float $originLat, float $originLng, ?bool &$published = null): ?array
{
    $published = false;
    $dest = routingDestination($pdo, $assignmentId);
    if (!$dest) {
        $del = $pdo->prepare('DELETE FROM mission_routes WHERE dispatch_assignment_id = ?');
        $del->execute([$assignmentId]);
        $published = $del->rowCount() > 0;
        return null;
    }

    $stmt = $pdo->prepare('SELECT *, TIMESTAMPDIFF(SECOND, computed_at, NOW()) AS age_s FROM mission_routes WHERE dispatch_assignment_id = ?');
    $stmt->execute([$assignmentId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($current) {
        $sameDest = routingDistanceM((float) $current['destination_latitude'], (float) $current['destination_longitude'], $dest['latitude'], $dest['longitude']) < 25;
        $moved = routingDistanceM((float) $current['origin_latitude'], (float) $current['origin_longitude'], $originLat, $originLng);
        $fresh = (int) $current['age_s'] < ROUTING_RECALC_SECONDS;
        // A road route is reused while it's fresh and the unit hasn't gone far.
        // An estimate is redone whenever the unit moves (it's free), and is
        // upgraded to a road route once it's stale if a TomTom key is set
        // (covers a key added mid-mission or a brief TomTom outage).
        $reuse = $sameDest && $moved < ROUTING_RECALC_METERS && (
            ($current['provider'] === 'tomtom' && $fresh)
            || ($current['provider'] === 'estimate' && $moved < 10 && ($fresh || routingConfig() === null))
        );
        if ($reuse) return routingFormat($current);
    }

    $route = routingFetchTomTom($pdo, $originLat, $originLng, $dest['latitude'], $dest['longitude'])
        ?? routingEstimate($originLat, $originLng, $dest['latitude'], $dest['longitude']);

    $pdo->prepare("
        INSERT INTO mission_routes
            (dispatch_assignment_id, provider, destination_kind, destination_label, origin_latitude, origin_longitude,
             destination_latitude, destination_longitude, distance_m, duration_s, traffic_delay_s, polyline, computed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE provider = VALUES(provider), destination_kind = VALUES(destination_kind), destination_label = VALUES(destination_label),
            origin_latitude = VALUES(origin_latitude), origin_longitude = VALUES(origin_longitude),
            destination_latitude = VALUES(destination_latitude), destination_longitude = VALUES(destination_longitude),
            distance_m = VALUES(distance_m), duration_s = VALUES(duration_s), traffic_delay_s = VALUES(traffic_delay_s),
            polyline = VALUES(polyline), computed_at = NOW()
    ")->execute([
        $assignmentId, $route['provider'], $dest['kind'], $dest['label'], $originLat, $originLng,
        $dest['latitude'], $dest['longitude'], $route['distance_m'], $route['duration_s'], $route['traffic_delay_s'], $route['polyline'],
    ]);
    $published = true;
    return routingForAssignment($pdo, $assignmentId);
}

/** The stored route for an assignment, formatted for screens (or null). */
function routingForAssignment(PDO $pdo, int $assignmentId): ?array
{
    $stmt = $pdo->prepare('SELECT *, TIMESTAMPDIFF(SECOND, computed_at, NOW()) AS age_s FROM mission_routes WHERE dispatch_assignment_id = ?');
    $stmt->execute([$assignmentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? routingFormat($row) : null;
}

function routingFormat(array $row): array
{
    return [
        'assignment_id' => (int) $row['dispatch_assignment_id'],
        'provider' => $row['provider'],
        'traffic' => $row['provider'] === 'tomtom',
        'distance_m' => (int) $row['distance_m'],
        'duration_s' => (int) $row['duration_s'],
        'traffic_delay_s' => (int) $row['traffic_delay_s'],
        // Seconds since it was computed (DB clock) — screens count the ETA down from here.
        'age_s' => max(0, (int) ($row['age_s'] ?? 0)),
        'polyline' => $row['polyline'],
        'origin' => ['latitude' => (float) $row['origin_latitude'], 'longitude' => (float) $row['origin_longitude']],
        'destination' => [
            'kind' => $row['destination_kind'],
            'label' => $row['destination_label'],
            'latitude' => (float) $row['destination_latitude'],
            'longitude' => (float) $row['destination_longitude'],
        ],
    ];
}

/** TomTom calculateRoute with live traffic; null when unavailable (caller falls back). */
function routingFetchTomTom(PDO $pdo, float $oLat, float $oLng, float $dLat, float $dLng): ?array
{
    $config = routingConfig();
    if (!$config) return null;

    $usage = $pdo->prepare("SELECT calls FROM routing_api_usage WHERE usage_date = CURDATE() AND provider = 'tomtom'");
    $usage->execute();
    if ((int) $usage->fetchColumn() >= ROUTING_DAILY_LIMIT) return null;

    $url = sprintf(
        'https://api.tomtom.com/routing/1/calculateRoute/%F,%F:%F,%F/json?%s',
        $oLat, $oLng, $dLat, $dLng,
        http_build_query(['key' => $config['key'], 'traffic' => 'true', 'travelMode' => 'car', 'routeType' => 'fastest'])
    );
    // Tests can swap the transport: $GLOBALS['DASCARE_ROUTING_FETCH'] = fn($url) => [status, body]
    if (isset($GLOBALS['DASCARE_ROUTING_FETCH']) && is_callable($GLOBALS['DASCARE_ROUTING_FETCH'])) {
        [$status, $body] = ($GLOBALS['DASCARE_ROUTING_FETCH'])($url);
    } else {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => ROUTING_HTTP_TIMEOUT, CURLOPT_CONNECTTIMEOUT => 2]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }

    $json = $status === 200 ? json_decode((string) $body, true) : null;
    $summary = $json['routes'][0]['summary'] ?? null;
    $ok = is_array($summary) && isset($summary['lengthInMeters'], $summary['travelTimeInSeconds']);
    $pdo->prepare("
        INSERT INTO routing_api_usage (usage_date, provider, calls, failures) VALUES (CURDATE(), 'tomtom', 1, ?)
        ON DUPLICATE KEY UPDATE calls = calls + 1, failures = failures + VALUES(failures)
    ")->execute([$ok ? 0 : 1]);
    if (!$ok) {
        error_log("Routing: TomTom request failed ($status): " . substr((string) $body, 0, 200));
        return null;
    }

    $points = [];
    foreach ($json['routes'][0]['legs'] ?? [] as $leg) {
        foreach ($leg['points'] ?? [] as $p) $points[] = [(float) $p['latitude'], (float) $p['longitude']];
    }
    return [
        'provider' => 'tomtom',
        'distance_m' => (int) $summary['lengthInMeters'],
        'duration_s' => (int) $summary['travelTimeInSeconds'],
        'traffic_delay_s' => (int) ($summary['trafficDelayInSeconds'] ?? 0),
        'polyline' => $points ? routingEncodePolyline($points) : null,
    ];
}

/** No provider: straight line x road factor at city speed, clearly labelled as an estimate. */
function routingEstimate(float $oLat, float $oLng, float $dLat, float $dLng): array
{
    $roadM = routingDistanceM($oLat, $oLng, $dLat, $dLng) * ROUTING_ESTIMATE_ROAD_FACTOR;
    return [
        'provider' => 'estimate',
        'distance_m' => (int) round($roadM),
        'duration_s' => (int) round($roadM / (ROUTING_ESTIMATE_SPEED_KPH * 1000 / 3600)),
        'traffic_delay_s' => 0,
        'polyline' => null,
    ];
}

/** Google encoded polyline (precision 5) — compact enough to send in a live event. */
function routingEncodePolyline(array $points): string
{
    $out = '';
    $prevLat = 0;
    $prevLng = 0;
    foreach ($points as [$lat, $lng]) {
        $iLat = (int) round($lat * 1e5);
        $iLng = (int) round($lng * 1e5);
        foreach ([$iLat - $prevLat, $iLng - $prevLng] as $delta) {
            $v = $delta < 0 ? ~($delta << 1) : ($delta << 1);
            while ($v >= 0x20) {
                $out .= chr((0x20 | ($v & 0x1f)) + 63);
                $v >>= 5;
            }
            $out .= chr($v + 63);
        }
        $prevLat = $iLat;
        $prevLng = $iLng;
    }
    return $out;
}

/**
 * After a GPS ping or a mission status change: refresh the route from the
 * ambulance's last fix and announce it if it changed. Runs at shutdown so the
 * crew's request isn't held up by the routing call more than necessary.
 */
function routingRefreshAndAnnounceLater(PDO $pdo, int $assignmentId): void
{
    register_shutdown_function(static function () use ($pdo, $assignmentId) {
        try {
            if ($pdo->inTransaction()) return;
            $stmt = $pdo->prepare("
                SELECT a.last_latitude, a.last_longitude, da.organization_id, da.emergency_request_id
                FROM dispatch_assignments da INNER JOIN ambulances a ON a.id = da.ambulance_id
                WHERE da.id = ?
            ");
            $stmt->execute([$assignmentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return;
            if ($row['last_latitude'] === null || $row['last_longitude'] === null) {
                $route = null;
                $published = false;
            } else {
                $route = routingRefresh($pdo, $assignmentId, (float) $row['last_latitude'], (float) $row['last_longitude'], $published);
            }
            if (!$published) return;
            $payload = ['assignment_id' => $assignmentId, 'request_id' => (int) $row['emergency_request_id'], 'route' => $route];
            realtimeQueue($pdo, realtimeChannel('org', (int) $row['organization_id']), 'route.updated', $payload);
            realtimeQueue($pdo, realtimeChannel('request', (int) $row['emergency_request_id']), 'route.updated', $payload);
            realtimeFlush($pdo);
        } catch (Throwable $e) {
            error_log('Routing refresh failed: ' . $e->getMessage());
        }
    });
}
