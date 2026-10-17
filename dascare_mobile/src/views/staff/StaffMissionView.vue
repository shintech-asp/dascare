<template>
  <!-- One mission (staff, Phase 4): the web's Active Missions detail +
       Live Tracking for a single unit, made for the crew's phone — status
       steps, live map with route/ETA, Navigate, GPS sharing (keeps running in
       the background), requester call, crew, and Close incident. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader :title="mission?.reference_number || 'Mission'" fallback="/staff/missions" />

    <div v-if="loading && !mission" class="space-y-3 px-4 pt-4">
      <div v-for="n in 3" :key="n" :class="[ui.card, 'h-28 animate-pulse']"></div>
    </div>
    <div v-else-if="!mission" class="px-4 pt-10 text-center">
      <Icon icon="lucide:file-x" width="28" class="mx-auto mb-3 text-slate-300" />
      <p class="text-sm font-semibold text-slate-500">Mission not found</p>
    </div>

    <main v-else class="space-y-3 px-4 pt-3">
      <!-- Summary + steps -->
      <section :class="[ui.card, 'p-4']">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="break-words text-base font-black text-slate-900 dark:text-white">{{ mission.category_name }} · <span class="capitalize">{{ mission.severity }}</span></p>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-white/45">Unit <b class="text-slate-700 dark:text-white/70">{{ mission.unit_code }}</b><template v-if="mission.plate_number"> · {{ mission.plate_number }}</template></p>
          </div>
          <span class="flex-shrink-0 rounded-full px-2.5 py-1 text-[0.62rem] font-black uppercase" :class="missionBadge(mission.assignment_status)">{{ missionLabel(mission.assignment_status) }}</span>
        </div>
        <div class="mt-4 flex gap-1">
          <span v-for="(s, i) in STEPPER" :key="s" class="h-1.5 flex-1 rounded-full" :class="i <= stepIndex ? 'bg-red-500' : 'bg-slate-200 dark:bg-white/10'"></span>
        </div>
        <p v-if="isMine" class="mt-3 inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-[0.62rem] font-black uppercase text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon icon="lucide:user-check" width="11" /> You're the {{ myRole }}</p>
      </section>

      <!-- Next action -->
      <section v-if="isActive && canAct && nextSteps.length" :class="[ui.card, 'space-y-2 border-red-200 p-4 dark:border-red-500/20']">
        <button v-for="a in nextSteps" :key="a.status" type="button" :disabled="saving"
          :class="a.secondary ? ui.secondaryButton : ui.primaryButton" @click="advance(a)">
          <Icon :icon="saving === a.status ? 'lucide:loader-circle' : a.icon" width="18" :class="saving === a.status ? 'animate-spin' : ''" /> {{ a.label }}
        </button>
        <RouterLink v-if="mission.assignment_status === 'transporting'" :to="{ name: 'StaffHandoff', params: { id } }" class="block text-center text-[0.7rem] font-semibold text-slate-500 no-underline dark:text-white/45">The Dispatcher confirms the facility handoff first, then the mission can be finished → <span class="text-red-600 dark:text-red-300">Facility handoff</span></RouterLink>
      </section>

      <!-- Map -->
      <section :class="[ui.card, 'overflow-hidden']">
        <div class="relative h-72">
          <LiveMissionMap
            :incident="{ latitude: mission.latitude, longitude: mission.longitude }"
            :ambulance="track?.last_latitude != null && isActive ? { latitude: track.last_latitude, longitude: track.last_longitude } : null"
            :route="isActive ? track?.route : null"
            :labels="mapLabels"
            :ambulance-seen-at="track?.seen_at ?? null"
            :station="isActive ? track?.station : null"
            :waiting-clickable="canShare && !sharingThis"
            @waiting-click="toggleSharing"
          />
        </div>
        <div v-if="isActive && navDestination" class="border-t border-base-300 p-3 pb-0 dark:border-white/10">
          <button type="button" class="tap flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 py-4 text-base font-black text-white shadow-lg shadow-emerald-600/25" @click="openNavigation">
            <Icon icon="lucide:navigation" width="20" /> Navigate {{ navDestination.kind === 'facility' ? 'to hospital' : 'to incident' }}
          </button>
        </div>
        <div v-if="isActive" class="grid grid-cols-2 gap-2 border-t border-base-300 p-3 dark:border-white/10" :class="navDestination ? 'border-t-0' : ''">
          <a :href="navigateUrl('google')" target="_blank" rel="noopener" class="tap flex items-center justify-center gap-1.5 rounded-xl bg-[#1976D2] py-3 text-sm font-bold text-white no-underline"><Icon icon="lucide:navigation" width="16" /> Google Maps</a>
          <a :href="navigateUrl('waze')" target="_blank" rel="noopener" class="tap flex items-center justify-center gap-1.5 rounded-xl border-2 border-base-300 py-3 text-sm font-bold text-slate-700 no-underline dark:border-white/10 dark:text-white/70"><Icon icon="lucide:car-front" width="16" /> Waze</a>
        </div>
      </section>

      <!-- GPS sharing (crew of this mission) -->
      <section v-if="canShare && isActive" :class="[ui.card, 'p-4']">
        <div class="flex items-start gap-3">
          <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-xl" :class="sharingThis ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-white/50'">
            <Icon :icon="sharingThis ? 'lucide:radio' : 'lucide:locate-off'" width="20" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-slate-900 dark:text-white">{{ sharingThis ? 'Sharing your location' : 'Location not shared' }}</p>
            <p class="text-xs leading-5 text-slate-500 dark:text-white/45">
              <template v-if="gpsState.permissionDenied">Location is blocked for DASCARE. Allow it in settings ("Allow all the time" is not needed).</template>
              <template v-else-if="sharingThis">{{ gpsState.lastSentAt ? `Last sent ${sentAgo}` : 'Waiting for GPS…' }} — keeps running with the screen off until the mission ends.</template>
              <template v-else>Dispatch and the requester see the ambulance move while you share.</template>
            </p>
            <p v-if="gpsState.lastError && !gpsState.permissionDenied && sharingThis" class="mt-1 text-xs font-semibold text-amber-600 dark:text-amber-300">{{ gpsState.lastError }}</p>
          </div>
        </div>
        <button v-if="gpsState.permissionDenied" type="button" :class="[ui.secondaryButton, 'mt-3']" @click="openLocationSettings">Open settings</button>
        <button v-else type="button" :class="[sharingThis ? ui.secondaryButton : ui.primaryButton, 'mt-3']" @click="toggleSharing">
          <Icon :icon="sharingThis ? 'lucide:pause' : 'lucide:radio'" width="18" /> {{ sharingThis ? 'Stop sharing' : 'Start sharing location' }}
        </button>
      </section>

      <!-- Care: assessment / handoff / field report for this mission -->
      <section v-if="careLinks.length" :class="[ui.card, 'grid gap-1 p-2']" :style="{ gridTemplateColumns: `repeat(${careLinks.length}, minmax(0, 1fr))` }">
        <RouterLink v-for="c in careLinks" :key="c.name" :to="{ name: c.name, params: { id } }" class="tap flex flex-col items-center gap-1.5 rounded-2xl px-1 py-3 text-center no-underline">
          <span class="grid h-10 w-10 place-items-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon :icon="c.icon" width="19" /></span>
          <span class="text-[0.68rem] font-semibold leading-tight text-slate-600 dark:text-white/60">{{ c.label }}</span>
        </RouterLink>
      </section>

      <!-- Incident -->
      <section :class="[ui.card, 'p-4']">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 dark:text-white/35">Incident</h3>
        <p class="selectable mt-2 text-sm font-semibold text-slate-800 dark:text-white/80">{{ mission.address_text }}<template v-if="mission.barangay">, {{ mission.barangay }}</template></p>
        <p v-if="mission.landmark" class="text-xs text-slate-500 dark:text-white/45">Near {{ mission.landmark }}</p>
        <p v-if="mission.description" class="selectable mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600 dark:text-white/60">{{ mission.description }}</p>
        <p v-if="mission.linked_count" class="mt-2 inline-flex rounded-full bg-red-50 px-2 py-0.5 text-[0.65rem] font-black text-red-600 dark:bg-red-500/10 dark:text-red-300">+{{ mission.linked_count }} linked {{ mission.linked_count === 1 ? 'report' : 'reports' }}</p>
        <div class="mt-3 flex items-center justify-between gap-3 border-t border-base-300 pt-3 dark:border-white/10">
          <div class="min-w-0">
            <p class="text-xs text-slate-400">Requester</p>
            <p class="truncate text-sm font-bold text-slate-800 dark:text-white/80">{{ mission.requester_name || 'Guest requester' }}</p>
          </div>
          <a v-if="mission.requester_phone" :href="`tel:${mission.requester_phone}`" class="tap flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white no-underline"><Icon icon="lucide:phone" width="15" /> Call</a>
          <span v-else class="text-xs text-slate-400">No number given</span>
        </div>
      </section>

      <!-- Crew -->
      <section :class="[ui.card, 'p-4']">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 dark:text-white/35">Crew</h3>
        <ul class="mt-2 divide-y divide-base-300 dark:divide-white/10">
          <li v-for="c in mission.crew" :key="c.organization_member_id" class="flex items-center justify-between gap-3 py-2.5">
            <span class="min-w-0 truncate text-sm font-semibold text-slate-800 dark:text-white/80">{{ c.first_name }} {{ c.last_name }}<span v-if="c.organization_member_id === memberId" class="text-red-600 dark:text-red-300"> (you)</span></span>
            <span class="flex-shrink-0 text-xs capitalize text-slate-500 dark:text-white/45">{{ String(c.crew_role).replace('_', ' ') }} · {{ c.response_status }}</span>
          </li>
        </ul>
      </section>

      <!-- Close incident (dispatch / admin) -->
      <button v-if="canClose" type="button" :class="[ui.secondaryButton, 'border-red-200 text-red-600 dark:border-red-500/25 dark:text-red-300']" @click="closeOpen = true">
        <Icon icon="lucide:octagon-x" width="16" /> Close incident
      </button>
    </main>

    <TransportFacilityPicker :open="transportOpen" :facilities="facilities" :from="mission ? { latitude: mission.latitude, longitude: mission.longitude } : null" :reference="mission?.reference_number || ''" :busy="transportBusy" @close="transportOpen = false" @confirm="confirmTransport" />
    <NavigationMode v-if="navOpen && navDestination" :destination="navDestination" :arrive-label="navArriveLabel" :start="track?.last_latitude != null ? { latitude: track.last_latitude, longitude: track.last_longitude } : null" @close="navOpen = false" @arrived="onArrived" />
    <CloseRequestModal :open="closeOpen" mode="organization" :reference="mission?.reference_number || ''" :linked-count="mission?.linked_count || 0" :busy="closing"
      @close="closeOpen = false" @confirm="closeIncident" />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ScreenHeader from '@/components/ScreenHeader.vue'
