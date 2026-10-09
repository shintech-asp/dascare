<?php
// organizations/incidents/update_details.php
//
// Lets a handling organization fill in / correct the requester-facing details
// on an emergency request after they make contact — name, contact number,
// barangay, address, landmark, and the situation description. This is the
// counterpart to the citizen side becoming (deliberately) almost field-less:
// a citizen/guest can now request rescue with nothing but a location, and the
// organization gathers the real details here once they reach the requester.
//
// Only an org that is actually handling the incident may edit it — it must
// have an incident_offer or a dispatch_assignment tying the request to this
// organization (same scoping as organizations/incidents/list.php) — and only
// with the incidents.emergency_requests.update permission (organization_admin
// bypasses, per requireOrganizationAccess).
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_guard.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    organizationJsonError(405, 'Method not allowed.');
}

$ctx = requireOrganizationAccess($pdo, 'incidents.emergency_requests.update');
$org = (int) $ctx['organization_id'];

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$requestId = (int) ($body['emergency_request_id'] ?? 0);
if ($requestId <= 0) {
    organizationJsonError(422, 'A valid emergency request is required.');
}

// ------------------------------------------------------------------
// Normalise + validate the editable fields. All are optional on the request
// itself (the citizen may have provided none), but what the org types is
// validated the same way citizen/create.php validates it, so the record stays
// consistent regardless of which side filled it in.
// ------------------------------------------------------------------
$requesterName = trim((string) ($body['requester_name'] ?? ''));
$requesterPhoneRaw = trim((string) ($body['requester_phone'] ?? ''));
$barangay = trim(strip_tags((string) ($body['barangay'] ?? '')));
$addressText = trim(strip_tags((string) ($body['address_text'] ?? '')));
$landmark = trim(strip_tags((string) ($body['landmark'] ?? '')));
$description = trim((string) ($body['description'] ?? ''));

if (mb_strlen($requesterName) > 160) organizationJsonError(422, 'Name is too long.');
if (mb_strlen($barangay) > 120) organizationJsonError(422, 'Barangay name is too long.');
if (mb_strlen($addressText) > 255) organizationJsonError(422, 'Address is too long.');
if (mb_strlen($landmark) > 255) organizationJsonError(422, 'Landmark is too long.');
if (mb_strlen($description) > 2000) organizationJsonError(422, 'Description is too long (max 2000 characters).');

$normalizedPhone = preg_replace('/[\s-]/', '', $requesterPhoneRaw);
if ($normalizedPhone !== '' && !preg_match('/^(\+639\d{9}|09\d{9})$/', $normalizedPhone)) {
    organizationJsonError(422, 'Please enter a valid PH mobile number (e.g. 09123456789).');
}

// Fall back to the same placeholders create.php uses, so a cleared field never
// leaves a NOT NULL column empty or a record looking broken.
if ($addressText === '') $addressText = 'Pinned location (no address provided)';
if ($barangay === '') $barangay = 'Unspecified';
if ($description === '') $description = 'EMERGENCY REQUEST. No details provided — confirm with requester on contact.';

try {
    $pdo->beginTransaction();

    // Lock the request and confirm this org is actually handling it (offer or
    // assignment), mirroring the incidents/list.php scoping.
    $stmt = $pdo->prepare("
        SELECT er.id, er.reference_number, er.requester_name, er.requester_phone,
               er.barangay, er.address_text, er.landmark, er.description, er.status
        FROM emergency_requests er
        WHERE er.id = ?
          AND (
            EXISTS (SELECT 1 FROM dispatch_assignments da WHERE da.emergency_request_id = er.id AND da.organization_id = ?)
            OR EXISTS (SELECT 1 FROM incident_offers io WHERE io.emergency_request_id = er.id AND io.organization_id = ?)
          )
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$requestId, $org, $org]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        $pdo->rollBack();
        organizationJsonError(404, 'Incident not found for this organization.');
    }

    // Don't allow editing a request that's already closed out.
    if (in_array($request['status'], ['completed', 'cancelled', 'rejected', 'duplicate', 'false_alarm'], true)) {
        $pdo->rollBack();
        organizationJsonError(409, 'This incident is already closed and can no longer be edited.');
    }

    // A blank name keeps whatever was there (e.g. the account name or the
    // "Guest requester" placeholder) rather than wiping it. Same for phone.
    $finalName = $requesterName !== '' ? $requesterName : $request['requester_name'];
    $finalPhone = $normalizedPhone !== '' ? $normalizedPhone : $request['requester_phone'];

    $old = [
        'requester_name' => $request['requester_name'],
        'requester_phone' => $request['requester_phone'],
        'barangay' => $request['barangay'],
        'address_text' => $request['address_text'],
        'landmark' => $request['landmark'],
        'description' => $request['description'],
    ];
    $new = [
        'requester_name' => $finalName,
        'requester_phone' => $finalPhone,
        'barangay' => $barangay,
        'address_text' => $addressText,
        'landmark' => $landmark !== '' ? $landmark : null,
        'description' => $description,
    ];

    $upd = $pdo->prepare("
        UPDATE emergency_requests
        SET requester_name = ?, requester_phone = ?, barangay = ?, address_text = ?, landmark = ?, description = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $upd->execute([
        $new['requester_name'], $new['requester_phone'], $new['barangay'],
        $new['address_text'], $new['landmark'], $new['description'], $requestId,
    ]);

    $audit = $pdo->prepare("
        INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
        VALUES (?, ?, 'incidents.requester_details_updated', 'emergency_request', ?, ?, ?, ?, ?)
    ");
    $audit->execute([
        (int) $ctx['user_id'], $org, $requestId,
        json_encode($old, JSON_UNESCAPED_UNICODE),
        json_encode($new, JSON_UNESCAPED_UNICODE),
        $_SERVER['REMOTE_ADDR'] ?? null,
        mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Incident details updated.',
        'request' => array_merge(['id' => $requestId, 'reference_number' => $request['reference_number']], $new),
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Incident details update failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to update incident details.');
}
