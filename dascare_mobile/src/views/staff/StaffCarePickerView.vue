<template>
  <!-- Patient Assessments / Facility Handoffs / Field Reports from the More
       tab: pick the mission first (most recent active first), then the
       per-mission screen. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader :title="tool.title" fallback="/staff/more" />
    <main class="space-y-2.5 px-4 pt-3">
      <p class="px-2 pb-1 text-xs text-slate-500 dark:text-white/45">Choose a mission.</p>
      <RouterLink v-for="m in missions" :key="m.id" :to="{ name: tool.route, params: { id: m.id } }" :class="[ui.card, 'tap flex items-center justify-between gap-3 p-4 no-underline']">
        <span class="min-w-0">
          <span class="block font-mono text-xs font-bold text-slate-500 dark:text-white/45">{{ m.reference_number }} · {{ m.unit_code }}</span>
          <span class="block truncate text-sm font-bold text-slate-900 dark:text-white">{{ m.address_text }}<template v-if="m.barangay">, {{ m.barangay }}</template></span>
        </span>
        <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[0.6rem] font-black uppercase" :class="missionBadge(m.assignment_status)">{{ missionLabel(m.assignment_status) }}</span>
      </RouterLink>
      <p v-if="loaded && !missions.length" class="py-12 text-center text-sm text-slate-500">No missions yet.</p>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ScreenHeader from '@/components/ScreenHeader.vue'
import * as ui from '@/components/ui/styles'
import { fetchOrganizationMissions } from '@/services/org/dispatchOperations'
import { ACTIVE_MISSION, missionBadge, missionLabel } from '@/utils/missionStatus'

const route = useRoute()
const TOOLS = {
  assessment: { title: 'Patient Assessments', route: 'StaffAssessment' },
  handoff: { title: 'Facility Handoffs', route: 'StaffHandoff' },
  report: { title: 'Field Reports', route: 'StaffFieldReport' },
}
const tool = computed(() => TOOLS[route.params.tool] || TOOLS.assessment)
const missions = ref([])
const loaded = ref(false)
onMounted(async () => {
  try {
    const d = await fetchOrganizationMissions()
    const list = (d.missions || []).filter((m) => !['cancelled', 'reassigned'].includes(m.assignment_status))
    missions.value = [...list.filter((m) => ACTIVE_MISSION.includes(m.assignment_status)), ...list.filter((m) => !ACTIVE_MISSION.includes(m.assignment_status))].slice(0, 30)
  } finally {
    loaded.value = true
  }
})
</script>
