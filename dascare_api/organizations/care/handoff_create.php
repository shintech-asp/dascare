<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
require_once __DIR__ . '/../../reusables/care_helpers.php';
require_once __DIR__ . '/../../reusables/routing.php';
header('Content-Type: application/json');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST') organizationJsonError(405,'Method not allowed.');
$ctx=requireOrganizationAccess($pdo,null);$body=json_decode(file_get_contents('php://input'),true)?:[];
$assignmentId=(int)($body['assignment_id']??0);$facilityId=(int)($body['medical_facility_id']??0);$eta=(int)($body['eta_minutes']??0);$notes=mb_substr(trim((string)($body['notes']??'')),0,500);
if($assignmentId<=0||$facilityId<=0) organizationJsonError(422,'Choose a mission and receiving facility.');
$assignment=requireCareMissionAccess($pdo,$ctx,$assignmentId,'hospital.handoffs.create',true);
if(!in_array($assignment['assignment_status'],['on_scene','transporting'],true)) organizationJsonError(409,'Create a facility endorsement after responders are on scene.');
if($eta<0||$eta>360) $eta=0;
try{
  $facility=$pdo->prepare("SELECT id,name FROM medical_facilities WHERE id=? AND status='active' LIMIT 1");$facility->execute([$facilityId]);$f=$facility->fetch(PDO::FETCH_ASSOC);if(!$f) throw new RuntimeException('Receiving facility is not available.');
  $open=$pdo->prepare("SELECT id,status FROM patient_handoffs WHERE dispatch_assignment_id=? AND status IN ('pending','accepted') ORDER BY id DESC LIMIT 1");$open->execute([$assignmentId]);if($open->fetch()) throw new RuntimeException('This mission already has an active facility handoff.');
  $stmt=$pdo->prepare("INSERT INTO patient_handoffs (emergency_request_id,dispatch_assignment_id,medical_facility_id,initiated_by_user_id,status,eta_minutes,notes) VALUES (?,?,?,?,'pending',?,?)");
  $stmt->execute([(int)$assignment['emergency_request_id'],$assignmentId,$facilityId,(int)$ctx['user_id'],$eta?:null,$notes?:null]);$id=(int)$pdo->lastInsertId();
  careAudit($pdo,$ctx,'care.handoff_created','patient_handoff',$id,null,['assignment_id'=>$assignmentId,'facility_id'=>$facilityId,'facility'=>$f['name']]);
  routingRefreshAndAnnounceLater($pdo,$assignmentId); // a facility to drive to while transporting
  echo json_encode(['success'=>true,'handoff_id'=>$id,'message'=>'Facility endorsement created. Record the facility response when available.']);
}catch(RuntimeException $e){organizationJsonError(409,$e->getMessage());}catch(Throwable $e){error_log('Handoff create failed: '.$e->getMessage());organizationJsonError(500,'Unable to create facility handoff.');}