import LiveMissionMap from '@/components/maps/LiveMissionMap.vue'
import CloseRequestModal from '@/components/incidents/CloseRequestModal.vue'
import NavigationMode from '@/components/maps/NavigationMode.vue'
import TransportFacilityPicker from '@/components/incidents/TransportFacilityPicker.vue'
import * as ui from '@/components/ui/styles'
import { apiMessage } from '@/services/api'
import { closeOrganizationIncident, fetchOrganizationMissions, updateMissionStatus } from '@/services/org/dispatchOperations'
import { createHandoff, fetchHandoffs, fetchLiveTracking, fetchReceivingFacilities } from '@/services/org/rescueOperations'
import { gpsState, openLocationSettings, startSharing, stopSharing } from '@/services/missionGps'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'
import { useLiveUpdates } from '@/composables/useLiveUpdates'
import { realtimeChannels } from '@/services/realtime'
import { ACTIVE_MISSION, NEXT_STEPS, STEPPER, missionBadge, missionLabel } from '@/utils/missionStatus'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const alert = useAlert()
const { hasPermission } = useSession()

const id = computed(() => Number(route.params.id))
const mission = ref(null)
const track = ref(null) // this mission's entry in tracking/list.php (GPS, route, station)
const memberId = ref(null)
const canDispatch = ref(false)
const canMission = ref(false)
const canShareLocation = ref(false)
const loading = ref(true)
const saving = ref(null)
const now = ref(Date.now())

