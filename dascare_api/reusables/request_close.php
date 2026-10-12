<?php
// reusables/request_close.php
// Closing an emergency request before its mission completes: cancellation by
// the requester, or cancelled / false alarm / rejected by the handling
// organization or a platform administrator.
//
// One transaction: pending offers are withdrawn, the DSS run stops, active
// assignments are cancelled, the ambulance and crew are released, and the
// request gets its status log + audit_logs row. After commit: live updates,
// notifications and app pushes (all queued, sent at shutdown).
//
// Linked (dedup) reports follow their incident: when the REQUESTER cancels,
// the reports linked to theirs are unlinked and dispatched on their own (other
// people still reported an emergency there); when STAFF close an incident,
// its linked reports are closed with it.
require_once __DIR__ . '/dispatch_dedup.php';
require_once __DIR__ . '/assignment_helpers.php';
require_once __DIR__ . '/fleet_helpers.php';

const REQUEST_FINAL_STATUSES = ['completed', 'cancelled', 'rejected', 'false_alarm'];
// The requester can cancel until the crew is with them (on scene / transporting).
const REQUEST_REQUESTER_CANCELLABLE = ['submitted', 'validating', 'verified', 'assigned', 'acknowledged', 'responding', 'duplicate'];

const REQUEST_CLOSE_LABELS = [
    'cancelled'   => ['title' => 'Request Cancelled', 'verb' => 'cancelled'],
    'false_alarm' => ['title' => 'Marked as False Alarm', 'verb' => 'marked as a false alarm'],
    'rejected'    => ['title' => 'Request Rejected', 'verb' => 'rejected'],
];

/**
 * @param array $actor ['kind' => 'requester'|'organization'|'platform', 'user_id' => ?int,
 *                      'organization_id' => ?int, 'label' => 'the requester' | org name | 'a platform administrator']
 * @return array ['id', 'status', 'linked_closed' => int[], 'linked_unlinked' => int[]]
 * @throws RuntimeException with a user-facing message when the request can't be closed.
 */
