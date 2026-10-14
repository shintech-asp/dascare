<template>
  <!-- Live monitoring board for City-wide Incidents: counters, fleet, a live
       map (incidents + ambulances on missions) and an urgency-ordered queue.
       Data: platform/incidents/monitor.php, re-fetched on every platform live
       event; ambulance pins move straight from `ambulance.location` events. -->
  <section ref="rootEl" class="overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#0d2943]" :class="fullscreen ? 'flex h-screen flex-col rounded-none' : ''">
    <!-- New emergency banner -->
    <Transition name="banner">
      <button v-if="banner" type="button" class="flex w-full items-center gap-3 bg-red-600 px-5 py-3 text-left text-white" @click="focusIncident(banner, true); banner = null">
        <span class="relative grid h-9 w-9 flex-shrink-0 place-items-center rounded-full bg-white/15">
          <span class="absolute inset-0 animate-ping rounded-full bg-white/25"></span>
          <Icon icon="lucide:siren" width="18" class="relative" />
        </span>
        <span class="min-w-0 flex-1">
          <span class="block text-sm font-black">New emergency · {{ banner.reference_number }}</span>
          <span class="block truncate text-xs text-white/85">{{ banner.category_name }} · {{ banner.barangay || 'Dasmariñas' }} · {{ titleCase(banner.severity) }} — tap to view</span>
        </span>
        <span class="rounded-lg p-1 text-white/80 hover:bg-white/10" @click.stop="banner = null"><Icon icon="lucide:x" width="16" /></span>
      </button>
    </Transition>

    <!-- Header: counters + fleet -->
    <div class="flex flex-col gap-3 border-b border-base-300 p-4 dark:border-white/10 xl:flex-row xl:items-center xl:justify-between">
      <div class="flex items-center gap-2.5">
        <span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-60"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-600"></span></span>
        <h2 class="whitespace-nowrap text-sm font-black text-slate-900 dark:text-white">Live monitor</h2>
        <span class="whitespace-nowrap text-xs text-slate-400 dark:text-white/35">{{ counts.active || 0 }} active · updated {{ updatedAgo }}</span>
      </div>
      <div class="flex flex-wrap items-center gap-1.5">
        <button v-for="c in counterChips" :key="c.key" type="button" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 !text-[0.68rem] !font-black uppercase leading-4 transition"
          :class="[c.cls, filter === c.key ? 'ring-2 ring-offset-1 ring-current dark:ring-offset-[#0d2943]' : '', !c.value ? 'opacity-45' : '']"
          :title="`Show only: ${c.label}`" @click="filter = filter === c.key ? null : c.key">
          <span class="h-1.5 w-1.5 rounded-full" :class="c.dot"></span>{{ c.label }} <span class="tabular-nums">{{ c.value }}</span>
        </button>
        <span class="mx-1 hidden h-5 w-px bg-base-300 dark:bg-white/10 sm:block"></span>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-base-200 px-2.5 py-1 text-[0.68rem] font-bold text-slate-600 dark:bg-white/5 dark:text-white/55" title="Ambulances across active organizations">
          <Icon icon="lucide:ambulance" width="13" /> {{ fleet.available || 0 }} available · {{ fleet.on_mission || 0 }} on mission<template v-if="fleet.returning"> · {{ fleet.returning }} returning</template>
        </span>
        <button type="button" class="ml-1 rounded-lg p-1.5 text-slate-400 hover:bg-base-200 dark:hover:bg-white/5" :title="muted ? 'Unmute new-emergency sound' : 'Mute new-emergency sound'" @click="toggleMute">
          <Icon :icon="muted ? 'lucide:volume-x' : 'lucide:volume-2'" width="16" />
        </button>
        <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-base-200 dark:hover:bg-white/5" :title="fullscreen ? 'Exit full screen' : 'Full screen (wall display)'" @click="toggleFullscreen">
          <Icon :icon="fullscreen ? 'lucide:minimize-2' : 'lucide:maximize-2'" width="16" />
        </button>
      </div>
    </div>

    <div class="grid lg:grid-cols-[minmax(0,1fr)_380px]" :class="fullscreen ? 'min-h-0 flex-1' : ''">
      <!-- Map -->
      <div class="relative border-base-300 dark:border-white/10 lg:border-r" :class="fullscreen ? 'h-full' : 'h-[460px]'">
        <!-- h-full/w-full: the map CSS sets position:relative, which cancels inset-0 -->
        <div ref="mapEl" class="absolute inset-0 h-full w-full"></div>
        <p v-if="mapError" class="absolute inset-0 grid place-items-center bg-base-200 p-4 text-center text-xs text-slate-500 dark:bg-white/5 dark:text-white/45">{{ mapError }}</p>
        <div class="pointer-events-none absolute left-2 top-2 flex max-w-[calc(100%-4rem)] flex-wrap gap-x-3 gap-y-1 rounded-xl bg-white/90 px-3 py-1.5 text-[0.65rem] font-bold text-slate-600 shadow dark:bg-[#071829]/90 dark:text-white/60">
          <span v-for="l in legend" :key="l.label" class="flex items-center gap-1"><span class="h-2 w-2 rounded-full" :class="l.dot"></span>{{ l.label }}</span>
        </div>
        <button v-if="ready && incidents.length" type="button" class="absolute right-2 top-[84px] rounded-lg bg-white px-2.5 py-1.5 !text-[0.68rem] !font-bold text-slate-600 shadow hover:bg-slate-50 dark:bg-[#071829] dark:text-white/65" @click="fitAll(true)">
          <Icon icon="lucide:scan" width="12" class="mr-1 inline" />Show all
        </button>
      </div>

      <!-- Queue -->
      <div class="min-h-0 overflow-y-auto" :class="fullscreen ? 'h-full' : 'max-h-[460px]'">
        <div v-if="loading && !incidents.length" class="grid place-items-center py-16"><span class="h-7 w-7 animate-spin rounded-full border-2 border-red-600 border-t-transparent"></span></div>
        <div v-else-if="!shown.length" class="p-10 text-center">
          <Icon icon="lucide:shield-check" width="26" class="mx-auto text-emerald-500" />
          <p class="mt-2 text-sm font-bold text-slate-600 dark:text-white/60">{{ filter ? 'Nothing in this group right now.' : 'No active emergencies.' }}</p>
          <p class="mt-1 text-xs text-slate-400">New requests appear here the moment they're sent.</p>
        </div>
        <button v-for="i in shown" :key="i.id" type="button"
          class="block w-full border-b border-base-300 px-4 py-3 text-left transition hover:bg-base-200/60 dark:border-white/5 dark:hover:bg-white/[0.03]"
          :class="[selectedId === i.id ? 'bg-red-50/70 dark:bg-red-500/10' : '', i.attention_level === 'critical_overdue' ? 'border-l-4 border-l-red-600' : i.attention_level === 'overdue' ? 'border-l-4 border-l-amber-400' : '']"
          @click="focusIncident(i, false)" @dblclick="emit('open', i)">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="flex items-center gap-1.5 text-sm font-black text-slate-900 dark:text-white">
                <span class="h-2 w-2 flex-shrink-0 rounded-full" :class="severityDot(i.severity)" :title="`${titleCase(i.severity)} severity`"></span>{{ i.reference_number }}
                <span v-if="i.linked_count" class="rounded-full bg-red-50 px-1.5 text-[0.6rem] font-black text-red-600 dark:bg-red-500/10 dark:text-red-300">+{{ i.linked_count }}</span>
              </p>
              <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-white/45">{{ i.barangay || 'Dasmariñas' }} · {{ i.category_name }}</p>
            </div>
            <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[0.6rem] font-black uppercase" :class="PHASES[i.phase].cls">{{ PHASES[i.phase].label }}</span>
          </div>
          <p class="mt-1.5 text-[0.7rem] leading-5 text-slate-500 dark:text-white/45">
            <span class="tabular-nums">{{ ageLabel(i) }}</span>
            <template v-if="i.offer"> · Offered to <b class="text-slate-700 dark:text-white/70">{{ i.offer.organization }}</b> · <span class="font-bold tabular-nums" :class="offerLeft(i) <= 60 ? 'text-red-600 dark:text-red-300' : 'text-amber-600 dark:text-amber-300'">{{ clock(offerLeft(i)) }} left</span></template>
            <template v-else-if="i.unit"> · <b class="text-slate-700 dark:text-white/70">{{ i.unit.unit_code }}</b> ({{ i.unit.organization }})<template v-if="etaLabel(i)"> · <span class="font-bold text-blue-600 dark:text-blue-300">{{ etaLabel(i) }}</span></template><template v-if="gpsStale(i)"> · <span class="font-bold text-amber-600 dark:text-amber-300">GPS {{ gpsAge(i) }}</span></template></template>
            <template v-else-if="i.accepted_organization"> · Accepted by <b class="text-slate-700 dark:text-white/70">{{ i.accepted_organization }}</b> — assigning a unit</template>
            <template v-else-if="i.phase === 'no_unit'"> · <span class="font-bold text-red-600 dark:text-red-300">No eligible unit — needs attention</span></template>
          </p>
          <span class="mt-1 inline-block text-[0.65rem] font-bold text-red-600 hover:underline dark:text-red-300" @click.stop="emit('open', i)">Open incident →</span>
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { fetchIncidentMonitor } from '@/services/dispatchOperations'
import { useLiveUpdates } from '@/composables/useLiveUpdates'
import { realtimeChannels, requestChannel } from '@/services/realtime'
import { useTheme } from '@/composables/useTheme'
import { MAPBOX_LOAD_TIMEOUT_MS, loadEngine, mapboxUsable, markMapboxUnavailable, styleFor } from '@/services/mapEngine'