const isActive = computed(() => ACTIVE_MISSION.includes(mission.value?.assignment_status))
const myCrew = computed(() => (mission.value?.crew || []).find((c) => c.organization_member_id === memberId.value))
const isMine = computed(() => !!myCrew.value)
const myRole = computed(() => String(myCrew.value?.crew_role || 'crew').replace('_', ' '))
// Same rule as the web: dispatchers act on any mission; crew with the mission
// permission on their own. Assigned crew may always acknowledge.
const canAct = computed(() => canDispatch.value || (isMine.value && (canMission.value || mission.value?.assignment_status === 'assigned')))
const nextSteps = computed(() => NEXT_STEPS[mission.value?.assignment_status] || [])
const stepIndex = computed(() => STEPPER.indexOf(mission.value?.assignment_status))
const canShare = computed(() => isMine.value && (canShareLocation.value || hasPermission('dispatch.tracking.update')))
const sharingThis = computed(() => gpsState.value.assignmentId === id.value)
const canClose = computed(() => canDispatch.value && hasPermission('incidents.emergency_requests.update') && isActive.value && mission.value?.assignment_status !== 'transporting')
const careLinks = computed(() => [
  hasPermission('incidents.patient_assessments.read') && { name: 'StaffAssessment', label: 'Patient assessment', icon: 'lucide:stethoscope' },
  hasPermission('hospital.handoffs.read') && { name: 'StaffHandoff', label: 'Facility handoff', icon: 'lucide:handshake' },
  hasPermission('incidents.incident_reports.read') && { name: 'StaffFieldReport', label: 'Field report', icon: 'lucide:notebook-pen' },
].filter(Boolean))
const sentAgo = computed(() => { const s = Math.max(0, Math.round((now.value - gpsState.value.lastSentAt) / 1000)); return s < 5 ? 'just now' : s < 60 ? `${s}s ago` : `${Math.floor(s / 60)} min ago` })

