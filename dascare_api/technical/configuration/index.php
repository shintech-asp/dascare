<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/technical_guard.php';
require_once __DIR__ . '/../../reusables/dispatch_dedup.php';
require_once __DIR__ . '/../../reusables/realtime.php';
header('Content-Type: application/json');
$actor=requireTechnicalSuperAdmin($pdo);
$allowed=['platform.app_version','emergency.guest_requests_enabled','tracking.default_interval_seconds','dispatch.offer_timeout_seconds','dispatch.dss.weights','platform.incident_stale_minutes','platform.incident_critical_stale_minutes','platform.compliance_warning_days','dispatch.dedup.enabled','dispatch.dedup.radius_meters','dispatch.dedup.window_seconds','dispatch.dedup.min_requests','realtime.enabled','guest.temporary_account_retention_days'];
$dedupKeys=['dispatch.dedup.enabled'=>'enabled','dispatch.dedup.radius_meters'=>'radius_meters','dispatch.dedup.window_seconds'=>'window_seconds','dispatch.dedup.min_requests'=>'min_requests'];
$dedupLabels=['radius_meters'=>'Radius','window_seconds'=>'Time window','min_requests'=>'Minimum reports'];
// Duplicate-detection values are range-checked here (ranges shared with
// reusables/dispatch_dedup.php); every other key stays free-form JSON.
function validateDedupValue(string $field,mixed $value,array $labels):mixed{if($field==='enabled'){$b=is_bool($value)?$value:filter_var($value,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);if($b===null)technicalJsonError(422,'Duplicate detection must be on (true) or off (false).');return $b;}[$min,$max]=DEDUP_LIMITS[$field];if(!is_numeric($value)||(int)$value!=$value||$value<$min||$value>$max)technicalJsonError(422,"{$labels[$field]} must be a whole number from $min to $max.");return (int)$value;}
// Every other editable setting is type- and range-checked too, so a typo like "none" can't be saved.
function validateConfigValue(string $key,mixed $v):mixed{
  $int=static fn($x,$min,$max)=>is_numeric($x)&&(int)$x==$x&&$x>=$min&&$x<=$max;
  $bool=static fn($x)=>is_bool($x)?$x:filter_var($x,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
  switch($key){
    case 'platform.app_version':if(!is_string($v)||!preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}$/',$v))technicalJsonError(422,'App version must look like 1.5.0.');return $v;
    case 'emergency.guest_requests_enabled':$b=$bool($v);if($b===null)technicalJsonError(422,'Guest requests must be true or false.');return $b;
    case 'dispatch.offer_timeout_seconds':if(!$int($v,30,3600))technicalJsonError(422,'Offer timeout must be a whole number of seconds from 30 to 3600.');return (int)$v;
    case 'platform.incident_stale_minutes':case 'platform.incident_critical_stale_minutes':if(!$int($v,1,1440))technicalJsonError(422,'Stale minutes must be a whole number from 1 to 1440.');return (int)$v;
    case 'platform.compliance_warning_days':if(!$int($v,1,365))technicalJsonError(422,'Compliance warning must be a whole number of days from 1 to 365.');return (int)$v;
    case 'tracking.default_interval_seconds':
      if(!is_array($v)||!isset($v['active'],$v['idle'])||!$int($v['active'],1,300)||!$int($v['idle'],5,3600))technicalJsonError(422,'GPS intervals must be {"active": 1–300, "idle": 5–3600} in seconds.');
      if((int)$v['idle']<(int)$v['active'])technicalJsonError(422,'The idle GPS interval cannot be shorter than the active one.');
      return ['active'=>(int)$v['active'],'idle'=>(int)$v['idle']];
    case 'dispatch.dss.weights':
      $k=['distance','availability','capability','workload'];$sum=0;$o=[];
      if(!is_array($v))technicalJsonError(422,'DSS weights must be an object with distance, availability, capability and workload.');
      foreach($k as $f){if(!isset($v[$f])||!is_numeric($v[$f])||$v[$f]<0||$v[$f]>1)technicalJsonError(422,"DSS weight \"$f\" must be a number from 0 to 1.");$o[$f]=(float)$v[$f];$sum+=$o[$f];}
      if(abs($sum-1)>0.001)technicalJsonError(422,'The four DSS weights must add up to exactly 1.');
      return $o;
  }
  return $v;
}
// Live updates: whether the Ably key file exists (never the secret itself).
function realtimeStatus(PDO $pdo):array{$c=realtimeConfig();return ['configured'=>$c!==null,'enabled'=>realtimeSettingEnabled($pdo),'key_name'=>$c['key_name']??null,'channel_prefix'=>$c['prefix']??null];}
if($_SERVER['REQUEST_METHOD']==='GET'){
 $place=implode(',',array_fill(0,count($allowed),'?'));$s=$pdo->prepare("SELECT setting_key,setting_value,description,updated_at FROM system_settings WHERE setting_key IN ($place) ORDER BY setting_key");$s->execute($allowed);$items=[];foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){$v=json_decode($r['setting_value'],true);$items[]=['key'=>$r['setting_key'],'value'=>json_last_error()===JSON_ERROR_NONE?$v:$r['setting_value'],'description'=>$r['description'],'updated_at'=>$r['updated_at']];}echo json_encode(['success'=>true,'items'=>$items,'dedup'=>dedupSettings($pdo),'dedup_defaults'=>DEDUP_DEFAULTS,'dedup_limits'=>DEDUP_LIMITS,'realtime'=>realtimeStatus($pdo)]);exit;
}
$input=json_decode(file_get_contents('php://input'),true)?:[];$changes=isset($input['items'])&&is_array($input['items'])?$input['items']:[['key'=>$input['key']??'','value'=>$input['value']??null]];if(!$changes)technicalJsonError(422,'No settings to save.');$prepared=[];foreach($changes as $c){$key=(string)($c['key']??'');if(!in_array($key,$allowed,true))technicalJsonError(422,'This setting is not editable here.');$value=$c['value']??null;if(isset($dedupKeys[$key]))$value=validateDedupValue($dedupKeys[$key],$value,$dedupLabels);if($key==='guest.temporary_account_retention_days'){if(!is_numeric($value)||(int)$value!=$value||$value<1||$value>90)technicalJsonError(422,'Temporary account retention must be a whole number of days from 1 to 90.');$value=(int)$value;}if($key==='realtime.enabled'){$value=is_bool($value)?$value:filter_var($value,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);if($value===null)technicalJsonError(422,'Live updates must be on (true) or off (false).');}$value=validateConfigValue($key,$value);$encoded=json_encode($value,JSON_UNESCAPED_SLASHES);if($encoded===false)technicalJsonError(422,'Invalid setting value.');$prepared[]=[$key,$value,$encoded];}
try{$pdo->beginTransaction();$old=$pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key=?');$save=$pdo->prepare("INSERT INTO system_settings (setting_key,setting_value,description,updated_by_user_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by_user_id=VALUES(updated_by_user_id)");$audit=$pdo->prepare("INSERT INTO audit_logs (user_id,action,entity_type,old_values,new_values,ip_address,user_agent) VALUES (?,?,?,?,?,?,?)");foreach($prepared as [$key,$value,$encoded]){$old->execute([$key]);$oldVal=$old->fetchColumn();$save->execute([$key,$encoded,'Managed from Technical Administration.',$actor]);$audit->execute([$actor,'technical.configuration.update','system_setting',json_encode(['key'=>$key,'value'=>$oldVal]),json_encode(['key'=>$key,'value'=>$value]),$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,255)]);}$pdo->commit();echo json_encode(['success'=>true,'message'=>'Configuration updated.']);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Technical config update failed: '.$e->getMessage());technicalJsonError(500,'Unable to update configuration.');}
