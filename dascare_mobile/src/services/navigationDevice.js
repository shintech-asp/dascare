/**
 * Device features for driving navigation (components/maps/NavigationMode.vue),
 * Android app version: Capacitor GPS, native text-to-speech (Android's WebView
 * has no speechSynthesis) and keep-awake. Same exports as the website's
 * dascare/src/services/navigationDevice.js. Falls back to browser APIs in
 * the app's browser preview.
 */
import { Capacitor } from '@capacitor/core'
import { Geolocation } from '@capacitor/geolocation'
import { TextToSpeech } from '@capacitor-community/text-to-speech'
import { KeepAwake } from '@capacitor-community/keep-awake'

const native = Capacitor.isNativePlatform()

/** Watch the device position; calls onFix({ lng, lat, accuracy, heading, speed }). Returns a stop function. */
export function watchPosition(onFix, onError) {
  if (!native) {
    if (!navigator.geolocation) { onError?.(new Error('GPS is not available.')); return () => {} }
    const id = navigator.geolocation.watchPosition(
      (p) => onFix({ lng: p.coords.longitude, lat: p.coords.latitude, accuracy: p.coords.accuracy, heading: p.coords.heading, speed: p.coords.speed }),
      (e) => onError?.(e),
      { enableHighAccuracy: true, maximumAge: 2000, timeout: 20000 },
    )
    return () => navigator.geolocation.clearWatch(id)
  }
  let watchId = null
  let stopped = false
  Geolocation.watchPosition({ enableHighAccuracy: true, timeout: 20000, maximumAge: 2000 }, (p, err) => {
    if (err) { onError?.(err); return }
    if (p) onFix({ lng: p.coords.longitude, lat: p.coords.latitude, accuracy: p.coords.accuracy, heading: p.coords.heading, speed: p.coords.speed })
  }).then((id) => { watchId = id; if (stopped) Geolocation.clearWatch({ id }) }).catch((e) => onError?.(e))
  return () => { stopped = true; if (watchId) Geolocation.clearWatch({ id: watchId }) }
}

export async function speak(text) {
  try {
    if (native) {
      await TextToSpeech.stop()
      await TextToSpeech.speak({ text, lang: 'en-US', rate: 1.0, category: 'playback' })
    } else if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel()
      window.speechSynthesis.speak(Object.assign(new SpeechSynthesisUtterance(text), { lang: 'en-US' }))
    }
  } catch { /* no voice */ }
}

export function stopSpeaking() {
  try { if (native) TextToSpeech.stop(); else window.speechSynthesis?.cancel() } catch { /* ignore */ }
}

export async function keepScreenOn(on) {
  try {
    if (native) await (on ? KeepAwake.keepAwake() : KeepAwake.allowSleep())
    else if ('wakeLock' in navigator && on) await navigator.wakeLock.request('screen')
  } catch { /* ignore */ }
}
