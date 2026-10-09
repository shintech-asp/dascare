import { createRouter, createWebHistory } from 'vue-router'
import { useSession } from '@/composables/useSession'
import { peekPostLoginRedirect, setPostLoginRedirect, clearPostLoginRedirect } from '@/utils/postLoginRedirect'

import PublicLayout from '@/layouts/PublicLayout.vue'
import CitizenLayout from '@/layouts/CitizenLayout.vue'
import TechnicalAdminLayout from '@/layouts/TechnicalAdminLayout.vue'
import PlatformAdminLayout from '@/layouts/PlatformAdminLayout.vue'
import OrganizationLayout from '@/layouts/OrganizationLayout.vue'

import LandingView from '@/views/LandingView.vue'
import AuthPage from '@/views/auth/AuthPage.vue'
import DashboardPlaceholder from '@/views/shared/DashboardPlaceholder.vue'
import About from '@/views/pages/About.vue'
import TermsOfService from '@/views/pages/TermsOfService.vue'
import PrivacyPolicy from '@/views/pages/PrivacyPolicy.vue'
import OrganizationApplication from '@/views/public/OrganizationApplication.vue'
import JoinOrganization from '@/views/public/JoinOrganization.vue'

import CitizenDashboardHome from '@/views/citizen/CitizenDashboardHome.vue'
import RequestEmergency from '@/views/citizen/CitizenPages/RequestEmergency.vue'
import MyRequests from '@/views/citizen/CitizenPages/MyRequests.vue'
import TrackRequest from '@/views/citizen/CitizenPages/TrackRequest.vue'
import CitizenNotifications from '@/views/citizen/CitizenPages/CitizenNotifications.vue'
import MedicalRecords from '@/views/citizen/CitizenPages/MedicalRecords.vue'
import CitizenSettings from '@/views/citizen/CitizenPages/CitizenSettings.vue'
import CitizenVerification from '@/views/pages/Verification.vue'

import TechnicalDashboardHome from '@/views/technical/TechnicalDashboardHome.vue'
import TechnicalSettings from '@/views/technical/pages/TechnicalSettings.vue'
import PlatformDashboardHome from '@/views/platform/PlatformDashboardHome.vue'
import PlatformSettings from '@/views/platform/pages/PlatformSettings.vue'
import CitizenVerifications from '@/views/platform/PlatformPages/CitizenVerifications.vue'
import OrganizationApplications from '@/views/platform/PlatformPages/OrganizationApplications.vue'
import OrganizationDashboardHome from '@/views/organization/OrganizationDashboardHome.vue'
import OrganizationSettings from '@/views/organization/pages/OrganizationSettings.vue'
import OrganizationApplicationStatus from '@/views/organization/OrganizationPages/OrganizationApplicationStatus.vue'
import OrganizationProfile from '@/views/organization/OrganizationPages/OrganizationProfile.vue'
import OrganizationPersonnel from '@/views/organization/OrganizationPages/OrganizationPersonnel.vue'
import OrganizationAccessControl from '@/views/organization/OrganizationPages/OrganizationAccessControl.vue'
import OrganizationFleet from '@/views/organization/OrganizationPages/OrganizationFleet.vue'
import OrganizationReadiness from '@/views/organization/OrganizationPages/OrganizationReadiness.vue'
import OrganizationMembershipStatus from '@/views/organization/OrganizationPages/OrganizationMembershipStatus.vue'
import CitizenAccounts from '@/views/platform/PlatformPages/CitizenAccounts.vue'
import CityWideIncidents from '@/views/platform/PlatformPages/CityWideIncidents.vue'
import OrganizationIncidentOffers from '@/views/organization/OrganizationPages/OrganizationIncidentOffers.vue'
import OrganizationResourceAssignments from '@/views/organization/OrganizationPages/OrganizationResourceAssignments.vue'
import OrganizationActiveMissions from '@/views/organization/OrganizationPages/OrganizationActiveMissions.vue'
import ApprovedOrganizations from '@/views/platform/PlatformPages/ApprovedOrganizations.vue'
import ComplianceReview from '@/views/platform/PlatformPages/ComplianceReview.vue'
import FleetAvailability from '@/views/platform/PlatformPages/FleetAvailability.vue'
import PlatformAnalytics from '@/views/platform/PlatformPages/PlatformAnalytics.vue'
import DssOperationsSettings from '@/views/platform/PlatformPages/DssOperationsSettings.vue'
import PlatformAuditLogs from '@/views/platform/PlatformPages/PlatformAuditLogs.vue'
import IncidentEscalations from '@/views/platform/PlatformPages/IncidentEscalations.vue'
import IncidentHeatmap from '@/views/platform/PlatformPages/IncidentHeatmap.vue'
import PlatformPolicies from '@/views/platform/PlatformPages/PlatformPolicies.vue'
import RequestReviewQueue from '@/views/platform/PlatformPages/RequestReviewQueue.vue'
import TechnicalSystemStatus from '@/views/technical/TechnicalPages/TechnicalSystemStatus.vue'
import TechnicalAccounts from '@/views/technical/TechnicalPages/TechnicalAccounts.vue'
import TechnicalAudit from '@/views/technical/TechnicalPages/TechnicalAudit.vue'
import TechnicalConfiguration from '@/views/technical/TechnicalPages/TechnicalConfiguration.vue'
import OrganizationDuty from '@/views/organization/OrganizationPages/OrganizationDuty.vue'
import OrganizationDocuments from '@/views/organization/OrganizationPages/OrganizationDocuments.vue'
import OrganizationReports from '@/views/organization/OrganizationPages/OrganizationReports.vue'
import OrganizationAudit from '@/views/organization/OrganizationPages/OrganizationAudit.vue'
import OrganizationIncidentRecords from '@/views/organization/OrganizationPages/OrganizationIncidentRecords.vue'
import OrganizationTracking from '@/views/organization/OrganizationPages/OrganizationTracking.vue'
import OrganizationFieldReports from '@/views/organization/OrganizationPages/OrganizationFieldReports.vue'
import OrganizationAssessments from '@/views/organization/OrganizationPages/OrganizationAssessments.vue'
import OrganizationHandoffs from '@/views/organization/OrganizationPages/OrganizationHandoffs.vue'

