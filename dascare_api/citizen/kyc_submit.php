<?php
// citizen/kyc_submit.php
// Handles a citizen's KYC submission: stores the uploaded ID image, the
// attested phone/birthdate/address, and flips the user's kyc_verifications
// row to "pending" (status = 1).
//
// Ported from Likhavite's kyc_submit.php reference. Two deliberate
// differences from that reference:
//   1. dascare's `users` table has no `birthdate` column, so birthdate is
//      only ever stored on kyc_verifications — there's no users-table sync
//      for it (the reference syncs both phone_number and birthdate back to
//      `users`; here only phone_number is synced).
//   2. Service area is Dasmariñas City only (see organization_service_areas
//      / hero.vue), not all of Cavite, so city/province are both fixed
//      server-side rather than validated against a whitelist array.
//
// ASSUMPTION (flagged, please verify against your actual files): this
// mirrors the session/rate-limit conventions visible in
// CitizenDashboardHome.vue and the rate_limits table schema — a PHP
// session with $_SESSION['user_id'], plus ../cors.php, ../db/db.php
// (PDO as $pdo), and ../rate_limit.php exposing checkRateLimit()/
// recordAttempt() with the same signature as the reference. Adjust the
// three requires below if dascare's actual paths/helpers differ.
require '../cors.php';
require_once __DIR__ . '/../db/db.php'; // must expose a PDO instance as $pdo
require_once __DIR__ . '/../reusables/rate_limit.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Not logged in.']);
    exit;
}

$userId = $_SESSION['user_id'];

// ==================================================
// ROLE CHECK
// KYC here is a citizen-only flow — organization staff (admins/operational
// users) are vetted separately at organization onboarding, not through
// this endpoint.
// ==================================================
$roleCheck = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = :id LIMIT 1");
$roleCheck->execute(['id' => $userId]);
$role = $roleCheck->fetchColumn();

if ($role !== 'citizen') {
    http_response_code(403);
    echo json_encode(['message' => 'KYC verification is only applicable to citizen accounts.']);
    exit;
}

// ==================================================
// RATE LIMIT
// KYC submissions touch file storage + a human review queue, so throttle
// per-user regardless of what the client sends.
// ==================================================
checkRateLimit($pdo, 'kyc_submit', 'user:' . $userId);

// ==================================================
// VALIDATE: BIRTHDATE
// Must be a real calendar date, and the submitter must be between
// 13 and 85 years old inclusive. Validated before ID type since which IDs
// are acceptable depends on the submitter's age bracket (see below).
// ==================================================
$birthdateInput = trim($_POST['birthdate'] ?? '');
$birthdate = DateTime::createFromFormat('Y-m-d', $birthdateInput);
$dateErrors = DateTime::getLastErrors();

