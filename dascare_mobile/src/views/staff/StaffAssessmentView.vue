<template>
  <!-- Patient assessment for one mission (staff, Phase 4) — the web's
       Patient Assessments: earlier assessments + a new one (patient, level of
       consciousness, breathing, vitals, injuries, summary). -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader :title="`Assessment · ${mission?.reference_number || ''}`" :fallback="`/staff/missions/${id}`" />
    <main class="space-y-4 px-4 pt-3">
      <section v-for="a in assessments" :key="a.id" :class="[ui.card, 'p-4']">
        <div class="flex items-start justify-between gap-2">
          <p class="text-sm font-black text-slate-900 dark:text-white">{{ a.patient_name || 'Unnamed patient' }}</p>
          <span class="text-[0.65rem] text-slate-400">{{ formatDate(a.assessed_at) }}</span>
        </div>
        <p class="mt-1 text-xs capitalize text-slate-500 dark:text-white/45">{{ pretty(a.consciousness) }}<template v-if="a.recorded_by"> · by {{ a.recorded_by }}</template></p>
        <p v-if="a.condition_summary" class="mt-2 text-sm text-slate-700 dark:text-white/70">{{ a.condition_summary }}</p>
        <p v-if="a.injuries" class="mt-1 text-xs text-slate-500 dark:text-white/45">Injuries: {{ a.injuries }}</p>
        <div v-if="vitals(a).length" class="mt-2 flex flex-wrap gap-1.5">
          <span v-for="v in vitals(a)" :key="v" class="rounded-full bg-base-200 px-2 py-0.5 text-[0.65rem] font-bold text-slate-600 dark:bg-white/5 dark:text-white/55">{{ v }}</span>
        </div>
      </section>

      <form :class="[ui.card, 'space-y-3 p-4']" @submit.prevent="save">
        <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ assessments.length ? 'Add another assessment' : 'New assessment' }}</h3>
        <div><label :class="ui.label">Patient name</label><input v-model="form.patient_name" :class="ui.input" placeholder="If known" /></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label :class="ui.label">Approx. age</label><input v-model="form.approximate_age" inputmode="numeric" :class="ui.input" placeholder="e.g. 65" /></div>
          <div><label :class="ui.label">Sex</label><select v-model="form.sex" :class="ui.input"><option value="unknown">Unknown</option><option value="male">Male</option><option value="female">Female</option></select></div>
        </div>
        <div>
          <label :class="ui.label">Level of consciousness</label>
          <div class="flex flex-wrap gap-1.5">
            <button v-for="c in CONSCIOUSNESS" :key="c.value" type="button" class="rounded-full border px-3 py-1.5 text-xs font-bold"
              :class="form.consciousness === c.value ? 'border-red-600 bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'border-base-300 text-slate-600 dark:border-white/10 dark:text-white/60'"
              @click="form.consciousness = c.value">{{ c.label }}</button>
          </div>
        </div>
        <div><label :class="ui.label">Breathing</label><input v-model="form.breathing_status" :class="ui.input" placeholder="e.g. Normal, laboured, not breathing" /></div>
        <div>
          <label :class="ui.label">Vital signs</label>
          <div class="grid grid-cols-2 gap-2">
            <input v-model="form.vital_signs.pulse_bpm" inputmode="numeric" :class="ui.input" placeholder="Pulse (bpm)" />
            <input v-model="form.vital_signs.respiratory_rate" inputmode="numeric" :class="ui.input" placeholder="Resp. rate" />
            <input v-model="form.vital_signs.blood_pressure" :class="ui.input" placeholder="BP (120/80)" />
            <input v-model="form.vital_signs.spo2_percent" inputmode="numeric" :class="ui.input" placeholder="SpO₂ %" />
            <input v-model="form.vital_signs.temperature_c" inputmode="decimal" :class="ui.input" placeholder="Temp °C" />
          </div>
        </div>
        <div><label :class="ui.label">Injuries</label><textarea v-model="form.injuries" rows="2" :class="ui.input"></textarea></div>
        <div><label :class="ui.label">Condition summary</label><textarea v-model="form.condition_summary" rows="3" :class="ui.input" required></textarea></div>
        <button type="submit" :disabled="saving" :class="ui.primaryButton"><Icon :icon="saving ? 'lucide:loader-circle' : 'lucide:save'" width="18" :class="saving ? 'animate-spin' : ''" /> Save assessment</button>
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
import { createAssessment, fetchAssessments } from '@/services/org/rescueOperations'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const toast = useToast()
const id = computed(() => Number(route.params.id))
const mission = ref(null)
const assessments = ref([])
const saving = ref(false)
const CONSCIOUSNESS = [
  { value: 'alert', label: 'Alert' }, { value: 'responsive_to_voice', label: 'Responds to voice' },
  { value: 'responsive_to_pain', label: 'Responds to pain' }, { value: 'unresponsive', label: 'Unresponsive' }, { value: 'unknown', label: 'Unknown' },
]
const blank = () => ({ patient_name: '', approximate_age: '', sex: 'unknown', consciousness: 'unknown', breathing_status: '', condition_summary: '', injuries: '', vital_signs: { pulse_bpm: '', respiratory_rate: '', blood_pressure: '', spo2_percent: '', temperature_c: '' } })
const form = ref(blank())
const pretty = (v) => String(v || '').replaceAll('_', ' ')
const formatDate = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '')
const VITAL_LABELS = { pulse_bpm: 'Pulse', respiratory_rate: 'RR', blood_pressure: 'BP', spo2_percent: 'SpO₂', temperature_c: 'Temp' }
const vitals = (a) => Object.entries(a.vital_signs || {}).filter(([, v]) => v !== '' && v != null).map(([k, v]) => `${VITAL_LABELS[k] || k} ${v}`)

async function loadAssessments() {
  try { assessments.value = (await fetchAssessments(mission.value.emergency_request_id)).assessments || [] } catch (err) { toast.error(apiMessage(err, 'Unable to load assessments.'), 'Error') }
}
async function save() {
  saving.value = true
  try {
    const d = await createAssessment({ ...form.value, assignment_id: id.value })
    toast.success(d.message || 'Assessment saved.', 'Saved')
    form.value = blank()
    await loadAssessments()
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to save the assessment.'), 'Not saved')
  } finally {
    saving.value = false
  }
}
onMounted(async () => {
  const d = await fetchOrganizationMissions()
  mission.value = (d.missions || []).find((m) => m.id === id.value) || null
  if (mission.value) await loadAssessments()
})
</script>
