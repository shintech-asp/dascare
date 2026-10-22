<?php
// reusables/incident_report.php
//
// What the requester sent, for the responders: who reported it (the person
// in need, or a witness), the description, attached photos, and — for a
// self-report by a citizen — the medical information snapshot taken at
// submit time. Reports dedup-linked to the incident are included, so
// several witnesses' photos all reach the crew.
//
// Read by organizations/incidents/report.php (an organization with an offer
// or assignment on the request) and platform/incidents/report.php; photos
// are streamed by the matching media.php with the same access check.
require_once __DIR__ . '/upload_paths.php';

/** Can this organization see the request (offer of any status or an assignment, on it or on the incident it was linked to)? */
function incidentReportOrgCanSee(PDO $pdo, int $organizationId, int $requestId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM emergency_requests er
        WHERE er.id = ? AND (
            EXISTS (SELECT 1 FROM incident_offers io WHERE io.emergency_request_id IN (er.id, COALESCE(er.merged_into_request_id, 0)) AND io.organization_id = ?)
            OR EXISTS (SELECT 1 FROM dispatch_assignments da WHERE da.emergency_request_id IN (er.id, COALESCE(er.merged_into_request_id, 0)) AND da.organization_id = ?)
        ) LIMIT 1");
    $stmt->execute([$requestId, $organizationId, $organizationId]);
    return (bool) $stmt->fetchColumn();
}

function incidentReportPhotos(PDO $pdo, int $requestId): array
{
    $stmt = $pdo->prepare('SELECT id, mime_type, file_size, created_at FROM emergency_request_media WHERE emergency_request_id = ? ORDER BY id');
    $stmt->execute([$requestId]);
    return array_map(fn ($m) => ['id' => (int) $m['id'], 'mime_type' => $m['mime_type'], 'size' => (int) $m['file_size'], 'created_at' => $m['created_at']], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

/** The report for one request (+ its linked reports). Null when the request doesn't exist. */
function incidentReportData(PDO $pdo, int $requestId): ?array
{
    $stmt = $pdo->prepare('SELECT id, reference_number, source, reporter_type, requester_user_id, requester_name, requester_phone, description, submitted_at FROM emergency_requests WHERE id = ?');
    $stmt->execute([$requestId]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;

    $medical = null;
    if ($r['reporter_type'] === 'self') {
        $m = $pdo->prepare('SELECT blood_type, allergies, medications, medical_conditions, disabilities_mobility_notes, organ_donor, emergency_contact_name, emergency_contact_phone, emergency_contact_relationship, additional_notes FROM emergency_request_medical_snapshots WHERE emergency_request_id = ? ORDER BY id DESC LIMIT 1');
        $m->execute([$requestId]);
        $medical = $m->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($medical) $medical['organ_donor'] = (bool) $medical['organ_donor'];
    }

    $linked = [];
    $l = $pdo->prepare("SELECT id, reference_number, reporter_type, description, submitted_at FROM emergency_requests WHERE merged_into_request_id = ? ORDER BY submitted_at, id");
    $l->execute([$requestId]);
    foreach ($l->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $linked[] = ['id' => (int) $c['id'], 'reference_number' => $c['reference_number'], 'reporter_type' => $c['reporter_type'], 'description' => $c['description'], 'submitted_at' => $c['submitted_at'], 'photos' => incidentReportPhotos($pdo, (int) $c['id'])];
    }

    return [
        'id' => (int) $r['id'],
        'reference_number' => $r['reference_number'],
        'reporter_type' => $r['reporter_type'],
        'source' => $r['source'],
        'requester_name' => $r['requester_name'],
        'requester_phone' => $r['requester_phone'],
        'description' => $r['description'],
        'submitted_at' => $r['submitted_at'],
        'photos' => incidentReportPhotos($pdo, (int) $r['id']),
        'medical' => $medical,
        'linked' => $linked,
    ];
}

/** Stream one photo after the caller checked access to $requestIdOfMedia (or its primary). */
function incidentReportMediaRow(PDO $pdo, int $mediaId): ?array
{
    $stmt = $pdo->prepare('SELECT m.id, m.emergency_request_id, m.file_path, m.mime_type, er.merged_into_request_id FROM emergency_request_media m JOIN emergency_requests er ON er.id = m.emergency_request_id WHERE m.id = ?');
    $stmt->execute([$mediaId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function incidentReportStreamMedia(array $media): void
{
    $file = dascareUploadsDir('emergency_requests') . basename($media['file_path']);
    if (!is_file($file)) { http_response_code(404); header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => 'Photo not found.']); exit; }
    header('Content-Type: ' . (in_array($media['mime_type'], ['image/jpeg', 'image/png', 'image/webp'], true) ? $media['mime_type'] : 'application/octet-stream'));
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
}