const emit = defineEmits(['open'])
const { theme } = useTheme()

// Phase → label, badge and map colour. Ordered by how urgently a human is needed.
const PHASES = {
  no_unit: { label: 'No unit', cls: 'bg-red-600 text-white', dot: 'bg-red-600', color: '#dc2626', rank: 0 },
  waiting: { label: 'Waiting', cls: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300', dot: 'bg-red-400', color: '#f87171', rank: 1 },
  offered: { label: 'Offered', cls: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300', dot: 'bg-amber-500', color: '#f59e0b', rank: 2 },
  accepted: { label: 'Accepted', cls: 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300', dot: 'bg-sky-500', color: '#0ea5e9', rank: 3 },
  en_route: { label: 'En route', cls: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300', dot: 'bg-blue-600', color: '#2563eb', rank: 4 },
  on_scene: { label: 'On scene', cls: 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300', dot: 'bg-violet-600', color: '#7c3aed', rank: 5 },
  transporting: { label: 'Transporting', cls: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', dot: 'bg-emerald-600', color: '#059669', rank: 6 },
}
const ATTENTION_RANK = { critical_overdue: 0, overdue: 1, normal: 2 }
const STALE_GPS_SECONDS = 60

const incidents = ref([])
const counts = ref({})
const fleet = ref({})
const loading = ref(true)
const loadedAt = ref(0) // Date.now() of the last successful load
const now = ref(Date.now())
const filter = ref(null)
const selectedId = ref(null)
const banner = ref(null)
const muted = ref(readMuted())
const fullscreen = ref(false)
const rootEl = ref(null)

const counterChips = computed(() => [
  { key: 'no_unit', label: 'No unit', value: counts.value.no_unit || 0, cls: PHASES.no_unit.cls, dot: 'bg-white' },
  { key: 'waiting', label: 'Waiting', value: counts.value.waiting || 0, cls: PHASES.waiting.cls, dot: PHASES.waiting.dot },
  { key: 'offered', label: 'Offered', value: counts.value.offered || 0, cls: PHASES.offered.cls, dot: PHASES.offered.dot },
  { key: 'accepted', label: 'Accepted', value: counts.value.accepted || 0, cls: PHASES.accepted.cls, dot: PHASES.accepted.dot },
  { key: 'en_route', label: 'En route', value: counts.value.en_route || 0, cls: PHASES.en_route.cls, dot: PHASES.en_route.dot },
  { key: 'on_scene', label: 'On scene', value: counts.value.on_scene || 0, cls: PHASES.on_scene.cls, dot: PHASES.on_scene.dot },
  { key: 'transporting', label: 'Transporting', value: counts.value.transporting || 0, cls: PHASES.transporting.cls, dot: PHASES.transporting.dot },
  { key: 'overdue', label: 'Overdue', value: counts.value.overdue || 0, cls: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300', dot: 'bg-amber-500' },
])
const legend = [
  { label: 'No unit / waiting', dot: 'bg-red-600' },
  { label: 'Offered', dot: 'bg-amber-500' },
  { label: 'En route', dot: 'bg-blue-600' },
  { label: 'On scene', dot: 'bg-violet-600' },
  { label: 'Transporting', dot: 'bg-emerald-600' },
  { label: 'Ambulance', dot: 'bg-slate-900 dark:bg-white' },
]

const sorted = computed(() => [...incidents.value].sort((a, b) =>
  (ATTENTION_RANK[a.attention_level] ?? 2) - (ATTENTION_RANK[b.attention_level] ?? 2)
  || PHASES[a.phase].rank - PHASES[b.phase].rank
  || b.age_seconds - a.age_seconds))
const shown = computed(() => sorted.value.filter((i) => !filter.value
  || (filter.value === 'overdue' ? i.attention_level !== 'normal' : i.phase === filter.value)))

// ---------- time helpers (server gives ages; we count on from load time) ----------
const elapsed = () => Math.max(0, Math.floor((now.value - loadedAt.value) / 1000))
const updatedAgo = computed(() => (loadedAt.value ? (elapsed() < 5 ? 'just now' : `${elapsed()}s ago`) : '—'))
function clock(seconds) { const s = Math.max(0, Math.round(seconds)); return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}` }
function ageLabel(i) {
  const s = i.age_seconds + elapsed()
  if (s < 60) return 'just now'
  if (s < 3600) return `${Math.floor(s / 60)} min ago`
  if (s < 86400) return `${Math.floor(s / 3600)} h ${Math.floor((s % 3600) / 60)} min ago`
  return `${Math.floor(s / 86400)} d ago`
}
const offerLeft = (i) => (i.offer ? i.offer.seconds_left - elapsed() : 0)
const gpsAgeSeconds = (i) => (i.unit?.location_age_seconds == null ? null : i.unit.location_age_seconds + elapsed())
const gpsStale = (i) => i.unit && (gpsAgeSeconds(i) == null || gpsAgeSeconds(i) > STALE_GPS_SECONDS)
function gpsAge(i) { const s = gpsAgeSeconds(i); return s == null ? 'not shared' : s < 3600 ? `${Math.floor(s / 60)} min old` : 'stale' }
function etaLabel(i) {
  const eta = i.unit?.eta
  if (!eta || gpsStale(i)) return ''
  const left = Math.max(0, eta.seconds - eta.age_seconds - elapsed())
  const where = eta.destination === 'facility' ? 'to hospital' : 'to scene'
  return left < 60 ? `arriving ${where}` : `ETA ${Math.round(left / 60)} min ${where}`
}
const titleCase = (v = '') => String(v).replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())
const severityDot = (v) => ({ critical: 'bg-red-600', high: 'bg-orange-500', moderate: 'bg-amber-400', low: 'bg-blue-400' }[v] || 'bg-slate-400')

// ---------- data ----------
// Emergencies this browser has already announced (kept across reloads), so a
// request sent just before the page was opened/reloaded still gets its banner.
const SEEN_KEY = 'dascare.monitorAnnounced'
const ANNOUNCE_WINDOW_S = 10 * 60
function readSeen() { try { return new Set(JSON.parse(localStorage.getItem(SEEN_KEY) || '[]')) } catch { return new Set() } }
function saveSeen(ids) { try { localStorage.setItem(SEEN_KEY, JSON.stringify([...ids].slice(-300))) } catch { /* private mode */ } }
const seenIds = readSeen()
async function load() {
  try {
    const d = await fetchIncidentMonitor()
    const list = d.incidents || []
    const fresh = list.filter((i) => !seenIds.has(i.id) && i.age_seconds < ANNOUNCE_WINDOW_S)
    if (fresh.length) {
      banner.value = fresh.reduce((a, b) => (b.age_seconds < a.age_seconds ? b : a)) // newest
      chime()
    }
    list.forEach((i) => seenIds.add(i.id))
    saveSeen(seenIds)
    incidents.value = list
    counts.value = d.counts || {}
    fleet.value = d.fleet || {}
    loadedAt.value = Date.now()
    now.value = loadedAt.value
    if (ready.value) syncMarkers()
  } catch { /* keep the last board; the next event or poll retries */ } finally {
    loading.value = false
  }
}

// GPS fix for a unit on one of the shown incidents: move it without a re-fetch.
function applyLocation(data) {
  const inc = incidents.value.find((i) => i.id === Number(data.request_id))
  if (!inc?.unit) return false
  inc.unit.latitude = data.latitude
  inc.unit.longitude = data.longitude
  inc.unit.location_age_seconds = -elapsed() // = 0 "now", counted from loadedAt
  if (ready.value) syncMarkers()
  return true
}

const { live } = useLiveUpdates(load, {
  channels: () => [realtimeChannels.value?.platform, ...incidents.value.filter((i) => i.unit).map((i) => requestChannel(i.id))],
  debounceMs: 800,
  onEvent: (msg) => {
    if (msg.name === 'ambulance.location') { applyLocation(msg.data); return false }
    if (msg.name === 'fleet.updated' || msg.name === 'badges.updated') return undefined
  },
})
defineExpose({ live })

// ---------- sound ----------
function readMuted() { try { return localStorage.getItem('dascare.monitorMuted') === '1' } catch { return false } }
function toggleMute() { muted.value = !muted.value; try { localStorage.setItem('dascare.monitorMuted', muted.value ? '1' : '0') } catch { /* ignore */ } }
let audio = null
function chime() {
  if (muted.value) return
  try {
    audio = audio || new (window.AudioContext || window.webkitAudioContext)()
    const t = audio.currentTime
    ;[[988, 0], [740, 0.18], [988, 0.36]].forEach(([freq, at]) => {
      const osc = audio.createOscillator(); const gain = audio.createGain()
      osc.type = 'triangle'; osc.frequency.value = freq
      gain.gain.setValueAtTime(0.12, t + at); gain.gain.exponentialRampToValueAtTime(0.0001, t + at + 0.16)
      osc.connect(gain).connect(audio.destination); osc.start(t + at); osc.stop(t + at + 0.17)
    })
  } catch { /* browsers allow sound only after the user has clicked the page */ }
}

// ---------- full screen (wall display) ----------
function toggleFullscreen() {
  if (document.fullscreenElement) document.exitFullscreen?.()
  else rootEl.value?.requestFullscreen?.()
}
function onFullscreenChange() {
  fullscreen.value = document.fullscreenElement === rootEl.value
  setTimeout(() => map?.resize(), 50)
}

// ---------- map ----------
const mapEl = ref(null)
const ready = ref(false)
const mapError = ref('')
let gl = null
let engine = 'maplibre'
let map = null
let mapboxTimer = null
let markers = {} // key → { marker, sig }
let fitted = false

function lngLat(o) { return o && o.latitude != null && o.longitude != null ? [Number(o.longitude), Number(o.latitude)] : null }

function pinElement({ color, label, pulse = false, square = false, title = '' }) {
  // The map positions the OUTER element itself (absolute + transform) — only
  // size it; layout styles live on an inner wrapper, or pins drift on zoom.
  const el = document.createElement('div')
  el.style.cssText = 'width:18px;height:18px;cursor:pointer'
  el.title = title
  const inner = document.createElement('div')
  inner.style.cssText = 'position:relative;width:100%;height:100%'
  el.appendChild(inner)
  if (pulse) {
    const ring = document.createElement('span')
    ring.style.cssText = `position:absolute;inset:-7px;border-radius:9999px;background:${color};opacity:.25;animation:dascare-ping 1.6s cubic-bezier(0,0,.2,1) infinite`
    inner.appendChild(ring)
  }
  const dot = document.createElement('span')
  dot.style.cssText = `position:absolute;inset:0;border-radius:${square ? '5px' : '9999px'};background:${color};border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.35)`
  inner.appendChild(dot)
  if (label) {
    const cap = document.createElement('span')
    cap.textContent = label // textContent: no HTML from data
    cap.style.cssText = 'position:absolute;left:50%;bottom:calc(100% + 6px);transform:translateX(-50%);white-space:nowrap;border-radius:9999px;background:#fff;color:#0f172a;font:700 10px/1.2 system-ui,sans-serif;padding:3px 7px;box-shadow:0 1px 4px rgba(0,0,0,.25)'
    inner.appendChild(cap)
  }
  return el
}

function setMarker(key, position, sig, factory, onClick) {
  const current = markers[key]
  if (!position) { if (current) { current.marker.remove(); delete markers[key] } return }
  if (current && current.sig === sig) { current.marker.setLngLat(position); return }
  current?.marker.remove()
  const el = factory()
  if (onClick) el.addEventListener('click', (e) => { e.stopPropagation(); onClick() })
  markers[key] = { marker: new gl.Marker({ element: el }).setLngLat(position).addTo(map), sig }
}

function syncMarkers() {
  if (!map) return
  const keep = new Set()
  for (const i of incidents.value) {
    const p = PHASES[i.phase]
    const selected = selectedId.value === i.id
    const urgent = i.phase === 'no_unit' || i.phase === 'waiting' || i.attention_level !== 'normal'
    const key = `incident:${i.id}`
    keep.add(key)
    setMarker(key, lngLat(i), `${i.phase}|${urgent}|${selected}`,
      () => pinElement({ color: p.color, label: selected ? i.reference_number : '', pulse: urgent, title: `${i.reference_number} · ${p.label}` }),
      () => focusIncident(i, false))
    if (i.unit && lngLat(i.unit)) {
      const ukey = `unit:${i.unit.ambulance_id}`
      keep.add(ukey)
      const stale = gpsStale(i)
      setMarker(ukey, lngLat(i.unit), `${stale}|${i.unit.unit_code}`,
        () => pinElement({ color: stale ? '#94a3b8' : (theme.value === 'dark' ? '#f8fafc' : '#0f172a'), label: i.unit.unit_code, square: true, title: `${i.unit.unit_code} · ${i.unit.organization}${stale ? ' · GPS stale' : ''}` }),
        () => focusIncident(i, false))
    }
  }
  for (const key of Object.keys(markers)) if (!keep.has(key)) { markers[key].marker.remove(); delete markers[key] }
  if (!fitted && incidents.value.length) { fitted = true; fitAll(false) }
}

function fitAll(animate) {
  if (!map) return
  const points = incidents.value.flatMap((i) => [lngLat(i), lngLat(i.unit)]).filter(Boolean)
  if (!points.length) return
  if (points.length === 1) { map.easeTo({ center: points[0], zoom: 14, duration: animate ? 600 : 0 }); return }
  const bounds = points.reduce((b, p) => b.extend(p), new gl.LngLatBounds(points[0], points[0]))
  map.fitBounds(bounds, { padding: 60, maxZoom: 15, duration: animate ? 600 : 0 })
}

function focusIncident(i, openToo) {
  selectedId.value = i.id
  if (ready.value) {
    syncMarkers()
    const target = lngLat(i)
    if (target) map.flyTo({ center: target, zoom: Math.max(map.getZoom(), 15), duration: 700 })
  }
  if (openToo) emit('open', i)
}

function buildMap() {
  map = new gl.Map({
    container: mapEl.value,
    style: styleFor(engine, theme.value),
    center: [120.9367, 14.3294], // Dasmariñas
    zoom: 12.5,
    attributionControl: false,
    ...(engine === 'mapbox' ? { projection: 'mercator' } : {}),
  })
  map.addControl(new gl.AttributionControl({ compact: true }))
  map.addControl(new gl.NavigationControl({ showCompass: false }), 'top-right')
  if (import.meta.env.DEV) window.__dascareMonitor = { map, markers: () => markers } // dev-only: inspect from DevTools
  map.on('load', () => {
    clearTimeout(mapboxTimer)
    ready.value = true
    map.resize()
    syncMarkers()
  })
  if (engine === 'mapbox') {
    map.on('error', (e) => { if (!ready.value && [401, 403, 429].includes(e?.error?.status)) fallBackToFreeMap() })
    mapboxTimer = setTimeout(() => { if (!ready.value) fallBackToFreeMap() }, MAPBOX_LOAD_TIMEOUT_MS)
  }
}

async function fallBackToFreeMap() {
  if (engine !== 'mapbox' || !map) return
  markMapboxUnavailable()
  clearTimeout(mapboxTimer)
  map.remove()
  map = null
  markers = {}
  fitted = false
  engine = 'maplibre'
  try { gl = await loadEngine(false); if (mapEl.value) buildMap() } catch { mapError.value = 'The map could not load. Check the internet connection.' }
}

let ticker = null
// The map sizes itself once at creation; re-measure whenever its box changes
// (page layout settling, sidebar toggle, full screen).
let resizeObserver = null
onMounted(async () => {
  resizeObserver = new ResizeObserver(() => map?.resize())
  if (mapEl.value) resizeObserver.observe(mapEl.value)
  load()
  ticker = setInterval(() => { now.value = Date.now() }, 1000)
  document.addEventListener('fullscreenchange', onFullscreenChange)
  try {
    engine = mapboxUsable() ? 'mapbox' : 'maplibre'
    try { gl = await loadEngine(engine === 'mapbox') } catch (err) { if (engine !== 'mapbox') throw err; engine = 'maplibre'; gl = await loadEngine(false) }
    buildMap()
  } catch (err) {
    mapError.value = 'The map could not load. Check the internet connection.'
    console.error('Monitor map failed to load', err)
  }
})

onBeforeUnmount(() => {
  clearInterval(ticker)
  clearTimeout(mapboxTimer)
  resizeObserver?.disconnect()
  document.removeEventListener('fullscreenchange', onFullscreenChange)
  map?.remove()
  map = null
  markers = {}
})

// Stale/fresh GPS flips colour as time passes; re-check every 10 s.
watch(() => Math.floor(now.value / 10000), () => { if (ready.value) syncMarkers() })
watch(theme, (t) => {
  if (!map) return
  map.setStyle(styleFor(engine, t))
  for (const m of Object.values(markers)) m.marker.remove()
  markers = {}
  map.once('style.load', syncMarkers)
})
</script>

<style>
@keyframes dascare-ping { 75%, 100% { transform: scale(2); opacity: 0; } }
</style>
<style scoped>
.banner-enter-active, .banner-leave-active { transition: all 0.25s ease; }
.banner-enter-from, .banner-leave-to { opacity: 0; transform: translateY(-100%); }
</style>
