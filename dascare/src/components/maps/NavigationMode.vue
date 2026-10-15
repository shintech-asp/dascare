<template>
  <!-- In-app driving navigation (like a maps app) for the ambulance crew:
       a tilted map that follows the vehicle, turn-by-turn banner + voice,
       instant re-routing when off the route, live time/distance left and an
       arrival prompt. Route + instructions from Mapbox Directions
       (driving-traffic, steps, banner/voice instructions), fetched from the
       device. Same file in dascare_mobile/src/components/maps/NavigationMode.vue
       — keep in sync (device features differ via services/navigationDevice.js). -->
  <Teleport to="body">
    <div class="fixed inset-0 z-[10060] bg-slate-900">
      <!-- Box ends above the bottom bar; the map fills it (h-full: the map CSS
           sets position:relative on the map element, which cancels inset). -->
      <div class="absolute inset-x-0 top-0" :style="{ bottom: 'calc(var(--safe-bottom, 0px) + 5.5rem)' }">
        <div ref="mapEl" class="h-full w-full"></div>
      </div>

      <!-- Turn banner -->
      <div class="absolute inset-x-0 top-0 p-3 pt-[calc(var(--safe-top,0px)+0.75rem)]">
        <div v-if="banner" class="rounded-3xl bg-emerald-700 p-4 text-white shadow-2xl">
          <div class="flex items-center gap-4">
            <Icon :icon="banner.icon" width="44" class="flex-shrink-0" />
            <div class="min-w-0">
              <p class="text-3xl font-black leading-none tabular-nums">{{ banner.distance }}</p>
              <p class="mt-1 truncate text-lg font-bold leading-tight">{{ banner.text }}</p>
            </div>
          </div>
          <p v-if="banner.then" class="mt-3 flex items-center gap-2 border-t border-white/20 pt-2 text-sm font-semibold text-white/85">
            Then <Icon :icon="banner.thenIcon" width="18" /> <span class="truncate">{{ banner.then }}</span>
          </p>
        </div>
        <div v-else class="rounded-3xl bg-slate-800/95 p-4 text-white shadow-2xl">
          <p class="flex items-center gap-2 text-base font-bold"><Icon icon="lucide:loader-circle" width="20" class="animate-spin" /> {{ statusText }}</p>
        </div>
        <p v-if="hasTraffic" class="mx-auto mt-2 flex w-fit items-center gap-2 rounded-full bg-white/95 px-3 py-1 text-[0.68rem] font-bold text-slate-600 shadow">
          <span class="h-1.5 w-4 rounded-full bg-blue-500"></span>Clear <span class="h-1.5 w-4 rounded-full bg-amber-500"></span>Slow <span class="h-1.5 w-4 rounded-full bg-red-500"></span>Heavy
        </p>
        <p v-if="rerouting" class="mx-auto mt-2 w-fit rounded-full bg-amber-500 px-4 py-1.5 text-sm font-black text-white shadow-lg">Rerouting…</p>
        <p v-if="gpsWeak" class="mx-auto mt-2 w-fit rounded-full bg-slate-800/90 px-4 py-1.5 text-xs font-bold text-white shadow-lg">Weak GPS signal</p>
      </div>

      <!-- Map buttons -->
      <div class="absolute right-3 top-[calc(var(--safe-top,0px)+11rem)] flex flex-col gap-2">
        <button type="button" class="grid h-12 w-12 place-items-center rounded-full bg-white text-slate-700 shadow-lg" :aria-label="muted ? 'Unmute voice' : 'Mute voice'" @click="toggleMute">
          <Icon :icon="muted ? 'lucide:volume-x' : 'lucide:volume-2'" width="22" />
        </button>
        <button type="button" class="grid h-12 w-12 place-items-center rounded-full shadow-lg" :class="showRoadTraffic ? 'bg-emerald-600 text-white' : 'bg-white text-slate-700'" :aria-label="showRoadTraffic ? 'Hide traffic on all roads' : 'Show traffic on all roads'" @click="toggleRoadTraffic">
          <Icon icon="lucide:traffic-cone" width="22" />
        </button>
        <button type="button" class="grid h-12 w-12 place-items-center rounded-full bg-white text-slate-700 shadow-lg" aria-label="Route overview" @click="overview">
          <Icon icon="lucide:route" width="22" />
        </button>
      </div>
      <button v-if="!following && fix" type="button" class="absolute bottom-[calc(var(--safe-bottom,0px)+8.5rem)] left-1/2 -translate-x-1/2 rounded-full bg-white px-5 py-2.5 text-sm font-black text-slate-800 shadow-lg" @click="recenter">
        <Icon icon="lucide:navigation" width="16" class="mr-1 inline" />Re-center
      </button>

      <!-- Bottom sheet -->
      <div class="absolute inset-x-0 bottom-0 rounded-t-3xl bg-white p-4 pb-[calc(var(--safe-bottom,0px)+1rem)] shadow-2xl dark:bg-[#0c1a2c]">
        <div v-if="arrived" class="space-y-3 text-center">
          <p class="flex items-center justify-center gap-2 text-xl font-black text-emerald-600"><Icon icon="lucide:flag" width="22" /> You've arrived</p>
          <p class="text-sm text-slate-500">{{ destination.label || 'Destination' }}</p>
          <div class="grid grid-cols-2 gap-2">
            <button type="button" class="rounded-2xl border-2 border-slate-200 py-3.5 text-sm font-bold text-slate-600 dark:border-white/10 dark:text-white/70" @click="close">Close</button>
            <button v-if="arriveLabel" type="button" class="rounded-2xl bg-red-600 py-3.5 text-sm font-black text-white" @click="emit('arrived'); close()">{{ arriveLabel }}</button>
          </div>
        </div>
        <div v-else class="flex items-center gap-4">
          <div class="min-w-0 flex-1">
            <p class="text-2xl font-black tabular-nums text-emerald-600">{{ remainingMinutes }} <span class="text-base">min</span></p>
            <p class="truncate text-sm font-semibold text-slate-500 dark:text-white/50">{{ remainingKm }} · arrive {{ arrivalClock }}<template v-if="destination.label"> · {{ destination.label }}</template></p>
          </div>
          <button type="button" class="grid h-14 w-14 flex-shrink-0 place-items-center rounded-full bg-red-600 text-white shadow-lg" aria-label="End navigation" @click="close"><Icon icon="lucide:x" width="26" /></button>
        </div>
        <p v-if="errorText" class="mt-2 text-center text-xs font-semibold text-red-600">{{ errorText }}</p>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import { useTheme } from '@/composables/useTheme'
