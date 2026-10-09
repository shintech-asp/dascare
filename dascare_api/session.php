<?php
require __DIR__ . '/cors.php';
require_once __DIR__ . '/db/db.php';

// ==================================================
// SESSION TIMEOUT
// Same 30-minute sliding-window idle timeout as Likhavite's version.
// ==================================================
$SESSION_TIMEOUT = 30 * 60;

if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > $SESSION_TIMEOUT) {
        session_unset();
        session_destroy();

        echo json_encode([
            "loggedIn" => false,
            "expired"  => true,
            "message"  => "Session expired"
        ]);
        exit;
    }
}

$_SESSION['last_activity'] = time();

function getRoleLabel(string $role): string
{
    $map = [
        'technical_super_admin'          => 'Technical Super Admin',
        'platform_executive_admin'       => 'Platform Executive Admin',
        'organization_admin'             => 'Organization Admin',
        'organization_operational_user'  => 'Organization Staff',
        'citizen'                        => 'Citizen',
    ];

    return $map[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

// The 2 roles that live in the platform_* RBAC tables (platform_roles,
// platform_permissions, platform_modules, platform_role_permissions,
// platform_account_roles) — technical/executive staff who administer
// DASCARE itself, not any one organization. See the platform block below.
const PLATFORM_SCOPED_ROLES = ['technical_super_admin', 'platform_executive_admin'];

// Roles that belong to an `organizations` row via `organization_members`
// (see index.js router). organization_admin owns the org outright;
// organization_operational_user is org staff whose actual permissions
// come from the org's own org_roles/org_role_permissions (this org's
// custom roles, e.g. "Dispatcher"/"Medic" — see the organization block
// below), the same way business_account_roles worked for Likhavite's
// `employee` role. Kept as a constant so the organization block below
// and any future callers (e.g. an org-switcher for multi-org staff) stay
// in lockstep.
const ORG_SCOPED_ROLES = ['organization_admin', 'organization_operational_user'];

// Guard so this file can also be `require`'d by other endpoints that
// just want $_SESSION populated/refreshed, without it emitting its
// own JSON response — same pattern as the Likhavite original.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {

    $response = [
        "loggedIn" => false
    ];

    /*
    |--------------------------------------------------------------------------
    | USER SESSION
    |--------------------------------------------------------------------------
    | One login (login.php → users + user_roles) for every role. This block
    | resolves who the user is; the organization block right below re-uses
    | the same $_SESSION['user_id'] / $_SESSION['user_level'] to decide
    | whether to also attach organization-membership data.
    */

    if (isset($_SESSION['user_id'])) {

        try {

            $stmt = $pdo->prepare("
                SELECT
                    u.id, u.first_name, u.last_name, u.email, u.phone,
                    u.account_status, u.email_verified_at, u.has_two_factor,
                    ur.role AS level,
                    COALESCE(k.status, 0) AS kyc_status,
                    k.verification_note AS kyc_note,
                    k.submitted_at AS kyc_submitted_at,
                    k.rejection_count AS kyc_rejection_count,
                    k.last_rejected_at AS kyc_last_rejected_at
                FROM users u
                LEFT JOIN user_roles ur
                    ON ur.user_id = u.id
                LEFT JOIN kyc_verifications k
                    ON k.user_id = u.id
                WHERE u.id = ? AND u.deleted_at IS NULL
            ");

            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['account_status'] !== 'active') {
                $status = $user['account_status'];
                session_unset();
                session_destroy();
                echo json_encode([
                    'loggedIn' => false,
                    'code' => $status === 'suspended' ? 'ACCOUNT_SUSPENDED' : 'ACCOUNT_INACTIVE',
                    'message' => $status === 'suspended'
                        ? 'Your account has been suspended.'
                        : 'Your account is not active.'
                ]);
                exit;
            }

            if ($user) {

                $_SESSION['user_name']  = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_phone'] = $user['phone'];
                $_SESSION['user_level'] = $user['level'];
                $_SESSION['user_status'] = $user['account_status'];
                // 0=unverified,1=pending,2=approved,3=rejected,4=resubmission requested — see
                // kyc_verifications.status in dascare.sql. Cached the same
                // way as the other user_* session keys so the catch block
                // below has something to fall back to on a transient DB
                // error instead of silently reporting unverified.
                $_SESSION['user_kyc_status'] = (int) $user['kyc_status'];
                // The rest of the KYC record — Verification.vue's rejection
                // modal and the "Submitted on ..." pending screen both read
                // these off session.user, so they need to actually be here,
                // not just kyc_status. All null on a fresh/never-submitted
                // account (no kyc_verifications row yet), which is fine —
                // the frontend already treats them as optional (?? '' / ?? 0).
                $_SESSION['user_kyc_note'] = $user['kyc_note'];
                $_SESSION['user_kyc_submitted_at'] = $user['kyc_submitted_at'];
                $_SESSION['user_kyc_rejection_count'] = $user['kyc_rejection_count'] !== null ? (int) $user['kyc_rejection_count'] : null;
                $_SESSION['user_kyc_last_rejected_at'] = $user['kyc_last_rejected_at'];

                $response["loggedIn"] = true;

                $response["user"] = [
                    "id"    => (int) $user['id'],
                    "name"  => $_SESSION['user_name'],
                    "email" => $user['email'],
                    "phone" => $user['phone'],
                    "level" => $user['level'],
                    "roleLabel" => getRoleLabel($user['level'] ?? ''),
                    // account_status: pending/active/suspended/disabled — a
                    // frontend inactivity/status guard can force-logout the
                    // moment an account is flipped away from 'active'
                    // mid-session, same spirit as Likhavite's useInactivityGuard.js.
                    "status" => $user['account_status'],
                    "emailVerified" => $user['email_verified_at'] !== null,
                    "hasTwoFactor"  => (bool) $user['has_two_factor'],
                    // Consumed by the KYC wall guard in index.js's router —
                    // only meaningful for citizens today, but harmless to
                    // send for every role since kyc_verifications.status
                    // defaults to 0 for anyone without a row.
                    "kyc_status" => (int) $user['kyc_status'],
                    // Consumed by Verification.vue: the rejection-reason
                    // modal (kyc_note, kyc_rejection_count, kyc_last_rejected_at)
                    // and the pending-review screen (kyc_submitted_at).
                    "kyc_note" => $user['kyc_note'],
                    "kyc_submitted_at" => $user['kyc_submitted_at'],
                    "kyc_rejection_count" => $user['kyc_rejection_count'] !== null ? (int) $user['kyc_rejection_count'] : 0,
                    "kyc_last_rejected_at" => $user['kyc_last_rejected_at'],
                ];
            }

        } catch (PDOException $e) {

            error_log("Session fetch error: " . $e->getMessage());

            $response["loggedIn"] = true;

            $response["user"] = [
                "id"    => $_SESSION['user_id'] ?? null,
                "name"  => $_SESSION['user_name'] ?? null,
                "email" => $_SESSION['user_email'] ?? null,
                "phone" => $_SESSION['user_phone'] ?? null,
                "level" => $_SESSION['user_level'] ?? null,
                "roleLabel" => getRoleLabel($_SESSION['user_level'] ?? ''),
                "status" => $_SESSION['user_status'] ?? null,
                // Falls back to whatever was last cached rather than
                // undefined→0, so a transient DB hiccup doesn't wall off an
                // already-approved citizen mid-session.
                "kyc_status" => $_SESSION['user_kyc_status'] ?? null,
                "kyc_note" => $_SESSION['user_kyc_note'] ?? null,
                "kyc_submitted_at" => $_SESSION['user_kyc_submitted_at'] ?? null,
                "kyc_rejection_count" => $_SESSION['user_kyc_rejection_count'] ?? null,
                "kyc_last_rejected_at" => $_SESSION['user_kyc_last_rejected_at'] ?? null,
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ORGANIZATION MEMBERSHIP + RBAC
    |--------------------------------------------------------------------------
    | organization_admin / organization_operational_user belong to exactly
    | one organization via organization_members. Mirrors the IMS block in
    | Likhavite's session:
    |   - role = 'organization_admin'  → owns the org outright, full
    |     permissions ('*') with no role lookup needed — same as an
    |     'artisan' shop owner in Likhavite.
    |   - role = 'organization_operational_user' → staff provisioned by an
    |     organization_admin. Their permissions are looked up from
    |     org_member_roles → org_roles (this org's own custom roles, e.g.
    |     "Dispatcher"/"Medic"/"Fleet Manager") → org_role_permissions →
    |     rbac_permissions → rbac_modules — the org-level equivalent of
    |     business_account_roles → roles → role_permissions → permissions
    |     → modules.
    */

    if (
        isset($_SESSION['user_id']) &&
        in_array($_SESSION['user_level'] ?? '', ORG_SCOPED_ROLES, true)
    ) {

        try {

            $userId = (int) $_SESSION['user_id'];
            $level  = $_SESSION['user_level'];

            $memberStmt = $pdo->prepare("
                SELECT
                    om.id AS organization_member_id,
                    om.organization_id,
                    om.employee_code,
                    om.is_default_password,
                    om.membership_status,
                    o.name AS organization_name,
                    o.organization_type,
                    o.status AS organization_status,
                    o.application_status,
                    o.application_reference,
                    o.application_submitted_at
                FROM organization_members om
                INNER JOIN organizations o ON o.id = om.organization_id
                WHERE om.user_id = ?
                  AND om.deleted_at IS NULL
                  AND o.deleted_at IS NULL
                LIMIT 1
            ");
            $memberStmt->execute([$userId]);
            $member = $memberStmt->fetch(PDO::FETCH_ASSOC);

            if ($member) {

                $assignedRoles = [];
                $roleNames     = [];

                if ($level === 'organization_admin') {
                    // Owner of this org — unrestricted within it.
                    $permissions = ['*'];
                } else {
                    // organization_operational_user — resolve from this
                    // org's own custom roles. Filtered by organization_id
                    // as well as the join itself, since org_roles are
                    // always created scoped to one org (see the
                    // trg_org_roles_active_org_only trigger in dascare.sql).
                    $roleStmt = $pdo->prepare("
                        SELECT r.id, r.role_name
                        FROM org_member_roles omr
                        INNER JOIN org_roles r ON r.id = omr.role_id
                        WHERE omr.organization_member_id = ?
                          AND r.organization_id = ?
                          AND r.deleted_at IS NULL
                    ");
                    $roleStmt->execute([$member['organization_member_id'], $member['organization_id']]);
                    $assignedRoles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
                    $roleNames     = array_column($assignedRoles, 'role_name');

                    $permStmt = $pdo->prepare("
                        SELECT DISTINCT CONCAT(m.module_key, '.', p.resource, '.', p.action) AS perm_key
                        FROM org_member_roles omr
                        JOIN org_role_permissions orp ON orp.role_id = omr.role_id
                        JOIN rbac_permissions p        ON p.id = orp.permission_id
                        JOIN rbac_modules m             ON m.id = p.module_id
                        WHERE omr.organization_member_id = ?
                    ");
                    $permStmt->execute([$member['organization_member_id']]);
                    $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);
                }

                $_SESSION['organization'] = [
                    'id'                    => (int) $member['organization_id'],
                    'name'                  => $member['organization_name'],
                    'type'                  => $member['organization_type'],
                    'status'                => $member['organization_status'],
                    'application_status'    => $member['application_status'],
                    'application_reference' => $member['application_reference'],
                    'application_submitted_at' => $member['application_submitted_at'],
                    'organization_member_id'=> (int) $member['organization_member_id'],
                    'employee_code'         => $member['employee_code'],
                    'is_default_password'   => (bool) $member['is_default_password'],
                    'membership_status'     => $member['membership_status'],
                    'permissions'           => $permissions,
                    'assigned_roles'        => $assignedRoles,
                    'role_names'            => $roleNames,
                ];

                $response["organization"] = [
                    "id"                  => (int) $member['organization_id'],
                    "name"                => $member['organization_name'],
                    "type"                => $member['organization_type'],
                    "status"              => $member['organization_status'],
                    "applicationStatus"   => $member['application_status'],
                    "applicationReference"=> $member['application_reference'],
                    "applicationSubmittedAt" => $member['application_submitted_at'],
                    "organizationMemberId"=> (int) $member['organization_member_id'],
                    "employeeCode"        => $member['employee_code'],
                    "isDefaultPassword"   => (bool) $member['is_default_password'],
                    "membershipStatus"    => $member['membership_status'],
                    "permissions"         => $permissions,
                    "assignedRoles"       => $assignedRoles,
                    "roleNames"           => $roleNames,
                ];
            } else {
                // Role says org-scoped but no membership row exists yet
                // (e.g. mid-onboarding) — not fatal, just no org attached.
                unset($_SESSION['organization']);
            }

        } catch (Throwable $e) {

            error_log("Organization session error: " . $e->getMessage());

            // Fall back to whatever was last cached, same spirit as the
            // user block's catch above — keeps the dashboard usable
            // through a transient DB hiccup instead of bouncing the user.
            if (isset($_SESSION['organization'])) {
                $response["organization"] = [
                    "id"                   => $_SESSION['organization']['id'] ?? null,
                    "name"                 => $_SESSION['organization']['name'] ?? null,
                    "type"                 => $_SESSION['organization']['type'] ?? null,
                    "status"               => $_SESSION['organization']['status'] ?? null,
                    "applicationStatus"    => $_SESSION['organization']['application_status'] ?? null,
                    "applicationReference" => $_SESSION['organization']['application_reference'] ?? null,
                    "applicationSubmittedAt" => $_SESSION['organization']['application_submitted_at'] ?? null,
                    "organizationMemberId" => $_SESSION['organization']['organization_member_id'] ?? null,
                    "employeeCode"         => $_SESSION['organization']['employee_code'] ?? null,
                    "isDefaultPassword"    => $_SESSION['organization']['is_default_password'] ?? false,
                    "membershipStatus"     => $_SESSION['organization']['membership_status'] ?? null,
                    "permissions"          => $_SESSION['organization']['permissions'] ?? [],
                    "assignedRoles"        => $_SESSION['organization']['assigned_roles'] ?? [],
                    "roleNames"            => $_SESSION['organization']['role_names'] ?? [],
                ];
            }
        }
    } else {
        // Not an org-scoped role (or not logged in) — don't let a stale
        // organization from a previous role/account leak forward.
        unset($_SESSION['organization']);
    }

    /*
    |--------------------------------------------------------------------------
    | PLATFORM RBAC
    |--------------------------------------------------------------------------
    | technical_super_admin / platform_executive_admin administer DASCARE
    | itself (organization approvals, platform-wide incident oversight,
    | analytics, system settings, audit log — see platform_modules), not
    | any single organization. Same pattern as the organization block
    | above, one tier up:
    |   - role = 'technical_super_admin' → full, unrestricted access
    |     ('*'), hardcoded with no DB role lookup — this role is
    |     is_system-locked in platform_roles and can't be edited/removed,
    |     so there's nothing to look up.
    |   - role = 'platform_executive_admin' → permissions looked up from
    |     platform_account_roles → platform_roles → platform_role_permissions
    |     → platform_permissions/platform_modules — org-level RBAC's
    |     platform-tier sibling (org_member_roles → org_roles →
    |     org_role_permissions → rbac_permissions/rbac_modules).
    */

    if (
        isset($_SESSION['user_id']) &&
        in_array($_SESSION['user_level'] ?? '', PLATFORM_SCOPED_ROLES, true)
    ) {

        try {

            $userId = (int) $_SESSION['user_id'];
            $level  = $_SESSION['user_level'];

            $assignedRoles = [];
            $roleNames     = [];

            if ($level === 'technical_super_admin') {
                $permissions = ['*'];
            } else {
                // platform_executive_admin — resolve from whichever
                // platform_roles they've been assigned (normally just the
                // seeded "Platform Executive Admin" role, id 2, but the
                // table supports more than one the same way
                // business_account_roles did for Likhavite employees).
                $roleStmt = $pdo->prepare("
                    SELECT r.id, r.role_name
                    FROM platform_account_roles par
                    INNER JOIN platform_roles r ON r.id = par.role_id
                    WHERE par.user_id = ?
                      AND r.deleted_at IS NULL
                ");
                $roleStmt->execute([$userId]);
                $assignedRoles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
                $roleNames     = array_column($assignedRoles, 'role_name');

                $permStmt = $pdo->prepare("
                    SELECT DISTINCT CONCAT(m.module_key, '.', p.resource, '.', p.action) AS perm_key
                    FROM platform_account_roles par
                    JOIN platform_role_permissions prp ON prp.role_id = par.role_id
                    JOIN platform_permissions p        ON p.id = prp.permission_id
                    JOIN platform_modules m            ON m.id = p.module_id
                    WHERE par.user_id = ?
                ");
                $permStmt->execute([$userId]);
                $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);
            }

            $_SESSION['platform'] = [
                'id'             => $userId,
                'role'           => $level,
                'permissions'    => $permissions,
                'assigned_roles' => $assignedRoles,
                'role_names'     => $roleNames,
            ];

            $response["platform"] = [
                "id"            => $userId,
                "role"          => $level,
                "roleLabel"     => getRoleLabel($level),
                "permissions"   => $permissions,
                "assignedRoles" => $assignedRoles,
                "roleNames"     => $roleNames,
            ];

        } catch (Throwable $e) {

            error_log("Platform session error: " . $e->getMessage());

            // Fall back to whatever was last cached, same spirit as the
            // user/organization blocks' catch above.
            if (isset($_SESSION['platform'])) {
                $fallback = $_SESSION['platform'];

                $response["platform"] = [
                    "id"            => $fallback['id'] ?? null,
                    "role"          => $fallback['role'] ?? null,
                    "roleLabel"     => getRoleLabel($fallback['role'] ?? ''),
                    "permissions"   => $fallback['permissions'] ?? [],
                    "assignedRoles" => $fallback['assigned_roles'] ?? [],
                    "roleNames"     => $fallback['role_names'] ?? [],
                ];
            }
        }
    } else {
        // Not a platform-scoped role (or not logged in) — don't let a
        // stale platform block from a previous role/account leak forward.
        unset($_SESSION['platform']);
    }

    echo json_encode($response);
}