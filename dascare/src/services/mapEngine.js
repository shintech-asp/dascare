/**
 * Map engine for full-page live maps (the platform's live incident monitor):
 * Mapbox navigation styles (live traffic) when VITE_MAPBOX_TOKEN is set,
 * otherwise — or once Mapbox has refused the token / hit its free limit /
 * failed to load this session — the free OpenFreeMap styles via MapLibre.
 * Same rules as components/maps/LiveMissionMap.vue (which keeps its own copy
 * because it is shared verbatim with the Android app).
 */
export const MAPBOX_TOKEN = import.meta.env.VITE_MAPBOX_TOKEN || ''
const FREE_STYLES = { light: 'https://tiles.openfreemap.org/styles/liberty', dark: 'https://tiles.openfreemap.org/styles/dark' }
const MAPBOX_STYLES = { light: 'mapbox://styles/mapbox/navigation-day-v1', dark: 'mapbox://styles/mapbox/navigation-night-v1' }
export const MAPBOX_LOAD_TIMEOUT_MS = 12000
const UNAVAILABLE_KEY = 'dascare.mapboxUnavailableUntil'
const UNAVAILABLE_FOR_MS = 10 * 60 * 1000

export function mapboxUsable() {
  if (!MAPBOX_TOKEN) return false
  try { return Number(sessionStorage.getItem(UNAVAILABLE_KEY) || 0) < Date.now() } catch { return true }
}

/**
 * Mapbox refused the token / hit its free limit (401/403/429): skip it for
 * 10 minutes in this tab. A merely slow load is NOT remembered — the next
 * map tries Mapbox again (it used to stick for the whole tab session).
 */
export function markMapboxUnavailable() {
  try { sessionStorage.setItem(UNAVAILABLE_KEY, String(Date.now() + UNAVAILABLE_FOR_MS)) } catch { /* private mode */ }
}

/** mapbox-gl or maplibre-gl (same API), with its CSS. */
export async function loadEngine(useMapbox) {
  if (useMapbox) {
    const [mod] = await Promise.all([import('mapbox-gl'), import('mapbox-gl/dist/mapbox-gl.css')])
    const lib = mod.default || mod
    lib.accessToken = MAPBOX_TOKEN
    return lib
  }
  const [mod] = await Promise.all([import('maplibre-gl'), import('maplibre-gl/dist/maplibre-gl.css')])
  return mod.default || mod
}

export function styleFor(engine, theme) {
  return (engine === 'mapbox' ? MAPBOX_STYLES : FREE_STYLES)[theme === 'dark' ? 'dark' : 'light']
}
