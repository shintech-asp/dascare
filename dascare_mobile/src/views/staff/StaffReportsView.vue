<template>
  <!-- Reports & Analytics (staff, Phase 4) — the web's operational summary
       from real offers, missions, fleet and personnel records. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader title="Reports & Analytics" fallback="/staff/more" />
    <main class="space-y-3 px-4 pt-3">
      <div class="grid grid-cols-2 gap-2">
        <div v-for="s in tiles" :key="s.label" :class="[ui.card, 'p-4']">
          <Icon :icon="s.icon" width="18" class="text-red-600 dark:text-red-300" />
          <p class="mt-2 text-2xl font-black text-slate-950 dark:text-white">{{ s.value ?? 0 }}</p>
          <p class="text-xs font-semibold text-slate-500 dark:text-white/45">{{ s.label }}</p>
        </div>
      </div>
      <section :class="[ui.card, 'p-4']">
        <h3 class="text-sm font-black text-slate-900 dark:text-white">Offer performance</h3>
        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
          <div v-for="m in offerTiles" :key="m.label" class="rounded-2xl bg-base-200 p-3 dark:bg-white/5"><p class="text-lg font-black text-slate-950 dark:text-white">{{ m.value ?? 0 }}</p><p class="text-[0.68rem] font-semibold text-slate-500">{{ m.label }}</p></div>
        </div>
        <p v-if="acceptRate !== null" class="mt-3 text-xs text-slate-500 dark:text-white/45">Acceptance rate <b class="text-slate-800 dark:text-white/80">{{ acceptRate }}%</b> of answered or expired offers.</p>
      </section>
      <section :class="[ui.card, 'p-4']">
        <h3 class="text-sm font-black text-slate-900 dark:text-white">Response timing</h3>
        <div class="mt-3 grid grid-cols-2 gap-2">
          <div class="rounded-2xl bg-base-200 p-3 dark:bg-white/5"><p class="text-lg font-black text-slate-950 dark:text-white">{{ performance.avg_response_minutes ? `${performance.avg_response_minutes} min` : '—' }}</p><p class="text-[0.68rem] font-semibold text-slate-500">Avg response</p></div>
          <div class="rounded-2xl bg-base-200 p-3 dark:bg-white/5"><p class="text-lg font-black text-slate-950 dark:text-white">{{ performance.avg_mission_minutes ? `${performance.avg_mission_minutes} min` : '—' }}</p><p class="text-[0.68rem] font-semibold text-slate-500">Avg mission</p></div>
        </div>
        <p v-if="!performance.avg_response_minutes" class="mt-2 text-xs text-slate-500">Not enough completed missions yet.</p>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import ScreenHeader from '@/components/ScreenHeader.vue'
import * as ui from '@/components/ui/styles'
import { fetchOrganizationReports } from '@/services/org/organizationCompletion'

const stats = ref({})
const performance = ref({})
const tiles = computed(() => [
  { label: 'Missions', value: stats.value.missions_total, icon: 'lucide:route' }, { label: 'Completed', value: stats.value.completed, icon: 'lucide:flag' },
  { label: 'Active', value: stats.value.active, icon: 'lucide:siren' }, { label: 'Fleet available', value: stats.value.fleet_available, icon: 'lucide:ambulance' },
  { label: 'Personnel available', value: stats.value.personnel_available, icon: 'lucide:users-round' },
])
const offerTiles = computed(() => [{ label: 'Accepted', value: stats.value.offers_accepted }, { label: 'Declined', value: stats.value.offers_declined }, { label: 'Timed out', value: stats.value.offers_timed_out }])
const acceptRate = computed(() => { const a = Number(stats.value.offers_accepted || 0), t = a + Number(stats.value.offers_declined || 0) + Number(stats.value.offers_timed_out || 0); return t ? Math.round((a / t) * 100) : null })
onMounted(async () => { const d = await fetchOrganizationReports(); stats.value = d.stats || {}; performance.value = d.performance || {} })
</script>
