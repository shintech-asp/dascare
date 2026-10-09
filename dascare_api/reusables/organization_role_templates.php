<?php

/** Create the starter operational roles for one active organization. */
function ensureStarterOrganizationRoles(PDO $pdo, int $organizationId, ?int $createdBy = null): void
{
    $templates = [
        'Dispatcher' => [
            'description' => 'Receives incident offers, reviews incidents, and coordinates resource assignment.',
            'permissions' => [
                'dispatch.dispatch_assignments.read', 'dispatch.dispatch_assignments.update', 'dispatch.dispatch_assignments.approve',
                'dispatch.crew_assignments.create', 'dispatch.crew_assignments.read', 'dispatch.crew_assignments.update', 'dispatch.mission_status.update',
                'dispatch.tracking.read', 'dispatch.tracking.update',
                'incidents.emergency_requests.read', 'incidents.emergency_requests.update', 'analytics.reports.read',
            ],
        ],
        'Driver' => [
            'description' => 'Operates assigned ambulances, follows mission status, and shares field location updates.',
            'permissions' => [
                'dispatch.dispatch_assignments.read', 'dispatch.crew_assignments.read', 'dispatch.mission_status.update',
                'dispatch.tracking.read', 'dispatch.tracking.update',
                'incidents.emergency_requests.read', 'hr.availability.update',
            ],
        ],
        'Medic / EMT' => [
            'description' => 'Handles patient assessment, field care records, endorsement, and handoff coordination.',
            'permissions' => [
                'dispatch.dispatch_assignments.read', 'dispatch.crew_assignments.read', 'dispatch.mission_status.update', 'dispatch.tracking.read', 'dispatch.tracking.update', 'incidents.emergency_requests.read',
                'incidents.incident_reports.create', 'incidents.incident_reports.read', 'incidents.incident_reports.update',
                'incidents.patient_assessments.create', 'incidents.patient_assessments.read', 'incidents.patient_assessments.update',
                'hospital.handoffs.create', 'hospital.handoffs.read', 'hospital.handoffs.update', 'hospital.handoffs.approve', 'hr.availability.update',
            ],
        ],
        'Rescuer' => [
            'description' => 'Supports field response, incident documentation, and operational mission tasks.',
            'permissions' => [
                'dispatch.dispatch_assignments.read', 'dispatch.crew_assignments.read', 'dispatch.mission_status.update', 'dispatch.tracking.read', 'dispatch.tracking.update', 'incidents.emergency_requests.read',
                'incidents.incident_reports.create', 'incidents.incident_reports.read', 'incidents.incident_reports.update',
                'hr.availability.update',
            ],
        ],
        'Fleet Manager' => [
            'description' => 'Maintains ambulance readiness, maintenance records, and fleet availability.',
            'permissions' => [
                'fleet.ambulances.create', 'fleet.ambulances.read', 'fleet.ambulances.update', 'fleet.ambulances.delete',
                'fleet.ambulance_status.update', 'fleet.ambulance_readiness.read', 'fleet.ambulance_readiness.update',
                'fleet.maintenance_records.create', 'fleet.maintenance_records.read', 'fleet.maintenance_records.update', 'hr.members.read',
            ],
        ],
        'Supervisor' => [
            'description' => 'Oversees organization operations, personnel readiness, incidents, and reports.',
            'permissions' => [
                'fleet.ambulances.create', 'fleet.ambulances.read', 'fleet.ambulances.update', 'fleet.ambulance_status.update',
                'fleet.ambulance_readiness.read', 'fleet.ambulance_readiness.update',
                'fleet.maintenance_records.create', 'fleet.maintenance_records.read', 'fleet.maintenance_records.update',
                'dispatch.dispatch_assignments.read', 'dispatch.dispatch_assignments.update', 'dispatch.dispatch_assignments.approve',
                'dispatch.crew_assignments.create', 'dispatch.crew_assignments.read', 'dispatch.crew_assignments.update', 'dispatch.mission_status.update',
                'dispatch.tracking.read', 'dispatch.tracking.update',
                'incidents.emergency_requests.read', 'incidents.emergency_requests.update',
                'incidents.incident_reports.create', 'incidents.incident_reports.read', 'incidents.incident_reports.update',
                'incidents.patient_assessments.create', 'incidents.patient_assessments.read', 'incidents.patient_assessments.update',
                'hr.members.read', 'hr.members.update', 'hr.availability.update',
                'hospital.handoffs.create', 'hospital.handoffs.read', 'hospital.handoffs.update', 'hospital.handoffs.approve',
                'analytics.reports.read', 'org_settings.org_profile.read', 'org_settings.org_documents.read',
            ],
        ],
    ];

    $orgStmt = $pdo->prepare('SELECT status FROM organizations WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $orgStmt->execute([$organizationId]);
    if ($orgStmt->fetchColumn() !== 'active') return;

    $findRole = $pdo->prepare('SELECT id FROM org_roles WHERE organization_id = ? AND role_name = ? AND deleted_at IS NULL LIMIT 1');
    $insertRole = $pdo->prepare('INSERT INTO org_roles (organization_id, role_name, description, is_system, created_by) VALUES (?, ?, ?, 1, ?)');
    $findPermission = $pdo->prepare("
        SELECT p.id
        FROM rbac_permissions p
        INNER JOIN rbac_modules m ON m.id = p.module_id
        WHERE CONCAT(m.module_key, '.', p.resource, '.', p.action) = ?
        LIMIT 1
    ");
    $linkPermission = $pdo->prepare('INSERT IGNORE INTO org_role_permissions (role_id, permission_id) VALUES (?, ?)');

    foreach ($templates as $roleName => $template) {
        $findRole->execute([$organizationId, $roleName]);
        $roleId = (int) ($findRole->fetchColumn() ?: 0);
        $createdNow = false;
        if ($roleId <= 0) {
            $insertRole->execute([$organizationId, $roleName, $template['description'], $createdBy]);
            $roleId = (int) $pdo->lastInsertId();
            $createdNow = true;
        }

        // Starter permissions are defaults, not a permanent override. Once a role
        // exists, Organization Access Control owns its permission set so removals
        // and customizations are not silently restored on the next page load.
        if ($createdNow) {
            foreach ($template['permissions'] as $permissionKey) {
                $findPermission->execute([$permissionKey]);
                $permissionId = (int) ($findPermission->fetchColumn() ?: 0);
                if ($permissionId > 0) $linkPermission->execute([$roleId, $permissionId]);
            }
        }
    }
}
