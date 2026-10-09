<template>
  <div class="relative h-full w-full overflow-hidden">
    <div ref="mapEl" class="h-full w-full"></div>

    <!-- ETA card -->
    <div
      v-if="route && ambulance"
      class="pointer-events-none absolute left-2.5 top-2.5 max-w-[75%] rounded-2xl border border-base-300 bg-base-100/95 px-3 py-2 shadow-md backdrop-blur dark:border-white/10 dark:bg-[#071829]/95"
    >
      <p class="flex items-baseline gap-1.5 leading-none">
        <span class="text-lg font-black text-slate-950 dark:text-white">{{ etaLabel }}</span>
        <span class="text-[0.68rem] font-bold text-slate-500 dark:text-white/45">· {{ distanceLabel }}</span>
      </p>
      <p class="mt-1 truncate text-[0.65rem] font-semibold text-slate-500 dark:text-white/45">
        to {{ route.destination?.kind === 'facility' ? route.destination.label : 'the incident' }}
      </p>
      <p class="mt-1 flex items-center gap-1 text-[0.6rem] font-bold" :class="route.traffic ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300'">
        <Icon :icon="route.traffic ? 'lucide:traffic-cone' : 'lucide:ruler'" width="11" />
        {{ route.traffic ? (delayLabel || 'Live traffic') + ' · road route' : 'Estimate · straight line' }}
      </p>
    </div>

    <!-- Back to following the ambulance after the user panned/zoomed -->
    <button
      v-if="!following && ready"
      type="button"
      class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-full border border-base-300 bg-base-100 px-3 py-2 text-xs font-bold text-slate-700 shadow-md dark:border-white/10 dark:bg-[#0d2943] dark:text-white/80"
      @click="recenter"
    >
      <Icon icon="lucide:locate-fixed" width="14" /> Recenter
    </button>

    <p v-if="loadError" class="absolute inset-0 grid place-items-center bg-base-200 p-4 text-center text-xs text-slate-500 dark:bg-white/5 dark:text-white/45">{{ loadError }}</p>
  </div>
</template>

<script setup>
/**
 * Live mission map (MapLibre GL + OpenFreeMap vector tiles) — the live-view
 * maps only; pin pickers stay on Leaflet. Shows the incident (red), a linked
 * report (orange), the ambulance (blue, gliding between GPS fixes), the
 * receiving facility (green) and the road route + ETA from
 * reusables/routing.php (dashed straight line when there is no road route).
 *
 * Follows the action (keeps everything in view) until the user pans or
 * zooms; then a Recenter button appears.
 *
 * Same file in dascare/src/components/maps/LiveMissionMap.vue (web) — keep in sync.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { useTheme } from '@/composables/useTheme'

const props = defineProps({
  incident: { type: Object, required: true }, // { latitude, longitude }
  linked: { type: Object, default: null }, // { latitude, longitude } — report this one was linked to
  ambulance: { type: Object, default: null }, // { latitude, longitude }
  route: { type: Object, default: null }, // from routing.php (polyline, destination, distance_m, duration_s, age_s, traffic)
})

const STYLES = { light: 'https://tiles.openfreemap.org/styles/liberty', dark: 'https://tiles.openfreemap.org/styles/dark' }
const ROUTE_COLOR = '#2563eb'

const { theme } = useTheme()
const mapEl = ref(null)
const ready = ref(false)
const following = ref(true)
const loadError = ref('')
let maplibregl = null
let map = null
let markers = {}
let animFrame = null
let shownAmbulance = null // [lng, lat] currently drawn

// ---------- ETA ----------
// ETA as last calculated from the ambulance's latest position. Deliberately
// not counted down on its own: if GPS pings stop, a countdown would reach
// "Arriving" while the unit hasn't moved. The server recalculates as it moves.
const secondsLeft = computed(() => (props.route ? Math.max(0, Math.round(props.route.duration_s)) : null))
const etaLabel = computed(() => {
  const s = secondsLeft.value
  if (s == null) return ''
  if (s < 45) return 'Arriving'
  const m = Math.round(s / 60)
  return m < 60 ? `${m} min` : `${Math.floor(m / 60)} h ${m % 60} min`
})
const distanceLabel = computed(() => {
  const d = props.route?.distance_m || 0
  return d >= 1000 ? `${(d / 1000).toFixed(1)} km` : `${Math.round(d)} m`
})
const delayLabel = computed(() => {
  const d = props.route?.traffic_delay_s || 0
  return d >= 60 ? `+${Math.round(d / 60)} min traffic` : ''
})

// ---------- helpers ----------
const lngLat = (p) => (p && p.latitude != null && p.longitude != null ? [Number(p.longitude), Number(p.latitude)] : null)

function decodePolyline(str) {
  const points = []
  let index = 0, lat = 0, lng = 0
  while (index < str.length) {
    for (const axis of [0, 1]) {
      let result = 0, shift = 0, b
      do { b = str.charCodeAt(index++) - 63; result |= (b & 0x1f) << shift; shift += 5 } while (b >= 0x20)
      const delta = result & 1 ? ~(result >> 1) : result >> 1
      if (axis === 0) lat += delta
      else lng += delta
    }
    points.push([lng / 1e5, lat / 1e5])
  }
  return points
}

function dotElement(color, { pulse = false, size = 16, icon = '' } = {}) {
  const el = document.createElement('div')
  el.style.cssText = `position:relative;width:${size}px;height:${size}px`
  el.innerHTML = `${pulse ? `<span style="position:absolute;inset:-6px;border-radius:9999px;background:${color};opacity:.25;animation:dascare-map-ping 2s cubic-bezier(0,0,.2,1) infinite"></span>` : ''}<span style="position:absolute;inset:0;display:grid;place-items:center;border-radius:9999px;background:${color};border:3px solid white;box-shadow:0 0 0 2px ${color}55,0 2px 6px rgba(0,0,0,.25);color:white;font-size:9px;font-weight:900">${icon}</span>`
  return el
}

function setMarker(key, position, factory) {
  if (!position) {
    markers[key]?.remove()
    delete markers[key]
    return
  }
  if (markers[key]) markers[key].setLngLat(position)
  else markers[key] = new maplibregl.Marker({ element: factory() }).setLngLat(position).addTo(map)
}

/** Where the ambulance is going (facility while transporting, else the incident / linked report). */
function targetPoint() {
  return lngLat(props.route?.destination) || lngLat(props.linked) || lngLat(props.incident)
}

function lineGeometry() {
  const amb = shownAmbulance
  if (!amb) return null
  if (props.route?.polyline) {
    const coords = decodePolyline(props.route.polyline)
    // Start the road line where the pin is drawn so it never looks detached.
    return { type: 'LineString', coordinates: [amb, ...coords] }
  }
  const target = targetPoint()
  return target ? { type: 'LineString', coordinates: [amb, target] } : null
}

function drawLine() {
  if (!map?.getSource('mission-route')) return
  const geometry = lineGeometry()
  map.getSource('mission-route').setData(geometry ? { type: 'Feature', geometry, properties: {} } : { type: 'FeatureCollection', features: [] })
  const dashed = !props.route?.polyline
  map.setPaintProperty('mission-route-line', 'line-dasharray', dashed ? [1.5, 1.5] : [1, 0])
  map.setPaintProperty('mission-route-line', 'line-width', dashed ? 3 : 5)
  map.setLayoutProperty('mission-route-casing', 'visibility', dashed ? 'none' : 'visible')
}

function addRouteLayers() {
  if (map.getSource('mission-route')) return
  map.addSource('mission-route', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } })
  map.addLayer({ id: 'mission-route-casing', type: 'line', source: 'mission-route', layout: { 'line-join': 'round', 'line-cap': 'round' }, paint: { 'line-color': '#ffffff', 'line-width': 8, 'line-opacity': 0.9 } })
  map.addLayer({ id: 'mission-route-line', type: 'line', source: 'mission-route', layout: { 'line-join': 'round', 'line-cap': 'round' }, paint: { 'line-color': ROUTE_COLOR, 'line-width': 5, 'line-opacity': 0.9 } })
  drawLine()
}

