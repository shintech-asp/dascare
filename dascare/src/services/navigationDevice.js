/**
 * Device features for driving navigation (components/maps/NavigationMode.vue),
 * website version: browser GPS, the browser's speech voice, and a screen
 * wake lock. The Android app has its own copy of this file using Capacitor
 * plugins (dascare_mobile/src/services/navigationDevice.js) — same exports.
 */

/** Watch the device position; calls onFix({ lng, lat, accuracy, heading, speed }). Returns a stop function. */
export function watchPosition(onFix, onError) {
  if (!navigator.geolocation) { onError?.(new Error('GPS is not available in this browser.')); return () => {} }
  const id = navigator.geolocation.watchPosition(
    (p) => onFix({ lng: p.coords.longitude, lat: p.coords.latitude, accuracy: p.coords.accuracy, heading: p.coords.heading, speed: p.coords.speed }),
    (e) => onError?.(e),
    { enableHighAccuracy: true, maximumAge: 2000, timeout: 20000 },
  )
  return () => navigator.geolocation.clearWatch(id)
}

export async function speak(text) {
  try {
    if (!('speechSynthesis' in window)) return
    window.speechSynthesis.cancel()
    const u = new SpeechSynthesisUtterance(text)
    u.lang = 'en-US'
    u.rate = 1.02
    window.speechSynthesis.speak(u)
  } catch { /* no voice */ }
}

export function stopSpeaking() {
  try { window.speechSynthesis?.cancel() } catch { /* ignore */ }
}

let wakeLock = null
export async function keepScreenOn(on) {
  try {
    if (on && 'wakeLock' in navigator) wakeLock = await navigator.wakeLock.request('screen')
    else if (!on && wakeLock) { await wakeLock.release(); wakeLock = null }
  } catch { /* not allowed (e.g. tab hidden) */ }
}