const HOME_PATH_BY_ROLE = {
  technical_super_admin: '/system',
  platform_executive_admin: '/platform',
  organization_admin: '/organization',
  organization_operational_user: '/organization',
  citizen: '/citizen',
}

const placeholder = ({
  path,
  name,
  title,
  section,
  description,
  icon,
  permissions,
  permissionMode = 'any',
  features,
}) => ({
  path,
  name,
  component: DashboardPlaceholder,
  meta: { title, section, description, icon, permissions, permissionMode, features },
})

const technicalChildren = [
  { path: '', name: 'TechnicalDashboardHome', component: TechnicalDashboardHome, meta: { title: 'Overview' } },
  { path: 'platform-health', name: 'TechnicalPlatformHealth', component: TechnicalSystemStatus, meta: { title: 'Platform Health', mode: 'health' } },
  { path: 'services', name: 'TechnicalServices', component: TechnicalSystemStatus, meta: { title: 'Services & APIs', mode: 'services' } },
  { path: 'accounts', name: 'TechnicalAccounts', component: TechnicalAccounts, meta: { title: 'Administrative Accounts' } },
  { path: 'security', name: 'TechnicalSecurity', component: TechnicalSystemStatus, meta: { title: 'Security Center', mode: 'security' } },
  { path: 'backups', name: 'TechnicalBackups', component: TechnicalSystemStatus, meta: { title: 'Backup & Recovery', mode: 'backups' } },
  { path: 'logs', name: 'TechnicalLogs', component: TechnicalAudit, meta: { title: 'System Logs' } },
  { path: 'audit', name: 'TechnicalAuditSupport', component: TechnicalAudit, meta: { title: 'Audit Support' } },
  { path: 'configuration', name: 'TechnicalConfiguration', component: TechnicalConfiguration, meta: { title: 'System Configuration' } },
  { path: 'settings', name: 'TechnicalSettings', component: TechnicalSettings, meta: { title: 'Settings' } },
]

