<template>
  <!-- Facility handoff for one mission (staff, Phase 4) — the web's Facility
       Handoffs: request a receiving hospital (on scene / transporting), then
       accepted → completed (completed = Dispatcher only). A transporting mission can only be completed
       after its handoff is completed (missions/status.php). -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader :title="`Handoff · ${mission?.reference_number || ''}`" :fallback="`/staff/missions/${id}`" />
    <main class="space-y-4 px-4 pt-3">
      <section v-for="h in handoffs" :key="h.id" :class="[ui.card, 'p-4']">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="text-sm font-black text-slate-900 dark:text-white">{{ h.facility_name }}</p>
            <p class="truncate text-xs text-slate-500 dark:text-white/45">{{ h.facility_address }}</p>
          </div>
          <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[0.6rem] font-black uppercase" :class="badge(h.status)">{{ h.status }}</span>
        </div>
        <p class="mt-2 text-xs text-slate-500 dark:text-white/45">ETA {{ h.eta_minutes ?? '—' }} min · {{ formatDate(h.created_at) }}<template v-if="h.initiated_by"> · {{ h.initiated_by }}</template></p>
        <p v-if="h.notes" class="mt-1 text-sm text-slate-700 dark:text-white/70">{{ h.notes }}</p>
        <div v-if="h.status === 'pending' && canRecord" class="mt-3 grid grid-cols-2 gap-2">
          <button type="button" :class="ui.primaryButton" @click="change(h, 'accepted')">Facility accepted</button>
          <button type="button" :class="ui.secondaryButton" @click="change(h, 'declined')">Declined</button>
        </div>
        <template v-if="h.status === 'accepted'">
          <button v-if="canConfirm" type="button" :class="[ui.primaryButton, 'mt-3']" @click="change(h, 'completed')"><Icon icon="lucide:handshake" width="18" /> Patient handed over</button>
          <p v-else class="mt-3 flex items-center gap-1.5 rounded-xl bg-base-200 px-3 py-2.5 text-xs font-bold text-slate-500 dark:bg-white/5 dark:text-white/45"><Icon icon="lucide:lock" width="14" /> Waiting for the Dispatcher to confirm the physical handoff.</p>
        </template>
      </section>

      <form v-if="canCreate" :class="[ui.card, 'space-y-3 p-4']" @submit.prevent="create">
        <h3 class="text-sm font-black text-slate-900 dark:text-white">Request a receiving facility</h3>
        <div>
          <label :class="ui.label">Facility</label>
          <select v-model="form.medical_facility_id" :class="ui.input" required>
            <option value="">Select facility</option>
            <option v-for="f in facilities" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
        </div>
        <div><label :class="ui.label">ETA (minutes)</label><input v-model="form.eta_minutes" inputmode="numeric" :class="ui.input" /></div>
        <div><label :class="ui.label">Notes for the facility</label><textarea v-model="form.notes" rows="3" :class="ui.input" placeholder="Patient condition, special needs…"></textarea></div>
        <button type="submit" :disabled="saving || !form.medical_facility_id" :class="ui.primaryButton"><Icon :icon="saving ? 'lucide:loader-circle' : 'lucide:send'" width="18" :class="saving ? 'animate-spin' : ''" /> Request handoff</button>
      </form>
      <p v-else-if="mission && !handoffs.some((h) => ['pending', 'accepted'].includes(h.status))" :class="[ui.card, 'p-4 text-sm text-slate-500 dark:text-white/45']">A facility handoff can be requested once the crew is on scene or transporting.</p>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ScreenHeader from '@/components/ScreenHeader.vue'
import * as ui from '@/components/ui/styles'
import { apiMessage } from '@/services/api'
import { fetchOrganizationMissions } from '@/services/org/dispatchOperations'
import { createHandoff, fetchHandoffs, fetchReceivingFacilities, updateHandoffStatus } from '@/services/org/rescueOperations'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'

const route = useRoute()
const toast = useToast()
const alert = useAlert()
const id = computed(() => Number(route.params.id))
const mission = ref(null)
const facilities = ref([])
const handoffs = ref([])
// Confirming the physical handoff is Dispatcher-only (handoffs_list.php can_confirm);
// recording facility accepted / declined follows RBAC (can_record).
const canConfirm = ref(false)
const canRecord = ref(false)
const saving = ref(false)
const form = ref({ medical_facility_id: '', eta_minutes: 15, notes: '' })

const canCreate = computed(() => mission.value && ['on_scene', 'transporting'].includes(mission.value.assignment_status) && !handoffs.value.some((h) => ['pending', 'accepted'].includes(h.status)))
const badge = (s) => ({ pending: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300', accepted: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300', declined: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300', completed: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' }[s] || 'bg-slate-100 text-slate-500')
const formatDate = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '')

async function loadHandoffs() {
  try { const d = await fetchHandoffs(id.value); handoffs.value = d.handoffs || []; canConfirm.value = !!d.can_confirm; canRecord.value = !!d.can_record } catch (err) { toast.error(apiMessage(err, 'Unable to load handoffs.'), 'Error') }
}
async function create() {
  saving.value = true
  try {
    const d = await createHandoff({ assignment_id: id.value, medical_facility_id: Number(form.value.medical_facility_id), eta_minutes: Number(form.value.eta_minutes || 0), notes: form.value.notes })
    toast.success(d.message || 'Handoff requested.', 'Requested')
    form.value = { medical_facility_id: '', eta_minutes: 15, notes: '' }
    await loadHandoffs()
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to request the handoff.'), 'Not requested')
  } finally {
    saving.value = false
  }
}
async function change(h, status) {
  const words = { accepted: 'Mark this handoff as accepted by the facility?', declined: 'Mark this handoff as declined?', completed: 'Confirm the patient was handed over to the facility?' }
  if (!(await alert.confirm(words[status], 'Update handoff'))) return
  try {
    const d = await updateHandoffStatus(h.id, status)
    toast.success(d.message || 'Handoff updated.', 'Updated')
    await loadHandoffs()
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to update the handoff.'), 'Error')
  }
}
onMounted(async () => {
  const [m, f] = await Promise.all([fetchOrganizationMissions(), fetchReceivingFacilities().catch(() => ({}))])
  mission.value = (m.missions || []).find((x) => x.id === id.value) || null
  facilities.value = f.facilities || []
  await loadHandoffs()
})
</script>
