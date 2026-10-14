import { createRouter, createWebHashHistory } from 'vue-router'
import { useSession } from '@/composables/useSession'

import WelcomeView from '@/views/WelcomeView.vue'
import AppShell from '@/layouts/AppShell.vue'
import StaffShell from '@/layouts/StaffShell.vue'

/**
 * meta:
 *   guestOnly — account screens; a signed-in citizen is sent home instead
 *   auth      — needs a signed-in citizen (else → Welcome)
 *   tab       — highlights that bottom-bar tab (inside AppShell)
 *   kyc       — needs an approved ID (else the screen shows the KYC gate);
 *               same pages the web locks with requiresKyc (router/index.js)
 *   title     — top bar title
 *   citizen   — citizen-only (staff are sent to their own home)
 *   staff     — organization/barangay staff only (Phase 4; StaffShell)
 * SOS is open to everyone, signed in or not — never gate an emergency.
 */
const routes = [
  { path: '/', redirect: () => { const s = useSession(); return !s.isLoggedIn.value ? '/welcome' : s.isStaff.value ? '/staff/home' : '/home' } },
  { path: '/welcome', name: 'Welcome', component: WelcomeView, meta: { guestOnly: true, root: true } },
  { path: '/login', name: 'Login', component: () => import('@/views/auth/LoginView.vue'), meta: { guestOnly: true } },
  { path: '/register', name: 'Register', component: () => import('@/views/auth/RegisterView.vue'), meta: { guestOnly: true } },
  { path: '/verify-code', name: 'VerifyCode', component: () => import('@/views/auth/OtpView.vue'), meta: { guestOnly: true } },
  { path: '/forgot-password', name: 'ForgotPassword', component: () => import('@/views/auth/ForgotView.vue'), meta: { guestOnly: true } },
  { path: '/reset-password', name: 'ResetPassword', component: () => import('@/views/auth/ResetPasswordView.vue'), meta: { guestOnly: true } },
  // Open to guests too: citizen/detail.php checks ownership (account, or the
  // guest SOS key the app sends).
  { path: '/track/:id', name: 'Track', component: () => import('@/views/TrackView.vue'), meta: { title: 'Request' } },
  { path: '/sos', name: 'Sos', component: () => import('@/views/SosView.vue'), meta: { title: 'Emergency SOS' } },
  { path: '/medical', name: 'MedicalRecords', component: () => import('@/views/MedicalRecordsView.vue'), meta: { auth: true, citizen: true, title: 'Emergency information' } },
  { path: '/account', name: 'Account', component: () => import('@/views/AccountView.vue'), meta: { auth: true, title: 'Account & security' } },
  { path: '/verify-identity', name: 'VerifyIdentity', component: () => import('@/views/VerifyIdentityView.vue'), meta: { auth: true, citizen: true, title: 'Verify identity' } },
  { path: '/legal/:slug', name: 'Legal', component: () => import('@/views/LegalView.vue'), meta: { title: 'Legal' } },
  { path: '/diagnostics', name: 'Diagnostics', component: () => import('@/views/ConnectionCheck.vue'), meta: { title: 'Server connection' } },
  {
    path: '/',
    component: AppShell,
    meta: { auth: true, citizen: true },
    children: [
      { path: 'home', name: 'Home', component: () => import('@/views/HomeView.vue'), meta: { tab: 'home', root: true } },
      { path: 'requests', name: 'Requests', component: () => import('@/views/RequestsView.vue'), meta: { tab: 'requests', title: 'My Requests', kyc: true } },
      { path: 'notifications', name: 'Notifications', component: () => import('@/views/NotificationsView.vue'), meta: { tab: 'notifications', title: 'Notifications' } },
      { path: 'profile', name: 'Profile', component: () => import('@/views/ProfileView.vue'), meta: { tab: 'profile', title: 'Profile' } },
    ],
  },
  // Organization/barangay staff (Phase 4)
  {
    path: '/staff',
    component: StaffShell,
    meta: { auth: true, staff: true },
    children: [
      { path: '', redirect: '/staff/home' },
      { path: 'home', name: 'StaffHome', component: () => import('@/views/staff/StaffHomeView.vue'), meta: { tab: 'home', root: true } },
      { path: 'missions', name: 'StaffMissions', component: () => import('@/views/staff/StaffMissionsView.vue'), meta: { tab: 'missions', title: 'Missions' } },
      { path: 'alerts', name: 'StaffAlerts', component: () => import('@/views/NotificationsView.vue'), meta: { tab: 'notifications', title: 'Alerts' } },
      { path: 'more', name: 'StaffMore', component: () => import('@/views/staff/StaffMoreView.vue'), meta: { tab: 'more', title: 'More' } },
    ],
  },
  // Stacked staff screens (own ScreenHeader, no tab bar)
  { path: '/staff/missions/:id', name: 'StaffMission', component: () => import('@/views/staff/StaffMissionView.vue'), meta: { auth: true, staff: true, title: 'Mission' } },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  // Hash history: Capacitor serves the app from local files, so there's no
  // server to rewrite deep links back to index.html.
  history: createWebHashHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const session = useSession()
  await session.init()
  const home = session.isStaff.value ? { name: 'StaffHome' } : { name: 'Home' }
  if (to.matched.some((r) => r.meta.auth) && !session.isLoggedIn.value) return { name: 'Welcome' }
  if (to.meta.guestOnly && session.isLoggedIn.value) return home
  if (session.isLoggedIn.value && session.isStaff.value && to.matched.some((r) => r.meta.citizen)) return home
  if (session.isLoggedIn.value && !session.isStaff.value && to.matched.some((r) => r.meta.staff)) return home
  return true
})

export default router
