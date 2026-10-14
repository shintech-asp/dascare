/**
 * Organization/barangay sections in the staff app — the same list and
 * permission keys as the web portal's sidebar (dascare/src/config/
 * dashboardNavigation.js, "organization" workspace). `route` is set once a
 * section exists in the app; the More tab shows the rest as "next step".
 * Permissions are cosmetic here — the API checks every call.
 */
export const STAFF_SECTIONS = [
  { group: 'Dispatch', items: [
    { key: 'offers', label: 'Incident Offers', icon: 'lucide:inbox', permissions: ['dispatch.dispatch_assignments.read', 'dispatch.dispatch_assignments.approve'] },
    { key: 'missions', label: 'Active Missions', icon: 'lucide:route', permissions: ['dispatch.dispatch_assignments.read'] },
    { key: 'assignments', label: 'Resource Assignment', icon: 'lucide:git-pull-request-arrow', permissions: ['dispatch.dispatch_assignments.update', 'dispatch.crew_assignments.create'] },
    { key: 'tracking', label: 'Live Tracking', icon: 'lucide:map-pinned', permissions: ['dispatch.tracking.read', 'dispatch.dispatch_assignments.read'] },
    { key: 'transports', label: 'Scheduled Transport', icon: 'lucide:calendar-clock', permissions: ['dispatch.dispatch_assignments.read'] },
  ] },
  { group: 'Incidents & care', items: [
    { key: 'incidents', label: 'Incident Records', icon: 'lucide:clipboard-list', permissions: ['incidents.emergency_requests.read'] },
    { key: 'field-reports', label: 'Field Reports', icon: 'lucide:notebook-pen', permissions: ['incidents.incident_reports.read'] },
    { key: 'assessments', label: 'Patient Assessments', icon: 'lucide:stethoscope', permissions: ['incidents.patient_assessments.read'] },
    { key: 'handoffs', label: 'Facility Handoffs', icon: 'lucide:handshake', permissions: ['hospital.handoffs.read'] },
  ] },
  { group: 'Fleet & people', items: [
    { key: 'fleet', label: 'Fleet', icon: 'lucide:ambulance', permissions: ['fleet.ambulances.read'] },
    { key: 'readiness', label: 'Maintenance & Readiness', icon: 'lucide:wrench', permissions: ['fleet.ambulances.read', 'fleet.ambulance_readiness.read'] },
    { key: 'personnel', label: 'Personnel', icon: 'lucide:users-round', permissions: ['hr.members.read'] },
    { key: 'duty', label: 'Duty & Availability', icon: 'lucide:user-check', permissions: ['hr.members.read', 'hr.availability.update'] },
  ] },
  { group: 'Organization', items: [
    { key: 'access-control', label: 'Roles & Permissions', icon: 'lucide:key-round', permissions: ['rbac.roles.read'] },
    { key: 'profile', label: 'Organization Profile', icon: 'lucide:building', permissions: ['org_settings.org_profile.read'] },
    { key: 'documents', label: 'Documents & Credentials', icon: 'lucide:files', permissions: ['org_settings.org_documents.read'] },
    { key: 'reports', label: 'Reports & Analytics', icon: 'lucide:chart-column', permissions: ['analytics.reports.read'] },
    { key: 'audit', label: 'Audit Log', icon: 'lucide:history', permissions: ['rbac.audit_log.read'] },
  ] },
]

/** Route name for a section once it's built in the app (filled in as Phase 4 progresses). */
export const STAFF_SECTION_ROUTES = {
  missions: 'StaffMissions',
  offers: 'StaffOffers',
  assignments: 'StaffAssign',
  tracking: 'StaffTracking',
  transports: 'StaffTransports',
}