if (!$birthdate || ($dateErrors && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
    http_response_code(422);
    echo json_encode(['message' => 'Please provide a valid birthdate.']);
    exit;
}

$today = new DateTime('today');
if ($birthdate > $today) {
    http_response_code(422);
    echo json_encode(['message' => 'Birthdate cannot be in the future.']);
    exit;
}

$age = $today->diff($birthdate)->y;
if ($age < 13) {
    http_response_code(422);
    echo json_encode(['message' => 'You must be at least 13 years old to verify an account.']);
    exit;
}
if ($age > 85) {
    http_response_code(422);
    echo json_encode(['message' => 'Please double check the birthdate entered (max age 85).']);
    exit;
}

$birthdateSql = $birthdate->format('Y-m-d');

// ==================================================
// VALIDATE: ID TYPE
// Whitelist against the exact options the form offers — never trust
// the raw string a client could send directly to the endpoint. Which IDs
// are acceptable is further scoped by age bracket, mirroring the
// frontend's ID_TYPES_BY_AGE_GROUP.
// ==================================================
$ageGroup = $age >= 60 ? 'senior' : ($age >= 18 ? 'adult' : 'minor');

$idTypesByAgeGroup = [
    'minor' => [
        'PhilSys (National ID)',
        'Passport',
        'Student ID',
        'PWD ID',
    ],
    'adult' => [
        'PhilSys (National ID)',
        'Passport',
        "Driver's License",
        'UMID',
        'SSS ID',
        'GSIS ID',
        'PhilHealth ID',
        'Pag-IBIG ID',
        'Postal ID',
        "Voter's ID",
        'PRC ID',
        'PWD ID',
        'OFW ID',
        "Seaman's Book",
    ],
];
$idTypesByAgeGroup['senior'] = array_merge($idTypesByAgeGroup['adult'], ['Senior Citizen ID']);

$allowedIdTypesForAge = $idTypesByAgeGroup[$ageGroup];

$idType = trim($_POST['id_type'] ?? '');
if (!in_array($idType, $allowedIdTypesForAge, true)) {
    http_response_code(422);
    echo json_encode(['message' => "That ID type isn't valid for the age you entered. Please select an ID available for your age group."]);
    exit;
}

// ==================================================
// VALIDATE: PHONE NUMBER
// PH mobile format: 09XXXXXXXXX (11 digits, starts with 09).
// ==================================================
$phoneNumber = trim($_POST['phone_number'] ?? '');
if (!preg_match('/^09\d{9}$/', $phoneNumber)) {
    http_response_code(422);
    echo json_encode(['message' => 'Please enter a valid PH mobile number (e.g. 09123456789).']);
    exit;
}

// ==================================================
// VALIDATE: CITY / PROVINCE / BARANGAY
// Service area is Dasmariñas City, Cavite only, so both are fixed
// server-side (never trust a client-supplied city/province — there's no
// picker for either on the form; unlike Likhavite's multi-city Cavite
// service area, dascare currently serves one city only). Barangay is
// free-text (sourced from the PSGC API client-side, scoped to
// Dasmariñas's city code) but still length-bound + sanitized.
// ==================================================
$province = 'Cavite';
$city = 'Dasmariñas';

$barangay = trim(strip_tags($_POST['barangay'] ?? ''));
if (mb_strlen($barangay) < 2 || mb_strlen($barangay) > 120) {
    http_response_code(422);
    echo json_encode(['message' => 'Please select a valid barangay.']);
    exit;
}

// ==================================================
// VALIDATE: ZIP CODE
// Dasmariñas's known zip codes only.
// ==================================================
$zipCode = trim($_POST['zip_code'] ?? '');
$allowedZips = ['4114', '4115', '4126'];
if (!in_array($zipCode, $allowedZips, true)) {
    http_response_code(422);
    echo json_encode(['message' => 'Please enter a valid Dasmariñas zip code (4114, 4115, or 4126).']);
    exit;
}

// ==================================================
// VALIDATE: ADDRESS
// House/unit no., street, and subdivision — barangay/city/province are
// their own fields, so we don't need the whole thing crammed in here.
// strip_tags defends against stored markup even though the frontend also
// escapes on render — never rely on one layer alone.
// ==================================================
$address = trim(strip_tags($_POST['address'] ?? ''));

if (mb_strlen($address) < 5 || mb_strlen($address) > 255) {
    http_response_code(422);
    echo json_encode(['message' => 'Please enter your house/unit no., street, and subdivision.']);
    exit;
}

// ==================================================
// VALIDATE: GEOLOCATION
// Required — the user must drop a pin or use "current location" on the map.
// ==================================================
$latRaw = $_POST['latitude'] ?? '';
$lngRaw = $_POST['longitude'] ?? '';

if ($latRaw === '' || $lngRaw === '' || !is_numeric($latRaw) || !is_numeric($lngRaw)) {
    http_response_code(422);
    echo json_encode(['message' => 'Please select your address location on the map.']);
    exit;
}

$latitude  = (float) $latRaw;
$longitude = (float) $lngRaw;

if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    http_response_code(422);
    echo json_encode(['message' => 'The selected location is invalid.']);
    exit;
}

// Sanity check: the pin should plausibly be within Dasmariñas City.
// (Loose bounding box, generous enough to cover the whole city footprint —
// widen if the service area ever expands beyond Dasmariñas.)
if ($latitude < 14.26 || $latitude > 14.40 || $longitude < 120.87 || $longitude > 121.00) {
    http_response_code(422);
    echo json_encode(['message' => 'Please select a location within Dasmariñas City.']);
    exit;
}

// ==================================================
// VALIDATE: ID IMAGE
// ==================================================
if (empty($_FILES['id_image']) || $_FILES['id_image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['message' => 'Please attach a valid ID image.']);
    exit;
}

$file = $_FILES['id_image'];

if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(422);
    echo json_encode(['message' => 'File is too large — max 5MB.']);
    exit;
}

// Check the real file content (not the client-supplied name/extension) via
// the fileinfo extension, then double-check it decodes as an actual image —
// two independent checks catch more disguised-file tricks than either alone.
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, $allowedTypes, true)) {
    http_response_code(422);
    echo json_encode(['message' => 'Only JPG, PNG, or WEBP images are allowed.']);
    exit;
}

$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    http_response_code(422);
    echo json_encode(['message' => 'The uploaded file is not a valid image.']);
    exit;
}

$mimeToExt = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
// Derive the extension from the verified MIME type — never from the
// client-supplied filename — so a renamed .php can't ride along as .jpg.
$ext = $mimeToExt[$mime];

// Reject re-submission while a previous one is still pending
$check = $pdo->prepare("SELECT status, rejection_count FROM kyc_verifications WHERE user_id = :id");
$check->execute(['id' => $userId]);
$existing = $check->fetch(PDO::FETCH_ASSOC);

