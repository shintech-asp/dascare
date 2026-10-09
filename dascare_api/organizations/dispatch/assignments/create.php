<?php
require_once __DIR__ . '/../../../cors.php';
require_once __DIR__ . '/../../../db/db.php';
require_once __DIR__ . '/../../../reusables/organization_rbac.php';
require_once __DIR__ . '/../../../reusables/assignment_helpers.php';
require_once __DIR__ . '/../../../reusables/fleet_helpers.php';
require_once __DIR__ . '/../../../reusables/push.php';
require_once __DIR__ . '/../../../reusables/realtime.php';
require_once __DIR__ . '/../../../reusables/routing.php';
header('Content-Type: application/json');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST') organizationJsonError(405,'Method not allowed.');
$ctx=requireOrganizationAccess($pdo,'dispatch.dispatch_assignments.update');
if(($ctx['user_role']??'')!=='organization_admin' && !organizationHasPermission($pdo,$ctx,'dispatch.crew_assignments.create')) organizationJsonError(403,'You also need permission to assign crew.');
$body=json_decode(file_get_contents('php://input'),true)?:[];
$requestId=(int)($body['emergency_request_id']??0);$ambulanceId=(int)($body['ambulance_id']??0);$crew=is_array($body['crew']??null)?$body['crew']:[];
$validRoles=['driver','team_leader','emt','paramedic','rescuer'];
$normalized=[];
foreach($crew as $item){$mid=(int)($item['organization_member_id']??0);$role=strtolower((string)($item['crew_role']??''));if($mid>0&&in_array($role,$validRoles,true))$normalized[$mid]=$role;}
if($requestId<=0||$ambulanceId<=0) organizationJsonError(422,'Choose an incident and ambulance.');
if(count($normalized)<2) organizationJsonError(422,'Assign at least a driver and one response crew member.');
if(count(array_filter($normalized,fn($r)=>$r==='driver'))!==1) organizationJsonError(422,'Exactly one crew member must be assigned as driver.');
try{
  $pdo->beginTransaction();
  $offerStmt=$pdo->prepare("SELECT io.id,io.recommendation_id,dr.total_score,dr.explanation,er.status,er.reference_number,er.requester_user_id FROM incident_offers io JOIN dss_recommendations dr ON dr.id=io.recommendation_id JOIN emergency_requests er ON er.id=io.emergency_request_id WHERE io.emergency_request_id=? AND io.organization_id=? AND io.offer_status='accepted' ORDER BY io.responded_at DESC LIMIT 1 FOR UPDATE");
  $offerStmt->execute([$requestId,$ctx['organization_id']]);$offer=$offerStmt->fetch(PDO::FETCH_ASSOC);if(!$offer) throw new RuntimeException('This organization has not accepted that incident.');
  $exists=$pdo->prepare('SELECT id FROM dispatch_assignments WHERE emergency_request_id=? LIMIT 1 FOR UPDATE');$exists->execute([$requestId]);if($exists->fetchColumn()) throw new RuntimeException('Resources have already been assigned to this incident.');
  $amb=assignmentAmbulanceEligibility($pdo,(int)$ctx['organization_id'],$ambulanceId,true);if(!$amb||!$amb['eligible']) throw new RuntimeException('The selected ambulance is no longer dispatch-ready. Refresh the assignment page.');

  $ids=array_keys($normalized);$ph=implode(',',array_fill(0,count($ids),'?'));$params=array_merge([(int)$ctx['organization_id']],$ids);
  $pstmt=$pdo->prepare("SELECT om.id,om.user_id,om.membership_status,u.account_status,pp.availability_status FROM organization_members om JOIN users u ON u.id=om.user_id JOIN personnel_profiles pp ON pp.organization_member_id=om.id WHERE om.organization_id=? AND om.id IN ($ph) AND om.deleted_at IS NULL FOR UPDATE");$pstmt->execute($params);$rows=$pstmt->fetchAll(PDO::FETCH_ASSOC);
  if(count($rows)!==count($ids)) throw new RuntimeException('One or more selected crew members are invalid.');
  foreach($rows as $row){if($row['membership_status']!=='active'||$row['account_status']!=='active'||$row['availability_status']!=='available'||assignmentMemberHasActiveMission($pdo,(int)$row['id'])) throw new RuntimeException('One or more selected crew members are no longer available.');}

  $insert=$pdo->prepare("INSERT INTO dispatch_assignments (emergency_request_id,incident_offer_id,organization_id,ambulance_id,assigned_by_user_id,assignment_status,dss_score,dss_explanation,assigned_at) VALUES (?,?,?,?,?,'assigned',?,?,NOW())");
  $insert->execute([$requestId,(int)$offer['id'],(int)$ctx['organization_id'],$ambulanceId,(int)$ctx['user_id'],$offer['total_score'],$offer['explanation']]);$assignmentId=(int)$pdo->lastInsertId();
  $cstmt=$pdo->prepare("INSERT INTO crew_assignments (dispatch_assignment_id,organization_member_id,crew_role,response_status,assigned_at) VALUES (?,?,?,'assigned',NOW())");
  $avail=$pdo->prepare("UPDATE personnel_profiles SET availability_status='assigned' WHERE organization_member_id=?");
  $alog=$pdo->prepare("INSERT INTO personnel_availability_logs (organization_member_id,old_status,new_status,changed_by_user_id,notes) VALUES (?,'available','assigned',?,?)");
  foreach($normalized as $mid=>$role){$cstmt->execute([$assignmentId,$mid,$role]);$avail->execute([$mid]);$alog->execute([$mid,(int)$ctx['user_id'],'Assigned to '.$offer['reference_number'].'.']);}
  $pdo->prepare("UPDATE ambulances SET status='reserved' WHERE id=?")->execute([$ambulanceId]);
  fleetStatusLog($pdo,$ambulanceId,'available','reserved',(int)$ctx['user_id'],'Reserved for '.$offer['reference_number'].'.');
  $old=$offer['status'];$pdo->prepare("UPDATE emergency_requests SET status='assigned',assigned_at=NOW() WHERE id=?")->execute([$requestId]);
  $pdo->prepare("INSERT INTO emergency_request_status_logs (emergency_request_id,old_status,new_status,changed_by_user_id,notes) VALUES (?,?,'assigned',?,?)")->execute([$requestId,$old,(int)$ctx['user_id'],'Ambulance '.$amb['unit_code'].' and response crew assigned.']);
  assignmentLog($pdo,$assignmentId,null,'assigned',(int)$ctx['user_id'],'Ambulance '.$amb['unit_code'].' and '.count($normalized).' crew assigned.');

  if(!empty($offer['requester_user_id'])) assignmentNotifyUser($pdo,(int)$offer['requester_user_id'],'dispatch_update','Ambulance Assigned','Ambulance '.$amb['unit_code'].' and a response crew have been assigned to '.$offer['reference_number'].'.','emergency_request',$requestId,'assignment-citizen:'.$assignmentId);
  // DASCARE app (no-op without Firebase): guest phones and linked (dedup) reports following this incident.
  pushQueueRequestFollowers($pdo,$requestId,'Ambulance Assigned','Ambulance '.$amb['unit_code'].' and a response crew have been assigned to '.$offer['reference_number'].'.','assigned');
  $userByMember=[];foreach($rows as $r)$userByMember[(int)$r['id']]=(int)$r['user_id'];
  foreach($normalized as $mid=>$role) assignmentNotifyUser($pdo,$userByMember[$mid],'mission_assignment','New Mission Assignment','You were assigned as '.str_replace('_',' ',$role).' for '.$offer['reference_number'].'.','dispatch_assignment',$assignmentId,'assignment-crew:'.$assignmentId.':'.$mid);
  $audit=$pdo->prepare("INSERT INTO audit_logs (user_id,organization_id,action,entity_type,entity_id,new_values,ip_address,user_agent) VALUES (?,?, 'dispatch.resources_assigned','dispatch_assignment',?,?,?,?)");
  $audit->execute([(int)$ctx['user_id'],(int)$ctx['organization_id'],$assignmentId,json_encode(['request_id'=>$requestId,'ambulance_id'=>$ambulanceId,'crew'=>$normalized]),$_SERVER['REMOTE_ADDR']??null,mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255)]);
  realtimeRequestChanged($pdo,$requestId,'mission.updated',['assignment_id'=>$assignmentId]);$pdo->commit();routingRefreshAndAnnounceLater($pdo,$assignmentId);/* route + ETA from the unit's current position */echo json_encode(['success'=>true,'assignment_id'=>$assignmentId,'message'=>'Ambulance and crew assigned. The mission is ready for acknowledgement.']);
}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();organizationJsonError(409,$e->getMessage());}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Create assignment failed: '.$e->getMessage());organizationJsonError(500,'Unable to assign resources.');}
