<?php
// organizations/dispatch/missions/mine.php
// Missions waiting for THIS member's acknowledgement (assigned as crew, not
// yet acknowledged by anyone). Powers the crew "New mission" pop-up on every
// organization page. Needs only active membership: being on the crew is the
// authorization, so a crew member without a dispatch role still gets alerted.
require_once __DIR__ . '/../../../cors.php';
require_once __DIR__ . '/../../../db/db.php';
require_once __DIR__ . '/../../../reusables/organization_rbac.php';
header('Content-Type: application/json');

$ctx = requireOrganizationAccess($pdo, null);
$memberId = (int) $ctx['organization_member_id'];
try {
    $stmt = $pdo->prepare("
        SELECT da.id, da.emergency_request_id, da.assignment_status, ca.crew_role AS my_role,
               er.reference_number, er.severity, er.address_text, er.barangay, er.landmark,
               ec.name AS category_name, a.unit_code
        FROM crew_assignments ca
        JOIN dispatch_assignments da ON da.id = ca.dispatch_assignment_id
        JOIN emergency_requests er ON er.id = da.emergency_request_id
        JOIN emergency_categories ec ON ec.id = er.emergency_category_id
        JOIN ambulances a ON a.id = da.ambulance_id
        WHERE ca.organization_member_id = ? AND da.organization_id = ?
          AND ca.response_status = 'assigned' AND da.assignment_status = 'assigned'
        ORDER BY da.assigned_at DESC
        LIMIT 5
    ");
    $stmt->execute([$memberId, (int) $ctx['organization_id']]);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $crewStmt = $pdo->prepare("SELECT ca.crew_role, u.first_name, u.last_name FROM crew_assignments ca JOIN organization_members om ON om.id = ca.organization_member_id JOIN users u ON u.id = om.user_id WHERE ca.dispatch_assignment_id = ? ORDER BY FIELD(ca.crew_role,'driver','team_leader','paramedic','emt','rescuer'), u.first_name");
    foreach ($missions as &$m) {
        $m['id'] = (int) $m['id'];
        $crewStmt->execute([$m['id']]);
        $m['crew'] = $crewStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($m);
    echo json_encode(['success' => true, 'missions' => $missions]);
} catch (Throwable $e) {
    error_log('My missions failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to load your missions.');
}
