<?php
// citizen/create.php
// Creates an emergency_requests row from either a logged-in citizen or an
// anonymous guest. Who's submitting is decided from $_SESSION server-side —
// never from a client-sent "source"/"requester_user_id" field — same
// principle kyc_submit.php uses for age/city: never trust the client for
// anything that decides identity or eligibility.
//
// Guest support is intentionally already wired here even though the guest
// *page* doesn't exist yet (per DASCARE spec: "citizens and guests" can both
// request assistance) — this endpoint just needs a public route added later
// that posts the same shape without a session cookie.
require '../cors.php';
require_once __DIR__ . '/../db/db.php';           // exposes PDO as $pdo
require_once __DIR__ . '/../reusables/rate_limit.php';
require_once __DIR__ . '/../reusables/guest_request_limit.php';
require_once __DIR__ . '/../reusables/dispatch_dss.php';
require_once __DIR__ . '/../reusables/dispatch_dedup.php';

header('Content-Type: application/json');

// ==================================================
// WHO IS SUBMITTING
// ==================================================
$isGuest = empty($_SESSION['user_id']);
$userId  = $isGuest ? null : (int) $_SESSION['user_id'];
$ip      = getClientIp();

// ==================================================
// RATE LIMIT — per-IP always, per-user additionally when logged in.
// Mirrors login.php's dual IP+identity check: IP alone would let one
// logged-in account get flooded from many IPs, IP-only misses a script
// hopping accounts on one IP.
// ==================================================
checkRateLimit($pdo, 'emergency_request', $ip);
if (!$isGuest) {
    checkRateLimit($pdo, 'emergency_request', 'user:' . $userId);
}

// ==================================================
// INPUT
// Multipart form (not JSON) since this endpoint also accepts optional
// evidence photos alongside the incident fields.
// ==================================================
$categoryId    = (int) ($_POST['emergency_category_id'] ?? 0);
$severity      = trim($_POST['severity'] ?? 'critical');
$description   = trim($_POST['description'] ?? '');
$addressText   = trim(strip_tags($_POST['address_text'] ?? ''));
$barangay      = trim(strip_tags($_POST['barangay'] ?? ''));
$landmark      = trim(strip_tags($_POST['landmark'] ?? ''));
$latRaw        = $_POST['latitude'] ?? '';
$lngRaw        = $_POST['longitude'] ?? '';
$requesterPhone = trim($_POST['requester_phone'] ?? '');
$requesterName  = trim($_POST['requester_name'] ?? '');
$requestMode    = trim($_POST['request_mode'] ?? 'standard');
$scheduledForRaw = trim($_POST['scheduled_for'] ?? '');

function fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// ==================================================
// CATEGORY
// The merged request form no longer asks the citizen to classify the
// incident (it's an emergency — minimise typing), so a missing/unknown
// category falls back to "Other" (id 8) instead of being rejected. Still
// whitelisted against the real table so a category deactivated later
// (is_active) can't slip through.
// ==================================================
const GENERIC_CATEGORY_ID = 8;
$stmt = $pdo->prepare("SELECT id FROM emergency_categories WHERE id = ? AND is_active = 1");
$stmt->execute([$categoryId]);
if (!$stmt->fetch()) {
    $categoryId = GENERIC_CATEGORY_ID;
    // Confirm the fallback itself exists/active — a misconfigured seed
    // shouldn't silently create orphaned rows.
    $stmt = $pdo->prepare("SELECT id FROM emergency_categories WHERE id = ? AND is_active = 1");
    $stmt->execute([$categoryId]);
    if (!$stmt->fetch()) {
        fail(500, 'Emergency categories are not configured. Please contact support.');
    }
}

// ==================================================
// SEVERITY
// The form no longer asks for severity — every request comes in as
// critical; a dispatcher can downgrade it after review. An unknown value
// defaults to critical rather than being rejected.
// ==================================================
$allowedSeverities = ['low', 'moderate', 'high', 'critical'];
if (!in_array($severity, $allowedSeverities, true)) {
    $severity = 'critical';
}

