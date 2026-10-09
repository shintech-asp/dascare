<?php
require_once __DIR__ . '/../../../cors.php';
require_once __DIR__ . '/../../../db/db.php';
require_once __DIR__ . '/../../../reusables/organization_rbac.php';
require_once __DIR__ . '/../../../reusables/assignment_helpers.php';
header('Content-Type: application/json');
$ctx=requireOrganizationAnyPermission($pdo,['dispatch.dispatch_assignments.update','dispatch.crew_assignments.create','dispatch.dispatch_assignments.read']);
try {
    $pending=$pdo->prepare("SELECT er.id,er.reference_number,er.severity,er.description,er.address_text,er.barangay,er.latitude,er.longitude,er.requester_name,er.requester_phone,er.submitted_at,ec.name category_name,
                                  io.id offer_id,io.responded_at accepted_at,dr.recommended_ambulance_id,dr.total_score,dr.explanation
                           FROM incident_offers io
                           JOIN emergency_requests er ON er.id=io.emergency_request_id
                           JOIN emergency_categories ec ON ec.id=er.emergency_category_id
                           JOIN dss_recommendations dr ON dr.id=io.recommendation_id
                           LEFT JOIN dispatch_assignments da ON da.emergency_request_id=er.id
                           WHERE io.organization_id=? AND io.offer_status='accepted' AND da.id IS NULL
                           ORDER BY io.responded_at ASC");
    $pending->execute([$ctx['organization_id']]);
    $incidents=$pending->fetchAll(PDO::FETCH_ASSOC);
    foreach($incidents as &$r){$r['id']=(int)$r['id'];$r['offer_id']=(int)$r['offer_id'];$r['recommended_ambulance_id']=$r['recommended_ambulance_id']?(int)$r['recommended_ambulance_id']:null;$r['total_score']=$r['total_score']!==null?(float)$r['total_score']:null;$r['latitude']=(float)$r['latitude'];$r['longitude']=(float)$r['longitude'];}unset($r);

    $amb=$pdo->prepare("SELECT a.id,a.unit_code,a.plate_number,a.ambulance_type,a.capacity,a.status,rc.overall_status readiness_status
                        FROM ambulances a JOIN ambulance_readiness_checks rc ON rc.id=(SELECT x.id FROM ambulance_readiness_checks x WHERE x.ambulance_id=a.id ORDER BY x.checked_at DESC,x.id DESC LIMIT 1)
                        WHERE a.organization_id=? AND a.deleted_at IS NULL AND a.status='available' AND rc.overall_status='ready'
                          AND (a.registration_expiry IS NULL OR a.registration_expiry>=CURDATE()) AND (a.inspection_expiry IS NULL OR a.inspection_expiry>=CURDATE())
                          AND NOT EXISTS(SELECT 1 FROM ambulance_maintenance_records m WHERE m.ambulance_id=a.id AND m.status='in_progress') ORDER BY a.unit_code");
    $amb->execute([$ctx['organization_id']]); $ambulances=$amb->fetchAll(PDO::FETCH_ASSOC); foreach($ambulances as &$a){$a['id']=(int)$a['id'];$a['capacity']=(int)$a['capacity'];}unset($a);

    $people=$pdo->prepare("SELECT om.id organization_member_id,u.id user_id,u.first_name,u.last_name,om.employee_code,pp.license_number,pp.certification_details,pp.availability_status,
                                  GROUP_CONCAT(DISTINCT r.role_name ORDER BY r.role_name SEPARATOR '||') role_names
                           FROM organization_members om JOIN users u ON u.id=om.user_id AND u.deleted_at IS NULL AND u.account_status='active'
                           JOIN personnel_profiles pp ON pp.organization_member_id=om.id
                           LEFT JOIN org_member_roles omr ON omr.organization_member_id=om.id LEFT JOIN org_roles r ON r.id=omr.role_id AND r.deleted_at IS NULL
                           WHERE om.organization_id=? AND om.deleted_at IS NULL AND om.membership_status='active' AND pp.availability_status='available'
                             AND NOT EXISTS(SELECT 1 FROM crew_assignments ca JOIN dispatch_assignments da ON da.id=ca.dispatch_assignment_id WHERE ca.organization_member_id=om.id AND da.assignment_status IN ('assigned','acknowledged','responding','on_scene','transporting'))
                           GROUP BY om.id ORDER BY u.first_name,u.last_name");
    $people->execute([$ctx['organization_id']]);$personnel=$people->fetchAll(PDO::FETCH_ASSOC);foreach($personnel as &$p){$p['organization_member_id']=(int)$p['organization_member_id'];$p['user_id']=(int)$p['user_id'];$p['role_names']=$p['role_names']?explode('||',$p['role_names']):[];}unset($p);

    $recent=$pdo->prepare("SELECT da.id,da.assignment_status,da.assigned_at,er.reference_number,er.severity,a.unit_code,COUNT(ca.id) crew_count
                           FROM dispatch_assignments da JOIN emergency_requests er ON er.id=da.emergency_request_id JOIN ambulances a ON a.id=da.ambulance_id LEFT JOIN crew_assignments ca ON ca.dispatch_assignment_id=da.id
                           WHERE da.organization_id=? GROUP BY da.id ORDER BY da.assigned_at DESC LIMIT 12");
    $recent->execute([$ctx['organization_id']]);$recentRows=$recent->fetchAll(PDO::FETCH_ASSOC);foreach($recentRows as &$r){$r['id']=(int)$r['id'];$r['crew_count']=(int)$r['crew_count'];}unset($r);

    echo json_encode(['success'=>true,'incidents'=>$incidents,'ambulances'=>$ambulances,'personnel'=>$personnel,'recent_assignments'=>$recentRows]);
} catch(Throwable $e){error_log('Assignment list failed: '.$e->getMessage());organizationJsonError(500,'Unable to load resource assignment data.');}
