<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
header('Content-Type: application/json');
$ctx=requireOrganizationAccess($pdo,'hospital.handoffs.read');
$assignmentId=(int)($_GET['assignment_id']??0);
if($assignmentId<=0) organizationJsonError(422,'Choose a mission.');
try{
  $check=$pdo->prepare('SELECT 1 FROM dispatch_assignments WHERE id=? AND organization_id=? LIMIT 1');$check->execute([$assignmentId,(int)$ctx['organization_id']]);if(!$check->fetchColumn()) organizationJsonError(404,'Mission not found.');
  $stmt=$pdo->prepare("SELECT ph.*,mf.name facility_name,mf.address_text facility_address,mf.phone facility_phone,mf.latitude,mf.longitude,
      CONCAT(u.first_name,' ',u.last_name) initiated_by
      FROM patient_handoffs ph
      JOIN medical_facilities mf ON mf.id=ph.medical_facility_id
      JOIN users u ON u.id=ph.initiated_by_user_id
      WHERE ph.dispatch_assignment_id=? ORDER BY ph.created_at DESC,ph.id DESC");
  $stmt->execute([$assignmentId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach($rows as &$r){$r['id']=(int)$r['id'];$r['medical_facility_id']=(int)$r['medical_facility_id'];$r['eta_minutes']=$r['eta_minutes']!==null?(int)$r['eta_minutes']:null;$r['latitude']=(float)$r['latitude'];$r['longitude']=(float)$r['longitude'];}unset($r);
  echo json_encode(['success'=>true,'handoffs'=>$rows]);
}catch(Throwable $e){error_log('Handoff list failed: '.$e->getMessage());organizationJsonError(500,'Unable to load facility handoffs.');}
