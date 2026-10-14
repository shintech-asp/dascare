/**
 * Leaflet for the pin-picker maps (SOS form, KYC, organization
 * application/profile, heatmap) — bundled with the site, not loaded from a
 * CDN (unpkg outages left every picker blank) — plus a free, keyless base
 * map: the OpenFreeMap "liberty" vector style, the same map as the live
 * MapLibre maps, drawn inside Leaflet by MapLibre's official Leaflet plugin.
 *
 * CARTO's basemaps started requiring an API key (tiles came back as
 * "API KEY REQUIRED" watermarks). If the vector map can't load, addBasemap
 * falls back to standard OpenStreetMap raster tiles so a picker is never
 * blank.
 */
import iconUrl from 'leaflet/dist/images/marker-icon.png'
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png'
import shadowUrl from 'leaflet/dist/images/marker-shadow.png'

const STYLE_URL = 'https://tiles.openfreemap.org/styles/liberty'
const ATTRIBUTION = '<a href="https://openfreemap.org" target="_blank" rel="noopener">OpenFreeMap</a> &copy; <a href="https://www.openmaptiles.org/" target="_blank" rel="noopener">OpenMapTiles</a> Data from <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'

let leafletReady = null
let pluginReady = null

/** Leaflet (lazy-loaded chunk). Also exposed as window.L for older code. */
export function loadLeaflet() {
  if (!leafletReady) {
    leafletReady = (async () => {
      const [{ default: L }] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')])
      // Bundlers break Leaflet's default marker image lookup; point it at the bundled files.
      delete L.Icon.Default.prototype._getIconUrl
      L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl })
      window.L = L
      return L
    })()
    leafletReady.catch(() => { leafletReady = null })
  }
  return leafletReady
}

function loadPlugin() {
  if (!pluginReady) {
    pluginReady = Promise.all([
      import('maplibre-gl/dist/maplibre-gl.css'),
      import('@maplibre/maplibre-gl-leaflet'), // adds L.maplibreGL
    ])
    pluginReady.catch(() => { pluginReady = null })
  }
  return pluginReady
}

/** Add the base map to a Leaflet map. Resolves with the layer. */
export async function addBasemap(L, map) {
  try {
    await loadPlugin()
    if (!L.maplibreGL) throw new Error('maplibre-gl-leaflet did not register')
    return L.maplibreGL({ style: STYLE_URL, attribution: ATTRIBUTION }).addTo(map)
  } catch (err) {
    console.warn('Vector base map unavailable, using OpenStreetMap tiles.', err)
    return L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
    }).addTo(map)
  }
}
