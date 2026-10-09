<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/technical_guard.php';
header('Content-Type: application/json');
$actor = requireTechnicalSuperAdmin($pdo);
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$userId=(int)($input['user_id']??0); $status=(string)($input['status']??''); $reason=trim((string)($input['reason']??''));
if ($userId<=0 || !in_array($status,['active','suspended','disabled'],true)) technicalJsonError(422,'A valid account and status are required.');
if ($userId === $actor && $status !== 'active') technicalJsonError(422,'You cannot restrict your own Technical Super Admin account.');
if ($status !== 'active' && $reason === '') technicalJsonError(422,'Provide an administrative reason.');

$stmt=$pdo->prepare("SELECT u.account_status,ur.role,CONCAT_WS(' ',u.first_name,u.last_name) name FROM users u JOIN user_roles ur ON ur.user_id=u.id WHERE u.id=? AND ur.role IN ('technical_super_admin','platform_executive_admin') AND u.deleted_at IS NULL LIMIT 1");
$stmt->execute([$userId]); $target=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$target) technicalJsonError(404,'Administrative account not found.');
try{
 $pdo->beginTransaction();
 $pdo->prepare('UPDATE users SET account_status=? WHERE id=?')->execute([$status,$userId]);
 $pdo->prepare("INSERT INTO audit_logs (user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?)")
     ->execute([$actor,'technical.account_status.update','user',$userId,json_encode(['account_status'=>$target['account_status']]),json_encode(['account_status'=>$status,'reason'=>$reason]),$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,255)]);
 $pdo->commit();
 echo json_encode(['success'=>true,'message'=>$status==='active'?'Administrative account reactivated.':'Administrative account updated.']);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Technical account status failed: '.$e->getMessage());technicalJsonError(500,'Unable to update this account.');}
