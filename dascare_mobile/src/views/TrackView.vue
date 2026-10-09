<template>
  <div class="min-h-screen bg-base-200 dark:bg-[#050e1a]">
    <ScreenHeader :title="request?.reference_number || 'Request'" :fallback="isLoggedIn ? '/home' : '/welcome'">
      <template #actions>
        <span v-if="isActive" class="mr-3 flex items-center gap-1.5 text-[0.65rem] font-bold" :class="live ? 'text-emerald-600 dark:text-emerald-300' : 'text-slate-400 dark:text-white/40'">
          <span class="h-1.5 w-1.5 rounded-full" :class="live ? 'animate-pulse bg-emerald-500' : 'bg-slate-400 dark:bg-white/30'"></span> {{ live ? 'LIVE' : 'EVERY 15S' }}
        </span>
      </template>
    </ScreenHeader>

    <PullToRefresh :on-refresh="() => load(true)">
      <main class="space-y-4 px-4 pb-[calc(var(--safe-bottom)+2rem)] pt-4">
        <div v-if="loading" class="space-y-4" aria-busy="true" aria-label="Loading">
          <div class="flex items-center gap-3">
            <div class="h-14 w-14 animate-pulse rounded-2xl bg-base-100 dark:bg-white/5"></div>
            <div class="flex-1 space-y-2"><div class="h-4 w-1/2 animate-pulse rounded bg-base-100 dark:bg-white/5"></div><div class="h-3 w-1/3 animate-pulse rounded bg-base-100 dark:bg-white/5"></div></div>
          </div>
          <div v-for="i in 3" :key="i" class="h-32 animate-pulse rounded-3xl bg-base-100 dark:bg-white/5"></div>
        </div>

        <template v-else-if="request">
          <!-- Identity — same block as the web TrackRequest header -->
          <section class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-start gap-3">
              <div class="relative flex-shrink-0">
                <div v-if="isActive" class="absolute inset-0 animate-ping rounded-2xl bg-red-500/20 [animation-duration:2.2s]"></div>
                <div class="relative grid h-14 w-14 place-items-center rounded-2xl bg-red-50 dark:bg-red-500/10">
                  <Icon :icon="categoryIcon(request.emergency_category_name)" width="26" class="text-red-600 dark:text-red-300" />
                </div>
              </div>
              <div class="min-w-0">
                <h1 class="truncate text-xl font-bold leading-tight text-slate-900 dark:text-white">{{ request.emergency_category_name }}</h1>
                <p class="mt-1 text-xs text-slate-500 dark:text-white/45">Submitted {{ relativeTime(request.submitted_at, now) }}</p>
              </div>
            </div>
            <span class="flex-shrink-0 whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-bold" :class="statusBadgeClass(effectiveStatus)">
              <template v-if="request.merged_into">Linked · </template>{{ statusLabel(effectiveStatus) }}
            </span>
          </section>

          <!-- Linked by duplicate detection (same as the web) -->
          <section v-if="request.merged_into" class="rounded-3xl border border-red-200 bg-red-50 p-4 dark:border-red-500/20 dark:bg-red-500/5">
            <div class="flex items-start gap-3">
              <Icon icon="lucide:git-merge" width="18" class="mt-0.5 flex-shrink-0 text-red-600 dark:text-red-300" />
              <div class="min-w-0">
                <p class="text-sm font-black text-red-800 dark:text-red-200">Linked to {{ request.merged_into.reference_number }} — {{ linkedHeadline }}</p>
                <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-white/55">
                  Someone {{ request.merged_into.distance_m }} m away reported this emergency first, so one unit is handling both reports instead of sending two.
                </p>
                <button type="button" :disabled="unmerging" class="tap mt-3 flex w-full items-center justify-center gap-1.5 rounded-xl border border-red-300 bg-white px-3 py-3 text-xs font-bold text-red-700 disabled:opacity-50 dark:border-red-500/30 dark:bg-white/5 dark:text-red-300" @click="requestSeparately">
                  <Icon :icon="unmerging ? 'lucide:loader-circle' : 'lucide:split'" width="14" :class="unmerging ? 'animate-spin' : ''" />
                  Not the same emergency? Request separately
                </button>
              </div>
            </div>
          </section>

          <section v-if="request.attention_level && request.attention_level !== 'normal'" class="rounded-3xl border p-4" :class="request.attention_level === 'critical_overdue' ? 'border-red-200 bg-red-50 dark:border-red-500/20 dark:bg-red-500/10' : 'border-amber-200 bg-amber-50 dark:border-amber-500/20 dark:bg-amber-500/10'">
            <p class="flex items-center gap-2 text-sm font-black" :class="request.attention_level === 'critical_overdue' ? 'text-red-800 dark:text-red-200' : 'text-amber-800 dark:text-amber-200'">
              <Icon icon="lucide:triangle-alert" width="16" />
              {{ request.attention_level === 'critical_overdue' ? 'This emergency needs urgent operational review' : 'This emergency is overdue for operational review' }}
            </p>
            <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-white/55">{{ request.attention_reason }}</p>
          </section>

          <!-- Dispatch progress -->
          <section v-if="!isTerminalAlt" :class="[ui.card, 'p-5']">
            <h2 class="mb-4 text-sm font-bold text-slate-900 dark:text-white">Dispatch Progress</h2>
            <ol class="space-y-0">
              <li v-for="(stage, i) in STAGES" :key="stage.key" class="flex gap-3">
                <div class="flex flex-col items-center">
                  <span class="grid h-8 w-8 flex-shrink-0 place-items-center rounded-full border-2 transition-colors" :class="stageClass(i)">
                    <Icon :icon="i < currentStage ? 'lucide:check' : stage.icon" width="14" />
                  </span>
                  <span v-if="i < STAGES.length - 1" class="my-0.5 w-0.5 flex-1 rounded-full" :class="i < currentStage ? 'bg-red-500' : 'bg-slate-200 dark:bg-white/10'" style="min-height: 14px"></span>
                </div>
                <p class="pb-3 pt-1.5 text-sm font-semibold" :class="i <= currentStage ? 'text-slate-800 dark:text-white/85' : 'text-slate-400 dark:text-white/30'">{{ stage.label }}</p>
              </li>
            </ol>
          </section>
          <section v-else class="flex items-center gap-3 rounded-3xl border p-5" :class="effectiveStatus === 'completed' ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/20 dark:bg-emerald-500/5' : 'border-base-300 bg-base-100 dark:border-white/10 dark:bg-[#071829]'">
            <Icon :icon="statusIcon(effectiveStatus)" width="20" class="flex-shrink-0 text-slate-400 dark:text-white/40" />
            <p class="text-sm font-semibold text-slate-700 dark:text-white/70">This request was marked <strong>{{ statusLabel(effectiveStatus).toLowerCase() }}</strong> — see the history below.</p>
          </section>

          <!-- Responding unit -->
          <section v-if="request.ambulance" :class="[ui.card, 'p-5']">
            <h2 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">Responding Unit</h2>
            <div class="flex items-center gap-3">
              <div class="relative flex-shrink-0">
                <div v-if="isActive" class="absolute inset-0 animate-ping rounded-2xl bg-blue-500/20 [animation-duration:2.2s]"></div>
                <div class="relative grid h-11 w-11 place-items-center rounded-2xl bg-blue-50 dark:bg-blue-500/10"><Icon icon="lucide:truck" width="20" class="text-blue-600 dark:text-blue-300" /></div>
              </div>
              <div class="min-w-0">
                <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ request.ambulance.unit_code }}</p>
                <p class="truncate text-xs text-slate-500 dark:text-white/45">{{ request.ambulance.organization_name }}</p>
              </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
              <span class="rounded-lg bg-blue-50 px-2.5 py-1.5 text-xs font-bold text-blue-600 dark:bg-blue-500/10 dark:text-blue-300">{{ request.ambulance.status_label }}</span>
              <span v-if="request.ambulance.location_at" class="rounded-lg px-2.5 py-1.5 text-xs font-bold" :class="request.ambulance.location_stale ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'">
                {{ request.ambulance.location_stale ? 'GPS stale' : 'GPS live' }}<span v-if="request.ambulance.accuracy_m != null"> · ±{{ Math.round(request.ambulance.accuracy_m) }}m</span>
              </span>
            </div>
          </section>
          <section v-else-if="isActive" class="rounded-3xl border border-dashed border-slate-300 p-5 text-center dark:border-white/15">
            <Icon icon="lucide:truck" width="20" class="mx-auto mb-2 text-slate-300 dark:text-white/20" />
            <p class="text-xs font-semibold text-slate-400 dark:text-white/35">No unit assigned yet</p>
            <p class="mt-1 text-[0.68rem] text-slate-400 dark:text-white/25">A dispatcher is reviewing this request.</p>
          </section>

          <!-- Map -->
          <section v-if="request.latitude" :class="[ui.card, 'p-4']">
            <div class="mb-3 flex items-center justify-between">
              <h2 class="text-sm font-bold text-slate-900 dark:text-white">Location</h2>
              <div class="flex items-center gap-3 text-[0.65rem] text-slate-500 dark:text-white/40">
                <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-red-600"></span> Incident</span>
                <span v-if="request.merged_into" class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-orange-500"></span> Linked</span>
                <span v-if="ambulancePos" class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-blue-600"></span> Ambulance</span>
              </div>
            </div>
            <div ref="mapEl" class="h-60 w-full overflow-hidden rounded-xl border border-base-300 dark:border-white/10"></div>
            <p class="mt-2 text-xs text-slate-500 dark:text-white/45">{{ request.address_text }}<span v-if="request.barangay && request.barangay !== 'Unspecified'">, Brgy. {{ request.barangay }}</span></p>
            <p v-if="ambulancePos" class="mt-1 text-[0.68rem] text-slate-400 dark:text-white/30">Straight-line position, updated {{ relativeTime(request.ambulance.location_at, now) }} — not the driving route.</p>
          </section>

          <!-- Details -->
          <section :class="[ui.card, 'p-5']">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Details</h2>
            <p class="selectable mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600 dark:text-white/60">{{ request.description }}</p>
            <p class="mt-3 text-xs text-slate-400 dark:text-white/35">{{ formatDate(request.submitted_at) }}<span v-if="request.landmark"> · Near {{ request.landmark }}</span></p>
          </section>

          <!-- History -->
          <section :class="[ui.card, 'p-5']">
            <h2 class="mb-4 text-sm font-bold text-slate-900 dark:text-white">Status History</h2>
            <div v-for="(log, i) in request.status_logs" :key="i" class="flex gap-3">
              <div class="flex flex-shrink-0 flex-col items-center">
                <span class="grid h-7 w-7 place-items-center rounded-full" :class="i === request.status_logs.length - 1 ? 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-300' : 'bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-white/35'">
                  <Icon :icon="statusIcon(log.new_status)" width="13" />
                </span>
                <span v-if="i < request.status_logs.length - 1" class="my-1 w-px flex-1 bg-slate-200 dark:bg-white/10"></span>
              </div>
              <div class="min-w-0 pb-5 pt-0.5">
                <p class="text-sm font-semibold text-slate-800 dark:text-white">{{ statusLabel(log.new_status) }}</p>
                <p class="text-xs text-slate-400 dark:text-white/35">{{ formatDate(log.created_at) }} · {{ relativeTime(log.created_at, now) }}</p>
                <p v-if="log.notes" class="mt-1 rounded-lg bg-base-200 px-2.5 py-1.5 text-xs text-slate-500 dark:bg-white/5 dark:text-white/45">{{ log.notes }}</p>
              </div>
            </div>
          </section>

          <a v-if="isActive" href="tel:911" class="tap flex items-center justify-center gap-2.5 rounded-3xl border border-red-200 bg-red-50 p-4 text-xs font-bold text-red-700 no-underline dark:border-red-500/20 dark:bg-red-500/5 dark:text-red-300">
            <Icon icon="lucide:phone-call" width="16" /> Life-threatening? Call 911 too.
          </a>
        </template>

        <div v-else class="rounded-3xl border border-dashed border-slate-300 py-16 text-center dark:border-white/15">
          <Icon :icon="loadError ? 'lucide:alert-triangle' : 'lucide:file-x'" width="28" class="mx-auto mb-3 text-slate-300 dark:text-white/20" />
          <p class="text-sm font-semibold text-slate-500 dark:text-white/40">{{ loadError || 'Request not found' }}</p>
          <button v-if="loadError" type="button" class="mt-3 py-2 text-xs font-bold text-red-600 dark:text-red-300" @click="load()">Try again</button>
        </div>
      </main>
    </PullToRefresh>
  </div>
