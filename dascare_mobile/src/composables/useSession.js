import { computed, ref } from 'vue'
import api, { onTokenInvalid, setAuthToken } from '@/services/api'
import { getItem, removeItem, setItem } from '@/services/storage'
import { disablePushForAccount, enablePush } from '@/services/push'

/**
 * Signed-in citizen for the app (singleton state, like the web's
 * useSession). The token is kept on the phone; the user profile, including
 * KYC status, comes from the same session.php the web uses.
 */
const TOKEN_KEY = 'dascare.token'

const token = ref(null)
const user = ref(null)
const ready = ref(false)
let initPromise = null

// KYC: 0=unverified, 1=pending, 2=approved, 3=rejected, 4=resubmission requested
const kycStatus = computed(() => Number(user.value?.kyc_status ?? 0))
const isVerified = computed(() => kycStatus.value === 2)
const isLoggedIn = computed(() => !!token.value && !!user.value)

async function clearLocal() {
  token.value = null
  user.value = null
  setAuthToken(null)
  await removeItem(TOKEN_KEY)
}

// A revoked/expired token (or an account suspended on the web) signs the
// app out on the next request.
onTokenInvalid(() => { if (token.value) clearLocal() })

async function fetchSession() {
  if (!token.value) { user.value = null; return null }
  try {
    const { data } = await api.get('/session.php')
    if (data?.loggedIn && data.user?.level === 'citizen') {
      user.value = data.user
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
  return { token, user, ready, isLoggedIn, kycStatus, isVerified, init, fetchSession, login, verify2fa, logout }
}