import { MAPBOX_TOKEN, loadEngine, mapboxUsable, styleFor } from '@/services/mapEngine'
import { keepScreenOn, speak, stopSpeaking, watchPosition } from '@/services/navigationDevice'
import { formatDistance, haversine, maneuverIcon, prepareRoute, routeBearingAt, snapToRoute, stepIndexAt } from '@/utils/navMath'

const props = defineProps({
  destination: { type: Object, required: true }, // { latitude, longitude, label }
  arriveLabel: { type: String, default: '' }, // e.g. "Arrived on scene" — emits 'arrived'
  // Last known vehicle position { latitude, longitude } — used until live GPS arrives.
  start: { type: Object, default: null },
})
const emit = defineEmits(['close', 'arrived'])
const { theme } = useTheme()

const OFF_ROUTE_M = 35
const ARRIVE_M = 40
const REROUTE_MIN_MS = 8000
const TRAFFIC_REFRESH_MS = 2 * 60 * 1000

const mapEl = ref(null)
const fix = ref(null) // { lng, lat, accuracy, heading, speed }
const route = ref(null) // prepareRoute() output
const along = ref(0)
const following = ref(true)
const rerouting = ref(false)
const arrived = ref(false)
const muted = ref(readMuted())
const errorText = ref('')
const now = ref(Date.now())
let gl = null, map = null, puck = null, stopWatch = null, lastRouteAt = 0, offCount = 0, snapHint = 0
let spoken = new Set()
let clock = null

const dest = computed(() => [Number(props.destination.longitude), Number(props.destination.latitude)])
const hasTraffic = computed(() => (route.value?.congestion || []).some((c) => c === 'moderate' || c === 'heavy' || c === 'severe'))
const gpsWeak = computed(() => fix.value && fix.value.accuracy > 60)
const statusText = computed(() => (errorText.value ? 'Navigation unavailable' : fix.value ? 'Finding the fastest route…' : 'Waiting for GPS…'))
const remaining = computed(() => (route.value ? Math.max(0, route.value.length - along.value) : null))
const remainingSeconds = computed(() => (route.value ? route.value.duration * (remaining.value / Math.max(1, route.value.length)) : null))
const remainingMinutes = computed(() => (remainingSeconds.value == null ? '—' : Math.max(1, Math.round(remainingSeconds.value / 60))))
const remainingKm = computed(() => (remaining.value == null ? '' : formatDistance(remaining.value)))
const arrivalClock = computed(() => (remainingSeconds.value == null ? '—' : new Date(now.value + remainingSeconds.value * 1000).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })))

