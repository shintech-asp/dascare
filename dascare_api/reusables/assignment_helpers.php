<?php
require_once __DIR__ . '/realtime.php';
require_once __DIR__ . '/organization_rbac.php';
require_once __DIR__ . '/push.php';

function assignmentActiveStatuses(): array
{
    return ['assigned','acknowledged','responding','on_scene','transporting'];
}

function assignmentMemberHasActiveMission(PDO $pdo, int $memberId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM crew_assignments ca INNER JOIN dispatch_assignments da ON da.id=ca.dispatch_assignment_id WHERE ca.organization_member_id=? AND da.assignment_status IN ('assigned','acknowledged','responding','on_scene','transporting') LIMIT 1");
    $stmt->execute([$memberId]);
    return (bool)$stmt->fetchColumn();
}

function assignmentAmbulanceEligibility(PDO $pdo, int $organizationId, int $ambulanceId, bool $forUpdate = false): ?array
{
    $sql = "SELECT a.*, rc.overall_status AS readiness_status,
                   EXISTS(SELECT 1 FROM ambulance_maintenance_records am WHERE am.ambulance_id=a.id AND am.status='in_progress') AS maintenance_in_progress
            FROM ambulances a
            LEFT JOIN ambulance_readiness_checks rc ON rc.id=(SELECT rc2.id FROM ambulance_readiness_checks rc2 WHERE rc2.ambulance_id=a.id ORDER BY rc2.checked_at DESC, rc2.id DESC LIMIT 1)
            WHERE a.id=? AND a.organization_id=? AND a.deleted_at IS NULL LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql); $stmt->execute([$ambulanceId,$organizationId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $today=date('Y-m-d');
    $row['eligible'] = $row['status']==='available'
        && $row['readiness_status']==='ready'
        && !(int)$row['maintenance_in_progress']
        && (empty($row['registration_expiry']) || $row['registration_expiry'] >= $today)
        && (empty($row['inspection_expiry']) || $row['inspection_expiry'] >= $today);
    return $row;
}

function assignmentLog(PDO $pdo, int $assignmentId, ?string $oldStatus, string $newStatus, ?int $userId, string $notes=''): void
{
    $stmt=$pdo->prepare('INSERT INTO dispatch_assignment_logs (dispatch_assignment_id,old_status,new_status,changed_by_user_id,notes) VALUES (?,?,?,?,?)');
    $stmt->execute([$assignmentId,$oldStatus,$newStatus,$userId,$notes?:null]);
}

function assignmentNotifyUser(PDO $pdo, int $userId, string $type, string $title, string $message, string $relatedType, int $relatedId, string $dedup): void
{
    $stmt=$pdo->prepare("INSERT INTO notifications (user_id,notification_type,title,message,related_type,related_id,dedup_key) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),message=VALUES(message),read_at=NULL,created_at=CURRENT_TIMESTAMP");
    $stmt->execute([$userId,$type,$title,$message,$relatedType,$relatedId,$dedup]);
    realtimeNotifyUsers($pdo,[$userId]);
    // Also to the DASCARE app on this user's phone, if any (no-op without Firebase).
    pushQueueUser($pdo,$userId,$title,$message,['type'=>$type,'related_type'=>$relatedType,'related_id'=>$relatedId],$dedup);
}
