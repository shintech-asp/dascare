<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
require_once __DIR__ . '/../../reusables/care_helpers.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405,'Method not allowed.');
$ctx = requireOrganizationAccess($pdo, null);
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$assignmentId = (int) ($body['assignment_id'] ?? 0);
if ($assignmentId <= 0) organizationJsonError(422,'Choose an active mission.');
$assignment = requireCareMissionAccess($pdo,$ctx,$assignmentId,'incidents.patient_assessments.create',true);
if (in_array($assignment['assignment_status'], ['completed','cancelled','reassigned'], true)) organizationJsonError(409,'This mission is already closed.');

$patientName = mb_substr(trim((string)($body['patient_name'] ?? '')),0,160);
$ageRaw = $body['approximate_age'] ?? null;
$age = ($ageRaw === '' || $ageRaw === null) ? null : (int)$ageRaw;
if ($age !== null && ($age < 0 || $age > 120)) organizationJsonError(422,'Approximate age must be between 0 and 120.');
$sex = strtolower((string)($body['sex'] ?? 'unknown'));
if (!in_array($sex,['male','female','unknown'],true)) $sex='unknown';
$consciousness = strtolower((string)($body['consciousness'] ?? 'unknown'));
if (!in_array($consciousness,['alert','responsive_to_voice','responsive_to_pain','unresponsive','unknown'],true)) $consciousness='unknown';
$breathing = mb_substr(trim((string)($body['breathing_status'] ?? '')),0,120);
$summary = trim((string)($body['condition_summary'] ?? ''));
$injuries = trim((string)($body['injuries'] ?? ''));
if ($summary === '') organizationJsonError(422,'Condition summary is required.');
$vitals = is_array($body['vital_signs'] ?? null) ? $body['vital_signs'] : [];
$cleanVitals=[];
foreach (['pulse_bpm','respiratory_rate','blood_pressure','spo2_percent','temperature_c'] as $key) {
    $val = $vitals[$key] ?? null;
    if ($val !== null && $val !== '') $cleanVitals[$key] = mb_substr(trim((string)$val),0,40);
}

try {
    $stmt=$pdo->prepare("INSERT INTO patient_assessments
        (emergency_request_id,recorded_by_user_id,patient_name,approximate_age,sex,consciousness,breathing_status,condition_summary,injuries,vital_signs_json,assessed_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
    $stmt->execute([
        (int)$assignment['emergency_request_id'],(int)$ctx['user_id'],$patientName?:null,$age,$sex,$consciousness,
        $breathing?:null,$summary,$injuries?:null,$cleanVitals?json_encode($cleanVitals,JSON_UNESCAPED_UNICODE):null
    ]);
    $id=(int)$pdo->lastInsertId();
    careAudit($pdo,$ctx,'care.assessment_created','patient_assessment',$id,null,['assignment_id'=>$assignmentId,'request_id'=>(int)$assignment['emergency_request_id']]);
    echo json_encode(['success'=>true,'assessment_id'=>$id,'message'=>'Patient assessment recorded.']);
} catch(Throwable $e){
    error_log('Assessment create failed: '.$e->getMessage());
    organizationJsonError(500,'Unable to save patient assessment.');
}