// Upcoming maneuver = the one at the end of the current step.
const banner = computed(() => {
  const r = route.value
  if (!r) return null
  const i = stepIndexAt(r, along.value)
  const step = r.steps[i]
  const next = r.steps[i + 1]
  const b = (step.bannerInstructions || [])[0]
  const primary = b?.primary
  const type = primary?.type || next?.maneuver?.type || 'arrive'
  const modifier = primary?.modifier || next?.maneuver?.modifier
  const text = type === 'arrive' ? (props.destination.label || 'Destination') : (primary?.text || next?.name || next?.maneuver?.instruction || 'Continue')
  const after = r.steps[i + 2]
  return {
    distance: formatDistance(step.endAlong - along.value),
    text: type === 'arrive' ? `Arrive at ${text}` : verb(type, modifier) + (text ? ` onto ${text}` : ''),
    icon: maneuverIcon(type, modifier),
    then: after && (step.endAlong - along.value) < 400 ? (after.maneuver.instruction || '') : '',
    thenIcon: after ? maneuverIcon(after.maneuver.type, after.maneuver.modifier) : '',
  }
})
function verb(type, modifier) {
  if (type === 'roundabout' || type === 'rotary') return 'Enter the roundabout'
  if (modifier === 'uturn') return 'Make a U-turn'
  if (type === 'merge') return 'Merge'
  if (type === 'fork') return `Keep ${modifier?.includes('left') ? 'left' : 'right'}`
  if (modifier === 'straight' || !modifier) return 'Continue'
  return `Turn ${modifier}`
}

function readMuted() { try { return localStorage.getItem('dascare.navMuted') === '1' } catch { return false } }
function toggleMute() { muted.value = !muted.value; try { localStorage.setItem('dascare.navMuted', muted.value ? '1' : '0') } catch { /* ignore */ } if (muted.value) stopSpeaking() }
function say(text) { if (!muted.value && text) speak(text) }

// ---------- route ----------
async function fetchRoute(origin, reason = 'start') {
  if (!MAPBOX_TOKEN) { errorText.value = 'Driving navigation needs Mapbox. Use Google Maps or Waze instead.'; return }
  lastRouteAt = Date.now()
  if (reason === 'reroute') { rerouting.value = true; say('Rerouting.') }
  try {
    const url = `https://api.mapbox.com/directions/v5/mapbox/driving-traffic/${origin[0]},${origin[1]};${dest.value[0]},${dest.value[1]}`
      + `?steps=true&banner_instructions=true&voice_instructions=true&voice_units=metric&geometries=geojson&overview=full&annotations=congestion&language=en&access_token=${MAPBOX_TOKEN}`
    const res = await fetch(url)
    const data = await res.json()
    if (data.code !== 'Ok' || !data.routes?.length) throw new Error(data.message || 'No route found.')
    route.value = prepareRoute(data.routes[0])
    route.value.congestion = data.routes[0].legs[0]?.annotation?.congestion || []
    if (route.value.length < 30) { markArrived(); return } // already there
    snapHint = 0
    spoken = new Set()
    along.value = 0
    errorText.value = ''
    drawRoute()
    if (reason === 'start') { say(data.routes[0].legs[0].steps[0]?.voiceInstructions?.[0]?.announcement || 'Starting navigation.'); overviewThenFollow() }
  } catch (err) {
    errorText.value = res429(err) ? 'Map route limit reached — use Google Maps or Waze.' : 'Could not get a route. Check the connection.'
  } finally {
    rerouting.value = false
  }
}
const res429 = (err) => /limit/i.test(String(err?.message || ''))

// Driving view: hide the traffic overlay (the green/yellow/red road lines)
// so only the route stands out — like a maps app's navigation screen.
function hideTraffic() { setRoadTraffic(showRoadTraffic.value) }
function setRoadTraffic(on) {
  if (!map?.getStyle) return
  for (const layer of map.getStyle()?.layers || []) {
    if (/traffic|congestion/i.test(layer.id) && !layer.id.startsWith('nav-')) { try { map.setLayoutProperty(layer.id, 'visibility', on ? 'visible' : 'none') } catch { /* ignore */ } }
  }
}
const showRoadTraffic = ref(false)
function toggleRoadTraffic() { showRoadTraffic.value = !showRoadTraffic.value; setRoadTraffic(showRoadTraffic.value) }

