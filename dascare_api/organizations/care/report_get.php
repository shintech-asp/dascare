<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
header('Content-Type: application/json');
$ctx=requireOrganizationAccess($pdo,'incidents.incident_reports.read');
$requestId=(int)($_GET['request_id']??0);
if($requestId<=0) organizationJsonError(422,'Choose an incident.');
try{
  $check=$pdo->prepare('SELECT 1 FROM dispatch_assignments WHERE emergency_request_id=? AND organization_id=? LIMIT 1');$check->execute([$requestId,(int)$ctx['organization_id']]);if(!$check->fetchColumn()) organizationJsonError(404,'Incident not found for this organization.');
  $stmt=$pdo->prepare("SELECT ir.*,mf.name destination_facility_name,CONCAT(u.first_name,' ',u.last_name) prepared_by FROM incident_reports ir LEFT JOIN medical_facilities mf ON mf.id=ir.destination_facility_id JOIN users u ON u.id=ir.prepared_by_user_id WHERE ir.emergency_request_id=? AND ir.organization_id=? ORDER BY ir.id DESC LIMIT 1");$stmt->execute([$requestId,(int)$ctx['organization_id']]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
  if($row)$row['id']=(int)$row['id'];
  echo json_encode(['success'=>true,'report'=>$row?:null]);
}catch(Throwable $e){error_log('Report get failed: '.$e->getMessage());organizationJsonError(500,'Unable to load field report.');}