function fitAll(animate = true) {
  if (!map) return
  const points = [lngLat(props.incident), lngLat(props.linked), shownAmbulance, lngLat(props.route?.destination)].filter(Boolean)
  if (props.route?.polyline) decodePolyline(props.route.polyline).forEach((p, i, all) => { if (i % 10 === 0 || i === all.length - 1) points.push(p) })
  if (points.length === 1) {
    map.easeTo({ center: points[0], zoom: 15, duration: animate ? 600 : 0 })
    return
  }
  const bounds = points.reduce((b, p) => b.extend(p), new maplibregl.LngLatBounds(points[0], points[0]))
  map.fitBounds(bounds, { padding: { top: 90, bottom: 50, left: 40, right: 40 }, maxZoom: 16, duration: animate ? 800 : 0 })
}

function recenter() {
  following.value = true
  fitAll(true)
}

// ---------- ambulance gliding between fixes ----------
function moveAmbulance(target) {
  cancelAnimationFrame(animFrame)
  if (!target) {
    shownAmbulance = null
    setMarker('ambulance', null)
    drawLine()
    return
  }
  const from = shownAmbulance
  if (!from) {
    shownAmbulance = target
    setMarker('ambulance', target, () => dotElement('#2563eb', { pulse: true, size: 18 }))
    drawLine()
    return
  }
  const start = performance.now()
  const duration = 1200
  const step = (t) => {
    const k = Math.min(1, (t - start) / duration)
    const e = k < 0.5 ? 2 * k * k : 1 - (-2 * k + 2) ** 2 / 2 // ease in-out
    shownAmbulance = [from[0] + (target[0] - from[0]) * e, from[1] + (target[1] - from[1]) * e]
    setMarker('ambulance', shownAmbulance, () => dotElement('#2563eb', { pulse: true, size: 18 }))
    drawLine()
    if (k < 1) animFrame = requestAnimationFrame(step)
  }
  animFrame = requestAnimationFrame(step)
}