const mapLabels = computed(() => {
  const dest = track.value?.route?.destination
  const toHospital = dest?.kind === 'facility'
  const crew = isMine.value
  return {
    ambulanceStale: crew ? 'You last shared' : 'Last seen',
    waiting: crew ? 'Share your GPS to go live' : 'Waiting for live location',
    station: `${mission.value?.unit_code || 'Unit'} station`,
    ambulance: crew ? 'You are here' : mission.value?.unit_code,
    incident: toHospital ? 'Pickup point' : 'Destination',
    facility: toHospital && dest.label ? `Destination · ${dest.label}` : 'Destination',
  }
})

// In-app driving navigation (components/maps/NavigationMode.vue): to the
// hospital while transporting, otherwise to the incident. Starts GPS sharing
// for crew, and on arrival offers the matching next step.
const navOpen = ref(false)
const navDestination = computed(() => {
  const m = mission.value
  if (!m || !isActive.value || m.assignment_status === 'on_scene') return null
  const d = track.value?.route?.destination
  if (d?.latitude != null) return { latitude: d.latitude, longitude: d.longitude, kind: d.kind, label: d.label || (d.kind === 'facility' ? 'Hospital' : m.address_text) }
  return m.assignment_status === 'transporting' ? null : { latitude: m.latitude, longitude: m.longitude, kind: 'incident', label: m.address_text }
})
const navArriveLabel = computed(() => {
  if (!canAct.value) return ''
  if (mission.value?.assignment_status === 'transporting') return 'Open facility handoff'
  return ['acknowledged', 'responding'].includes(mission.value?.assignment_status) ? 'Arrived on scene' : ''
})
async function openNavigation() {
  if (canShare.value && !sharingThis.value) await startSharing(id.value)
  navOpen.value = true
}
async function onArrived() {
  const status = mission.value?.assignment_status
  if (status === 'transporting') { router.push({ name: 'StaffHandoff', params: { id: id.value } }); return }
  if (status === 'acknowledged') await advance({ status: 'responding' })
  if (['acknowledged', 'responding'].includes(mission.value?.assignment_status)) await advance({ status: 'on_scene' })
}

function navigateUrl(app) {
  const d = track.value?.route?.destination || { latitude: mission.value?.latitude, longitude: mission.value?.longitude }
  const ll = `${d.latitude},${d.longitude}`
  return app === 'waze' ? `https://waze.com/ul?ll=${encodeURIComponent(ll)}&navigate=yes` : `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(ll)}&travelmode=driving`
}

