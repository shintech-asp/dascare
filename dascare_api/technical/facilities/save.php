<?php
// Technical → Hospitals: add or edit a receiving facility, or switch it on/off.
// POST {id?, name, facility_type, address_text, phone?, latitude, longitude, capabilities?} or {id, status: active|inactive}.
// The pin (latitude/longitude) decides the "3 nearest hospitals" list and where navigation/ETA go, so it is range-checked.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/technical_guard.php';
require_once __DIR__ . '/../../reusables/input_validation.php';
header('Content-Type: application/json');
$actor = requireTechnicalSuperAdmin($pdo);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') technicalJsonError(405, 'Method not allowed.');
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) technicalJsonError(422, 'Invalid request body.');

function facAudit(PDO $pdo, int $actor, string $action, int $id, ?array $old, array $new): void
{
    $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$actor, $action, 'medical_facility', $id, $old ? json_encode($old) : null, json_encode($new), $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
}

try {
    $id = (int) ($in['id'] ?? 0);

    // ---- switch on / off
    if (isset($in['status']) && !isset($in['name'])) {
        $status = (string) $in['status'];
        if ($id <= 0 || !in_array($status, ['active', 'inactive'], true)) technicalJsonError(422, 'Choose a hospital and a status.');
        $s = $pdo->prepare('SELECT status FROM medical_facilities WHERE id = ?');
        $s->execute([$id]);
        $old = $s->fetchColumn();
        if ($old === false) technicalJsonError(404, 'Hospital not found.');
        $pdo->prepare('UPDATE medical_facilities SET status = ? WHERE id = ?')->execute([$status, $id]);
        facAudit($pdo, $actor, 'technical.facility_' . $status, $id, ['status' => $old], ['status' => $status]);
        echo json_encode(['success' => true, 'message' => $status === 'active' ? 'Hospital switched on — it can be recommended again.' : 'Hospital switched off — it will no longer be recommended. Past handoffs keep it.']);
        exit;
    }

    // ---- add / edit
    $name = mb_substr(trim(strip_tags((string) ($in['name'] ?? ''))), 0, 180);
    $type = (string) ($in['facility_type'] ?? 'hospital');
    $address = mb_substr(trim(strip_tags((string) ($in['address_text'] ?? ''))), 0, 255);
    $phone = trim((string) ($in['phone'] ?? ''));
    $capabilities = mb_substr(trim(strip_tags((string) ($in['capabilities'] ?? ''))), 0, 500);
    $lat = $in['latitude'] ?? null;
    $lng = $in['longitude'] ?? null;
    if (mb_strlen($name) < 3 || !inputLooksLikeText($name, 3)) technicalJsonError(422, 'Enter the hospital name.');
    if (!in_array($type, ['hospital', 'clinic', 'trauma_center', 'maternity', 'other'], true)) technicalJsonError(422, 'Choose a facility type.');
    if (mb_strlen($address) < 5 || !inputLooksLikeText($address, 4)) technicalJsonError(422, 'Enter the address (street, barangay, city).');
    if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $phone)) technicalJsonError(422, 'Enter a valid phone number, or leave it blank.');
    if ($capabilities !== '' && !inputLooksLikeText($capabilities, 3)) technicalJsonError(422, 'Capabilities must be readable text (e.g. 24/7 ER, trauma, ICU).');
    if (!is_numeric($lat) || !is_numeric($lng) || $lat < 14.20 || $lat > 14.45 || $lng < 120.80 || $lng > 121.10) technicalJsonError(422, 'Place the pin on the map inside Dasmariñas and its surroundings.');
    $lat = round((float) $lat, 7);
    $lng = round((float) $lng, 7);

    $dup = $pdo->prepare('SELECT 1 FROM medical_facilities WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1');
    $dup->execute([$name, $id]);
    if ($dup->fetchColumn()) technicalJsonError(409, 'A hospital with that name already exists.');

    if ($id > 0) {
        $o = $pdo->prepare('SELECT name, facility_type, address_text, phone, latitude, longitude, capabilities FROM medical_facilities WHERE id = ?');
        $o->execute([$id]);
        $old = $o->fetch(PDO::FETCH_ASSOC);
        if (!$old) technicalJsonError(404, 'Hospital not found.');
        $pdo->prepare("UPDATE medical_facilities SET name = ?, facility_type = ?, address_text = ?, phone = NULLIF(?, ''), latitude = ?, longitude = ?, capabilities = NULLIF(?, '') WHERE id = ?")
            ->execute([$name, $type, $address, $phone, $lat, $lng, $capabilities, $id]);
        facAudit($pdo, $actor, 'technical.facility_updated', $id, $old, ['name' => $name, 'latitude' => $lat, 'longitude' => $lng]);
        echo json_encode(['success' => true, 'message' => 'Hospital saved.', 'id' => $id]);
    } else {
        $pdo->prepare("INSERT INTO medical_facilities (name, facility_type, address_text, phone, latitude, longitude, capabilities, status) VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, NULLIF(?, ''), 'active')")
            ->execute([$name, $type, $address, $phone, $lat, $lng, $capabilities]);
        $id = (int) $pdo->lastInsertId();
        facAudit($pdo, $actor, 'technical.facility_created', $id, null, ['name' => $name, 'latitude' => $lat, 'longitude' => $lng]);
        echo json_encode(['success' => true, 'message' => 'Hospital added and switched on.', 'id' => $id]);
    }
} catch (Throwable $e) {
    error_log('Technical facility save failed: ' . $e->getMessage());
    technicalJsonError(500, 'Unable to save the hospital.');
}
