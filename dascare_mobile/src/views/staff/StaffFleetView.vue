<template>
  <!-- Fleet / Maintenance & Readiness (staff, Phase 4) — the web's Fleet and
       Readiness pages share this list: counters, search, status filter;
       readiness mode sorts by what needs attention. Tap a unit for status,
       readiness checks, maintenance, history, edit and archive. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader :title="readinessMode ? 'Maintenance & Readiness' : 'Fleet'" fallback="/staff/more">
      <template #actions>
        <RouterLink v-if="!readinessMode && canCreate" :to="{ name: 'StaffUnitForm' }" class="tap grid h-11 w-11 place-items-center rounded-xl text-red-600 dark:text-red-300" aria-label="Add ambulance"><Icon icon="lucide:plus" width="22" /></RouterLink>
      </template>
    </ScreenHeader>
    <main class="space-y-3 px-4 pt-3">
      <div class="grid grid-cols-4 gap-2">
        <div v-for="s in statTiles" :key="s.label" :class="[ui.card, 'p-2.5 text-center']">
          <p class="text-lg font-black text-slate-950 dark:text-white">{{ s.value }}</p>
          <p class="text-[0.6rem] font-semibold leading-tight text-slate-500 dark:text-white/45">{{ s.label }}</p>
        </div>
      </div>
      <div class="relative">
        <Icon icon="lucide:search" width="18" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input v-model="search" :class="[ui.input, 'pl-10']" placeholder="Unit, plate, model…" />
      </div>
      <div class="-mx-4 flex gap-1.5 overflow-x-auto px-4">
        <button v-for="f in FILTERS" :key="f.value" type="button" class="flex-shrink-0 rounded-full border px-3 py-1.5 text-xs font-bold"
          :class="filter === f.value ? 'border-red-600 bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'border-base-300 text-slate-600 dark:border-white/10 dark:text-white/60'" @click="filter = f.value">{{ f.label }}</button>
      </div>

      <div v-if="loading" class="space-y-2"><div v-for="n in 3" :key="n" :class="[ui.card, 'h-20 animate-pulse']"></div></div>
      <RouterLink v-for="u in shown" :key="u.id" :to="{ name: 'StaffUnit', params: { id: u.id } }" :class="[ui.card, 'tap block p-4 no-underline']">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-base font-black text-slate-900 dark:text-white">{{ u.unit_code }} <span class="text-sm font-normal text-slate-500">· {{ u.plate_number }}</span></p>
            <p class="truncate text-xs text-slate-500 dark:text-white/45">{{ typeLabel(u.ambulance_type) }}<template v-if="u.vehicle_make_model"> · {{ u.vehicle_make_model }}</template></p>
          </div>
          <span class="flex-shrink-0 rounded-full px-2.5 py-1 text-[0.62rem] font-bold" :class="statusPill(u.status)">{{ statusLabel(u.status) }}</span>
        </div>
        <div class="mt-2 flex flex-wrap gap-1.5">
          <span class="rounded-full px-2 py-0.5 text-[0.62rem] font-bold" :class="readinessPill(u.readiness_status)">{{ readinessLabel(u.readiness_status) }}</span>
          <span v-if="u.open_maintenance" class="rounded-full bg-amber-100 px-2 py-0.5 text-[0.62rem] font-bold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">Maintenance open</span>
          <span v-if="u.credential_alert" class="rounded-full bg-red-100 px-2 py-0.5 text-[0.62rem] font-bold text-red-700 dark:bg-red-500/15 dark:text-red-300">{{ u.credential_alert === true ? 'Credentials expiring' : u.credential_alert }}</span>
        </div>
      </RouterLink>
      <p v-if="!loading && !shown.length" class="py-10 text-center text-sm text-slate-500">No ambulances match.</p>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ScreenHeader from '@/components/ScreenHeader.vue'
import * as ui from '@/components/ui/styles'
import { apiMessage } from '@/services/api'
import { fetchFleetUnits } from '@/services/org/organizationFleet'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useLiveUpdates } from '@/composables/useLiveUpdates'
import { realtimeChannels } from '@/services/realtime'
import { readinessLabel, readinessPill, statusLabel, statusPill, typeLabel } from '@/utils/fleetLabels'

const route = useRoute()
const toast = useToast()
const { hasPermission } = useSession()
const readinessMode = computed(() => route.name === 'StaffReadiness')
const canCreate = computed(() => hasPermission('fleet.ambulances.create'))
const units = ref([])
const stats = ref({})
const loading = ref(true)
const search = ref('')
const filter = ref('')
const FILTERS = [{ value: '', label: 'All' }, { value: 'available', label: 'Available' }, { value: 'mission', label: 'On mission' }, { value: 'returning', label: 'Returning' }, { value: 'maintenance', label: 'Maintenance' }, { value: 'offline', label: 'Offline' }]
const MISSION = ['reserved', 'dispatched', 'on_scene', 'transporting']
const statTiles = computed(() => [
  { label: 'Units', value: stats.value.total ?? 0 }, { label: 'Available', value: stats.value.available ?? 0 },
  { label: 'Ready', value: stats.value.ready ?? 0 }, { label: 'Need attention', value: (stats.value.attention ?? 0) + (stats.value.credential_alerts ?? 0) },
])
const attentionRank = (u) => (u.readiness_status === 'out_of_service' ? 0 : u.credential_alert ? 1 : u.readiness_status !== 'ready' ? 2 : u.open_maintenance ? 3 : 4)
const shown = computed(() => {
  const q = search.value.trim().toLowerCase()
  const list = units.value.filter((u) => (!filter.value || (filter.value === 'mission' ? MISSION.includes(u.status) : u.status === filter.value))
    && (!q || `${u.unit_code} ${u.plate_number} ${u.vehicle_make_model || ''} ${typeLabel(u.ambulance_type)}`.toLowerCase().includes(q)))
  return readinessMode.value ? [...list].sort((a, b) => attentionRank(a) - attentionRank(b)) : list
})

async function load() {
  try {
    const d = await fetchFleetUnits()
    units.value = d.units || []
    stats.value = d.stats || {}
  } catch (err) {
    toast.error(apiMessage(err, 'Could not load the fleet.'), 'Error')
  } finally {
    loading.value = false
  }
}
useLiveUpdates(load, { channels: () => [realtimeChannels.value?.org], events: ['fleet.updated', 'mission.updated'] })
onMounted(load)
</script>
