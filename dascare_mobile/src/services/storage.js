import { Preferences } from '@capacitor/preferences'

/**
 * Small JSON key/value store that survives app restarts (Capacitor
 * Preferences → Android SharedPreferences; localStorage in the browser
 * preview). Used for the login token, guest SOS keys, remembered email.
 */
export async function getItem(key, fallback = null) {
  try {
    const { value } = await Preferences.get({ key })
    return value == null ? fallback : JSON.parse(value)
  } catch {
    return fallback
  }
}

export async function setItem(key, value) {
  await Preferences.set({ key, value: JSON.stringify(value) })
}

export async function removeItem(key) {
  await Preferences.remove({ key })
}