</template>

<script setup>
// Phone version of the web's TrackRequest.vue — same data (citizen/detail.php),
// stepper, unit card, map and history. Works for the signed-in citizen and
// for guests (their SOS key rides along in X-Guest-Tokens).
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import L from 'leaflet'
import ScreenHeader from '@/components/ScreenHeader.vue'
import PullToRefresh from '@/components/PullToRefresh.vue'
import * as ui from '@/components/ui/styles'
import api, { apiMessage } from '@/services/api'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'
import { useLiveUpdates } from '@/composables/useLiveUpdates'
import { requestChannel } from '@/services/realtime'
import { STAGES, TERMINAL_ALT, categoryIcon, formatDate, relativeTime, stageIndex, statusBadgeClass, statusIcon, statusLabel } from '@/utils/requestStatus'

const route = useRoute()
const { isLoggedIn } = useSession()
const toast = useToast()
const alert = useAlert()

const loading = ref(true)
const request = ref(null)
const loadError = ref('')
const now = ref(Date.now())

const effectiveStatus = computed(() => request.value?.merged_into?.status ?? request.value?.status)
const isTerminalAlt = computed(() => TERMINAL_ALT.includes(effectiveStatus.value))
const isActive = computed(() => !!request.value && !isTerminalAlt.value && effectiveStatus.value !== 'completed')
const currentStage = computed(() => stageIndex(effectiveStatus.value))
const ambulancePos = computed(() => {
  const a = request.value?.ambulance
  return a?.latitude != null && a?.longitude != null ? [a.latitude, a.longitude] : null
})
const linkedHeadline = computed(() => {
  const s = effectiveStatus.value
  if (['submitted', 'validating'].includes(s)) return 'dispatch is already finding a unit'
  if (['verified', 'assigned', 'acknowledged'].includes(s)) return 'a unit has been assigned'
  if (s === 'responding') return 'a unit is already on the way'
  if (['on_scene', 'transporting'].includes(s)) return 'a unit is already on scene'
  if (s === 'completed') return 'that response has been completed'
  return `that report was marked ${statusLabel(s).toLowerCase()}`
})
function stageClass(i) {
  if (i < currentStage.value) return 'border-red-600 bg-red-600 text-white'
  if (i === currentStage.value) return 'border-red-600 bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300'
  return 'border-slate-200 bg-white text-slate-300 dark:border-white/10 dark:bg-white/5 dark:text-white/25'
}

