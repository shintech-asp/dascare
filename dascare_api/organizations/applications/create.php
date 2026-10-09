<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/realtime.php';
require_once __DIR__ . '/../../reusables/password_helpers.php';
require_once __DIR__ . '/../../reusables/rate_limit.php';
require_once __DIR__ . '/../../reusables/email_helper.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

function jsonError(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function cleanText(string $value, int $max): string
{
    $value = trim(strip_tags($value));
    if (mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }
    return $value;
}

function normalizeMobile(string $phone): string
{
    $phone = preg_replace('/[\s\-()]/', '', $phone);
    if (preg_match('/^\+639\d{9}$/', $phone)) {
        return '0' . substr($phone, 3);
    }
    return $phone;
}

function cleanupApplication(PDO $pdo, ?int $userId, ?int $organizationId, array $savedFiles): void
{
    foreach ($savedFiles as $path) {
        if (is_string($path) && $path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    try {
        $pdo->beginTransaction();
        if ($organizationId) {
            $pdo->prepare('DELETE FROM organization_members WHERE organization_id = ?')->execute([$organizationId]);
            $pdo->prepare('DELETE FROM organizations WHERE id = ?')->execute([$organizationId]);
        }
        if ($userId) {
            $pdo->prepare('DELETE FROM user_security_tokens WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM users WHERE id = ? AND email_verified_at IS NULL')->execute([$userId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Organization application cleanup failed: ' . $e->getMessage());
    }
}

function validateUpload(array $file, bool $required, string $label): ?array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        if ($required) jsonError(422, $label . ' is required.');
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonError(422, 'Could not upload ' . strtolower($label) . '.');
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > 8 * 1024 * 1024) {
        jsonError(422, $label . ' must be 8MB or smaller.');
    }

    $mime = mime_content_type($file['tmp_name']);
    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        jsonError(422, $label . ' must be a PDF, JPG, PNG, or WEBP file.');
    }
    if (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false) {
        jsonError(422, $label . ' is not a valid image file.');
    }

    return [
        'tmp_name' => $file['tmp_name'],
        'original_name' => cleanText((string) ($file['name'] ?? 'document'), 255),
        'mime' => $mime,
        'extension' => $allowed[$mime],
        'size' => (int) $file['size'],
    ];
}

$ip = getClientIp();
checkRateLimit($pdo, 'organization_apply', $ip);

$firstName = cleanText((string) ($_POST['first_name'] ?? ''), 80);
$lastName = cleanText((string) ($_POST['last_name'] ?? ''), 80);
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone = normalizeMobile((string) ($_POST['phone'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

$organizationName = cleanText((string) ($_POST['organization_name'] ?? ''), 150);
$organizationType = trim((string) ($_POST['organization_type'] ?? ''));
$registrationNumber = cleanText((string) ($_POST['registration_number'] ?? ''), 100);
$accreditationBody = cleanText((string) ($_POST['accreditation_body'] ?? ''), 150);
$organizationEmail = strtolower(trim((string) ($_POST['organization_email'] ?? '')));
$organizationPhone = preg_replace('/[\s\-()]/', '', (string) ($_POST['organization_phone'] ?? ''));
$addressLine = cleanText((string) ($_POST['address_line'] ?? ''), 255);
$latitudeRaw = $_POST['latitude'] ?? '';
$longitudeRaw = $_POST['longitude'] ?? '';
$serviceAreasRaw = $_POST['service_areas'] ?? '[]';
$attested = (string) ($_POST['attested'] ?? '') === '1';

if (mb_strlen($firstName) < 2 || mb_strlen($lastName) < 2) jsonError(422, 'Please provide the representative\'s complete name.');
if (!preg_match("/^[A-Za-zÀ-ÖØ-öø-ÿÑñ' -]+$/u", $firstName) || !preg_match("/^[A-Za-zÀ-ÖØ-öø-ÿÑñ' -]+$/u", $lastName)) {
    jsonError(422, 'Representative name contains invalid characters.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError(422, 'Please provide a valid representative email address.');
if (!preg_match('/^09\d{9}$/', $phone)) jsonError(422, 'Please provide a valid PH mobile number for the representative.');

$passwordError = passwordStrengthError($password, [$email, $firstName, $lastName, $organizationName]);
if ($passwordError !== null) jsonError(422, $passwordError);

if (mb_strlen($organizationName) < 3) jsonError(422, 'Organization name must be at least 3 characters.');
$allowedTypes = ['city_rescue', 'barangay_rescue', 'hospital', 'private_ambulance', 'other'];
if (!in_array($organizationType, $allowedTypes, true)) jsonError(422, 'Please select a valid organization type.');
if ($organizationEmail !== '' && !filter_var($organizationEmail, FILTER_VALIDATE_EMAIL)) jsonError(422, 'Please provide a valid organization email address.');
if ($organizationPhone !== '' && !preg_match('/^\+?[0-9]{7,15}$/', $organizationPhone)) jsonError(422, 'Please provide a valid organization contact number.');
if (mb_strlen($addressLine) < 8) jsonError(422, 'Please provide the organization base/station address.');
if (!$attested) jsonError(422, 'Please confirm that the submitted organization information is accurate.');

if ($latitudeRaw === '' || $longitudeRaw === '' || !is_numeric($latitudeRaw) || !is_numeric($longitudeRaw)) {
    jsonError(422, 'Please pin the organization base/station location on the map.');
}
$latitude = (float) $latitudeRaw;
$longitude = (float) $longitudeRaw;
if ($latitude < 14.26 || $latitude > 14.40 || $longitude < 120.87 || $longitude > 121.00) {
    jsonError(422, 'The organization base/station must be within Dasmariñas City.');
}

$serviceAreasDecoded = json_decode((string) $serviceAreasRaw, true);
if (!is_array($serviceAreasDecoded)) jsonError(422, 'Invalid service area selection.');
$serviceAreas = [];
foreach ($serviceAreasDecoded as $area) {
    $area = cleanText((string) $area, 120);
    if (mb_strlen($area) >= 2) $serviceAreas[$area] = true;
}
$serviceAreas = array_keys($serviceAreas);
if (!$serviceAreas) jsonError(422, 'Select at least one Dasmariñas barangay as a service area.');
if (count($serviceAreas) > 100) jsonError(422, 'Too many service areas were selected.');

$uploads = [
    ['key' => 'registration_document', 'type' => 'registration_document', 'required' => true, 'label' => 'Registration or authorization document'],
    ['key' => 'operating_document', 'type' => 'operating_authority', 'required' => false, 'label' => 'Operating or accreditation document'],
    ['key' => 'supporting_document', 'type' => 'supporting_document', 'required' => false, 'label' => 'Additional supporting document'],
];
$validatedUploads = [];
foreach ($uploads as $definition) {
    $validated = validateUpload($_FILES[$definition['key']] ?? [], $definition['required'], $definition['label']);
    if ($validated) {
        $validated['doc_type'] = $definition['type'];
        $validatedUploads[] = $validated;
    }
}

$stmt = $pdo->prepare('SELECT id, email_verified_at FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$email]);
if ($existing = $stmt->fetch(PDO::FETCH_ASSOC)) {
    jsonError(409, $existing['email_verified_at'] ? 'That email is already registered. Please log in instead.' : 'That email already has a registration awaiting verification. Complete or cancel it before starting another application.');
}
$stmt = $pdo->prepare('SELECT id FROM users WHERE phone = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$phone]);
if ($stmt->fetch()) jsonError(409, 'That representative phone number is already registered.');
$stmt = $pdo->prepare('SELECT id FROM organizations WHERE LOWER(name) = LOWER(?) AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$organizationName]);
if ($stmt->fetch()) jsonError(409, 'An organization with that name already exists or has an application in progress.');

$otp = random_int(100000, 999999);
$otpHash = password_hash((string) $otp, PASSWORD_DEFAULT);
$otpExpiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$savedFiles = [];
$userId = null;
$organizationId = null;

try {
    $pdo->beginTransaction();

    $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password_hash, account_status, email_verified_at) VALUES (?, ?, ?, ?, ?, 'pending', NULL)")
        ->execute([$firstName, $lastName, $email, $phone, $hashedPassword]);
    $userId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, 'organization_admin')")->execute([$userId]);
    $pdo->prepare('INSERT INTO user_security_tokens (user_id, email_otp, email_otp_expires_at) VALUES (?, ?, ?)')
        ->execute([$userId, $otpHash, $otpExpiry]);

    $reference = null;
    for ($i = 0; $i < 8; $i++) {
        $candidate = 'ORG-' . date('Y') . '-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $check = $pdo->prepare('SELECT id FROM organizations WHERE application_reference = ? LIMIT 1');
        $check->execute([$candidate]);
        if (!$check->fetch()) { $reference = $candidate; break; }
    }
    if (!$reference) throw new RuntimeException('Could not generate application reference.');

    $pdo->prepare("INSERT INTO organizations
        (application_reference, name, organization_type, registration_number, accreditation_body, email, phone, address_line, latitude, longitude, status, application_status, primary_admin_user_id, application_submitted_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, NOW())")
        ->execute([
            $reference, $organizationName, $organizationType,
            $registrationNumber !== '' ? $registrationNumber : null,
            $accreditationBody !== '' ? $accreditationBody : null,
            $organizationEmail !== '' ? $organizationEmail : $email,
            $organizationPhone !== '' ? $organizationPhone : $phone,
            $addressLine, $latitude, $longitude, $userId,
        ]);
    $organizationId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO organization_members (organization_id, user_id, is_default_password, membership_status, joined_at) VALUES (?, ?, 0, 'active', NULL)")
        ->execute([$organizationId, $userId]);

    $serviceStmt = $pdo->prepare('INSERT INTO organization_service_areas (organization_id, barangay) VALUES (?, ?)');
    foreach ($serviceAreas as $area) $serviceStmt->execute([$organizationId, $area]);

    $storageDir = __DIR__ . '/../../storage/organization-documents/';
    if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
        throw new RuntimeException('Could not create organization document storage.');
    }

    $documentStmt = $pdo->prepare("INSERT INTO organization_documents
        (organization_id, doc_type, file_path, original_name, mime_type, file_size, status, uploaded_by_user_id)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");

    foreach ($validatedUploads as $upload) {
        $filename = 'org_' . $organizationId . '_' . bin2hex(random_bytes(16)) . '.' . $upload['extension'];
        $destination = $storageDir . $filename;
        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            throw new RuntimeException('Could not store organization document.');
        }
        $savedFiles[] = $destination;
        $documentStmt->execute([
            $organizationId, $upload['doc_type'], $filename,
            $upload['original_name'], $upload['mime'], $upload['size'], $userId,
        ]);
    }

    $pdo->prepare("INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, new_values, ip_address, user_agent)
        VALUES (?, ?, 'organization.application_submitted', 'organization', ?, ?, ?, ?)")
        ->execute([
            $userId,
            $organizationId,
            $organizationId,
            json_encode(['application_reference' => $reference, 'application_status' => 'pending', 'organization_name' => $organizationName], JSON_UNESCAPED_UNICODE),
            $ip,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);

    // Surface new applications in every active Platform Executive account.
    // The generic notification table is user-scoped, so each executive gets
    // their own unread state without introducing a second notification model.
    $platformNotify = $pdo->prepare("
        INSERT INTO notifications (user_id, notification_type, title, message, related_type, related_id, dedup_key)
        SELECT u.id, 'organization_application_submitted', 'New Organization Application', ?,
               'organization_application', ?, ?
        FROM users u
        INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.role = 'platform_executive_admin'
        WHERE u.deleted_at IS NULL AND u.account_status = 'active'
        ON DUPLICATE KEY UPDATE
            title = VALUES(title), message = VALUES(message), read_at = NULL, created_at = CURRENT_TIMESTAMP
    ");
    $platformNotify->execute([
        $organizationName . ' submitted an organization application for review.',
        $organizationId,
        'org_application_submitted_' . $organizationId,
    ]);
    realtimeNotifyRole($pdo, 'platform_executive_admin');
    realtimePlatformBadgesChanged($pdo, 'applications');

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($savedFiles as $path) if (is_file($path)) @unlink($path);
    error_log('Organization application creation failed: ' . $e->getMessage());
    recordAttempt($pdo, 'organization_apply', $ip);
    jsonError(500, 'Could not submit the organization application. Please try again.');
}

$body = render_email_template(
    'Organization Application',
    "Verify your<br><span style='color:#c0392b;'>representative email</span>",
    "<div style='font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.8; color:#6b7280; margin:0 0 24px 0;'>
        Your application for <strong>" . htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') . "</strong> has been received. Verify this email to activate the applicant account and track the review status.
     </div>" . render_otp_card((string) $otp) .
    "<div style='margin-top:20px; font-family:Arial, Helvetica, sans-serif; font-size:13px; color:#6b7280;'>Application reference: <strong>" . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . "</strong></div>",
    "<strong style='color:#0f203a;'>This code expires in 10 minutes.</strong><br>Approval is performed separately by the DASCARE Platform Executive after document review."
);

$result = send_email($email, 'Verify Organization Application – DASCARE', $body);
if (!$result['success']) {
    cleanupApplication($pdo, $userId, $organizationId, $savedFiles);
    recordAttempt($pdo, 'organization_apply', $ip);
    jsonError(500, 'The application could not be completed because the verification email failed to send. Please try again.');
}

recordAttempt($pdo, 'organization_apply', $ip);

echo json_encode([
    'success' => true,
    'message' => 'Application submitted. Verify the representative email to continue.',
    'email' => $email,
    'application_reference' => $reference,
]);