// Route split into runs of equal congestion (Mapbox annotations=congestion):
// blue = moving, orange = moderate, red = heavy, dark red = severe — live
// traffic on the route itself, like a maps app.
function routeFeatures() {
  const { coords, congestion = [] } = route.value
  const features = []
  let start = 0
  for (let i = 1; i <= coords.length - 1; i++) {
    const level = congestion[i - 1] || 'unknown'
    const nextLevel = congestion[i] || 'unknown'
    if (i === coords.length - 1 || nextLevel !== level) {
      features.push({ type: 'Feature', properties: { congestion: level }, geometry: { type: 'LineString', coordinates: coords.slice(start, i + 1) } })
      start = i
    }
  }
  return { type: 'FeatureCollection', features }
}

function drawRoute() {
  if (!map || !route.value) return
  const data = routeFeatures()
  const src = map.getSource('nav-route')
  if (src) { src.setData(data); return }
  // Style still loading: draw as soon as it's ready (used to skip the line).
  if (!map.isStyleLoaded()) { map.once('idle', drawRoute); return }
  map.addSource('nav-route', { type: 'geojson', data })
  map.addLayer({ id: 'nav-route-casing', type: 'line', source: 'nav-route', layout: { 'line-cap': 'round', 'line-join': 'round' }, paint: { 'line-color': '#1e3a8a', 'line-width': 14 } })
  map.addLayer({ id: 'nav-route-line', type: 'line', source: 'nav-route', layout: { 'line-cap': 'round', 'line-join': 'round' }, paint: {
    'line-color': ['match', ['get', 'congestion'], 'moderate', '#f59e0b', 'heavy', '#ef4444', 'severe', '#991b1b', '#3b82f6'],
    'line-width': 9,
  } })
}

// ---------- GPS ----------
function onFix(f) {
  fix.value = f
  now.value = Date.now()
  if (/GPS|Location/i.test(errorText.value)) errorText.value = ''
  const pos = [f.lng, f.lat]
  movePuck(pos, f)
  // Arrival first — never re-route once we're at the destination.
  if (!arrived.value && haversine(pos, dest.value) < ARRIVE_M) markArrived()
  if (arrived.value) { if (following.value) follow(pos, f); return }
  if (!route.value) {
    if (!lastRouteAt || Date.now() - lastRouteAt > REROUTE_MIN_MS) fetchRoute(pos, 'start')
    return
  }
  const snap = snapToRoute(route.value, pos, snapHint)
  snapHint = snap.index
  // Off the route (allowing for GPS accuracy) on two fixes in a row → new route.
  const tolerance = Math.max(OFF_ROUTE_M, (f.accuracy || 0) * 1.5)
  offCount = snap.dist > tolerance ? offCount + 1 : 0
  if (offCount >= 2 && Date.now() - lastRouteAt > REROUTE_MIN_MS && !arrived.value) { offCount = 0; fetchRoute(pos, 'reroute'); return }
  if (snap.dist <= tolerance) along.value = Math.max(along.value - 20, snap.along) // don't jump backwards on GPS jitter
  // Fresh traffic every couple of minutes.
  if (Date.now() - lastRouteAt > TRAFFIC_REFRESH_MS && !arrived.value) fetchRoute(pos, 'refresh')
  announce()
  if (remaining.value < 25) markArrived()
  if (following.value) follow(pos, f)
}

function markArrived() {
  if (arrived.value) return
  arrived.value = true
  say(`You have arrived${props.destination.label ? ` at ${props.destination.label}` : ''}.`)
}

// Speak each instruction of the current step once, when it comes into range.
function announce() {
  const r = route.value
  const i = stepIndexAt(r, along.value)
  const step = r.steps[i]
  const toEnd = step.endAlong - along.value
  const due = (step.voiceInstructions || []).filter((v) => v.distanceAlongGeometry >= toEnd && !spoken.has(`${i}:${v.distanceAlongGeometry}`))
  if (!due.length) return
  due.forEach((v) => spoken.add(`${i}:${v.distanceAlongGeometry}`))
  say(due[due.length - 1].announcement) // the closest one
}

