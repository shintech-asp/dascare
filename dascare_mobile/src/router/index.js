import { createRouter, createWebHashHistory } from 'vue-router'
import { useSession } from '@/composables/useSession'

import WelcomeView from '@/views/WelcomeView.vue'
import AppShell from '@/layouts/AppShell.vue'

/**
 * meta:
 *   guestOnly — account screens; a signed-in citizen is sent home instead
 *   auth      — needs a signed-in citizen (else → Welcome)
 *   tab       — highlights that bottom-bar tab (inside AppShell)
 *   kyc       — needs an approved ID (else the screen shows the KYC gate);
 *               same pages the web locks with requiresKyc (router/index.js)
 *   title     — top bar title
 * SOS is open to everyone, signed in or not — never gate an emergency.
 */
const routes = [
  { path: '/', redirect: () => (useSession().isLoggedIn.value ? '/home' : '/welcome') },
  { path: '/welcome', name: 'Welcome', component: WelcomeView, meta: { guestOnly: true, root: true } },
  { path: '/login', name: 'Login', component: () => import('@/views/auth/LoginView.vue'), meta: { guestOnly: true } },
  { path: '/register', name: 'Register', component: () => import('@/views/auth/RegisterView.vue'), meta: { guestOnly: true } },
  { path: '/verify-code', name: 'VerifyCode', component: () => import('@/views/auth/OtpView.vue'), meta: { guestOnly: true } },
  { path: '/forgot-password', name: 'ForgotPassword', component: () => import('@/views/auth/ForgotView.vue'), meta: { guestOnly: true } },
  { path: '/reset-password', name: 'ResetPassword', component: () => import('@/views/auth/ResetPasswordView.vue'), meta: { guestOnly: true } },
  { path: '/sos', name: 'Sos', component: () => import('@/views/SosView.vue'), meta: { title: 'Emergency SOS' } },
  { path: '/verify-identity', name: 'VerifyIdentity', component: () => import('@/views/VerifyIdentityView.vue'), meta: { auth: true, title: 'Verify identity' } },
  { path: '/diagnostics', name: 'Diagnostics', component: () => import('@/views/ConnectionCheck.vue'), meta: { title: 'Server connection' } },
  {
    path: '/',
    component: AppShell,
    meta: { auth: true },
    children: [
      { path: 'home', name: 'Home', component: () => import('@/views/HomeView.vue'), meta: { tab: 'home', root: true } },
      { path: 'requests', name: 'Requests', component: () => import('@/views/RequestsView.vue'), meta: { tab: 'requests', title: 'My Requests', kyc: true } },
      { path: 'notifications', name: 'Notifications', component: () => import('@/views/NotificationsView.vue'), meta: { tab: 'notifications', title: 'Notifications' } },
      { path: 'profile', name: 'Profile', component: () => import('@/views/ProfileView.vue'), meta: { tab: 'profile', title: 'Profile' } },
    ],
  },
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
  if (to.matched.some((r) => r.meta.auth) && !session.isLoggedIn.value) return { name: 'Welcome' }
  if (to.meta.guestOnly && session.isLoggedIn.value) return { name: 'Home' }
  return true
})

export default router