const platformChildren = [
  { path: '', name: 'PlatformDashboardHome', component: PlatformDashboardHome, meta: { title: 'Overview' } },
  { path: 'applications', name: 'PlatformApplications', component: OrganizationApplications, meta: { title: 'Organization Applications', permissions: ['organizations.organizations.read'] } },
  { path: 'organizations', name: 'PlatformOrganizations', component: ApprovedOrganizations, meta: { title: 'Approved Organizations', permissions: ['organizations.organizations.read'] } },
  { path: 'compliance', name: 'PlatformCompliance', component: ComplianceReview, meta: { title: 'Compliance Review', permissions: ['organizations.org_documents.read'] } },
  { path: 'incidents', name: 'PlatformIncidents', component: CityWideIncidents, meta: { title: 'City-wide Incidents', permissions: ['oversight.emergency_requests.read'] } },
  { path: 'fleet-availability', name: 'PlatformFleetAvailability', component: FleetAvailability, meta: { title: 'Fleet Availability', permissions: ['oversight.emergency_requests.read'] } },
  { path: 'escalations', name: 'PlatformEscalations', component: IncidentEscalations, meta: { title: 'Incident Escalations', permissions: ['oversight.emergency_requests.update'] } },
  { path: 'reviews', name: 'PlatformReviews', component: RequestReviewQueue, meta: { title: 'Cancellations & Complaints', reviewMode: 'cancelled', permissions: ['oversight.emergency_requests.update'] } },
  { path: 'false-alarms', name: 'PlatformFalseAlarms', component: RequestReviewQueue, meta: { title: 'False Alarm Review', reviewMode: 'false_alarm', permissions: ['oversight.emergency_requests.update'] } },
  { path: 'citizen-verifications', name: 'PlatformCitizenVerifications', component: CitizenVerifications, meta: { title: 'Citizen Verifications', permissions: ['citizens.kyc_verifications.read'] } },
  { path: 'citizens', name: 'PlatformCitizens', component: CitizenAccounts, meta: { title: 'Citizen Accounts', permissions: ['citizens.citizens.read'] } },
  { path: 'heatmaps', name: 'PlatformHeatmaps', component: IncidentHeatmap, meta: { title: 'Incident Heatmaps', permissions: ['analytics.reports.read'] } },
  { path: 'analytics', name: 'PlatformAnalytics', component: PlatformAnalytics, meta: { title: 'Analytics & Reports', permissions: ['analytics.reports.read'] } },
  { path: 'dss-settings', name: 'PlatformDssSettings', component: DssOperationsSettings, meta: { title: 'DSS & Operational Settings', permissions: ['system.system_settings.read'] } },
  { path: 'audit', name: 'PlatformAudit', component: PlatformAuditLogs, meta: { title: 'Platform Audit Logs', permissions: ['audit.audit_log.read'] } },
  { path: 'policies', name: 'PlatformPolicies', component: PlatformPolicies, meta: { title: 'Platform Policies' } },
  { path: 'settings', name: 'PlatformSettings', component: PlatformSettings, meta: { title: 'Settings' } },
]

