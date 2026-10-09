<?php
require __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/incident_attention.php';

header('Content-Type: application/json; charset=utf-8');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$sessionLevel = (string) ($_SESSION['user_level'] ?? '');

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

try {
    syncIncidentAttentionFlags($pdo);
    $accountStmt = $pdo->prepare("\n        SELECT u.account_status, ur.role\n        FROM users u\n        INNER JOIN user_roles ur ON ur.user_id = u.id\n        WHERE u.id = ? AND u.deleted_at IS NULL\n        LIMIT 1\n    ");
    $accountStmt->execute([$userId]);
    $account = $accountStmt->fetch(PDO::FETCH_ASSOC);

    if (!$account || $account['account_status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'This account is not active.']);
        exit;
    }

    $level = (string) $account['role'];
    if ($sessionLevel !== '' && $sessionLevel !== $level) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Session role is no longer current.']);
        exit;
    }

    $badges = [];

    $scalar = static function (PDO $pdo, string $sql, array $params = []): int {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    };

    if ($level === 'citizen') {
        $badges['/citizen/requests'] = $scalar($pdo, "\n            SELECT COUNT(*)\n            FROM emergency_requests\n            WHERE requester_user_id = ?\n              AND status IN ('submitted','validating','verified','assigned','acknowledged','responding','on_scene','transporting')\n        ", [$userId]);

        $badges['/citizen/notifications'] = $scalar($pdo, "\n            SELECT COUNT(*)\n            FROM notifications\n            WHERE user_id = ? AND read_at IS NULL\n        ", [$userId]);
    }

    if ($level === 'platform_executive_admin') {
        $badges['/platform/applications'] = $scalar($pdo, "\n            SELECT COUNT(*) FROM organizations\n            WHERE application_status = 'pending' AND deleted_at IS NULL\n        ");

        $badges['/platform/citizen-verifications'] = $scalar($pdo, "\n            SELECT COUNT(*) FROM kyc_verifications WHERE status = 1\n        ");

        $badges['/platform/incidents'] = $scalar($pdo, "\n            SELECT COUNT(*) FROM emergency_requests\n            WHERE status IN ('submitted','validating','verified','assigned','acknowledged','responding','on_scene','transporting')\n        ");

        $badges['/platform/escalations'] = $scalar($pdo, "\n            SELECT COUNT(DISTINCT er.id)\n            FROM emergency_requests er\n            WHERE er.status IN ('submitted','validating','verified')\n              AND (\n                EXISTS (\n                    SELECT 1 FROM dss_recommendation_runs dr\n                    WHERE dr.emergency_request_id = er.id AND dr.run_status = 'exhausted'\n                )\n                OR (\n                    SELECT COUNT(*) FROM incident_offers io\n                    WHERE io.emergency_request_id = er.id\n                      AND io.offer_status IN ('declined','timed_out')\n                ) >= 2\n              )\n        ");

        $badges['/platform/compliance'] = $scalar($pdo, "\n            SELECT COUNT(*) FROM organization_documents\n            WHERE status IN ('pending','rejected')\n        ");
    }

    if (in_array($level, ['organization_admin', 'organization_operational_user'], true)) {
        $orgStmt = $pdo->prepare("\n            SELECT om.organization_id\n            FROM organization_members om\n            INNER JOIN organizations o ON o.id = om.organization_id AND o.deleted_at IS NULL\n            WHERE om.user_id = ? AND om.deleted_at IS NULL\n            LIMIT 1\n        ");
        $orgStmt->execute([$userId]);
        $organizationId = (int) ($orgStmt->fetchColumn() ?: 0);

        if ($organizationId > 0) {
            $badges['/organization/offers'] = $scalar($pdo, "\n                SELECT COUNT(*)\n                FROM incident_offers\n                WHERE organization_id = ?\n                  AND offer_status = 'sent'\n                  AND expires_at > NOW()\n            ", [$organizationId]);

            $badges['/organization/assignments'] = $scalar($pdo, "\n                SELECT COUNT(*)\n                FROM incident_offers io\n                WHERE io.organization_id = ?\n                  AND io.offer_status = 'accepted'\n                  AND NOT EXISTS (\n                    SELECT 1 FROM dispatch_assignments da\n                    WHERE da.emergency_request_id = io.emergency_request_id\n                      AND da.organization_id = io.organization_id\n                      AND da.assignment_status NOT IN ('declined','cancelled','reassigned')\n                  )\n            ", [$organizationId]);

            $badges['/organization/missions'] = $scalar($pdo, "\n                SELECT COUNT(*)\n                FROM dispatch_assignments\n                WHERE organization_id = ?\n                  AND assignment_status IN ('assigned','acknowledged','responding','on_scene','transporting')\n            ", [$organizationId]);

            $badges['/organization/personnel'] = $scalar($pdo, "\n                SELECT COUNT(*)\n                FROM organization_invitations\n                WHERE organization_id = ?\n                  AND status = 'pending'\n                  AND expires_at > NOW()\n            ", [$organizationId]);
        }
    }

    if ($level === 'technical_super_admin') {
        $badges['/system/accounts'] = $scalar($pdo, "\n            SELECT COUNT(DISTINCT u.id)\n            FROM users u\n            INNER JOIN user_roles ur ON ur.user_id = u.id\n            WHERE ur.role IN ('technical_super_admin','platform_executive_admin')\n              AND u.account_status IN ('pending','suspended','disabled')\n              AND u.deleted_at IS NULL\n        ");

        $badges['/system/security'] = $scalar($pdo, "\n            SELECT COUNT(*)\n            FROM rate_limits\n            WHERE attempts >= 5\n              AND (expires_at IS NULL OR expires_at > NOW())\n        ");
    }

    // Keep the response compact and omit zeroes. Components interpret a
    // missing key exactly the same as a zero badge.
    $badges = array_filter($badges, static fn ($count) => (int) $count > 0);

    echo json_encode(['success' => true, 'badges' => $badges]);
} catch (Throwable $e) {
    error_log('Sidebar badge error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load sidebar counts.']);
}
