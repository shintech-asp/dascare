<?php
require_once __DIR__ . '/../../../cors.php';
require_once __DIR__ . '/../../../db/db.php';
require_once __DIR__ . '/../../../reusables/organization_rbac.php';
header('Content-Type: application/json');
$ctx=requireOrganizationAnyPermission($pdo,['rbac.member_roles.create','rbac.member_roles.delete','rbac.roles.update']);
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')organizationJsonError(405,'Method not allowed.');
$data=json_decode(file_get_contents('php://input'),true)?:[];$memberId=(int)($data['organization_member_id']??0);$roleIds=is_array($data['role_ids']??null)?array_values(array_unique(array_filter(array_map('intval',$data['role_ids']),fn($id)=>$id>0))):[];
if($memberId<=0)organizationJsonError(422,'A valid organization member is required.');
try{
 $memberStmt=$pdo->prepare("SELECT om.id,om.user_id,om.membership_status,ur.role AS account_role FROM organization_members om INNER JOIN user_roles ur ON ur.user_id=om.user_id WHERE om.id=? AND om.organization_id=? AND om.deleted_at IS NULL LIMIT 1");$memberStmt->execute([$memberId,$ctx['organization_id']]);$member=$memberStmt->fetch(PDO::FETCH_ASSOC);if(!$member)organizationJsonError(404,'Organization member not found.');if($member['account_role']==='organization_admin')organizationJsonError(409,'The Organization Admin does not use assigned operational roles.');
 if($roleIds){$ph=implode(',',array_fill(0,count($roleIds),'?'));$roleStmt=$pdo->prepare("SELECT id FROM org_roles WHERE organization_id=? AND deleted_at IS NULL AND id IN ($ph)");$roleStmt->execute(array_merge([$ctx['organization_id']],$roleIds));$valid=array_map('intval',$roleStmt->fetchAll(PDO::FETCH_COLUMN));sort($valid);$req=$roleIds;sort($req);if($valid!==$req)organizationJsonError(422,'One or more selected roles are invalid.');
   if($ctx['user_role']!=='organization_admin'){
      $ownPerms=organizationPermissionIdsForMember($pdo,$ctx['organization_member_id']);
      $permStmt=$pdo->prepare("SELECT DISTINCT orp.permission_id FROM org_role_permissions orp INNER JOIN org_roles r ON r.id=orp.role_id WHERE r.organization_id=? AND r.id IN ($ph)");$permStmt->execute(array_merge([$ctx['organization_id']],$roleIds));$targetPerms=array_map('intval',$permStmt->fetchAll(PDO::FETCH_COLUMN));if(array_diff($targetPerms,$ownPerms))organizationJsonError(403,'You cannot assign a role containing permissions you do not possess.');
   }
 }
 $currentStmt=$pdo->prepare('SELECT role_id FROM org_member_roles WHERE organization_member_id=?');$currentStmt->execute([$memberId]);$oldIds=array_map('intval',$currentStmt->fetchAll(PDO::FETCH_COLUMN));
 if(array_diff($oldIds,$roleIds)&&!organizationHasPermission($pdo,$ctx,'rbac.member_roles.delete'))organizationJsonError(403,'You do not have permission to remove assigned roles.');
 if(array_diff($roleIds,$oldIds)&&!organizationHasPermission($pdo,$ctx,'rbac.member_roles.create'))organizationJsonError(403,'You do not have permission to assign roles.');
 $pdo->beginTransaction();$pdo->prepare('DELETE FROM org_member_roles WHERE organization_member_id=?')->execute([$memberId]);$insert=$pdo->prepare('INSERT INTO org_member_roles (organization_member_id,role_id,assigned_by) VALUES (?,?,?)');foreach($roleIds as $rid)$insert->execute([$memberId,$rid,$ctx['user_id']]);logOrganizationRbac($pdo,$ctx,'org_member_roles',$memberId,'assign',['role_ids'=>$oldIds],['role_ids'=>$roleIds]);$pdo->commit();echo json_encode(['success'=>true,'message'=>'Employee role assignments updated.']);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Organization member role update failed: '.$e->getMessage());organizationJsonError(500,'Unable to update employee roles.');}