function requestClose(PDO $pdo, int $requestId, string $newStatus, string $reason, array $actor): array
{
    if (!isset(REQUEST_CLOSE_LABELS[$newStatus])) throw new RuntimeException('Choose a valid outcome.');
    $kind = $actor['kind'];
    $userId = !empty($actor['user_id']) ? (int) $actor['user_id'] : null;
    $orgId = !empty($actor['organization_id']) ? (int) $actor['organization_id'] : null;
    $reason = mb_substr(trim(strip_tags($reason)), 0, 255);
    $label = REQUEST_CLOSE_LABELS[$newStatus];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id, reference_number, status, requester_user_id, merged_into_request_id FROM emergency_requests WHERE id = ? FOR UPDATE');
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$request) throw new RuntimeException('Emergency request not found.');
        $old = $request['status'];
        if (in_array($old, REQUEST_FINAL_STATUSES, true)) throw new RuntimeException('This request is already closed.');
        if ($kind === 'requester' && !in_array($old, REQUEST_REQUESTER_CANCELLABLE, true)) {
            throw new RuntimeException('The crew is already with you, so this request can no longer be cancelled here — please tell the responders.');
        }
        if ($kind !== 'requester' && $old === 'transporting') {
            throw new RuntimeException('A patient is being transported — finish the handoff and complete the mission instead.');
        }

        $note = ucfirst($label['verb']) . ' by ' . $actor['label'] . '.' . ($reason !== '' ? ' Reason: ' . $reason : '');
        $touchedOrgs = requestCloseReleaseDispatch($pdo, $requestId, $request['reference_number'], $userId, $note);

        $pdo->prepare("UPDATE emergency_requests SET status = ?, attention_level = 'normal', attention_flagged_at = NULL, attention_reason = NULL WHERE id = ?")
            ->execute([$newStatus, $requestId]);
        requestCloseLog($pdo, $requestId, $old, $newStatus, $userId, $note);
        requestCloseAudit($pdo, $userId, $orgId, $requestId, $old, $newStatus, $reason, $actor['label']);

        // Linked reports: staff closing an incident close them too.
        $linkedClosed = [];
        $linkedUsers = [];
        $children = $pdo->prepare("SELECT id, requester_user_id, reference_number FROM emergency_requests WHERE merged_into_request_id = ? AND status = 'duplicate' FOR UPDATE");
        $children->execute([$requestId]);
        $childRows = $children->fetchAll(PDO::FETCH_ASSOC);
        if ($kind !== 'requester') {
            $childNote = ucfirst($label['verb']) . ' with ' . $request['reference_number'] . ' (the incident this report was linked to) by ' . $actor['label'] . '.' . ($reason !== '' ? ' Reason: ' . $reason : '');
            foreach ($childRows as $child) {
                $pdo->prepare('UPDATE emergency_requests SET status = ? WHERE id = ?')->execute([$newStatus, (int) $child['id']]);
                requestCloseLog($pdo, (int) $child['id'], 'duplicate', $newStatus, $userId, $childNote);
                $linkedClosed[] = (int) $child['id'];
                if (!empty($child['requester_user_id'])) $linkedUsers[(int) $child['requester_user_id']] = $child;
            }
        }

        // Tell people (all queued; pushes/live events go out after commit).
        $message = 'Your emergency request ' . $request['reference_number'] . ' was ' . $label['verb'] . ($kind === 'requester' ? '.' : ' by ' . $actor['label'] . '.') . ($reason !== '' ? ' Reason: ' . $reason : '');
        if ($kind !== 'requester' && !empty($request['requester_user_id'])) {
            assignmentNotifyUser($pdo, (int) $request['requester_user_id'], 'dispatch_update', $label['title'], $message, 'emergency_request', $requestId, 'request-closed:' . $requestId);
        }
        foreach ($linkedUsers as $uid => $child) {
            assignmentNotifyUser($pdo, $uid, 'dispatch_update', $label['title'],
                'Your report ' . $child['reference_number'] . ' was ' . $label['verb'] . ' together with ' . $request['reference_number'] . '.' . ($reason !== '' ? ' Reason: ' . $reason : ''),
                'emergency_request', (int) $child['id'], 'request-closed:' . (int) $child['id']);
        }
        // Guests' phones (and linked reports' phones) following this request.
        if ($kind !== 'requester') pushQueueRequestFollowers($pdo, $requestId, $label['title'], $message, $newStatus);
        requestCloseNotifyOrganizations($pdo, array_keys($touchedOrgs), $orgId, $userId, $requestId, $request['reference_number'], $label, $reason, $actor['label']);

        realtimeRequestChanged($pdo, $requestId, 'request.closed', ['closed_status' => $newStatus], array_keys($touchedOrgs));
        foreach ($linkedClosed as $cid) realtimeRequestChanged($pdo, $cid, 'request.closed', ['closed_status' => $newStatus]);
        if (!empty($request['merged_into_request_id'])) {
            realtimeRequestChanged($pdo, (int) $request['merged_into_request_id'], 'request.unlinked', ['unlinked_request_id' => $requestId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    // Requester cancelled: anyone else who reported this emergency still
    // needs help — their linked reports become incidents of their own.
    $unlinked = [];
    if ($kind === 'requester') {
        foreach ($childRows as $child) {
            try {
                dedupUnmergeRequest($pdo, (int) $child['id'], null, null, 'the system', 'The first report (' . $request['reference_number'] . ') was cancelled by its requester.');
                $unlinked[] = (int) $child['id'];
            } catch (Throwable $e) {
                error_log('Unlink after cancel failed for request ' . $child['id'] . ': ' . $e->getMessage());
            }
        }
    }
    return ['id' => $requestId, 'status' => $newStatus, 'linked_closed' => $linkedClosed, 'linked_unlinked' => $unlinked];
}

/**
 * Withdraw sent offers, stop the DSS run, cancel active assignments and free
 * their ambulance + crew. Returns the organizations involved (id => true).
 */
function requestCloseReleaseDispatch(PDO $pdo, int $requestId, string $reference, ?int $userId, string $note): array
{
    $orgs = [];
    $offers = $pdo->prepare("SELECT id, organization_id FROM incident_offers WHERE emergency_request_id = ? AND offer_status = 'sent' FOR UPDATE");
    $offers->execute([$requestId]);
    foreach ($offers->fetchAll(PDO::FETCH_ASSOC) as $o) $orgs[(int) $o['organization_id']] = true;
    $pdo->prepare("UPDATE incident_offers SET offer_status = 'cancelled', responded_at = NOW(), response_note = 'The request was closed.' WHERE emergency_request_id = ? AND offer_status = 'sent'")
        ->execute([$requestId]);
    $accepted = $pdo->prepare("SELECT DISTINCT organization_id FROM incident_offers WHERE emergency_request_id = ? AND offer_status = 'accepted'");
    $accepted->execute([$requestId]);
    foreach ($accepted->fetchAll(PDO::FETCH_COLUMN) as $o) $orgs[(int) $o] = true;
    $pdo->prepare("UPDATE dss_recommendation_runs SET run_status = 'superseded' WHERE emergency_request_id = ? AND run_status = 'active'")->execute([$requestId]);

    $stmt = $pdo->prepare("
        SELECT da.id, da.organization_id, da.ambulance_id, da.assignment_status, a.status AS ambulance_status
        FROM dispatch_assignments da
        INNER JOIN ambulances a ON a.id = da.ambulance_id
        WHERE da.emergency_request_id = ? AND da.assignment_status IN ('assigned','acknowledged','responding','on_scene','transporting')
        FOR UPDATE
    ");
    $stmt->execute([$requestId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $da) {
        $daId = (int) $da['id'];
        $orgs[(int) $da['organization_id']] = true;
        $pdo->prepare("UPDATE dispatch_assignments SET assignment_status = 'cancelled' WHERE id = ?")->execute([$daId]);
        assignmentLog($pdo, $daId, $da['assignment_status'], 'cancelled', $userId, mb_substr($note, 0, 500));

        // Ambulance: not yet on the road → available again; already out →
        // 'returning', which Fleet clears (same as after a completed mission).
        $newAmb = in_array($da['ambulance_status'], ['reserved'], true) ? 'available'
            : (in_array($da['ambulance_status'], ['dispatched', 'on_scene', 'transporting'], true) ? 'returning' : null);
        if ($newAmb !== null) {
            $pdo->prepare('UPDATE ambulances SET status = ? WHERE id = ?')->execute([$newAmb, (int) $da['ambulance_id']]);
            $pdo->prepare('INSERT INTO ambulance_status_logs (ambulance_id, old_status, new_status, changed_by_user_id, reason) VALUES (?, ?, ?, ?, ?)')
                ->execute([(int) $da['ambulance_id'], $da['ambulance_status'], $newAmb, $userId, 'Mission ' . $reference . ' was closed before completion.']);
            realtimeFleetChanged($pdo, (int) $da['organization_id'], (int) $da['ambulance_id']);
        }

        // Crew back to available.
        $members = $pdo->prepare('SELECT organization_member_id FROM crew_assignments WHERE dispatch_assignment_id = ?');
        $members->execute([$daId]);
        $up = $pdo->prepare("UPDATE personnel_profiles SET availability_status = 'available' WHERE organization_member_id = ? AND availability_status = 'assigned'");
        $log = $pdo->prepare("INSERT INTO personnel_availability_logs (organization_member_id, old_status, new_status, changed_by_user_id, notes) VALUES (?, 'assigned', 'available', ?, ?)");
        foreach (array_map('intval', $members->fetchAll(PDO::FETCH_COLUMN)) as $mid) {
            $up->execute([$mid]);
            if ($up->rowCount()) $log->execute([$mid, $userId, 'Released: ' . $reference . ' was closed.']);
        }
    }
    return $orgs;
}

function requestCloseLog(PDO $pdo, int $requestId, string $old, string $new, ?int $userId, string $note): void
{
    $pdo->prepare('INSERT INTO emergency_request_status_logs (emergency_request_id, old_status, new_status, changed_by_user_id, notes) VALUES (?, ?, ?, ?, ?)')
        ->execute([$requestId, $old, $new, $userId, mb_substr($note, 0, 500)]);
}

function requestCloseAudit(PDO $pdo, ?int $userId, ?int $orgId, int $requestId, string $old, string $new, string $reason, string $actorLabel): void
{
    $pdo->prepare("
        INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
        VALUES (?, ?, 'emergency_request.closed', 'emergency_request', ?, ?, ?, ?, ?)
    ")->execute([
        $userId, $orgId, $requestId,
        json_encode(['status' => $old]),
        json_encode(['status' => $new, 'reason' => $reason, 'actor' => $actorLabel], JSON_UNESCAPED_UNICODE),
        $_SERVER['REMOTE_ADDR'] ?? null,
        mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
}

/** Admins/dispatchers of every involved organization, plus assigned crew — except whoever closed it. */
function requestCloseNotifyOrganizations(PDO $pdo, array $orgIds, ?int $actorOrgId, ?int $actorUserId, int $requestId, string $reference, array $label, string $reason, string $actorLabel): void
{
    if (!$orgIds) return;
    $ph = implode(',', array_fill(0, count($orgIds), '?'));
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id
        FROM organization_members om
        INNER JOIN users u ON u.id = om.user_id AND u.deleted_at IS NULL AND u.account_status = 'active'
        INNER JOIN user_roles ur ON ur.user_id = u.id
        LEFT JOIN org_member_roles omr ON omr.organization_member_id = om.id
        LEFT JOIN org_roles r ON r.id = omr.role_id AND r.deleted_at IS NULL
        LEFT JOIN org_role_permissions orp ON orp.role_id = r.id
        LEFT JOIN rbac_permissions p ON p.id = orp.permission_id
        LEFT JOIN rbac_modules m ON m.id = p.module_id
        WHERE om.organization_id IN ($ph) AND om.deleted_at IS NULL AND om.membership_status = 'active'
          AND (
            ur.role = 'organization_admin'
            OR CONCAT(m.module_key, '.', p.resource, '.', p.action) IN ('dispatch.dispatch_assignments.read','dispatch.dispatch_assignments.approve')
            OR EXISTS (
                SELECT 1 FROM crew_assignments ca INNER JOIN dispatch_assignments da ON da.id = ca.dispatch_assignment_id
                WHERE ca.organization_member_id = om.id AND da.emergency_request_id = ?
            )
          )
    ");
    $stmt->execute(array_merge(array_map('intval', $orgIds), [$requestId]));
    $message = $reference . ' was ' . $label['verb'] . ' by ' . $actorLabel . '.' . ($reason !== '' ? ' Reason: ' . $reason : '') . ' The unit and crew were released.';
    foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $uid) {
        if ($uid === $actorUserId) continue;
        assignmentNotifyUser($pdo, $uid, 'dispatch_update', $label['title'], $message, 'emergency_request', $requestId, 'request-closed:' . $requestId . ':user:' . $uid);
    }
}