function syncMarkers() {
  setMarker('incident', lngLat(props.incident), () => dotElement('#dc2626', { pulse: true }))
  setMarker('linked', lngLat(props.linked), () => dotElement('#f97316'))
  const facility = props.route?.destination?.kind === 'facility' ? lngLat(props.route.destination) : null
  setMarker('facility', facility, () => dotElement('#059669', { size: 20, icon: 'H' }))
}

// ---------- lifecycle ----------
onMounted(async () => {
  try {
    const mod = await import('maplibre-gl')
    await import('maplibre-gl/dist/maplibre-gl.css')
    maplibregl = mod.default || mod
    map = new maplibregl.Map({
      container: mapEl.value,
      style: STYLES[theme.value === 'dark' ? 'dark' : 'light'],
      center: lngLat(props.incident) || [120.9367, 14.3294],
      zoom: 14,
      attributionControl: { compact: true },
    })
    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right')
    if (import.meta.env.DEV) window.__dascareLiveMap = map // dev-only: inspect from DevTools
    map.on('style.load', addRouteLayers)
    // A real user gesture stops auto-follow — not our own camera moves (no
    // originalEvent) and not container resizes (originalEvent is a 'resize').
    map.on('movestart', (e) => {
      const type = e.originalEvent?.type
      if (type && type !== 'resize') following.value = false
    })
    map.on('load', () => {
      ready.value = true
      syncMarkers()
      moveAmbulance(lngLat(props.ambulance))
      fitAll(false)
    })
  } catch (err) {
    loadError.value = 'The map could not load. Check the internet connection.'
    console.error('Live map failed to load', err)
  }
})

onBeforeUnmount(() => {
  cancelAnimationFrame(animFrame)
  map?.remove()
  map = null
  markers = {}
})

watch(() => [props.ambulance?.latitude, props.ambulance?.longitude], () => {
  if (!ready.value) return
  const hadAmbulance = !!shownAmbulance
  moveAmbulance(lngLat(props.ambulance))
  if (following.value || !hadAmbulance) setTimeout(() => fitAll(true), 1250)
})

watch(() => [props.route?.polyline, props.route?.duration_s, props.route?.destination?.latitude], () => {
  if (!ready.value) return
  syncMarkers()
  drawLine()
  if (following.value) fitAll(true)
})

watch(() => [props.incident?.latitude, props.linked?.latitude], () => {
  if (!ready.value) return
  syncMarkers()
  drawLine()
})

watch(theme, (t) => {
  if (!map) return
  map.setStyle(STYLES[t === 'dark' ? 'dark' : 'light']) // route layers are re-added on style.load
})
</script>

<style>
@keyframes dascare-map-ping {
  0% { transform: scale(0.8); opacity: 0.45; }
  100% { transform: scale(2); opacity: 0; }
}
</style>
