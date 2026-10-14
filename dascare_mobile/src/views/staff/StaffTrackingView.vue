<template>
  <!-- Live Tracking (staff, Phase 4): the web's Live Tracking — every unit
       on an active mission, its GPS freshness, and the selected one on the
       live map with route/ETA. Tap through to the mission for actions. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader title="Live Tracking" fallback="/staff/more" />

    <div v-if="loading" class="space-y-3 px-4 pt-4"><div :class="[ui.card, 'h-72 animate-pulse']"></div></div>

    <main v-else class="space-y-3 px-4 pt-3">
      <div v-if="!missions.length" class="rounded-3xl border border-dashed border-slate-300 py-14 text-center dark:border-white/15">
        <Icon icon="lucide:map-pinned" width="28" class="mx-auto mb-3 text-slate-300 dark:text-white/20" />
        <p class="text-sm font-semibold text-slate-500 dark:text-white/40">No units on a mission</p>
      </div>

      <template v-else>
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
          <button v-for="m in missions" :key="m.id" type="button" class="tap flex-shrink-0 rounded-2xl border px-3 py-2 text-left"
            :class="selected?.id === m.id ? 'border-red-400 bg-red-50 dark:border-red-500/40 dark:bg-red-500/10' : 'border-base-300 bg-base-100 dark:border-white/10 dark:bg-[#071829]'" @click="selectedId = m.id">
            <p class="text-sm font-black text-slate-900 dark:text-white">{{ m.unit_code }}</p>
            <p class="text-[0.65rem] font-semibold" :class="isStale(m) ? 'text-amber-600 dark:text-amber-300' : 'text-emerald-600 dark:text-emerald-300'">{{ gpsText(m) }}</p>
          </button>
        </div>

        <section v-if="selected" :class="[ui.card, 'overflow-hidden']">
          <div class="relative h-80">
            <LiveMissionMap :key="selected.id"
              :incident="{ latitude: selected.incident_latitude, longitude: selected.incident_longitude }"
              :ambulance="selected.last_latitude != null ? { latitude: selected.last_latitude, longitude: selected.last_longitude } : null"
              :route="selected.route" :labels="labels" :ambulance-seen-at="selected.seen_at ?? null" :station="selected.station" />
          </div>
          <RouterLink :to="{ name: 'StaffMission', params: { id: selected.id } }" class="tap flex items-center justify-between gap-3 border-t border-base-300 p-4 no-underline dark:border-white/10">
            <span class="min-w-0">
              <span class="block font-mono text-xs font-bold text-slate-500 dark:text-white/45">{{ selected.reference_number }} · {{ String(selected.assignment_status).replace('_', ' ') }}</span>
              <span class="block truncate text-sm font-bold text-slate-900 dark:text-white">{{ selected.address_text }}<template v-if="selected.barangay">, {{ selected.barangay }}</template></span>
            </span>
            <span class="flex items-center gap-0.5 text-xs font-bold text-red-600 dark:text-red-300">Mission <Icon icon="lucide:chevron-right" width="14" /></span>
          </RouterLink>
        </section>
      </template>
    </main>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import ScreenHeader from '@/components/ScreenHeader.vue'
import LiveMissionMap from '@/components/maps/LiveMissionMap.vue'
import * as ui from '@/components/ui/styles'
import { fetchLiveTracking } from '@/services/org/rescueOperations'
import { useLiveUpdates } from '@/composables/useLiveUpdates'
import { realtimeChannels } from '@/services/realtime'

const missions = ref([])
const staleAfter = ref(60)
const selectedId = ref(null)
const loading = ref(true)
const now = ref(Date.now())

const selected = computed(() => missions.value.find((m) => m.id === selectedId.value) || missions.value[0] || null)
const labels = computed(() => {
  const dest = selected.value?.route?.destination
  const toHospital = dest?.kind === 'facility'
  return { ambulance: selected.value?.unit_code, ambulanceStale: 'Last seen', waiting: 'Waiting for live location', station: `${selected.value?.unit_code} station`, incident: toHospital ? 'Pickup point' : 'Incident', facility: toHospital && dest.label ? `Destination · ${dest.label}` : 'Destination' }
})
const ageSeconds = (m) => (m.seen_at ? Math.round((now.value - m.seen_at) / 1000) : null)
const isStale = (m) => ageSeconds(m) == null || ageSeconds(m) > staleAfter.value
function gpsText(m) { const s = ageSeconds(m); if (s == null) return 'No GPS yet'; if (s < 60) return 'GPS live'; return `GPS ${Math.floor(s / 60)} min old` }

async function load() {
  try {
    const d = await fetchLiveTracking()
    staleAfter.value = d.stale_after_seconds || 60
    missions.value = (d.missions || []).map((m) => ({ ...m, seen_at: m.location_age_seconds != null ? Date.now() - m.location_age_seconds * 1000 : null }))
    now.value = Date.now()
  } catch { /* keep the last data */ } finally {
    loading.value = false
  }
}
function applyLocation(d) {
  const m = missions.value.find((x) => x.id === Number(d.assignment_id))
  if (!m) return
  Object.assign(m, { last_latitude: d.latitude, last_longitude: d.longitude, seen_at: Date.now() })
}
useLiveUpdates(load, {
  channels: () => [realtimeChannels.value?.org],
  onEvent: (msg) => {
    if (msg.name === 'ambulance.location') { applyLocation(msg.data); return false }
    if (msg.name === 'route.updated') { const m = missions.value.find((x) => x.id === Number(msg.data?.assignment_id)); if (m) m.route = msg.data.route; return false }
  },
})
const clock = setInterval(() => { now.value = Date.now() }, 10000)
onBeforeUnmount(() => clearInterval(clock))
onMounted(load)
</script>
