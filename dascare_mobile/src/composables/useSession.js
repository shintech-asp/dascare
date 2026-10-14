import { computed, ref } from 'vue'
import api, { onTokenInvalid, setAuthToken } from '@/services/api'
import { getItem, removeItem, setItem } from '@/services/storage'
import { disablePushForAccount, enablePush } from '@/services/push'
import { resetRealtime } from '@/services/realtime'

/**
 * Signed-in user for the app (singleton state, like the web's useSession):
 * a citizen, or organization/barangay staff (Phase 4). The token is kept on
 * the phone; the profile (KYC status for citizens; organization + its
 * permissions for staff) comes from the same session.php the web uses.
 */
const TOKEN_KEY = 'dascare.token'

const STAFF_ROLES = ['organization_admin', 'organization_operational_user']
const APP_ROLES = ['citizen', ...STAFF_ROLES]

const token = ref(null)
const user = ref(null)
const organization = ref(null) // staff: { id, name, status, permissions[], roleNames[] … } from session.php
const ready = ref(false)
let initPromise = null

// KYC: 0=unverified, 1=pending, 2=approved, 3=rejected, 4=resubmission requested
const kycStatus = computed(() => Number(user.value?.kyc_status ?? 0))
const isVerified = computed(() => kycStatus.value === 2)
const isLoggedIn = computed(() => !!token.value && !!user.value)
const isStaff = computed(() => STAFF_ROLES.includes(user.value?.level))
const isOrgAdmin = computed(() => user.value?.level === 'organization_admin')

// Same rule as the web: organization admins can do everything in their
// organization; other staff need the permission in one of their roles.
// (Cosmetic only — every endpoint re-checks with requireOrganizationAccess.)
function hasPermission(...keys) {
  if (!isStaff.value) return false
  if (isOrgAdmin.value || !keys.length) return true
  const perms = organization.value?.permissions || []
  return perms.includes('*') || keys.some((k) => perms.includes(k))
}

async function clearLocal() {
  token.value = null
  user.value = null
  organization.value = null
  setAuthToken(null)
  resetRealtime() // live-update channels belong to the account
  await removeItem(TOKEN_KEY)
}

// A revoked/expired token (or an account suspended on the web) signs the
// app out on the next request.
onTokenInvalid(() => { if (token.value) clearLocal() })

async function fetchSession() {
  if (!token.value) { user.value = null; return null }
  try {
    const { data } = await api.get('/session.php')
    if (data?.loggedIn && APP_ROLES.includes(data.user?.level)) {
      user.value = data.user
      organization.value = STAFF_ROLES.includes(data.user.level) ? (data.organization ?? null) : null
    } else {
      await clearLocal()
    }
  } catch (err) {
    // Offline: keep the stored token so the app still opens signed in.
    if (err.response) await clearLocal()
  }
  return user.value
}

async function adoptToken(newToken, newUser) {
  token.value = newToken
  setAuthToken(newToken)
  resetRealtime()
  await setItem(TOKEN_KEY, newToken)
  user.value = newUser
  await fetchSession()
  enablePush() // ask for notification permission + link this phone to the account
}

async function login(email, password) {
  const { data } = await api.post('/mobile/auth/login.php', { email, password, device_name: deviceName() })
  if (data.success && data.token) await adoptToken(data.token, data.user)
  return data
}

async function verify2fa(challenge, otp) {
  const { data } = await api.post('/mobile/auth/verify_2fa.php', { challenge, otp })
  if (data.success && data.token) await adoptToken(data.token, data.user)
  return data
}

async function logout() {
  await disablePushForAccount()
  try { await api.post('/mobile/auth/logout.php') } catch { /* sign out locally anyway */ }
  await clearLocal()
}

function deviceName() {
  const ua = navigator.userAgent || ''
  const model = ua.match(/Android [\d.]+; ([^;)]+)/)?.[1]
  return model ? `Android · ${model}` : 'DASCARE app'
}

function init() {
  if (!initPromise) {
    initPromise = (async () => {
      token.value = await getItem(TOKEN_KEY)
      setAuthToken(token.value)
      if (token.value) await fetchSession()
      ready.value = true
    })()
  }
  return initPromise
}

export function useSession() {
  return { token, user, organization, ready, isLoggedIn, isStaff, isOrgAdmin, hasPermission, kycStatus, isVerified, init, fetchSession, login, verify2fa, logout }
}
