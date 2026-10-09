<?php
require_once __DIR__ . '/../../../cors.php';
require_once __DIR__ . '/../../../db/db.php';
require_once __DIR__ . '/../../../reusables/organization_rbac.php';
require_once __DIR__ . '/../../../reusables/dispatch_dedup.php';
header('Content-Type: application/json');
$ctx=requireOrganizationAccess($pdo,'dispatch.dispatch_assignments.read');
try{
  $stmt=$pdo->prepare("SELECT da.*,er.reference_number,er.severity,er.description,er.address_text,er.barangay,er.landmark,er.latitude,er.longitude,er.requester_name,er.requester_phone,ec.name category_name,a.unit_code,a.plate_number,a.ambulance_type,a.status ambulance_status
                       FROM dispatch_assignments da JOIN emergency_requests er ON er.id=da.emergency_request_id JOIN emergency_categories ec ON ec.id=er.emergency_category_id JOIN ambulances a ON a.id=da.ambulance_id
                       WHERE da.organization_id=? AND da.assignment_status NOT IN ('cancelled','reassigned') ORDER BY FIELD(da.assignment_status,'assigned','acknowledged','responding','on_scene','transporting','completed'),da.assigned_at DESC LIMIT 100");
  $stmt->execute([$ctx['organization_id']]);$missions=$stmt->fetchAll(PDO::FETCH_ASSOC);$ids=[];
  foreach($missions as &$m){$m['id']=(int)$m['id'];$m['emergency_request_id']=(int)$m['emergency_request_id'];$m['ambulance_id']=(int)$m['ambulance_id'];$m['latitude']=(float)$m['latitude'];$m['longitude']=(float)$m['longitude'];$ids[]=$m['id'];$m['crew']=[];}unset($m);
  if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$c=$pdo->prepare("SELECT ca.dispatch_assignment_id,ca.organization_member_id,ca.crew_role,ca.response_status,ca.acknowledged_at,u.first_name,u.last_name,om.employee_code FROM crew_assignments ca JOIN organization_members om ON om.id=ca.organization_member_id JOIN users u ON u.id=om.user_id WHERE ca.dispatch_assignment_id IN ($ph) ORDER BY FIELD(ca.crew_role,'driver','team_leader','paramedic','emt','rescuer'),u.first_name");$c->execute($ids);$map=[];foreach($c->fetchAll(PDO::FETCH_ASSOC) as $r){$r['organization_member_id']=(int)$r['organization_member_id'];$map[(int)$r['dispatch_assignment_id']][]=$r;}foreach($missions as &$m)$m['crew']=$map[$m['id']]??[];unset($m);}
  $linked=dedupLinkedCounts($pdo,array_column($missions,'emergency_request_id'));foreach($missions as &$m)$m['linked_count']=$linked[$m['emergency_request_id']]??0;unset($m);
  $canDispatch=($ctx['user_role']==='organization_admin')||organizationHasPermission($pdo,$ctx,'dispatch.dispatch_assignments.update');
  $canMission=($ctx['user_role']==='organization_admin')||organizationHasPermission($pdo,$ctx,'dispatch.mission_status.update');
  echo json_encode(['success'=>true,'missions'=>$missions,'can_dispatch'=>$canDispatch,'can_update_mission'=>$canMission,'current_member_id'=>(int)$ctx['organization_member_id']]);
}catch(Throwable $e){error_log('Mission list failed: '.$e->getMessage());organizationJsonError(500,'Unable to load active missions.');}