// ==================================================
// VALIDATE: REQUEST MODE + SCHEDULE
// Mirrors emergency_requests.request_mode enum ('instant','standard').
// scheduled_for only makes sense for 'standard' — instant is always "now"
// by definition — and is otherwise left null.
// ==================================================
$allowedRequestModes = ['instant', 'standard'];
if (!in_array($requestMode, $allowedRequestModes, true)) {
    fail(422, 'Invalid request mode.');
}

$scheduledFor = null;
if ($scheduledForRaw !== '') {
    if ($requestMode !== 'standard') {
        fail(422, 'A schedule can only be set on a standard request.');
    }
    $scheduledDt = DateTime::createFromFormat('Y-m-d H:i:s', $scheduledForRaw);
    if (!$scheduledDt || $scheduledDt->format('Y-m-d H:i:s') !== $scheduledForRaw) {
        fail(422, 'Please provide a valid schedule date/time.');
    }
    if ($scheduledDt->getTimestamp() <= time()) {
        fail(422, 'The scheduled date/time must be in the future.');
    }
    $scheduledFor = $scheduledForRaw;
}

// ==================================================
// DESCRIPTION
// Optional now — someone who can't type still gets help. Empty falls back to
// a placeholder that tells dispatch to confirm on contact. Only the upper
// bound is still enforced (to protect the column / storage).
// ==================================================
if (mb_strlen($description) > 2000) {
    fail(422, 'Please keep the description under 2000 characters.');
}
if ($description === '') {
    $description = 'EMERGENCY REQUEST. No details provided — confirm with requester on contact.';
}

// ==================================================
// ADDRESS / BARANGAY / LANDMARK
// All optional now — the pinned coordinates (validated + geofenced below) are
// what actually locate the incident. Blank address/barangay fall back to
// placeholders so a no-typing submit still succeeds; only upper length bounds
// are enforced. City isn't a stored column (service area is all Dasmariñas,
// enforced via the lat/lng geofence).
// ==================================================
if (mb_strlen($addressText) > 255) {
    fail(422, 'Please keep the address under 255 characters.');
}
if (mb_strlen($barangay) > 120) {
    fail(422, 'Barangay name is too long.');
}
if (mb_strlen($landmark) > 255) {
    fail(422, 'Landmark is too long.');
}
if ($addressText === '') {
    $addressText = 'Pinned location (no address provided)';
}
if ($barangay === '') {
    $barangay = 'Unspecified';
}

// ==================================================
// VALIDATE: GEOLOCATION — required, and geofenced to Dasmariñas
// Loose bounding box around Dasmariñas City (mirrors the client-side map's
// maxBounds) — adjust if the service area ever expands beyond one city.
// ==================================================
if ($latRaw === '' || $lngRaw === '' || !is_numeric($latRaw) || !is_numeric($lngRaw)) {
    fail(422, 'Please pin the incident location on the map.');
}
$latitude  = (float) $latRaw;
$longitude = (float) $lngRaw;

const DASMARINAS_BOUNDS = ['south' => 14.26, 'north' => 14.40, 'west' => 120.86, 'east' => 121.00];
if (
    $latitude  < DASMARINAS_BOUNDS['south'] || $latitude  > DASMARINAS_BOUNDS['north'] ||
    $longitude < DASMARINAS_BOUNDS['west']  || $longitude > DASMARINAS_BOUNDS['east']
) {
    fail(422, 'The pinned location is outside Dasmariñas — DASCARE currently only covers Dasmariñas City.');
}

// ==================================================
// CONTACT PHONE
// Contact details are NOT required to request rescue — the responding
// organization gathers patient/contact info on the call or on scene. A
// number is only validated when one is actually provided. A logged-in
// citizen with a blank field still has their account number filled in below
// (so it's there if available), but a guest can submit with nothing at all.
// Same PH mobile pattern as register.php / kyc_submit.php.
// ==================================================
$phonePattern = '/^(\+639\d{9}|09\d{9})$/';
$normalizedPhone = preg_replace('/[\s-]/', '', $requesterPhone);
if ($normalizedPhone !== '' && !preg_match($phonePattern, $normalizedPhone)) {
    fail(422, 'Please enter a valid PH mobile number (e.g. 09123456789).');
}