const organizationChildren = [
  { path: '', name: 'OrganizationDashboardHome', component: OrganizationDashboardHome, meta: { title: 'Overview' } },
  { path: 'application-status', name: 'OrganizationApplicationStatus', component: OrganizationApplicationStatus, meta: { title: 'Application Status', allowInactiveOrganization: true } },
  { path: 'offers', name: 'OrganizationOffers', component: OrganizationIncidentOffers, meta: { title: 'Incident Offers', permissions: ['dispatch.dispatch_assignments.read', 'dispatch.dispatch_assignments.approve'] } },
  { path: 'missions', name: 'OrganizationMissions', component: OrganizationActiveMissions, meta: { title: 'Active Missions', permissions: ['dispatch.dispatch_assignments.read'] } },
  { path: 'assignments', name: 'OrganizationAssignments', component: OrganizationResourceAssignments, meta: { title: 'Resource Assignment', permissions: ['dispatch.dispatch_assignments.update', 'dispatch.crew_assignments.create'], permissionMode: 'any' } },
  { path: 'tracking', name: 'OrganizationTracking', component: OrganizationTracking, meta: { title: 'Live Mission Tracking', permissions: ['dispatch.tracking.read', 'dispatch.dispatch_assignments.read'], permissionMode: 'any' } },
  placeholder({ path: 'transports', name: 'OrganizationTransports', title: 'Scheduled Transport', section: 'Dispatch', icon: 'lucide:calendar-clock', description: 'Coordinate planned patient transport, reservations, readiness checks, conflicts, and rescheduling.', permissions: ['dispatch.dispatch_assignments.read'] }),
  { path: 'incidents', name: 'OrganizationIncidents', component: OrganizationIncidentRecords, meta: { title: 'Incident Records', permissions: ['incidents.emergency_requests.read'] } },
  { path: 'field-reports', name: 'OrganizationFieldReports', component: OrganizationFieldReports, meta: { title: 'Field Reports', permissions: ['incidents.incident_reports.read'] } },
  { path: 'assessments', name: 'OrganizationAssessments', component: OrganizationAssessments, meta: { title: 'Patient Assessments', permissions: ['incidents.patient_assessments.read'] } },
  { path: 'handoffs', name: 'OrganizationHandoffs', component: OrganizationHandoffs, meta: { title: 'Facility Handoffs', permissions: ['hospital.handoffs.read'] } },
  { path: 'fleet', name: 'OrganizationFleet', component: OrganizationFleet, meta: { title: 'Fleet', permissions: ['fleet.ambulances.read'] } },
  { path: 'readiness', name: 'OrganizationReadiness', component: OrganizationReadiness, meta: { title: 'Maintenance & Readiness', permissions: ['fleet.ambulances.read'] } },
  { path: 'personnel', name: 'OrganizationPersonnel', component: OrganizationPersonnel, meta: { title: 'Personnel', permissions: ['hr.members.read'] } },
  { path: 'duty', name: 'OrganizationDuty', component: OrganizationDuty, meta: { title: 'Duty & Availability', permissions: ['hr.members.read', 'hr.availability.update'], permissionMode: 'any' } },
  { path: 'access-control', name: 'OrganizationAccessControl', component: OrganizationAccessControl, meta: { title: 'Roles & Permissions', permissions: ['rbac.roles.read'] } },
  { path: 'membership-status', name: 'OrganizationMembershipStatus', component: OrganizationMembershipStatus, meta: { title: 'Membership Status', allowInactiveMembership: true } },
  { path: 'profile', name: 'OrganizationProfile', component: OrganizationProfile, meta: { title: 'Organization Profile', permissions: ['org_settings.org_profile.read'] } },
  { path: 'documents', name: 'OrganizationDocuments', component: OrganizationDocuments, meta: { title: 'Documents & Credentials', permissions: ['org_settings.org_documents.read'] } },
  { path: 'reports', name: 'OrganizationReports', component: OrganizationReports, meta: { title: 'Reports & Analytics', permissions: ['analytics.reports.read'] } },
  { path: 'audit', name: 'OrganizationAudit', component: OrganizationAudit, meta: { title: 'Organization Audit Log', permissions: ['rbac.audit_log.read'] } },
  { path: 'settings', name: 'OrganizationSettings', component: OrganizationSettings, meta: { title: 'Settings' } },
]

const routes = [
  {
    path: '/',
    component: PublicLayout,
    children: [
      { path: '', name: 'Landing', component: LandingView, meta: { title: 'Home' } },
      { path: 'login', name: 'Login', component: AuthPage, meta: { title: 'Login', guestOnly: true } },
      { path: 'register', name: 'Register', component: AuthPage, meta: { title: 'Register', guestOnly: true } },
      { path: 'apply/organization', name: 'OrganizationApplication', component: OrganizationApplication, meta: { title: 'Organization Application' } },
      { path: 'join-organization', name: 'JoinOrganization', component: JoinOrganization, meta: { title: 'Join Organization', guestOnly: true } },
      { path: 'about', name: 'About', component: About, meta: { title: 'About' } },
      { path: 'terms-of-service', name: 'TermsOfService', component: TermsOfService, meta: { title: 'Terms of Service' } },
      { path: 'privacy-policy', name: 'PrivacyPolicy', component: PrivacyPolicy, meta: { title: 'Privacy Policy' } },
    ],
  },
  {
    path: '/citizen',
    component: CitizenLayout,
    meta: { requiresAuth: true, roles: ['citizen'], dashboardArea: true, citizenArea: true },
    children: [
      { path: '', name: 'CitizenDashboardHome', component: CitizenDashboardHome, meta: { title: 'Dashboard', requiresKyc: true } },
      { path: 'request', name: 'RequestEmergency', component: RequestEmergency, meta: { title: 'Request Assistance' } },
      { path: 'requests', name: 'MyRequests', component: MyRequests, meta: { title: 'My Requests', requiresKyc: true } },
      { path: 'requests/:id', name: 'TrackRequest', component: TrackRequest, meta: { title: 'Request Details', requiresKyc: true } },
      { path: 'medical-records', name: 'MedicalRecords', component: MedicalRecords, meta: { title: 'Emergency Information', requiresKyc: true } },
      { path: 'notifications', name: 'CitizenNotifications', component: CitizenNotifications, meta: { title: 'Notifications' } },
      { path: 'settings', name: 'CitizenSettings', component: CitizenSettings, meta: { title: 'Settings' } },
      { path: 'verify', name: 'CitizenVerification', component: CitizenVerification, meta: { title: 'Verification' } },
    ],
  },
  {
    path: '/system',
    component: TechnicalAdminLayout,
    meta: { requiresAuth: true, roles: ['technical_super_admin'], dashboardArea: true },
    children: technicalChildren,
  },
  {
    path: '/platform',
    component: PlatformAdminLayout,
    meta: { requiresAuth: true, roles: ['platform_executive_admin'], dashboardArea: true },
    children: platformChildren,
  },
  {
    path: '/organization',
    component: OrganizationLayout,
    meta: { requiresAuth: true, roles: ['organization_admin', 'organization_operational_user'], dashboardArea: true },
    children: organizationChildren,
  },
  { path: '/staff/:pathMatch(.*)*', redirect: '/organization' },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    if (to.hash) {
      // Landing-page section anchors (#decision-support, #roles,
      // #mobile-app, ...) only exist on LandingView — when navigating to
      // them from a different route, that component hasn't mounted (and
      // rendered those sections) yet at the instant scrollBehavior runs,
      // so `document.querySelector` comes back empty and vue-router logs
      // VUE_ROUTER_R0042. Wait a tick and re-check before scrolling;
      // fall back to the top of the page instead of throwing if the
      // element genuinely doesn't exist (e.g. a stale/typo'd hash).
      return new Promise((resolve) => {
        setTimeout(() => {
          const el = document.querySelector(to.hash)
          resolve(el ? { el: to.hash, behavior: 'smooth' } : { top: 0 })
        }, 300)
      })
    }
    return { top: 0 }
  },
})