async function load(silent = false) {
  if (!silent) loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/citizen/detail.php', { params: { id: route.params.id } })
    request.value = data
    now.value = Date.now()
    await nextTick()
    drawMap()
  } catch (err) {
    if (!silent) request.value = null
    if (err.response?.status !== 404 && err.response?.status !== 401) loadError.value = apiMessage(err, 'Could not load this request. Please try again.')
  } finally {
    loading.value = false
  }
}

// Live updates (same as the web's TrackRequest): the ambulance's GPS moves
// the pin directly; other events on this request re-fetch it. Polls every
// 15 s while live updates are unavailable, only while still active. A
// dedup-linked report also follows the incident it was linked to.
function applyAmbulanceLocation(data) {
  const shownRequestId = Number(request.value?.merged_into?.id ?? request.value?.id)
  const amb = request.value?.ambulance
  if (!amb || Number(data.request_id) !== shownRequestId) return false
  Object.assign(amb, {
    latitude: data.latitude,
    longitude: data.longitude,
    accuracy_m: data.accuracy_m,
    location_at: new Date(data.sent_at || Date.now()).toISOString(),
    location_stale: false,
  })
  now.value = Date.now()
  drawMap()
  return true
}
const { live } = useLiveUpdates(load, {
  channels: () => [requestChannel(route.params.id), requestChannel(request.value?.merged_into?.id)],
  enabled: () => isActive.value,
  onEvent: (message) => (message.name === 'ambulance.location' && applyAmbulanceLocation(message.data) ? false : undefined),
})

