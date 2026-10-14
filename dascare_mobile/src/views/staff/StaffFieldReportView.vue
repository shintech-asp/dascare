<template>
  <!-- Field report for one mission (staff, Phase 4) — the web's Field
       Reports: summary, actions taken, outcome, destination facility; save
       as draft or submit. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader :title="`Field report · ${mission?.reference_number || ''}`" :fallback="`/staff/missions/${id}`" />
    <main class="space-y-4 px-4 pt-3">
      <p v-if="report" :class="[ui.card, 'p-3 text-xs text-slate-500 dark:text-white/45']">
        Status <b class="uppercase text-slate-700 dark:text-white/70">{{ report.report_status }}</b><template v-if="report.prepared_by"> · by {{ report.prepared_by }}</template><template v-if="report.submitted_at"> · submitted {{ formatDate(report.submitted_at) }}</template>
      </p>
      <form :class="[ui.card, 'space-y-3 p-4']" @submit.prevent="save(true)">
        <div><label :class="ui.label">Incident summary</label><textarea v-model="form.incident_summary" rows="4" :class="ui.input" required></textarea></div>
        <div><label :class="ui.label">Actions taken</label><textarea v-model="form.actions_taken" rows="4" :class="ui.input"></textarea></div>
        <div>
          <label :class="ui.label">Outcome</label>
          <select v-model="form.outcome" :class="ui.input">
            <option value="transported">Transported</option><option value="treated_on_scene">Treated on scene</option>
            <option value="refused_transport">Refused transport</option><option value="no_patient_found">No patient found</option>
            <option value="deceased">Deceased</option><option value="other">Other</option>
          </select>
        </div>
        <div>
          <label :class="ui.label">Destination facility</label>
          <select v-model="form.destination_facility_id" :class="ui.input">
            <option value="">None / not transported</option>
            <option v-for="f in facilities" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <button type="button" :disabled="saving" :class="ui.secondaryButton" @click="save(false)">Save draft</button>
          <button type="submit" :disabled="saving" :class="ui.primaryButton"><Icon v-if="saving" icon="lucide:loader-circle" width="18" class="animate-spin" /> Submit</button>
        </div>
      </form>
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
import { fetchFieldReport, fetchReceivingFacilities, saveFieldReport } from '@/services/org/rescueOperations'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const toast = useToast()
const id = computed(() => Number(route.params.id))
const mission = ref(null)
const facilities = ref([])
const report = ref(null)
const saving = ref(false)
const form = ref({ incident_summary: '', actions_taken: '', outcome: 'other', destination_facility_id: '' })
const formatDate = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '')

async function loadReport() {
  try {
    report.value = (await fetchFieldReport(mission.value.emergency_request_id)).report || null
    form.value = { incident_summary: report.value?.incident_summary || '', actions_taken: report.value?.actions_taken || '', outcome: report.value?.outcome || 'other', destination_facility_id: report.value?.destination_facility_id || '' }
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to load the field report.'), 'Error')
  }
}
async function save(submit) {
  saving.value = true
  try {
    const d = await saveFieldReport({ assignment_id: id.value, ...form.value, destination_facility_id: form.value.destination_facility_id ? Number(form.value.destination_facility_id) : null, submit })
    toast.success(d.message || (submit ? 'Report submitted.' : 'Draft saved.'), submit ? 'Submitted' : 'Saved')
    await loadReport()
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to save the report.'), 'Not saved')
  } finally {
    saving.value = false
  }
}
onMounted(async () => {
  const [m, f] = await Promise.all([fetchOrganizationMissions(), fetchReceivingFacilities().catch(() => ({}))])
  mission.value = (m.missions || []).find((x) => x.id === id.value) || null
  facilities.value = f.facilities || []
  if (mission.value) await loadReport()
})
</script>
