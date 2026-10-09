<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
require_once __DIR__ . '/../../reusables/care_helpers.php';
require_once __DIR__ . '/../../reusables/assignment_helpers.php';
header('Content-Type: application/json');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST') organizationJsonError(405,'Method not allowed.');
$ctx=requireOrganizationAccess($pdo,null);$body=json_decode(file_get_contents('php://input'),true)?:[];$handoffId=(int)($body['handoff_id']??0);$next=strtolower(trim((string)($body['status']??'')));$note=mb_substr(trim((string)($body['note']??'')),0,500);
if($handoffId<=0||!in_array($next,['accepted','declined','completed'],true)) organizationJsonError(422,'Choose a valid handoff action.');
if(!organizationHasPermission($pdo,$ctx,$next==='completed'?'hospital.handoffs.approve':'hospital.handoffs.update')) organizationJsonError(403,'You do not have permission to update this handoff.');
try{
  $pdo->beginTransaction();
  $stmt=$pdo->prepare("SELECT ph.*,da.organization_id,da.assignment_status,er.reference_number,er.requester_user_id,mf.name facility_name FROM patient_handoffs ph JOIN dispatch_assignments da ON da.id=ph.dispatch_assignment_id JOIN emergency_requests er ON er.id=ph.emergency_request_id JOIN medical_facilities mf ON mf.id=ph.medical_facility_id WHERE ph.id=? AND da.organization_id=? LIMIT 1 FOR UPDATE");
  $stmt->execute([$handoffId,(int)$ctx['organization_id']]);$h=$stmt->fetch(PDO::FETCH_ASSOC);if(!$h) throw new RuntimeException('Handoff not found.');
  if($ctx['user_role']!=='organization_admin'&&!careUserAssignedToMission($pdo,$ctx,(int)$h['dispatch_assignment_id'])&&!organizationHasPermission($pdo,$ctx,'dispatch.dispatch_assignments.update')) throw new RuntimeException('Only assigned responders or dispatch staff can update this handoff.');
  $allowed=['pending'=>['accepted','declined'],'accepted'=>['completed']];if(!in_array($next,$allowed[$h['status']]??[],true)) throw new RuntimeException('That handoff transition is not allowed.');
  if($next==='completed'&&!in_array($h['assignment_status'],['transporting','on_scene'],true)) throw new RuntimeException('The mission must be on scene or transporting before physical handoff can be completed.');
  $sets=['status=?'];$params=[$next];
  if(in_array($next,['accepted','declined'],true)){$sets[]='responded_at=NOW()';}
  if($next==='completed'){$sets[]='completed_at=NOW()';}
  if($note!==''){$sets[]='notes=?';$params[]=$note;}
  $params[]=$handoffId;$pdo->prepare('UPDATE patient_handoffs SET '.implode(',',$sets).' WHERE id=?')->execute($params);
  if($next==='completed'&&!empty($h['requester_user_id'])) assignmentNotifyUser($pdo,(int)$h['requester_user_id'],'dispatch_update','Patient Handoff Completed','The response team completed patient handoff at '.$h['facility_name'].' for '.$h['reference_number'].'.','emergency_request',(int)$h['emergency_request_id'],'handoff-completed:'.$handoffId);
  // DASCARE app (no-op without Firebase): guest phones and linked (dedup) reports following this incident.
  if($next==='completed') pushQueueRequestFollowers($pdo,(int)$h['emergency_request_id'],'Patient Handoff Completed','The response team completed patient handoff at '.$h['facility_name'].' for '.$h['reference_number'].'.');
  careAudit($pdo,$ctx,'care.handoff_'.$next,'patient_handoff',$handoffId,['status'=>$h['status']],['status'=>$next,'note'=>$note]);
  $pdo->commit();echo json_encode(['success'=>true,'message'=>$next==='completed'?'Physical handoff recorded. The mission can now be completed.':'Facility response recorded.']);
}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();organizationJsonError(409,$e->getMessage());}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Handoff status failed: '.$e->getMessage());organizationJsonError(500,'Unable to update facility handoff.');}
