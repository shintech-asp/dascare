export const dashboardWorkspaces = {
  technical: {
    key: 'technical',
    title: 'System Administration',
    shortTitle: 'System Admin',
    eyebrow: 'Technical Platform',
    description: 'Maintain DASCARE infrastructure, security, reliability, and platform configuration.',
    basePath: '/system',
    roleLabels: ['Technical Super Admin'],
    accent: 'red',
    primaryAction: { label: 'System Health', description: 'Check platform readiness', to: '/system/platform-health', icon: 'lucide:activity' },
    navGroups: [
      {
        label: 'Workspace',
        items: [
          { label: 'Overview', to: '/system', icon: 'lucide:layout-dashboard' },
          { label: 'Platform Health', to: '/system/platform-health', icon: 'lucide:activity' },
          { label: 'Services & APIs', to: '/system/services', icon: 'lucide:server-cog' },
        ],
      },
      {
        label: 'Administration',
        items: [
          { label: 'Administrative Accounts', to: '/system/accounts', icon: 'lucide:shield-user' },
          { label: 'Security Center', to: '/system/security', icon: 'lucide:shield-check' },
          { label: 'Backup & Recovery', to: '/system/backups', icon: 'lucide:database-backup' },
        ],
      },
      {
        label: 'Monitoring',
        items: [
          { label: 'System Logs', to: '/system/logs', icon: 'lucide:scroll-text' },
          { label: 'Audit Support', to: '/system/audit', icon: 'lucide:file-search' },
          { label: 'Configuration', to: '/system/configuration', icon: 'lucide:sliders-horizontal' },
        ],
      },
      {
        label: 'Account',
        items: [
          { label: 'Settings', to: '/system/settings', icon: 'lucide:settings' },
        ],
      },
    ],
  },

  platform: {
    key: 'platform',
    title: 'Platform Executive Dashboard',
    shortTitle: 'Executive Admin',
    eyebrow: 'Dasmariñas City Operations',
    description: 'Oversee organizations, city-wide incidents, compliance, analytics, and operational policy.',
    basePath: '/platform',
    roleLabels: ['Platform Executive Admin'],
    accent: 'red',
    primaryAction: { label: 'Review Applications', description: 'Verify new rescue organizations', to: '/platform/applications', icon: 'lucide:building-2' },
    navGroups: [
      {
        label: 'Workspace',
        items: [
          { label: 'Overview', to: '/platform', icon: 'lucide:layout-dashboard' },
          { label: 'Organization Applications', to: '/platform/applications', icon: 'lucide:building-2', permissions: ['organizations.organizations.read'] },
          { label: 'Approved Organizations', to: '/platform/organizations', icon: 'lucide:badge-check', permissions: ['organizations.organizations.read'] },
          { label: 'Compliance Review', to: '/platform/compliance', icon: 'lucide:file-check-2', permissions: ['organizations.org_documents.read'] },
        ],
      },
      {
        label: 'City Operations',
        items: [
          { label: 'City-wide Incidents', to: '/platform/incidents', icon: 'lucide:siren', permissions: ['oversight.emergency_requests.read'] },
          { label: 'Fleet Availability', to: '/platform/fleet-availability', icon: 'lucide:ambulance', permissions: ['oversight.emergency_requests.read'] },
          { label: 'Escalations', to: '/platform/escalations', icon: 'lucide:triangle-alert', permissions: ['oversight.emergency_requests.update'] },
          { label: 'Cancellations & Complaints', to: '/platform/reviews', icon: 'lucide:message-square-warning', permissions: ['oversight.emergency_requests.update'] },
          { label: 'False Alarm Review', to: '/platform/false-alarms', icon: 'lucide:shield-alert', permissions: ['oversight.emergency_requests.update'] },
        ],
      },
      {
        label: 'Oversight',
        items: [
          { label: 'Citizen Verifications', to: '/platform/citizen-verifications', icon: 'lucide:scan-search', permissions: ['citizens.kyc_verifications.read'] },
          { label: 'Citizen Accounts', to: '/platform/citizens', icon: 'lucide:users', permissions: ['citizens.citizens.read'] },
          { label: 'Heatmaps', to: '/platform/heatmaps', icon: 'lucide:map' , permissions: ['analytics.reports.read'] },
          { label: 'Analytics & Reports', to: '/platform/analytics', icon: 'lucide:chart-no-axes-combined', permissions: ['analytics.reports.read'] },
          { label: 'DSS & Operations', to: '/platform/dss-settings', icon: 'lucide:brain-circuit', permissions: ['system.system_settings.read'] },
          { label: 'Audit Logs', to: '/platform/audit', icon: 'lucide:history', permissions: ['audit.audit_log.read'] },
          { label: 'Platform Policies', to: '/platform/policies', icon: 'lucide:notebook-tabs' },
        ],
      },
      {
        label: 'Account',
        items: [
          { label: 'Settings', to: '/platform/settings', icon: 'lucide:settings' },
        ],
      },
    ],
  },

  organization: {
    key: 'organization',
    title: 'Organization Operations',
    shortTitle: 'Organization Portal',
    eyebrow: 'Ambulance & Rescue Workspace',
    description: 'Coordinate incidents, personnel, fleet readiness, assignments, and organization compliance.',
    basePath: '/organization',
    roleLabels: ['Organization Admin', 'Organization Staff'],
    accent: 'red',
    primaryAction: { label: 'Incident Offers', description: 'Review incoming rescue work', to: '/organization/offers', icon: 'lucide:siren' },
    navGroups: [
      {
        label: 'Workspace',
        items: [
          { label: 'Overview', to: '/organization', icon: 'lucide:layout-dashboard' },
          { label: 'Incident Offers', to: '/organization/offers', icon: 'lucide:inbox', permissions: ['dispatch.dispatch_assignments.read', 'dispatch.dispatch_assignments.approve'], permissionMode: 'any' },
          { label: 'Active Missions', to: '/organization/missions', icon: 'lucide:route', permissions: ['dispatch.dispatch_assignments.read'] },
          { label: 'Resource Assignment', to: '/organization/assignments', icon: 'lucide:git-pull-request-arrow', permissions: ['dispatch.dispatch_assignments.update', 'dispatch.crew_assignments.create'], permissionMode: 'any' },
          { label: 'Live Tracking', to: '/organization/tracking', icon: 'lucide:map-pinned', permissions: ['dispatch.tracking.read', 'dispatch.dispatch_assignments.read'], permissionMode: 'any' },
          { label: 'Scheduled Transport', to: '/organization/transports', icon: 'lucide:calendar-clock', permissions: ['dispatch.dispatch_assignments.read'] },
        ],
      },
      {
        label: 'Incidents & Care',
        items: [
          { label: 'Incident Records', to: '/organization/incidents', icon: 'lucide:clipboard-list', permissions: ['incidents.emergency_requests.read'] },
          { label: 'Field Reports', to: '/organization/field-reports', icon: 'lucide:notebook-pen', permissions: ['incidents.incident_reports.read'] },
          { label: 'Patient Assessments', to: '/organization/assessments', icon: 'lucide:stethoscope', permissions: ['incidents.patient_assessments.read'] },
          { label: 'Facility Handoffs', to: '/organization/handoffs', icon: 'lucide:handshake', permissions: ['hospital.handoffs.read'] },
        ],
      },
      {
        label: 'Resources',
        items: [
          { label: 'Fleet', to: '/organization/fleet', icon: 'lucide:ambulance', permissions: ['fleet.ambulances.read'] },
          { label: 'Maintenance & Readiness', to: '/organization/readiness', icon: 'lucide:wrench', permissions: ['fleet.ambulances.read', 'fleet.ambulance_readiness.read', 'fleet.ambulance_status.update'], permissionMode: 'any' },
          { label: 'Personnel', to: '/organization/personnel', icon: 'lucide:users-round', permissions: ['hr.members.read'] },
          { label: 'Duty & Availability', to: '/organization/duty', icon: 'lucide:user-check', permissions: ['hr.members.read', 'hr.availability.update'], permissionMode: 'any' },
          { label: 'Roles & Permissions', to: '/organization/access-control', icon: 'lucide:key-round', permissions: ['rbac.roles.read'] },
        ],
      },
      {
        label: 'Management',
        items: [
          { label: 'Organization Profile', to: '/organization/profile', icon: 'lucide:building', permissions: ['org_settings.org_profile.read'] },
          { label: 'Documents & Credentials', to: '/organization/documents', icon: 'lucide:files', permissions: ['org_settings.org_documents.read'] },
          { label: 'Reports & Analytics', to: '/organization/reports', icon: 'lucide:chart-column', permissions: ['analytics.reports.read'] },
          { label: 'Audit Log', to: '/organization/audit', icon: 'lucide:history', permissions: ['rbac.audit_log.read'] },
          { label: 'Settings', to: '/organization/settings', icon: 'lucide:settings' },
        ],
      },
    ],
  },
}

export function findWorkspaceItem(workspace, path) {
  return workspace.navGroups.flatMap((group) => group.items).find((item) => item.to === path)
}
