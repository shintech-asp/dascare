<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
header('Content-Type: application/json');
$actor=requirePlatformExecutive($pdo,'organizations.organizations.read');

if($_SERVER['REQUEST_METHOD']==='POST'){
    requirePlatformExecutive($pdo,'organizations.organizations.update');
    $in=json_decode(file_get_contents('php://input'),true)?:[];
    $id=(int)($in['organization_id']??0); $status=(string)($in['status']??''); $reason=trim((string)($in['reason']??''));
    if($id<=0||!in_array($status,['active','suspended','inactive'],true)) platformJsonError(422,'A valid organization and status are required.');
    if($status!=='active'&&$reason==='') platformJsonError(422,'Provide a reason for restricting this organization.');
    $s=$pdo->prepare("SELECT id,name,status,application_status,primary_admin_user_id FROM organizations WHERE id=? AND deleted_at IS NULL LIMIT 1");$s->execute([$id]);$org=$s->fetch(PDO::FETCH_ASSOC);if(!$org)platformJsonError(404,'Organization not found.');
    if($org['application_status']!=='approved') platformJsonError(422,'Only approved organizations can be managed from this page.');
    try{$pdo->beginTransaction();$note = $reason !== '' ? $reason : null; $pdo->prepare('UPDATE organizations SET status=?,verification_note=? WHERE id=?')->execute([$status,$note,$id]);
        $pdo->prepare("INSERT INTO audit_logs (user_id,organization_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$actor,$id,'platform.organization_status.update','organization',$id,json_encode(['status'=>$org['status']]),json_encode(['status'=>$status,'reason'=>$reason]),$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,255)]);
        if(!empty($org['primary_admin_user_id'])){$title=$status==='active'?'Organization Reactivated':'Organization Status Updated';$msg=$status==='active'?'Your organization has been reactivated and operational access is available.':'Your organization status is now '.ucfirst($status).'.'.($reason!==''?' Reason: '.$reason:'');$pdo->prepare("INSERT INTO notifications (user_id,notification_type,title,message,related_type,related_id,dedup_key) VALUES (?,?,?,?,?,?,?)")->execute([(int)$org['primary_admin_user_id'],'organization_status',$title,$msg,'organization',$id,'org-status-'.$id.'-'.$status.'-'.time()]);}
        $pdo->commit();echo json_encode(['success'=>true,'message'=>'Organization status updated.']);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Platform org manage failed: '.$e->getMessage());platformJsonError(500,'Unable to update organization status.');}
    exit;
}

$search=trim((string)($_GET['search']??''));$status=strtolower(trim((string)($_GET['status']??'all')));$where=["o.deleted_at IS NULL","o.application_status='approved'"];$params=[];
if(in_array($status,['active','suspended','inactive'],true)){$where[]='o.status=?';$params[]=$status;}if($search!==''){$n='%'.$search.'%';$where[]="(o.name LIKE ? OR o.application_reference LIKE ? OR o.email LIKE ? OR o.phone LIKE ?)";array_push($params,$n,$n,$n,$n);} $ws=implode(' AND ',$where);
try{
 $stats=$pdo->query("SELECT COUNT(*) total,SUM(status='active') active,SUM(status='suspended') suspended,SUM(status='inactive') inactive FROM organizations WHERE deleted_at IS NULL AND application_status='approved'")->fetch(PDO::FETCH_ASSOC)?:[];
 $q=$pdo->prepare("SELECT o.id,o.application_reference,o.name,o.organization_type,o.email,o.phone,o.address_line,o.status,o.verified_at,o.verification_note,CONCAT_WS(' ',u.first_name,u.last_name) admin_name,u.email admin_email,(SELECT COUNT(*) FROM organization_members om WHERE om.organization_id=o.id AND om.deleted_at IS NULL AND om.membership_status='active') member_count,(SELECT COUNT(*) FROM ambulances a WHERE a.organization_id=o.id AND a.deleted_at IS NULL) ambulance_count,(SELECT COUNT(*) FROM ambulances a WHERE a.organization_id=o.id AND a.deleted_at IS NULL AND a.status='available') available_count FROM organizations o LEFT JOIN users u ON u.id=o.primary_admin_user_id WHERE $ws ORDER BY o.name");$q->execute($params);$items=$q->fetchAll(PDO::FETCH_ASSOC);foreach($items as &$i){$i['id']=(int)$i['id'];$i['member_count']=(int)$i['member_count'];$i['ambulance_count']=(int)$i['ambulance_count'];$i['available_count']=(int)$i['available_count'];}unset($i);
 echo json_encode(['success'=>true,'items'=>$items,'stats'=>array_map('intval',$stats)]);
}catch(Throwable $e){error_log('Platform organizations management failed: '.$e->getMessage());platformJsonError(500,'Unable to load approved organizations.');}
