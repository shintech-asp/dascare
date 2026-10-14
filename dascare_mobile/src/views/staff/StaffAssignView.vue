<template>
  <!-- Resource assignment (staff, Phase 4): the web's Resource Assignment —
       pick an accepted incident, a ready ambulance (DSS suggestion first) and
       the crew (exactly one driver + at least one more), then create the
       mission. Same endpoints and rules (assignments/list.php, create.php). -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+7rem)] dark:bg-[#050e1a]">
    <ScreenHeader title="Assign unit & crew" fallback="/staff/home" />

    <div v-if="loading" class="space-y-3 px-4 pt-4"><div v-for="n in 3" :key="n" :class="[ui.card, 'h-24 animate-pulse']"></div></div>

    <main v-else class="space-y-4 px-4 pt-3">
      <div v-if="!incidents.length" class="rounded-3xl border border-dashed border-slate-300 py-14 text-center dark:border-white/15">
        <Icon icon="lucide:git-pull-request-arrow" width="28" class="mx-auto mb-3 text-slate-300 dark:text-white/20" />
        <p class="text-sm font-semibold text-slate-500 dark:text-white/40">Nothing waiting for a unit</p>
        <p class="mt-1 text-xs text-slate-400">Accepted incidents appear here until a unit and crew are assigned.</p>
      </div>

      <template v-else>
        <!-- Incident -->
        <section>
          <p class="px-2 pb-2 text-[0.68rem] font-black uppercase tracking-[0.16em] text-slate-400 dark:text-white/35">Incident</p>
          <div class="space-y-2">
            <button v-for="i in incidents" :key="i.id" type="button" :class="[ui.card, 'tap block w-full p-4 text-left', selected?.id === i.id ? 'border-red-400 ring-2 ring-red-500/20 dark:border-red-500/40' : '']" @click="selectIncident(i)">
              <p class="font-mono text-xs font-bold text-slate-500 dark:text-white/45">{{ i.reference_number }}</p>
              <p class="mt-0.5 text-sm font-black text-slate-900 dark:text-white">{{ i.category_name || 'Emergency' }} · <span class="capitalize">{{ i.severity }}</span></p>
              <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-white/45">{{ i.address_text }}<template v-if="i.barangay">, {{ i.barangay }}</template></p>
            </button>
          </div>
        </section>

        <!-- Ambulance -->
        <section>
          <p class="px-2 pb-2 text-[0.68rem] font-black uppercase tracking-[0.16em] text-slate-400 dark:text-white/35">Ambulance</p>
          <div v-if="ambulances.length" :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
            <button v-for="a in ambulances" :key="a.id" type="button" class="tap flex w-full items-center gap-3 p-4 text-left" @click="ambulanceId = a.id">
              <span class="grid h-6 w-6 flex-shrink-0 place-items-center rounded-full border-2" :class="ambulanceId === a.id ? 'border-red-600 bg-red-600 text-white' : 'border-slate-300 dark:border-white/20'"><Icon v-if="ambulanceId === a.id" icon="lucide:check" width="14" /></span>
              <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-slate-900 dark:text-white">{{ a.unit_code }} <span class="font-normal text-slate-500">· {{ a.plate_number }}</span></span>
                <span class="block text-xs capitalize text-slate-500 dark:text-white/45">{{ String(a.ambulance_type || '').replaceAll('_', ' ') }} · readiness {{ a.readiness_status }}</span>
              </span>
              <span v-if="selected && a.id === selected.recommended_ambulance_id" class="rounded-full bg-emerald-50 px-2 py-0.5 text-[0.6rem] font-black uppercase text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">DSS pick</span>
            </button>
          </div>
          <p v-else :class="[ui.card, 'p-4 text-sm text-amber-700 dark:text-amber-300']">No ambulance is ready (available + ready check + valid registration/inspection). Fix it in Fleet first.</p>
        </section>

        <!-- Crew -->
        <section>
          <p class="px-2 pb-2 text-[0.68rem] font-black uppercase tracking-[0.16em] text-slate-400 dark:text-white/35">Crew · {{ selectedCrew.length }} selected</p>
          <div v-if="personnel.length" :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
            <div v-for="p in personnel" :key="p.organization_member_id" class="p-4">
              <button type="button" class="tap flex w-full items-center gap-3 text-left" @click="toggleCrew(p)">
                <span class="grid h-6 w-6 flex-shrink-0 place-items-center rounded-lg border-2" :class="crew[p.organization_member_id] ? 'border-red-600 bg-red-600 text-white' : 'border-slate-300 dark:border-white/20'"><Icon v-if="crew[p.organization_member_id]" icon="lucide:check" width="14" /></span>
                <span class="min-w-0 flex-1">
                  <span class="block text-sm font-bold text-slate-900 dark:text-white">{{ p.first_name }} {{ p.last_name }}</span>
                  <span class="block truncate text-xs text-slate-500 dark:text-white/45">{{ (p.role_names || []).join(' · ') || 'No role' }}</span>
                </span>
              </button>
              <div v-if="crew[p.organization_member_id]" class="mt-3 flex flex-wrap gap-1.5 pl-9">
                <button v-for="r in CREW_ROLES" :key="r.value" type="button" class="rounded-full border px-2.5 py-1 text-[0.7rem] font-bold"
                  :class="crew[p.organization_member_id] === r.value ? 'border-red-600 bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'border-base-300 text-slate-600 dark:border-white/10 dark:text-white/60'"
                  @click="crew = { ...crew, [p.organization_member_id]: r.value }">{{ r.label }}</button>
              </div>
            </div>
          </div>
          <p v-else :class="[ui.card, 'p-4 text-sm text-amber-700 dark:text-amber-300']">No crew is free right now — everyone is off duty or already on another mission. Mark people available in Duty & Availability, or finish their current mission.</p>
          <p class="mt-2 px-2 text-xs" :class="crewHint.ok ? 'text-slate-400' : 'text-amber-600 dark:text-amber-300'">{{ crewHint.text }}</p>
        </section>
      </template>
    </main>

    <!-- Submit bar -->
    <div v-if="incidents.length" class="fixed inset-x-0 bottom-0 z-30 border-t border-base-300 bg-base-100/95 p-4 pb-[calc(var(--safe-bottom)+1rem)] backdrop-blur dark:border-white/10 dark:bg-[#071829]/95">
      <button type="button" :disabled="!canSubmit || saving" :class="ui.primaryButton" @click="assign">
        <Icon :icon="saving ? 'lucide:loader-circle' : 'lucide:send'" width="18" :class="saving ? 'animate-spin' : ''" /> Create mission
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ScreenHeader from '@/components/ScreenHeader.vue'
import * as ui from '@/components/ui/styles'
import { apiMessage } from '@/services/api'
import { createResourceAssignment, fetchResourceAssignments } from '@/services/org/dispatchOperations'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const alert = useAlert()

const CREW_ROLES = [
  { value: 'driver', label: 'Driver' }, { value: 'team_leader', label: 'Team leader' },
  { value: 'paramedic', label: 'Paramedic' }, { value: 'emt', label: 'EMT' }, { value: 'rescuer', label: 'Rescuer' },
]
const loading = ref(true)
const saving = ref(false)
const incidents = ref([])
const ambulances = ref([])
const personnel = ref([])
const selectedId = ref(null)
const ambulanceId = ref(null)
const crew = ref({}) // organization_member_id → crew_role

const selected = computed(() => incidents.value.find((i) => i.id === selectedId.value) || incidents.value[0] || null)
const selectedCrew = computed(() => Object.entries(crew.value).filter(([, r]) => r).map(([id, crew_role]) => ({ organization_member_id: Number(id), crew_role })))
const drivers = computed(() => selectedCrew.value.filter((c) => c.crew_role === 'driver').length)
const canSubmit = computed(() => selected.value && ambulanceId.value && selectedCrew.value.length >= 2 && drivers.value === 1)
const crewHint = computed(() => {
  if (selectedCrew.value.length < 2) return { ok: false, text: 'Pick at least two people: a driver and one more responder.' }
  if (drivers.value !== 1) return { ok: false, text: 'Exactly one crew member must be the driver.' }
  return { ok: true, text: 'Crew is complete.' }
})

function suggestedRole(p) {
  const r = (p.role_names || []).join(' ').toLowerCase()
  if (r.includes('driver') && !selectedCrew.value.some((c) => c.crew_role === 'driver')) return 'driver'
  if (r.includes('medic') || r.includes('emt')) return 'emt'
  if (r.includes('supervisor')) return 'team_leader'
  return 'rescuer'
}
function toggleCrew(p) {
  const next = { ...crew.value }
  if (next[p.organization_member_id]) delete next[p.organization_member_id]
  else next[p.organization_member_id] = suggestedRole(p)
  crew.value = next
}
function selectIncident(i) {
  selectedId.value = i.id
  ambulanceId.value = ambulances.value.some((a) => a.id === i.recommended_ambulance_id) ? i.recommended_ambulance_id : (ambulances.value[0]?.id || null)
}

async function load() {
  loading.value = true
  try {
    const d = await fetchResourceAssignments()
    incidents.value = d.incidents || []
    ambulances.value = d.ambulances || []
    personnel.value = (d.personnel || []).map((p) => ({ ...p, role_names: Array.isArray(p.role_names) ? p.role_names : String(p.role_names || '').split('||').filter(Boolean) }))
    const wanted = Number(route.query.request) || null
    const first = incidents.value.find((i) => i.id === wanted) || incidents.value[0]
    if (first) selectIncident(first)
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to load assignment data.'), 'Error')
  } finally {
    loading.value = false
  }
}

async function assign() {
  if (!(await alert.confirm(`Assign the ambulance and ${selectedCrew.value.length} crew members to ${selected.value.reference_number}?`, 'Create rescue mission'))) return
  saving.value = true
  try {
    const d = await createResourceAssignment({ emergency_request_id: selected.value.id, ambulance_id: ambulanceId.value, crew: selectedCrew.value })
    toast.success(d.message || 'Mission created. The crew has been notified.', 'Mission created')
    router.replace(d.assignment_id ? { name: 'StaffMission', params: { id: d.assignment_id } } : { name: 'StaffMissions' })
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to assign resources.'), 'Not assigned')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
