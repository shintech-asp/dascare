import { ref } from 'vue'
import { Capacitor, registerPlugin } from '@capacitor/core'
import { pushAmbulanceLocation } from '@/services/org/rescueOperations'
import { getItem, removeItem, setItem } from '@/services/storage'

/**
 * Ambulance GPS sharing for crew (Phase 4) — organizations/tracking/update.php.
 *
 * On the phone it uses @capacitor-community/background-geolocation, which
 * runs as an Android foreground service: positions keep flowing with the
 * screen off or the app in the background, and Android shows a permanent
 * "Sharing ambulance location" notification while it runs. In the browser
 * preview it falls back to navigator.geolocation (foreground only).
 *
 * Sends at most one fix every SEND_EVERY_MS, and re-sends the last fix every
 * HEARTBEAT_MS while stationary (GPS only reports movement — a parked unit
 * would otherwise look stale). The active assignment is remembered so a
 * restarted app resumes sharing while that mission is still active.
 */
const BackgroundGeolocation = registerPlugin('BackgroundGeolocation')
const STORE_KEY = 'dascare.gpsAssignment'
const SEND_EVERY_MS = 5000
const HEARTBEAT_MS = 30000

export const gpsState = ref({
  assignmentId: null, // sharing for this mission (null = off)
  lastSentAt: null,   // Date.now() of the last accepted ping
  lastError: '',
  permissionDenied: false,
})

let watcherId = null // native watcher id, or a navigator.geolocation watch id
let heartbeat = null
let lastFix = null
let lastSentFixAt = 0
let sending = false

async function send(fix, force = false) {
  const id = gpsState.value.assignmentId
  if (!id || !fix || sending) return
  if (!force && Date.now() - lastSentFixAt < SEND_EVERY_MS) return
  sending = true
  try {
    await pushAmbulanceLocation({
      assignment_id: id,
      latitude: fix.latitude,
      longitude: fix.longitude,
      accuracy_m: fix.accuracy ?? null,
      speed_kph: fix.speed != null && fix.speed >= 0 ? fix.speed * 3.6 : null,
      heading_degrees: fix.bearing ?? fix.heading ?? null,
    })
    lastSentFixAt = Date.now()
    gpsState.value = { ...gpsState.value, lastSentAt: lastSentFixAt, lastError: '' }
  } catch (err) {
    const message = err?.response?.data?.message || err?.message || 'Could not send your location.'
    gpsState.value = { ...gpsState.value, lastError: message }
    // Mission over / not on this crew any more: stop quietly.
    if (/only available during an active mission|Only assigned field responders|Mission not found/i.test(message)) await stopSharing()
  } finally {
    sending = false
  }
}

function onFix(fix) {
  lastFix = fix
  send(fix)
}

export async function startSharing(assignmentId) {
  if (gpsState.value.assignmentId === assignmentId && watcherId !== null) return true
  await stopSharing()
  gpsState.value = { assignmentId, lastSentAt: null, lastError: '', permissionDenied: false }
  await setItem(STORE_KEY, String(assignmentId))
  try {
    if (Capacitor.isNativePlatform()) {
      watcherId = await BackgroundGeolocation.addWatcher({
        backgroundTitle: 'Sharing ambulance location',
        backgroundMessage: 'DASCARE is sending your position to dispatch and the requester during this mission.',
        requestPermissions: true,
        stale: false,
        distanceFilter: 10,
      }, (location, error) => {
        if (error) {
          if (error.code === 'NOT_AUTHORIZED') {
            gpsState.value = { ...gpsState.value, permissionDenied: true, lastError: 'Location permission is off for DASCARE.' }
          }
          return
        }
        onFix(location)
      })
    } else if (navigator.geolocation) {
      watcherId = navigator.geolocation.watchPosition(
        (p) => onFix({ latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: p.coords.accuracy, speed: p.coords.speed, heading: p.coords.heading }),
        (e) => { gpsState.value = { ...gpsState.value, permissionDenied: e.code === 1, lastError: e.code === 1 ? 'Location permission is blocked.' : 'Waiting for GPS…' } },
        { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 },
      )
    } else {
      throw new Error('GPS is not available on this device.')
    }
  } catch (err) {
    gpsState.value = { ...gpsState.value, lastError: err?.message || 'Could not start GPS.' }
    await stopSharing()
    return false
  }
  heartbeat = setInterval(() => { if (lastFix) send(lastFix, true) }, HEARTBEAT_MS)
  return true
}

export async function stopSharing() {
  clearInterval(heartbeat)
  heartbeat = null
  if (watcherId !== null) {
    try {
      if (Capacitor.isNativePlatform()) await BackgroundGeolocation.removeWatcher({ id: watcherId })
      else navigator.geolocation.clearWatch(watcherId)
    } catch { /* already stopped */ }
  }
  watcherId = null
  lastFix = null
  gpsState.value = { ...gpsState.value, assignmentId: null }
  await removeItem(STORE_KEY)
}

/** On app start: resume sharing for the remembered mission if it's still active for this crew member. */
export async function resumeSharingIfActive(activeAssignmentIds) {
  const stored = Number(await getItem(STORE_KEY)) || null
  if (!stored) return
  if (activeAssignmentIds.includes(stored)) await startSharing(stored)
  else await removeItem(STORE_KEY)
}

/** Opens the app's settings page (to re-allow location after "Don't allow"). */
export async function openLocationSettings() {
  if (Capacitor.isNativePlatform()) {
    try { await BackgroundGeolocation.openSettings() } catch { /* ignore */ }
  }
}
