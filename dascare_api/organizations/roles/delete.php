<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
header('Content-Type: application/json');
$ctx=requireOrganizationAccess($pdo,'rbac.roles.delete');
if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') organizationJsonError(405,'Method not allowed.');
$data=json_decode(file_get_contents('php://input'),true)?:[];$roleId=(int)($data['role_id']??0);if($roleId<=0)organizationJsonError(422,'A valid role is required.');
try{$stmt=$pdo->prepare("SELECT id,role_name,description,is_system FROM org_roles WHERE id=? AND organization_id=? AND deleted_at IS NULL LIMIT 1");$stmt->execute([$roleId,$ctx['organization_id']]);$role=$stmt->fetch(PDO::FETCH_ASSOC);if(!$role)organizationJsonError(404,'Role not found.');if((int)$role['is_system']===1)organizationJsonError(409,'Starter roles cannot be deleted.');$count=$pdo->prepare('SELECT COUNT(*) FROM org_member_roles WHERE role_id=?');$count->execute([$roleId]);if((int)$count->fetchColumn()>0)organizationJsonError(409,'Remove this role from all employees before deleting it.');$pdo->beginTransaction();$pdo->prepare('DELETE FROM org_role_permissions WHERE role_id=?')->execute([$roleId]);$pdo->prepare('UPDATE org_roles SET deleted_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$roleId]);logOrganizationRbac($pdo,$ctx,'org_roles',$roleId,'delete',$role,null);$pdo->commit();echo json_encode(['success'=>true,'message'=>'Custom role deleted.']);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Organization role delete failed: '.$e->getMessage());organizationJsonError(500,'Unable to delete this role.');}