router.beforeEach(async (to) => {
  const session = useSession()
  const needsSession = to.meta.requiresAuth || to.meta.roles || to.meta.permissions || to.meta.guestOnly || Boolean(peekPostLoginRedirect())

  // Resolve the session once on the first navigation so a refreshed public
  // page can still send an authenticated user back to the correct dashboard.
  if (!needsSession && session.ready.value) return true
  await session.fetchSession()
  return true
})

router.beforeEach((to) => {
  if (!to.meta.requiresAuth) return true
  const { user } = useSession()
  if (user.value) return true
  setPostLoginRedirect(to.fullPath)
  return { path: '/login', query: { redirect: to.fullPath } }
})

router.beforeEach((to) => {
  if (!to.meta.roles) return true
  const { user } = useSession()
  if (to.meta.roles.includes(user.value?.level)) return true
  return HOME_PATH_BY_ROLE[user.value?.level] || '/'
})

router.beforeEach((to) => {
  const { user, organization } = useSession()
  if (!['organization_admin', 'organization_operational_user'].includes(user.value?.level)) return true
  if (to.meta.allowInactiveOrganization) return true
  if (!organization.value) return { name: 'OrganizationApplicationStatus' }
  if (organization.value.status !== 'active') return { name: 'OrganizationApplicationStatus' }
  if (user.value?.level === 'organization_operational_user' && !['active', 'invited'].includes(organization.value.membershipStatus)) {
    return to.meta.allowInactiveMembership ? true : { name: 'OrganizationMembershipStatus' }
  }
  if (to.name === 'OrganizationMembershipStatus') return { name: 'OrganizationDashboardHome' }
  return true
})

router.beforeEach((to) => {
  if (!to.meta.permissions?.length) return true
  const { user, hasAnyPermission, hasAllPermissions } = useSession()
  const allowed = to.meta.permissionMode === 'all'
    ? hasAllPermissions(...to.meta.permissions)
    : hasAnyPermission(...to.meta.permissions)
  return allowed ? true : (HOME_PATH_BY_ROLE[user.value?.level] || '/')
})

router.beforeEach((to) => {
  if (!to.meta.requiresKyc) return true
  const { user } = useSession()
  return user.value?.kyc_status === 2 ? true : { name: 'CitizenVerification' }
})

router.beforeEach((to) => {
  if (to.meta.guestOnly) {
    const { user } = useSession()
    if (!user.value) return true
    clearPostLoginRedirect()
    return HOME_PATH_BY_ROLE[user.value.level] || '/'
  }
  return true
})

router.beforeEach((to) => {
  if (to.meta.dashboardArea || to.meta.requiresAuth || to.meta.guestOnly) return true
  const { user } = useSession()
  return user.value ? (HOME_PATH_BY_ROLE[user.value.level] || '/') : true
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} | DASCARE` : 'DASCARE'
})

export default router