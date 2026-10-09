<?php
/**
 * citizen/medical_records/get.php
 *
 * Powers MedicalRecords.vue. Returns the signed-in citizen's own
 * medical_records row (one row per user_id, same one-to-one pattern
 * as kyc_verifications). Guests never see this page — same session.php
 * bootstrap (CORS + cookie session + $pdo) as the rest of the API.
 *
 * Response shape:
 *   { success: true, has_record: bool, record: {...} | null }
 * `record` is null (has_record: false) the first time a citizen opens
 * the page, before they've saved anything — the frontend renders the
 * form with empty defaults in that case, not an error.
 */

require_once __DIR__ . '/../session.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['user_level'] ?? '') !== 'citizen') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in to view your medical records.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT
            blood_type, allergies, medications, medical_conditions,
            disabilities_mobility_notes, organ_donor,
            emergency_contact_name, emergency_contact_phone, emergency_contact_relationship,
            primary_physician_name, primary_physician_phone,
            insurance_provider, insurance_policy_number,
            additional_notes, updated_at
        FROM medical_records
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        echo json_encode(['success' => true, 'has_record' => false, 'record' => null]);
        exit;
    }

    $record['organ_donor'] = (bool) $record['organ_donor'];

    echo json_encode(['success' => true, 'has_record' => true, 'record' => $record]);
} catch (PDOException $e) {
    error_log('citizen/medical_records/get.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load your medical records. Please try again.']);
}
