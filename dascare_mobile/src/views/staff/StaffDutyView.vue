<template>
  <!-- Duty & Availability (staff, Phase 4) — the web's Duty page: who's
       available for dispatch; one tap to change a member's availability
       (members on a mission show "On mission" and can't be changed). -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader title="Duty & Availability" fallback="/staff/more" />
    <main class="space-y-3 px-4 pt-3">
      <div class="grid grid-cols-3 gap-2">
        <div v-for="s in statTiles" :key="s.label" :class="[ui.card, 'p-3 text-center']">
          <p class="text-xl font-black text-slate-950 dark:text-white">{{ s.value }}</p>
          <p class="text-[0.65rem] font-semibold text-slate-500 dark:text-white/45">{{ s.label }}</p>
        </div>
      </div>
      <article v-for="m in active" :key="m.id" :class="[ui.card, 'p-4']">
        <div class="flex items-center justify-between gap-3">
          <div class="min-w-0">
            <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ m.first_name }} {{ m.last_name }}</p>
            <p class="truncate text-xs text-slate-500 dark:text-white/45">{{ (m.role_names || []).join(' · ') || (m.account_role === 'organization_admin' ? 'Organization admin' : 'No role') }}</p>
          </div>
          <span v-if="m.availability_status === 'assigned'" class="rounded-full bg-blue-50 px-2 py-0.5 text-[0.62rem] font-black uppercase text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">On mission</span>
        </div>
        <div v-if="m.availability_status !== 'assigned'" class="mt-3 grid grid-cols-4 gap-1.5">
          <button v-for="s in OPTIONS" :key="s.value" type="button" :disabled="!canUpdate || savingId === m.id" class="rounded-xl border-2 py-2 text-[0.68rem] font-black disabled:opacity-60"
            :class="(m.availability_status || 'off_duty') === s.value ? s.on : 'border-base-300 text-slate-500 dark:border-white/10 dark:text-white/50'" @click="change(m, s.value)">{{ s.label }}</button>
        </div>
      </article>
      <p v-if="!loading && !active.length" class="py-10 text-center text-sm text-slate-500">No active members.</p>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import ScreenHeader from '@/components/ScreenHeader.vue'
import * as ui from '@/components/ui/styles'
import { apiMessage } from '@/services/api'
import { fetchOrganizationMembers, updateOrganizationMemberAvailability } from '@/services/org/organizationManagement'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const { hasPermission } = useSession()
const canUpdate = computed(() => hasPermission('hr.availability.update'))
const OPTIONS = [
  { value: 'available', label: 'Available', on: 'border-emerald-600 bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' },
  { value: 'off_duty', label: 'Off duty', on: 'border-slate-500 bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-white/70' },
  { value: 'leave', label: 'Leave', on: 'border-amber-500 bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' },
  { value: 'unavailable', label: 'Unavail.', on: 'border-red-600 bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' },
]
const members = ref([])
const loading = ref(true)
const savingId = ref(null)
const active = computed(() => members.value.filter((m) => m.membership_status === 'active'))
const statTiles = computed(() => [
  { label: 'Available', value: active.value.filter((m) => m.availability_status === 'available').length },
  { label: 'On mission', value: active.value.filter((m) => m.availability_status === 'assigned').length },
  { label: 'Off / leave', value: active.value.filter((m) => !['available', 'assigned'].includes(m.availability_status)).length },
])
async function change(m, status) {
  if ((m.availability_status || 'off_duty') === status) return
  savingId.value = m.id
  try {
    const d = await updateOrganizationMemberAvailability(m.id, status, 'Updated from the staff app (Duty & Availability).')
    toast.success(d.message || 'Availability updated.', 'Updated')
    await load()
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to update availability.'), 'Error')
  } finally {
    savingId.value = null
  }
}
async function load() {
  try { members.value = (await fetchOrganizationMembers()).members || [] } catch (err) { toast.error(apiMessage(err, 'Could not load members.'), 'Error') } finally { loading.value = false }
}
onMounted(load)
</script>