function headingFor(f) {
  if (f.heading != null && !Number.isNaN(f.heading) && (f.speed || 0) > 2) return f.heading
  return route.value ? routeBearingAt(route.value, along.value) : (map?.getBearing() || 0)
}
function follow(pos, f) {
  if (!map) return
  const h = map.getContainer().clientHeight
  // Close, tilted and looking ahead: the arrow sits low on the screen.
  const zoom = (f.speed || 0) > 22 ? 17 : (f.speed || 0) > 12 ? 17.6 : 18.2
  map.easeTo({ center: pos, bearing: headingFor(f), pitch: 62, zoom, padding: { top: h * 0.55, bottom: 20, left: 0, right: 0 }, duration: 900, essential: true })
}
function movePuck(pos, f) {
  if (!map || !gl) return
  if (!puck) {
    // Big, upright vehicle arrow (like a maps app): 'viewport' pitch keeps it
    // facing the screen at full size instead of lying flat on the tilted map
    // (where it looked tiny); it still turns with the direction of travel.
    const el = document.createElement('div')
    el.style.cssText = 'width:64px;height:64px;filter:drop-shadow(0 3px 6px rgba(0,0,0,.35))'
    el.innerHTML = '<svg viewBox="0 0 64 64" width="64" height="64"><circle cx="32" cy="32" r="29" fill="#2563eb" fill-opacity=".16"/><circle cx="32" cy="32" r="21" fill="#fff"/><path d="M32 14 L45 44 L32 37 L19 44 Z" fill="#2563eb" stroke="#1e40af" stroke-width="1.5" stroke-linejoin="round"/></svg>'
    puck = new gl.Marker({ element: el, rotationAlignment: 'map', pitchAlignment: 'viewport' }).setLngLat(pos).addTo(map)
  }
  puck.setLngLat(pos)
  puck.setRotation(headingFor(f))
}

function overview() {
  if (!map || !route.value) return
  following.value = false
  const b = route.value.coords.reduce((acc, c) => acc.extend(c), new gl.LngLatBounds(route.value.coords[0], route.value.coords[0]))
  map.fitBounds(b, { padding: { top: 200, bottom: 160, left: 50, right: 70 }, pitch: 0, bearing: 0, duration: 800 })
}
function overviewThenFollow() { overview(); setTimeout(() => { following.value = true; if (fix.value) follow([fix.value.lng, fix.value.lat], fix.value) }, 1800) }
function recenter() { following.value = true; if (fix.value) follow([fix.value.lng, fix.value.lat], fix.value) }
function close() { emit('close') }

onMounted(async () => {
  keepScreenOn(true)
  clock = setInterval(() => { now.value = Date.now() }, 15000)
  try {
    const useMapbox = mapboxUsable()
    gl = await loadEngine(useMapbox)
    map = new gl.Map({
      container: mapEl.value,
      style: styleFor(useMapbox ? 'mapbox' : 'maplibre', theme.value),
      center: dest.value, zoom: 14, pitch: 0, attributionControl: false,
      ...(useMapbox ? { projection: 'mercator' } : {}),
    })
    map.addControl(new gl.AttributionControl({ compact: true }), 'bottom-left')
    map.on('style.load', () => { hideTraffic(); drawRoute() })
    const pin = document.createElement('div')
    pin.style.cssText = 'width:30px;height:30px'
    pin.innerHTML = '<svg viewBox="0 0 30 30" width="30" height="30"><circle cx="15" cy="15" r="11" fill="#dc2626" stroke="#fff" stroke-width="4"/></svg>'
    new gl.Marker({ element: pin, pitchAlignment: 'viewport' }).setLngLat(dest.value).addTo(map)
    // A finger on the map stops following until Re-center.
    map.on('dragstart', () => { following.value = false })
  } catch (err) {
    errorText.value = 'The map could not load.'
  }
  // Don't wait on a blank map: start from the unit's last shared position.
  if (props.start?.latitude != null && props.start?.longitude != null) {
    onFix({ lng: Number(props.start.longitude), lat: Number(props.start.latitude), accuracy: 30, heading: null, speed: 0 })
  }
  stopWatch = watchPosition(onFix, (e) => { errorText.value = e?.code === 1 ? 'Location permission is off.' : 'Waiting for GPS…' })
})
onBeforeUnmount(() => {
  stopWatch?.()
  stopSpeaking()
  keepScreenOn(false)
  clearInterval(clock)
  map?.remove()
})
</script>
