import { Capacitor } from '@capacitor/core'
import { PushNotifications } from '@capacitor/push-notifications'
import api from '@/services/api'
import { getItem, setItem } from '@/services/storage'

/**
 * Push notifications (Firebase Cloud Messaging) — Phase 7.
 *
 * Only active when the app was built with Firebase configured
 * (android/app/google-services.json present → __DASCARE_PUSH__ is true, see
 * vite.config.js). Without it every function here is a no-op, so the app
 * still works exactly as before (15 s polling while open).
 *
 * Flow: after login or after sending an SOS we ask for notification
 * permission (Android 13+), FCM gives us a token, and we send it to
 * mobile/push/register.php together with the login token / guest SOS keys,
 * so the server knows which requests this phone follows.
 */
// eslint-disable-next-line no-undef
export const PUSH_AVAILABLE = typeof __DASCARE_PUSH__ !== 'undefined' && __DASCARE_PUSH__ && Capacitor.isNativePlatform()

const TOKEN_KEY = 'dascare.pushToken'
const CHANNEL_ID = 'dascare_alerts' // same id the server sends (reusables/push.php)
let initialized = false
let fcmToken = null

async function sendToken() {
  if (!fcmToken) return
  try { await api.post('/mobile/push/register.php', { token: fcmToken }) } catch { /* retried next time */ }
}

/** Listeners + channel. Call once at startup (App.vue). */
export async function initPush({ onOpen, onForeground } = {}) {
  if (!PUSH_AVAILABLE || initialized) return
  initialized = true
  fcmToken = await getItem(TOKEN_KEY)

  await PushNotifications.createChannel({
    id: CHANNEL_ID,
    name: 'Emergency updates',
    description: 'Status of your emergency requests and account',
    importance: 5, // high: heads-up banner + sound
    visibility: 1,
    vibration: true,
  }).catch(() => {})

  await PushNotifications.addListener('registration', async ({ value }) => {
    fcmToken = value
    await setItem(TOKEN_KEY, value)
    await sendToken()
  })
  await PushNotifications.addListener('registrationError', (err) => console.warn('Push registration failed', err))
  // App open: no system banner — show it in-app and refresh the screen.
  await PushNotifications.addListener('pushNotificationReceived', (n) => onForeground?.(n))
  // Tapped in the notification tray.
  await PushNotifications.addListener('pushNotificationActionPerformed', ({ notification }) => onOpen?.(notification.data || {}))

  // Already allowed earlier → refresh the token silently.
  const perm = await PushNotifications.checkPermissions().catch(() => null)
  if (perm?.receive === 'granted') PushNotifications.register().catch(() => {})
}

/**
 * Ask (once) and register. Call at moments the user understands why:
 * right after logging in, or right after sending an SOS.
 */
export async function enablePush() {
  if (!PUSH_AVAILABLE) return
  try {
    let perm = await PushNotifications.checkPermissions()
    if (perm.receive === 'prompt' || perm.receive === 'prompt-with-rationale') perm = await PushNotifications.requestPermissions()
    if (perm.receive !== 'granted') return
    await PushNotifications.register() // → 'registration' → sendToken()
    await sendToken() // token already known: update account / guest links now
  } catch (err) {
    console.warn('Push not enabled', err)
  }
}

/** On logout: stop account notifications to this phone (guest SOS ones stay). */
export async function disablePushForAccount() {
  if (!PUSH_AVAILABLE || !fcmToken) return
  try { await api.post('/mobile/push/unregister.php', { token: fcmToken }) } catch { /* best effort */ }
}