async function load() {
  try {
    const [d, t] = await Promise.all([fetchOrganizationMissions(), fetchLiveTracking().catch(() => null)])
    mission.value = (d.missions || []).find((m) => m.id === id.value) || null
    memberId.value = d.current_member_id
    canDispatch.value = !!d.can_dispatch
    canMission.value = !!d.can_update_mission
    if (t) {
      canShareLocation.value = !!t.can_share_location
      const entry = (t.missions || []).find((m) => m.id === id.value)
      track.value = entry ? { ...entry, seen_at: entry.location_age_seconds != null ? Date.now() - entry.location_age_seconds * 1000 : null } : null
    }
    now.value = Date.now()
    // Mission over: stop sending GPS for it.
    if (mission.value && !isActive.value && sharingThis.value) await stopSharing()
  } catch { /* keep what we have */ } finally {
    loading.value = false
  }
}

// Start transport: pick the receiving facility first (creates the facility
// handoff), then transporting — and navigation goes to that facility.
const transportOpen = ref(false)
const transportBusy = ref(false)
const facilities = ref([])
async function startTransport() {
  try {
    const open = ((await fetchHandoffs(id.value)).handoffs || []).find((h) => ['pending', 'accepted'].includes(h.status))
    if (open) {
      if (!(await alert.confirm(`Start transport to ${open.facility_name}?`, 'Start transport'))) return
      await finishTransportStart()
      return
    }
    facilities.value = (await fetchReceivingFacilities()).facilities || []
    transportOpen.value = true
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to start transport.'), 'Error')
  }
}
async function confirmTransport(form) {
  transportBusy.value = true
  try {
    await createHandoff({ assignment_id: id.value, ...form })
    transportOpen.value = false
    await finishTransportStart()
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to start transport.'), 'Not started')
  } finally {
    transportBusy.value = false
  }
}
async function finishTransportStart() {
  saving.value = 'transporting'
  try {
    const d = await updateMissionStatus(id.value, 'transporting')
    toast.success(d.message || 'Patient transport started.', 'Transporting')
    await load()
    // Straight into navigation to the hospital for the crew.
    if (isMine.value && navDestination.value?.kind === 'facility') await openNavigation()
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to start transport.'), 'Not started')
  } finally {
    saving.value = null
  }
}

async function advance(a) {
  if (a.status === 'transporting') return startTransport()
  if (a.status === 'completed' && mission.value.assignment_status === 'on_scene') {
    if (!(await alert.confirm('Finish this mission without transporting a patient?', 'Complete mission'))) return
  }
  saving.value = a.status
  try {
    const data = await updateMissionStatus(mission.value.id, a.status)
    toast.success(data.message || 'Mission updated.', missionLabel(a.status))
    // Crew start sharing automatically once they're heading out.
    if (canShare.value && ['acknowledged', 'responding'].includes(a.status) && !sharingThis.value) await startSharing(mission.value.id)
    if (a.status === 'completed' && sharingThis.value) await stopSharing()
    await load()
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to update the mission.'), 'Not updated')
  } finally {
    saving.value = null
  }
}

async function toggleSharing() {
  if (sharingThis.value) { await stopSharing(); toast.info('Location sharing stopped.', 'GPS off'); return }
  const ok = await startSharing(id.value)
  if (ok) toast.success('Your location is now shared with dispatch and the requester.', 'GPS on')
}

const closeOpen = ref(false)
const closing = ref(false)
async function closeIncident({ status, reason }) {
  closing.value = true
  try {
    const data = await closeOrganizationIncident(mission.value.emergency_request_id, status, reason)
    closeOpen.value = false
    toast.success(data.message, 'Incident closed')
    if (sharingThis.value) await stopSharing()
    router.replace('/staff/missions')
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to close this incident.'), 'Error')
  } finally {
    closing.value = false
  }
}

// GPS fixes for this unit move the pin without a re-fetch; anything else re-loads.
function applyLocation(data) {
  if (!track.value || Number(data.assignment_id) !== id.value) return false
  Object.assign(track.value, { last_latitude: data.latitude, last_longitude: data.longitude, seen_at: Date.now() })
  return true
}
useLiveUpdates(load, {
  channels: () => [realtimeChannels.value?.user, realtimeChannels.value?.org],
  onEvent: (msg) => {
    if (msg.name === 'ambulance.location') { applyLocation(msg.data); return false }
    if (msg.name === 'route.updated') { if (track.value && Number(msg.data?.assignment_id) === id.value) track.value.route = msg.data.route; return false }
    if (msg.name === 'fleet.updated') return false
  },
})
const clock = setInterval(() => { now.value = Date.now() }, 5000)
onBeforeUnmount(() => clearInterval(clock))
onMounted(load)
</script>