if ($existing && (int) $existing['status'] === 1) {
    http_response_code(409);
    echo json_encode(['message' => 'You already have a submission under review.']);
    exit;
}

if ($existing && (int) $existing['status'] === 2) {
    http_response_code(409);
    echo json_encode(['message' => 'Your account is already verified.']);
    exit;
}

// Random filename — avoids collisions and stops filenames being guessable/enumerable.
$filename = 'id_' . $userId . '_' . bin2hex(random_bytes(16)) . '.' . $ext;

// Absolute path off the web server's own document root, mirroring the
// convention used by the reference system, so the file lands in the PHP
// server's public folder and is reachable over HTTP from there
// (e.g. http://localhost/dascare/uploads/kyc/<filename>).
$destDir = $_SERVER['DOCUMENT_ROOT'] . '/dascare/uploads/kyc/';
if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}
$destPath = $destDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to save the uploaded file.']);
    exit;
}

$rejectionCount = $existing['rejection_count'] ?? 0;

// ==================================================
// PERSIST
// Update both the KYC record (source of truth for what was verified) and
// the user's phone number, in one transaction so they never disagree.
// (No birthdate sync to `users` — that column doesn't exist in dascare's
// users table; birthdate lives on kyc_verifications only.)
// ==================================================
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO kyc_verifications
            (user_id, status, id_image, id_type, phone_number, birthdate, address, barangay, city, province, zip_code, latitude, longitude, submitted_at, rejection_count)
        VALUES
            (:user_id, 1, :id_image, :id_type, :phone_number, :birthdate, :address, :barangay, :city, :province, :zip_code, :latitude, :longitude, NOW(), :rejection_count)
        ON DUPLICATE KEY UPDATE
            status = 1,
            id_image = VALUES(id_image),
            id_type = VALUES(id_type),
            phone_number = VALUES(phone_number),
            birthdate = VALUES(birthdate),
            address = VALUES(address),
            barangay = VALUES(barangay),
            city = VALUES(city),
            province = VALUES(province),
            zip_code = VALUES(zip_code),
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            submitted_at = NOW(),
            verification_note = NULL,
            verified_at = NULL,
            verified_by = NULL
    ");
    $stmt->execute([
        'user_id'         => $userId,
        'id_image'        => $filename,
        'id_type'         => $idType,
        'phone_number'    => $phoneNumber,
        'birthdate'       => $birthdateSql,
        'address'         => $address,
        'barangay'        => $barangay,
        'city'            => $city,
        'province'        => $province,
        'zip_code'        => $zipCode,
        'latitude'        => $latitude,
        'longitude'       => $longitude,
        'rejection_count' => $rejectionCount,
    ]);

    // Keep the account-level phone in sync so the rest of the app
    // (profile, dispatch contact info, etc.) reflects the same verified
    // number. `phone` is NOT NULL on dascare's users table already
    // (captured at registration), so this just keeps the two in sync
    // rather than filling a previously-empty field.
    $userStmt = $pdo->prepare("UPDATE users SET phone = :phone WHERE id = :user_id");
    $userStmt->execute([
        'phone'   => $phoneNumber,
        'user_id' => $userId,
    ]);

    $citizenNameStmt = $pdo->prepare("SELECT CONCAT_WS(' ', first_name, last_name) FROM users WHERE id = ?");
    $citizenNameStmt->execute([$userId]);
    $citizenName = trim((string) $citizenNameStmt->fetchColumn()) ?: 'A citizen';

    $platformNotify = $pdo->prepare("
        INSERT INTO notifications (user_id, notification_type, title, message, related_type, related_id, dedup_key)
        SELECT u.id, 'kyc_submission_pending', 'Citizen Verification Submitted', ?,
               'kyc_verification', ?, ?
        FROM users u
        INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.role = 'platform_executive_admin'
        WHERE u.deleted_at IS NULL AND u.account_status = 'active'
        ON DUPLICATE KEY UPDATE
            title = VALUES(title), message = VALUES(message), read_at = NULL, created_at = CURRENT_TIMESTAMP
    ");
    $platformNotify->execute([
        $citizenName . ' submitted identity verification for review.',
        $userId,
        'kyc_submission_pending_' . $userId,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    // Clean up the file we already saved so it doesn't become an orphan.
    if (file_exists($destPath)) {
        unlink($destPath);
    }
    error_log('KYC submit failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Something went wrong while saving your submission. Please try again.']);
    exit;
}

// Only count it against the 3-per-24h limit once it actually lands in the
// review queue. Requests that got rejected earlier by validation (bad
// birthdate, wrong ID type, unreadable image, etc.) never reach this line,
// so users can freely fix and resubmit without burning attempts.
recordAttempt($pdo, 'kyc_submit', 'user:' . $userId);

echo json_encode(['message' => 'Submitted for review.']);