// ==================================================
// GUEST SOFT LIMIT — read-only check here, nothing recorded yet.
// Never rejects: this only decides what guest_verification_flag (if any)
// the request gets once it's actually created below. See
// reusables/guest_request_limit.php for the reasoning — a guest is
// never turned away for volume, a flagged request just gets an extra
// look from a dispatcher before treating it as routine.
// ==================================================
// Only meaningful when a guest actually left a phone number — the soft limit
// is phone-keyed. A guest who provides nothing simply gets no flag (nothing
// to key the lookup on); the per-IP rate limit above still applies.
$guestVerificationFlag = null;
$projectedOrdinal = null; // stays null for a guest who left the phone blank
if ($isGuest && $normalizedPhone !== '') {
    $projectedOrdinal = getGuestPhoneRequestCount($pdo, $normalizedPhone) + 1;
    $falseAlarmCount = getGuestFalseAlarmCount($pdo, $normalizedPhone);
    $guestVerificationFlag = determineGuestVerificationFlag($projectedOrdinal, $falseAlarmCount);
}

// ==================================================
// RESOLVE REQUESTER IDENTITY
// Logged-in citizen: name comes from the account, not the client, so it
// can't be spoofed to something else on a real account. Guest: name must
// be typed in, since there's no account to pull it from.
// ==================================================
if ($isGuest) {
    // Name is optional too — a guest can submit with nothing. Only the upper
    // bound is enforced; an empty name falls back to a generic label the
    // dispatcher/organization can replace once they make contact.
    if (mb_strlen($requesterName) > 160) {
        fail(422, 'Name is too long.');
    }
    if ($requesterName === '') {
        $requesterName = 'Guest requester';
    }
    $source = 'guest';
} else {
    $stmt = $pdo->prepare("SELECT first_name, last_name, phone FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$userId]);
    $account = $stmt->fetch();
    if (!$account) {
        fail(401, 'Your session is no longer valid. Please log in again.');
    }
    $requesterName = trim($account['first_name'] . ' ' . $account['last_name']);
    $source = 'citizen_app';

    // No number typed → use the one on the account if it has a valid one, so
    // it's available to responders. Not required, though: if the account has
    // none, the request still goes through and the organization gathers
    // contact info on the call / on scene.
    if ($normalizedPhone === '') {
        $accountPhone = preg_replace('/[\s-]/', '', (string) ($account['phone'] ?? ''));
        if (preg_match($phonePattern, $accountPhone)) {
            $normalizedPhone = $accountPhone;
        }
    }
}

// ==================================================
// OPTIONAL EVIDENCE PHOTOS (up to 3)
// Same double-check as kyc_submit.php: real MIME via fileinfo, then confirm
// it actually decodes as an image — filename extension is never trusted.
// ==================================================
$allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
$mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$maxPhotos = 3;
$maxPhotoBytes = 5 * 1024 * 1024;

$photoFiles = [];
if (!empty($_FILES['photos'])) {
    $names = (array) $_FILES['photos']['name'];
    $count = count($names);
    if ($count > $maxPhotos) {
        fail(422, "Please attach at most {$maxPhotos} photos.");
    }
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) {
            fail(422, 'One of the attached photos failed to upload. Please try again.');
        }
        if ($_FILES['photos']['size'][$i] > $maxPhotoBytes) {
            fail(422, 'Each photo must be 5MB or smaller.');
        }

        $tmpPath = $_FILES['photos']['tmp_name'][$i];
        $mime = mime_content_type($tmpPath);
        if (!in_array($mime, $allowedImageTypes, true)) {
            fail(422, 'Photos must be JPG, PNG, or WEBP.');
        }
        if (@getimagesize($tmpPath) === false) {
            fail(422, 'One of the attached files is not a valid image.');
        }

        $photoFiles[] = [
            'tmp_name' => $tmpPath,
            'ext'      => $mimeToExt[$mime],
            'size'     => (int) $_FILES['photos']['size'][$i],
            'mime'     => $mime,
        ];
    }
}

