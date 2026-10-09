<?php
/**
 * citizen/medical_records/save.php
 *
 * Powers MedicalRecords.vue's Save action. Upserts the signed-in
 * citizen's own medical_records row (one row per user_id — INSERT ...
 * ON DUPLICATE KEY UPDATE against the user_id primary key). Guests
 * never see this page — same session.php bootstrap (CORS + cookie
 * session + $pdo) as the rest of the API.
 *
 * Expects a JSON body. All fields optional/nullable except blood_type,
 * which defaults to 'unknown' if omitted or not recognized.
 */

require_once __DIR__ . '/../session.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['user_level'] ?? '') !== 'citizen') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in to update your medical records.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request body.']);
    exit;
}

// ----------------------------------
// Sanitize / validate
// ----------------------------------
$ALLOWED_BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'unknown'];

$str = static function ($v, int $maxLen = 65535): ?string {
    if (!is_string($v)) return null;
    $v = trim($v);
    if ($v === '') return null;
    return mb_substr($v, 0, $maxLen);
};

$bloodType = strtoupper(trim((string) ($input['blood_type'] ?? '')));
if ($bloodType === '' || strtolower($bloodType) === 'unknown') {
    $bloodType = 'unknown';
} elseif (!in_array($bloodType, $ALLOWED_BLOOD_TYPES, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please choose a valid blood type.']);
    exit;
}

$fields = [
    'blood_type'                     => $bloodType,
    'allergies'                      => $str($input['allergies'] ?? null),
    'medications'                    => $str($input['medications'] ?? null),
    'medical_conditions'             => $str($input['medical_conditions'] ?? null),
    'disabilities_mobility_notes'    => $str($input['disabilities_mobility_notes'] ?? null),
    'organ_donor'                    => !empty($input['organ_donor']) ? 1 : 0,
    'emergency_contact_name'         => $str($input['emergency_contact_name'] ?? null, 160),
    'emergency_contact_phone'        => $str($input['emergency_contact_phone'] ?? null, 30),
    'emergency_contact_relationship' => $str($input['emergency_contact_relationship'] ?? null, 80),
    'primary_physician_name'         => $str($input['primary_physician_name'] ?? null, 160),
    'primary_physician_phone'        => $str($input['primary_physician_phone'] ?? null, 30),
    'insurance_provider'             => $str($input['insurance_provider'] ?? null, 160),
    'insurance_policy_number'        => $str($input['insurance_policy_number'] ?? null, 100),
    'additional_notes'               => $str($input['additional_notes'] ?? null),
];

try {
    $stmt = $pdo->prepare("
        INSERT INTO medical_records (
            user_id, blood_type, allergies, medications, medical_conditions,
            disabilities_mobility_notes, organ_donor,
            emergency_contact_name, emergency_contact_phone, emergency_contact_relationship,
            primary_physician_name, primary_physician_phone,
            insurance_provider, insurance_policy_number, additional_notes
        ) VALUES (
            :user_id, :blood_type, :allergies, :medications, :medical_conditions,
            :disabilities_mobility_notes, :organ_donor,
            :emergency_contact_name, :emergency_contact_phone, :emergency_contact_relationship,
            :primary_physician_name, :primary_physician_phone,
            :insurance_provider, :insurance_policy_number, :additional_notes
        )
        ON DUPLICATE KEY UPDATE
            blood_type = VALUES(blood_type),
            allergies = VALUES(allergies),
            medications = VALUES(medications),
            medical_conditions = VALUES(medical_conditions),
            disabilities_mobility_notes = VALUES(disabilities_mobility_notes),
            organ_donor = VALUES(organ_donor),
            emergency_contact_name = VALUES(emergency_contact_name),
            emergency_contact_phone = VALUES(emergency_contact_phone),
            emergency_contact_relationship = VALUES(emergency_contact_relationship),
            primary_physician_name = VALUES(primary_physician_name),
            primary_physician_phone = VALUES(primary_physician_phone),
            insurance_provider = VALUES(insurance_provider),
            insurance_policy_number = VALUES(insurance_policy_number),
            additional_notes = VALUES(additional_notes)
    ");

    // array_merge, not [...$fields]: unpacking string keys needs PHP 8.1 and
    // XAMPP here runs 8.0 (it was a fatal error — saves never went through).
    $stmt->execute(array_merge(['user_id' => $userId], $fields));

    echo json_encode(['success' => true, 'message' => 'Medical records updated.']);
} catch (PDOException $e) {
    error_log('citizen/medical_records/save.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save your medical records. Please try again.']);
}
