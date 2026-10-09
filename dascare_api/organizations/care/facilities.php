<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo,'hospital.handoffs.read');
try{
  $rows=$pdo->query("SELECT id,name,facility_type,address_text,phone,latitude,longitude,capabilities FROM medical_facilities WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
  foreach($rows as &$r){$r['id']=(int)$r['id'];$r['latitude']=(float)$r['latitude'];$r['longitude']=(float)$r['longitude'];} unset($r);
  echo json_encode(['success'=>true,'facilities'=>$rows]);
}catch(Throwable $e){error_log('Facility list failed: '.$e->getMessage());organizationJsonError(500,'Unable to load receiving facilities.');}