// ==================================================
// PERSIST
// reference_number needs the row's own id to build ("DAS-2026-000101"), so
// it's inserted with a temporary unique placeholder and updated right after
// — avoids a separate counter table / race condition on a shared sequence.
// ==================================================
$destDir = $_SERVER['DOCUMENT_ROOT'] . '/dascare/uploads/emergency_requests/'; // adjust to match actual deployment folder
$savedFiles = []; // for cleanup if the transaction fails after files are written

// Instant requests hold the dedup lock from insert through the duplicate
// check below, so a report still being saved can't be missed by one that
// arrives a moment later. If the lock times out the request still goes
// through — dedup is skipped, never the emergency.
$holdsDedupLock = $requestMode === 'instant'
    && (int) $pdo->query("SELECT GET_LOCK('dascare_dispatch_dedup', 5)")->fetchColumn() === 1;

try {
    $pdo->beginTransaction();

    $placeholderRef = 'PENDING-' . bin2hex(random_bytes(8));

    $stmt = $pdo->prepare("
        INSERT INTO emergency_requests
            (reference_number, requester_user_id, created_by_user_id, source, guest_verification_flag, request_mode, scheduled_for,
             requester_name, requester_phone, emergency_category_id, severity, description,
             address_text, barangay, landmark, latitude, longitude, status, submitted_at)
        VALUES
            (:reference_number, :requester_user_id, :created_by_user_id, :source, :guest_verification_flag, :request_mode, :scheduled_for,
             :requester_name, :requester_phone, :emergency_category_id, :severity, :description,
             :address_text, :barangay, :landmark, :latitude, :longitude, 'submitted', NOW())
    ");
    $stmt->execute([
        ':reference_number'         => $placeholderRef,
        ':requester_user_id'        => $userId,
        ':created_by_user_id'       => $userId,
        ':source'                   => $source,
        ':guest_verification_flag'  => $guestVerificationFlag,
        ':request_mode'             => $requestMode,
        ':scheduled_for'            => $scheduledFor,
        ':requester_name'           => $requesterName,
        ':requester_phone'          => $normalizedPhone,
        ':emergency_category_id'    => $categoryId,
        ':severity'                 => $severity,
        ':description'              => $description,
        ':address_text'             => $addressText,
        ':barangay'                 => $barangay,
        ':landmark'                 => $landmark !== '' ? $landmark : null,
        ':latitude'                 => $latitude,
        ':longitude'                => $longitude,
    ]);

    $requestId = (int) $pdo->lastInsertId();

    $referenceNumber = 'DAS-' . date('Y') . '-' . str_pad((string) $requestId, 6, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE emergency_requests SET reference_number = ? WHERE id = ?")
        ->execute([$referenceNumber, $requestId]);

    // Initial status log entry — every later transition (verified, assigned,
    // etc.) appends here too, so this keeps the timeline complete from t=0.
    // When flagged, the reason rides along in the note itself so a
    // dispatcher scanning the timeline sees it immediately without a
    // separate lookup — the flag never changes what status the request
    // starts in, only what it says.
    $initialNote = 'Request submitted';
    if ($guestVerificationFlag === GUEST_FLAG_DAILY_THRESHOLD) {
        $initialNote .= ' — flagged for verification (guest request #' . $projectedOrdinal . ' today for this phone number)';
    } elseif ($guestVerificationFlag === GUEST_FLAG_FALSE_ALARM_HISTORY) {
        $initialNote .= ' — flagged for verification (this phone number has ' . $falseAlarmCount . ' confirmed false alarm(s) in the past ' . GUEST_FALSE_ALARM_LOOKBACK_DAYS . ' days)';
    }
    $pdo->prepare("
        INSERT INTO emergency_request_status_logs
            (emergency_request_id, old_status, new_status, changed_by_user_id, notes, latitude, longitude)
        VALUES
            (:request_id, NULL, 'submitted', :changed_by, :notes, :latitude, :longitude)
    ")->execute([
        ':request_id' => $requestId,
        ':changed_by' => $userId,
        ':notes'      => $initialNote,
        ':latitude'   => $latitude,
        ':longitude'  => $longitude,
    ]);

    // Snapshot the citizen's medical_records row (if they have one) onto
    // this request — never for guests, who have no account/row to pull
    // from. A snapshot, not a live join: what a responder saw the day
    // this request was filed should stay fixed even if the citizen edits
    // their medical records afterward. See
    // emergency_request_medical_snapshots migration for the full reasoning.
    $medicalSnapshotAttached = false;
    if (!$isGuest) {
        $medStmt = $pdo->prepare("
            SELECT
                blood_type, allergies, medications, medical_conditions,
                disabilities_mobility_notes, organ_donor,
                emergency_contact_name, emergency_contact_phone, emergency_contact_relationship,
                primary_physician_name, primary_physician_phone,
                insurance_provider, insurance_policy_number, additional_notes
            FROM medical_records
            WHERE user_id = ?
        ");
        $medStmt->execute([$userId]);
        $medicalRecord = $medStmt->fetch(PDO::FETCH_ASSOC);

        if ($medicalRecord) {
            $pdo->prepare("
                INSERT INTO emergency_request_medical_snapshots
                    (emergency_request_id, blood_type, allergies, medications, medical_conditions,
                     disabilities_mobility_notes, organ_donor,
                     emergency_contact_name, emergency_contact_phone, emergency_contact_relationship,
                     primary_physician_name, primary_physician_phone,
                     insurance_provider, insurance_policy_number, additional_notes)
                VALUES
                    (:request_id, :blood_type, :allergies, :medications, :medical_conditions,
                     :disabilities_mobility_notes, :organ_donor,
                     :emergency_contact_name, :emergency_contact_phone, :emergency_contact_relationship,
                     :primary_physician_name, :primary_physician_phone,
                     :insurance_provider, :insurance_policy_number, :additional_notes)
            ")->execute([
                ':request_id'                     => $requestId,
                ':blood_type'                     => $medicalRecord['blood_type'],
                ':allergies'                      => $medicalRecord['allergies'],
                ':medications'                    => $medicalRecord['medications'],
                ':medical_conditions'              => $medicalRecord['medical_conditions'],
                ':disabilities_mobility_notes'    => $medicalRecord['disabilities_mobility_notes'],
                ':organ_donor'                    => (int) $medicalRecord['organ_donor'],
                ':emergency_contact_name'         => $medicalRecord['emergency_contact_name'],
                ':emergency_contact_phone'        => $medicalRecord['emergency_contact_phone'],
                ':emergency_contact_relationship' => $medicalRecord['emergency_contact_relationship'],
                ':primary_physician_name'         => $medicalRecord['primary_physician_name'],
                ':primary_physician_phone'        => $medicalRecord['primary_physician_phone'],
                ':insurance_provider'             => $medicalRecord['insurance_provider'],
                ':insurance_policy_number'        => $medicalRecord['insurance_policy_number'],
                ':additional_notes'               => $medicalRecord['additional_notes'],
            ]);
            $medicalSnapshotAttached = true;
        }
    }

    // Save + record any attached photos.
    if ($photoFiles) {
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $mediaStmt = $pdo->prepare("
            INSERT INTO emergency_request_media (emergency_request_id, uploaded_by_user_id, file_path, mime_type, file_size)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($photoFiles as $photo) {
            $filename = 'req_' . $requestId . '_' . bin2hex(random_bytes(16)) . '.' . $photo['ext'];
            $destPath = $destDir . $filename;
            if (!copy($photo['tmp_name'], $destPath)) {
                throw new RuntimeException('Failed to save an attached photo.');
            }
            $savedFiles[] = $destPath;
            $mediaStmt->execute([$requestId, $userId, $filename, $photo['mime'], $photo['size']]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($savedFiles as $path) {
        if (file_exists($path)) {
            unlink($path);
        }
    }
    error_log('Emergency request create failed: ' . $e->getMessage());
    if ($holdsDedupLock) $pdo->query("SELECT RELEASE_LOCK('dascare_dispatch_dedup')");
    fail(500, 'Something went wrong while submitting your request. Please try again.');
}

// Every submission — success is exactly the case this limiter needs to
// count, same fix as register.php's missing recordAttempt on the happy path.
recordAttempt($pdo, 'emergency_request', $ip);
if (!$isGuest) {
    recordAttempt($pdo, 'emergency_request', 'user:' . $userId);
}

// Record this request against the phone number's rolling window now that
// it has actually succeeded (same "count on success" placement as
// recordAttempt above). guestStatus rides back in the response so the
// frontend can show "2 of 3 today" / the flagged notice without a
// second request.
// Start the first DSS offer cycle for immediate emergencies. This runs only
// after the request transaction has safely committed; a DSS/resource problem
// must never roll back or hide a genuine emergency submission.
// Duplicate detection first: if this is another report of an emergency that's
// already being handled nearby, link it there instead of sending a second
// ambulance (see reusables/dispatch_dedup.php).
$merge = ['merged' => false];
if ($requestMode === 'instant') {
    try {
        $merge = dedupCheckNewRequest($pdo, $requestId);
    } catch (Throwable $dedupError) {
        error_log('Duplicate check failed for request ' . $requestId . ': ' . $dedupError->getMessage());
    }
}
if ($holdsDedupLock) $pdo->query("SELECT RELEASE_LOCK('dascare_dispatch_dedup')");

// Guest-created requests are remembered in this browser session so the guest
// can still say "not my emergency" on a merged report (citizen/unmerge.php).
if ($isGuest && $merge['merged']) {
    $_SESSION['guest_request_ids'][] = $requestId;
}

$dssStarted = false;
$dssCandidateCount = 0;
if ($requestMode === 'instant' && !$merge['merged']) {
    try {
        $dssResult = dssGenerateRecommendations($pdo, $requestId, null);
        $dssStarted = true;
        $dssCandidateCount = count($dssResult['candidates'] ?? []);
    } catch (Throwable $dssError) {
        error_log('Automatic DSS start failed for request ' . $requestId . ': ' . $dssError->getMessage());
    }
}

$guestStatus = null;
if ($isGuest) {
    // Phone-keyed counter only when a number was given — otherwise every
    // blank-phone guest would pile onto one shared hash('') bucket.
    if ($normalizedPhone !== '') {
        consumeGuestPhoneRequest($pdo, $normalizedPhone);
    }
    consumeGuestIpRequest($pdo, $ip); // indicative counter only, see guest_status.php
    $guestStatus = [
        'limit'     => GUEST_DAILY_SOFT_LIMIT,
        'ordinal'   => $projectedOrdinal, // this request's position today, e.g. 4th
        'flagged'   => $guestVerificationFlag !== null,
        'flagReason'=> $guestVerificationFlag,
    ];
}

$response = [
    'success'          => true,
    'id'               => $requestId,
    'reference_number' => $referenceNumber,
    // null when logged in; {limit, ordinal, flagged, flagReason} for guests.
    // "flagged" is informational only — the request above was already
    // created and will be dispatched normally either way.
    'guestStatus'      => $guestStatus,
    // true only for a logged-in citizen who had a medical_records row at
    // submit time — always false for guests, never meant to prompt them
    // to create one mid-emergency.
    'medicalRecordAttached' => $medicalSnapshotAttached,
    'dssStarted' => $dssStarted,
    'dssCandidateCount' => $dssCandidateCount,
    // Set when this report was linked to an earlier one nearby:
    // {id, reference_number, distance_m, gap_seconds}. null otherwise.
    'mergedInto' => $merge['merged'] ? $merge['primary'] : null,
];

// Android app only (X-Dascare-Client: mobile, see reusables/mobile_auth.php):
// a guest SOS gets a private key so the same phone can track the request and
// say "not my emergency" later without an account. Web responses are
// unchanged. A failure here must never fail the emergency itself.
if ($isGuest && function_exists('isMobileAppRequest') && isMobileAppRequest()) {
    try {
        $response['guestAccessToken'] = mobileIssueGuestRequestToken($pdo, $requestId);
    } catch (Throwable $e) {
        error_log('Guest access key failed for request ' . $requestId . ': ' . $e->getMessage());
    }
}

echo json_encode($response);