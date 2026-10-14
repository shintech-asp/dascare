<template>
  <!-- Missions tab (staff, Phase 4): my missions first, then — for
       dispatchers — the organization's other active missions, then finished
       ones. Same data as the web's Active Missions (missions/list.php). -->
  <PullToRefresh :on-refresh="load">
    <div class="space-y-4 px-4 pt-2">
      <div v-if="loading && !missions.length" class="space-y-3">
        <div v-for="n in 3" :key="n" :class="[ui.card, 'h-24 animate-pulse']"></div>
      </div>

      <template v-else>
        <section v-for="g in groups" :key="g.key">
          <p class="px-2 pb-2 text-[0.68rem] font-black uppercase tracking-[0.16em] text-slate-400 dark:text-white/35">{{ g.label }} · {{ g.items.length }}</p>
          <div class="space-y-2.5">
            <RouterLink v-for="m in g.items" :key="m.id" :to="{ name: 'StaffMission', params: { id: m.id } }" :class="[ui.card, 'tap block p-4 no-underline']">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="font-mono text-xs font-bold text-slate-500 dark:text-white/45">{{ m.reference_number }} · {{ m.unit_code }}</p>
                  <p class="mt-0.5 truncate text-sm font-black text-slate-900 dark:text-white">{{ m.category_name }} · <span class="capitalize">{{ m.severity }}</span></p>
                  <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-white/45">{{ m.address_text }}<template v-if="m.barangay">, {{ m.barangay }}</template></p>
                </div>
                <span class="flex-shrink-0 rounded-full px-2.5 py-1 text-[0.62rem] font-black uppercase" :class="missionBadge(m.assignment_status)">{{ missionLabel(m.assignment_status) }}</span>
              </div>
              <p v-if="isMine(m)" class="mt-2 inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-[0.62rem] font-black uppercase text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon icon="lucide:user-check" width="11" /> You · {{ myRole(m) }}</p>
            </RouterLink>
          </div>
        </section>

        <div v-if="!groups.length" class="rounded-3xl border border-dashed border-slate-300 py-16 text-center dark:border-white/15">
          <Icon icon="lucide:route" width="28" class="mx-auto mb-3 text-slate-300 dark:text-white/20" />
          <p class="text-sm font-semibold text-slate-500 dark:text-white/40">No missions yet</p>
          <p class="mt-1 text-xs text-slate-400">Missions appear here when a unit is assigned.</p>
        </div>
      </template>
    </div>
  </PullToRefresh>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import PullToRefresh from '@/components/PullToRefresh.vue'
import * as ui from '@/components/ui/styles'
import { fetchOrganizationMissions } from '@/services/org/dispatchOperations'
import { useLiveUpdates } from '@/composables/useLiveUpdates'
import { realtimeChannels } from '@/services/realtime'
import { ACTIVE_MISSION, missionBadge, missionLabel } from '@/utils/missionStatus'

const missions = ref([])
const memberId = ref(null)
const canDispatch = ref(false)
const loading = ref(true)

const isMine = (m) => (m.crew || []).some((c) => c.organization_member_id === memberId.value)
const myRole = (m) => String((m.crew || []).find((c) => c.organization_member_id === memberId.value)?.crew_role || '').replace('_', ' ')

const groups = computed(() => {
  const active = missions.value.filter((m) => ACTIVE_MISSION.includes(m.assignment_status))
  const done = missions.value.filter((m) => !ACTIVE_MISSION.includes(m.assignment_status)).slice(0, 15)
  return [
    { key: 'mine', label: 'My missions', items: active.filter(isMine) },
    canDispatch.value && { key: 'others', label: 'Other active missions', items: active.filter((m) => !isMine(m)) },
    { key: 'done', label: 'Finished', items: done },
  ].filter((g) => g && g.items.length)
})

async function load() {
  try {
    const d = await fetchOrganizationMissions()
    missions.value = d.missions || []
    memberId.value = d.current_member_id
    canDispatch.value = !!d.can_dispatch
  } catch { /* keep the last list */ } finally {
    loading.value = false
  }
}

useLiveUpdates(load, { channels: () => [realtimeChannels.value?.user, realtimeChannels.value?.org], onEvent: (msg) => (['ambulance.location', 'route.updated', 'fleet.updated'].includes(msg.name) ? false : undefined) })
onMounted(load)
</script>
