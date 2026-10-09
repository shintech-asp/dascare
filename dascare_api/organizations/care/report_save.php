<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
require_once __DIR__ . '/../../reusables/care_helpers.php';
header('Content-Type: application/json');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST') organizationJsonError(405,'Method not allowed.');
$ctx=requireOrganizationAccess($pdo,null);$body=json_decode(file_get_contents('php://input'),true)?:[];$assignmentId=(int)($body['assignment_id']??0);$submit=!empty($body['submit']);
if($assignmentId<=0) organizationJsonError(422,'Choose a mission.');
$permission=$submit?'incidents.incident_reports.update':'incidents.incident_reports.create';
if(!organizationHasPermission($pdo,$ctx,$permission)&&!organizationHasPermission($pdo,$ctx,'incidents.incident_reports.update')) organizationJsonError(403,'You do not have permission to save a field report.');
$assignment=careFindAssignment($pdo,$ctx,$assignmentId,false);if(!$assignment) organizationJsonError(404,'Mission not found.');
if($ctx['user_role']!=='organization_admin'&&!careUserAssignedToMission($pdo,$ctx,$assignmentId)&&!organizationHasPermission($pdo,$ctx,'dispatch.dispatch_assignments.update')) organizationJsonError(403,'Only assigned responders or dispatch staff can document this mission.');
$summary=trim((string)($body['incident_summary']??''));$actions=trim((string)($body['actions_taken']??''));$outcome=strtolower((string)($body['outcome']??'other'));$valid=['treated_on_scene','transported','refused_transport','no_patient_found','deceased','other'];if(!in_array($outcome,$valid,true))$outcome='other';
$facilityId=(int)($body['destination_facility_id']??0);if($facilityId<=0)$facilityId=null;
if($summary===''||$actions==='') organizationJsonError(422,'Incident summary and actions taken are required.');
try{
  $pdo->beginTransaction();
  $existing=$pdo->prepare("SELECT * FROM incident_reports WHERE emergency_request_id=? AND organization_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE");$existing->execute([(int)$assignment['emergency_request_id'],(int)$ctx['organization_id']]);$old=$existing->fetch(PDO::FETCH_ASSOC);
  if($old&&$old['report_status']==='locked') throw new RuntimeException('This field report is locked.');
  $status=$submit?'submitted':'draft';
  if($old){
    $stmt=$pdo->prepare("UPDATE incident_reports SET destination_facility_id=?,incident_summary=?,actions_taken=?,outcome=?,report_status=?,submitted_at=IF(?='submitted',COALESCE(submitted_at,NOW()),submitted_at),updated_at=NOW() WHERE id=?");
    $stmt->execute([$facilityId,$summary,$actions,$outcome,$status,$status,(int)$old['id']]);$id=(int)$old['id'];
  }else{
    $stmt=$pdo->prepare("INSERT INTO incident_reports (emergency_request_id,organization_id,prepared_by_user_id,destination_facility_id,incident_summary,actions_taken,outcome,report_status,submitted_at) VALUES (?,?,?,?,?,?,?,?,IF(?='submitted',NOW(),NULL))");
    $stmt->execute([(int)$assignment['emergency_request_id'],(int)$ctx['organization_id'],(int)$ctx['user_id'],$facilityId,$summary,$actions,$outcome,$status,$status]);$id=(int)$pdo->lastInsertId();
  }
  careAudit($pdo,$ctx,'care.field_report_saved','incident_report',$id,$old?['status'=>$old['report_status']]:null,['status'=>$status,'assignment_id'=>$assignmentId]);
  $pdo->commit();echo json_encode(['success'=>true,'report_id'=>$id,'message'=>$submit?'Field report submitted.':'Field report draft saved.']);
}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();organizationJsonError(409,$e->getMessage());}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Report save failed: '.$e->getMessage());organizationJsonError(500,'Unable to save field report.');}
