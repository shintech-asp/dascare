<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/technical_guard.php';
header('Content-Type: application/json');
requireTechnicalSuperAdmin($pdo);
$search=trim((string)($_GET['search']??'')); $page=max(1,(int)($_GET['page']??1)); $per=min(50,max(10,(int)($_GET['per_page']??20)));$off=($page-1)*$per;
$where=['1=1'];$params=[];if($search!==''){$n='%'.$search.'%';$where[]="(a.action LIKE ? OR a.entity_type LIKE ? OR CONCAT_WS(' ',u.first_name,u.last_name) LIKE ?)";array_push($params,$n,$n,$n);} $ws=implode(' AND ',$where);
try{
 $c=$pdo->prepare("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE $ws");$c->execute($params);$total=(int)$c->fetchColumn();
 $s=$pdo->prepare("SELECT a.id,a.action,a.entity_type,a.entity_id,a.organization_id,a.ip_address,a.created_at,CONCAT_WS(' ',u.first_name,u.last_name) actor_name,u.email actor_email,o.name organization_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id LEFT JOIN organizations o ON o.id=a.organization_id WHERE $ws ORDER BY a.created_at DESC LIMIT $per OFFSET $off");$s->execute($params);$items=$s->fetchAll(PDO::FETCH_ASSOC);
 echo json_encode(['success'=>true,'items'=>$items,'pagination'=>['page'=>$page,'pages'=>max(1,(int)ceil($total/$per)),'total'=>$total]]);
}catch(Throwable $e){error_log('Technical audit failed: '.$e->getMessage());technicalJsonError(500,'Unable to load system activity.');}