// ------------------------------------------------------------------
// Map: incident (red), linked report's incident (orange), ambulance (blue)
// ------------------------------------------------------------------
const mapEl = ref(null)
let map = null
const layers = {}
const dot = (color) => L.divIcon({ className: '', html: `<span style="display:block;width:16px;height:16px;border-radius:9999px;background:${color};border:3px solid white;box-shadow:0 0 0 2px ${color}55"></span>`, iconSize: [16, 16], iconAnchor: [8, 8] })
function setMarker(key, pos, color) {
  if (!pos) { if (layers[key]) { map.removeLayer(layers[key]); delete layers[key] } return }
  if (layers[key]) layers[key].setLatLng(pos)
  else layers[key] = L.marker(pos, { icon: dot(color) }).addTo(map)
}
function drawMap() {
  if (!request.value?.latitude || !mapEl.value) return
  if (!map) {
    map = L.map(mapEl.value, { zoomControl: true, attributionControl: true, minZoom: 11 })
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap', maxZoom: 19 }).addTo(map)
  }
  const incident = [request.value.latitude, request.value.longitude]
  const linked = request.value.merged_into ? [request.value.merged_into.latitude, request.value.merged_into.longitude] : null
  setMarker('incident', incident, '#dc2626')
  setMarker('linked', linked, '#f97316')
  setMarker('ambulance', ambulancePos.value, '#2563eb')
  const target = linked || incident
  if (ambulancePos.value) {
    if (layers.line) layers.line.setLatLngs([target, ambulancePos.value])
    else layers.line = L.polyline([target, ambulancePos.value], { color: '#2563eb', weight: 2, dashArray: '5 6', opacity: 0.6 }).addTo(map)
  } else if (layers.line) { map.removeLayer(layers.line); delete layers.line }
  const points = [incident, linked, ambulancePos.value].filter(Boolean)
  if (points.length > 1) map.fitBounds(L.latLngBounds(points).pad(0.4), { maxZoom: 16 })
  else map.setView(incident, 15)
  setTimeout(() => map?.invalidateSize(), 300)
}

// "Not the same emergency?" — same as the web; guests are authorised by key.
const unmerging = ref(false)
async function requestSeparately() {
  const ok = await alert.confirm(`Only do this if your emergency is different from ${request.value.merged_into.reference_number} — for example, a different person or a different place. Dispatch will find a separate unit for you.`, 'Request separately?')
  if (!ok) return
  unmerging.value = true
  try {
    const { data } = await api.post('/citizen/unmerge.php', { id: request.value.id })
    toast.success(data.message, 'Requested separately')
    await load(true)
  } catch (err) {
    toast.error(apiMessage(err, 'Something went wrong. Please try again.'), 'Error')
  } finally {
    unmerging.value = false
  }
}

let clock = null
onMounted(() => { load(); clock = setInterval(() => { now.value = Date.now() }, 30000) })
watch(() => route.params.id, (id) => { if (id) load() })
onBeforeUnmount(() => { clearInterval(clock); map?.remove(); map = null })
</script>
