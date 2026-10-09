<?php
require_once __DIR__ . '/organization_guard.php';
require_once __DIR__ . '/realtime.php';

function fleetBody(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) organizationJsonError(422, 'Invalid request body.');
    return $data;
}

function fleetOwnedAmbulance(PDO $pdo, int $organizationId, int $ambulanceId, bool $forUpdate = false): array
{
    if ($ambulanceId <= 0) organizationJsonError(422, 'Invalid ambulance.');
    $sql = 'SELECT * FROM ambulances WHERE id = ? AND organization_id = ? AND deleted_at IS NULL LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ambulanceId, $organizationId]);
    $ambulance = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ambulance) organizationJsonError(404, 'Ambulance not found.');
    return $ambulance;
}

function fleetAudit(PDO $pdo, array $ctx, string $action, string $entityType, ?int $entityId, ?array $oldValues, ?array $newValues): void
{
    $stmt = $pdo->prepare("\n        INSERT INTO audit_logs\n            (user_id, organization_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)\n        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)\n    ");
    $stmt->execute([
        (int) $ctx['user_id'], (int) $ctx['organization_id'], $action, $entityType, $entityId,
        $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
    // Every fleet change is audited here, so this is where it's announced live.
    realtimeFleetChanged($pdo, (int) $ctx['organization_id'], $entityType === 'ambulance' ? $entityId : null);
}

function fleetStatusLog(PDO $pdo, int $ambulanceId, ?string $oldStatus, string $newStatus, int $userId, ?string $reason = null): void
{
    $stmt = $pdo->prepare('INSERT INTO ambulance_status_logs (ambulance_id, old_status, new_status, changed_by_user_id, reason) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$ambulanceId, $oldStatus, $newStatus, $userId, $reason !== '' ? $reason : null]);
}

function fleetCredentialState(array $ambulance): array
{
    $today = date('Y-m-d');
    $expired = [];
    if (!empty($ambulance['registration_expiry']) && $ambulance['registration_expiry'] < $today) $expired[] = 'registration';
    if (!empty($ambulance['inspection_expiry']) && $ambulance['inspection_expiry'] < $today) $expired[] = 'inspection';
    return ['valid' => !$expired, 'expired' => $expired];
}

function fleetLatestReadiness(PDO $pdo, int $ambulanceId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM ambulance_readiness_checks WHERE ambulance_id = ? ORDER BY checked_at DESC, id DESC LIMIT 1');
    $stmt->execute([$ambulanceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function fleetHasOpenMaintenance(PDO $pdo, int $ambulanceId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM ambulance_maintenance_records WHERE ambulance_id = ? AND status = 'in_progress' LIMIT 1");
    $stmt->execute([$ambulanceId]);
    return (bool) $stmt->fetchColumn();
}

function fleetValidateUnitPayload(array $data): array
{
    $unitCode = strtoupper(trim((string) ($data['unit_code'] ?? '')));
    $plate = strtoupper(trim((string) ($data['plate_number'] ?? '')));
    $type = trim((string) ($data['ambulance_type'] ?? ''));
    $makeModel = trim(strip_tags((string) ($data['vehicle_make_model'] ?? '')));
    $year = ($data['model_year'] ?? '') !== '' ? (int) $data['model_year'] : null;
    $capacity = (int) ($data['capacity'] ?? 1);
    $capability = trim(strip_tags((string) ($data['capability_notes'] ?? '')));
    $registrationExpiry = trim((string) ($data['registration_expiry'] ?? '')) ?: null;
    $inspectionExpiry = trim((string) ($data['inspection_expiry'] ?? '')) ?: null;

    if (!preg_match('/^[A-Z0-9][A-Z0-9\-_ ]{1,49}$/', $unitCode)) organizationJsonError(422, 'Enter a valid unit code.');
    if ($plate === '' || mb_strlen($plate) > 30) organizationJsonError(422, 'Enter a valid plate number.');
    $types = ['basic_life_support','advanced_life_support','patient_transport','rescue_unit'];
    if (!in_array($type, $types, true)) organizationJsonError(422, 'Choose a valid ambulance type.');
    if ($makeModel !== '' && mb_strlen($makeModel) > 120) organizationJsonError(422, 'Vehicle make/model is too long.');
    $currentYear = (int) date('Y');
    if ($year !== null && ($year < 1980 || $year > $currentYear + 1)) organizationJsonError(422, 'Enter a valid model year.');
    if ($capacity < 1 || $capacity > 20) organizationJsonError(422, 'Capacity must be between 1 and 20.');
    if (mb_strlen($capability) > 2000) organizationJsonError(422, 'Capability notes are too long.');
    foreach ([$registrationExpiry, $inspectionExpiry] as $dateValue) {
        if ($dateValue !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) organizationJsonError(422, 'Use valid credential dates.');
    }

    return [
        'unit_code' => $unitCode,
        'plate_number' => $plate,
        'ambulance_type' => $type,
        'vehicle_make_model' => $makeModel ?: null,
        'model_year' => $year,
        'capacity' => $capacity,
        'capability_notes' => $capability ?: null,
        'registration_expiry' => $registrationExpiry,
        'inspection_expiry' => $inspectionExpiry,
    ];
}
