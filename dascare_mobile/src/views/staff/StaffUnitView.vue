<template>
  <!-- One ambulance (staff, Phase 4) — the web's Fleet detail + Readiness:
       status (Fleet's own gates still apply on the server), readiness check,
       maintenance records, history, edit, archive. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader :title="unit?.unit_code || 'Ambulance'" fallback="/staff/fleet">
      <template #actions>
        <RouterLink v-if="unit && canUpdate" :to="{ name: 'StaffUnitForm', query: { id: unit.id } }" class="tap grid h-11 w-11 place-items-center rounded-xl text-slate-600 dark:text-white/70" aria-label="Edit"><Icon icon="lucide:pencil" width="20" /></RouterLink>
      </template>
    </ScreenHeader>

    <div v-if="!unit" class="space-y-3 px-4 pt-4"><div v-for="n in 3" :key="n" :class="[ui.card, 'h-24 animate-pulse']"></div></div>
    <main v-else class="space-y-3 px-4 pt-3">
      <section :class="[ui.card, 'p-4']">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-lg font-black text-slate-900 dark:text-white">{{ unit.unit_code }} <span class="text-sm font-normal text-slate-500">· {{ unit.plate_number }}</span></p>
            <p class="text-xs text-slate-500 dark:text-white/45">{{ typeLabel(unit.ambulance_type) }}<template v-if="unit.vehicle_make_model"> · {{ unit.vehicle_make_model }} {{ unit.model_year || '' }}</template> · {{ unit.capacity }} patient{{ unit.capacity == 1 ? '' : 's' }}</p>
          </div>
          <span class="flex-shrink-0 rounded-full px-2.5 py-1 text-[0.62rem] font-bold" :class="statusPill(unit.status)">{{ statusLabel(unit.status) }}</span>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
          <div class="rounded-xl bg-base-200 p-2.5 dark:bg-white/5"><p class="text-[0.6rem] font-black uppercase text-slate-400">Registration</p><p class="mt-0.5 font-bold" :class="expired(unit.registration_expiry) ? 'text-red-600 dark:text-red-300' : 'text-slate-700 dark:text-white/70'">{{ formatDay(unit.registration_expiry) }}</p></div>
          <div class="rounded-xl bg-base-200 p-2.5 dark:bg-white/5"><p class="text-[0.6rem] font-black uppercase text-slate-400">Inspection</p><p class="mt-0.5 font-bold" :class="expired(unit.inspection_expiry) ? 'text-red-600 dark:text-red-300' : 'text-slate-700 dark:text-white/70'">{{ formatDay(unit.inspection_expiry) }}</p></div>
        </div>
        <p v-if="unit.capability_notes" class="mt-2 text-xs text-slate-500 dark:text-white/45">{{ unit.capability_notes }}</p>
        <button v-if="canStatus" type="button" :class="[ui.secondaryButton, 'mt-3']" @click="openStatus"><Icon icon="lucide:power" width="16" /> Change status</button>
      </section>

      <!-- Readiness -->
      <section :class="[ui.card, 'p-4']">
        <div class="flex items-center justify-between gap-2">
          <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 dark:text-white/35">Readiness</h3>
          <span v-if="latestCheck" class="rounded-full px-2 py-0.5 text-[0.62rem] font-bold" :class="readinessPill(latestCheck.overall_status)">{{ readinessLabel(latestCheck.overall_status) }}</span>
        </div>
        <p v-if="latestCheck" class="mt-1 text-xs text-slate-500 dark:text-white/45">Last check {{ formatWhen(latestCheck.checked_at) }}<template v-if="latestCheck.checked_by_name"> by {{ latestCheck.checked_by_name }}</template> · fuel {{ latestCheck.fuel_level }}<template v-if="failedItems(latestCheck).length"> · <span class="font-bold text-amber-600 dark:text-amber-300">{{ failedItems(latestCheck).join(', ') }}</span></template></p>
        <p v-else class="mt-1 text-xs text-slate-500">No readiness check yet — a unit needs a "ready" check before dispatch can offer it.</p>
        <button v-if="canReadiness" type="button" :class="[ui.primaryButton, 'mt-3']" @click="openCheck"><Icon icon="lucide:clipboard-check" width="18" /> New readiness check</button>
      </section>

      <!-- Maintenance -->
      <section :class="[ui.card, 'p-4']">
        <div class="flex items-center justify-between gap-2">
          <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 dark:text-white/35">Maintenance</h3>
          <button v-if="canMaintCreate" type="button" class="text-xs font-bold text-red-600 dark:text-red-300" @click="openMaint">+ Add record</button>
        </div>
        <div v-for="m in detail.maintenance_records" :key="m.id" class="mt-3 rounded-xl bg-base-200 p-3 dark:bg-white/5">
          <div class="flex items-start justify-between gap-2">
            <p class="text-sm font-bold text-slate-800 dark:text-white/80">{{ maintenanceTypeLabel(m.maintenance_type) }}</p>
            <span class="text-[0.65rem] font-black uppercase text-slate-500">{{ maintenanceStatusLabel(m.status) }}</span>
          </div>
          <p class="text-xs text-slate-500 dark:text-white/45">{{ m.scheduled_for ? `Scheduled ${formatDay(m.scheduled_for)}` : '' }}<template v-if="m.provider"> · {{ m.provider }}</template><template v-if="m.cost"> · ₱{{ m.cost }}</template></p>
          <p v-if="m.notes" class="mt-1 text-xs text-slate-600 dark:text-white/60">{{ m.notes }}</p>
          <div v-if="canMaintUpdate && ['scheduled', 'in_progress'].includes(m.status)" class="mt-2 flex flex-wrap gap-2">
            <button v-if="m.status === 'scheduled'" type="button" class="rounded-lg bg-base-100 px-2.5 py-1.5 text-xs font-bold text-slate-700 dark:bg-white/10 dark:text-white/70" @click="setMaint(m, 'in_progress')">Start</button>
            <button type="button" class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-bold text-white" @click="setMaint(m, 'completed')">Complete</button>
            <button type="button" class="rounded-lg bg-base-100 px-2.5 py-1.5 text-xs font-bold text-red-600 dark:bg-white/10 dark:text-red-300" @click="setMaint(m, 'cancelled')">Cancel</button>
          </div>
        </div>
        <p v-if="!detail.maintenance_records?.length" class="mt-1 text-xs text-slate-500">No maintenance records.</p>
      </section>

      <!-- History -->
      <section :class="[ui.card, 'p-4']">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 dark:text-white/35">Status history</h3>
        <ul class="mt-2 space-y-2">
          <li v-for="h in (detail.status_history || []).slice(0, historyLimit)" :key="h.id" class="text-xs">
            <p class="font-semibold text-slate-700 dark:text-white/70">{{ statusLabel(h.old_status) }} → {{ statusLabel(h.new_status) }} <span class="font-normal text-slate-400">· {{ formatWhen(h.created_at) }}</span></p>
            <p v-if="h.reason || h.changed_by_name" class="text-slate-500 dark:text-white/45">{{ h.reason }}<template v-if="h.changed_by_name?.trim()"> — {{ h.changed_by_name }}</template></p>
          </li>
        </ul>
        <button v-if="(detail.status_history || []).length > historyLimit" type="button" class="mt-2 text-xs font-bold text-red-600 dark:text-red-300" @click="historyLimit += 10">Show more</button>
      </section>

      <button v-if="canDelete" type="button" :class="[ui.secondaryButton, 'border-red-200 text-red-600 dark:border-red-500/25 dark:text-red-300']" @click="archive"><Icon icon="lucide:archive" width="16" /> Archive ambulance</button>
    </main>

    <!-- Sheets -->
    <Teleport to="body">
      <div v-if="sheet" class="fixed inset-0 z-[10040] flex items-end bg-black/50" @click.self="sheet = null">
        <form class="max-h-[90vh] w-full space-y-3 overflow-y-auto rounded-t-[28px] bg-base-100 p-6 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#0c1a2c]" @submit.prevent="submitSheet">
          <template v-if="sheet === 'status'">
            <h2 class="text-lg font-black text-slate-900 dark:text-white">Change {{ unit.unit_code }} status</h2>
            <div class="grid grid-cols-3 gap-2">
              <button v-for="s in ['available', 'maintenance', 'offline']" :key="s" type="button" class="rounded-xl border-2 py-2.5 text-xs font-black" :class="statusForm.status === s ? 'border-red-600 bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'border-base-300 text-slate-600 dark:border-white/10 dark:text-white/60'" @click="statusForm.status = s">{{ statusLabel(s) }}</button>
            </div>
            <div><label :class="ui.label">Reason</label><input v-model="statusForm.reason" :class="ui.input" placeholder="Optional" /></div>
            <p class="text-xs text-slate-500">Setting "Available" needs a ready check, valid registration/inspection and no maintenance in progress.</p>
          </template>
          <template v-else-if="sheet === 'check'">
            <h2 class="text-lg font-black text-slate-900 dark:text-white">Readiness check · {{ unit.unit_code }}</h2>
            <div>
              <label :class="ui.label">Fuel</label>
              <div class="grid grid-cols-3 gap-2"><button v-for="f in ['full', 'adequate', 'low']" :key="f" type="button" class="rounded-xl border-2 py-2.5 text-xs font-black capitalize" :class="check.fuel_level === f ? 'border-red-600 bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'border-base-300 text-slate-600 dark:border-white/10 dark:text-white/60'" @click="check.fuel_level = f">{{ f }}</button></div>
            </div>
            <label v-for="item in CHECK_ITEMS" :key="item.key" class="flex items-center gap-3 rounded-2xl border border-base-300 p-3 dark:border-white/10">
              <Icon :icon="item.icon" width="17" class="text-slate-400" />
              <span class="flex-1 text-sm font-semibold text-slate-700 dark:text-white/70">{{ item.label }}</span>
              <input v-model="check[item.key]" type="checkbox" class="h-5 w-5 accent-red-600" />
            </label>
            <label class="flex items-center gap-3 rounded-2xl border border-red-200 p-3 dark:border-red-500/25">
              <Icon icon="lucide:octagon-alert" width="17" class="text-red-500" />
              <span class="flex-1 text-sm font-semibold text-red-700 dark:text-red-300">Critical issue (out of service)</span>
              <input v-model="check.critical_issue" type="checkbox" class="h-5 w-5 accent-red-600" />
            </label>
            <div><label :class="ui.label">Notes</label><textarea v-model="check.notes" rows="2" :class="ui.input"></textarea></div>
          </template>
          <template v-else-if="sheet === 'maint'">
            <h2 class="text-lg font-black text-slate-900 dark:text-white">Maintenance · {{ unit.unit_code }}</h2>
            <div><label :class="ui.label">Type</label><select v-model="maint.maintenance_type" :class="ui.input"><option value="preventive">Preventive</option><option value="repair">Repair</option><option value="inspection">Inspection</option><option value="other">Other</option></select></div>
            <div><label :class="ui.label">Status</label><select v-model="maint.status" :class="ui.input"><option value="scheduled">Scheduled</option><option value="in_progress">In progress</option></select></div>
            <div><label :class="ui.label">Scheduled for</label><input v-model="maint.scheduled_for" type="date" :class="ui.input" /></div>
            <div><label :class="ui.label">Provider</label><input v-model="maint.provider" :class="ui.input" /></div>
            <div><label :class="ui.label">Cost (₱)</label><input v-model="maint.cost" inputmode="decimal" :class="ui.input" /></div>
            <div><label :class="ui.label">Notes</label><textarea v-model="maint.notes" rows="2" :class="ui.input"></textarea></div>
          </template>
          <div class="grid grid-cols-2 gap-2 pt-1">
            <button type="button" :class="ui.secondaryButton" @click="sheet = null">Cancel</button>
            <button type="submit" :disabled="saving" :class="ui.primaryButton"><Icon v-if="saving" icon="lucide:loader-circle" width="18" class="animate-spin" /> Save</button>
          </div>
        </form>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ScreenHeader from '@/components/ScreenHeader.vue'
import * as ui from '@/components/ui/styles'
import { apiMessage } from '@/services/api'
import { archiveFleetUnit, createMaintenanceRecord, fetchFleetUnit, saveReadinessCheck, updateFleetUnitStatus, updateMaintenanceRecord } from '@/services/org/organizationFleet'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'
import { formatDay, formatWhen, maintenanceStatusLabel, maintenanceTypeLabel, readinessLabel, readinessPill, statusLabel, statusPill, typeLabel } from '@/utils/fleetLabels'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const alert = useAlert()
const { hasPermission } = useSession()
const id = computed(() => Number(route.params.id))
const detail = ref({})
const unit = computed(() => detail.value.unit || null)
const latestCheck = computed(() => (detail.value.readiness_checks || [])[0] || null)
const historyLimit = ref(10)
const canUpdate = computed(() => hasPermission('fleet.ambulances.update'))
const canDelete = computed(() => hasPermission('fleet.ambulances.delete'))
const canStatus = computed(() => hasPermission('fleet.ambulance_status.update'))
const canReadiness = computed(() => hasPermission('fleet.ambulance_readiness.update'))
const canMaintCreate = computed(() => hasPermission('fleet.maintenance_records.create'))
const canMaintUpdate = computed(() => hasPermission('fleet.maintenance_records.update'))

const CHECK_ITEMS = [
  { key: 'oxygen_ready', label: 'Oxygen', icon: 'lucide:wind' }, { key: 'medical_supplies_ready', label: 'Medical supplies', icon: 'lucide:briefcase-medical' },
  { key: 'lights_siren_ready', label: 'Lights & siren', icon: 'lucide:siren' }, { key: 'communications_ready', label: 'Radio / communications', icon: 'lucide:radio' },
  { key: 'stretcher_ready', label: 'Stretcher', icon: 'lucide:bed' }, { key: 'cleanliness_ready', label: 'Clean & disinfected', icon: 'lucide:sparkles' },
]
const failedItems = (c) => CHECK_ITEMS.filter((i) => !Number(c[i.key])).map((i) => i.label)
const expired = (d) => d && new Date(`${String(d).slice(0, 10)}T23:59:59`) < new Date()

const sheet = ref(null)
const saving = ref(false)
const statusForm = ref({ status: 'available', reason: '' })
const check = ref({})
const maint = ref({})
function openStatus() { statusForm.value = { status: unit.value.status === 'available' ? 'available' : 'offline', reason: '' }; sheet.value = 'status' }
function openCheck() { check.value = { fuel_level: 'adequate', oxygen_ready: true, medical_supplies_ready: true, lights_siren_ready: true, communications_ready: true, stretcher_ready: true, cleanliness_ready: true, critical_issue: false, notes: '' }; sheet.value = 'check' }
function openMaint() { maint.value = { maintenance_type: 'preventive', status: 'scheduled', scheduled_for: '', provider: '', cost: '', notes: '' }; sheet.value = 'maint' }

async function submitSheet() {
  saving.value = true
  try {
    let r
    if (sheet.value === 'status') r = await updateFleetUnitStatus(id.value, statusForm.value.status, statusForm.value.reason)
    else if (sheet.value === 'check') r = await saveReadinessCheck({ ambulance_id: id.value, ...check.value })
    else r = await createMaintenanceRecord({ ambulance_id: id.value, ...maint.value })
    toast.success(r.message || 'Saved.', 'Saved')
    sheet.value = null
    await load()
  } catch (err) {
    toast.error(apiMessage(err, 'Could not save.'), 'Not saved')
  } finally {
    saving.value = false
  }
}
async function setMaint(m, status) {
  try {
    const r = await updateMaintenanceRecord({ id: m.id, status, notes: m.notes || '' })
    toast.success(r.message || 'Maintenance updated.', 'Updated')
    await load()
  } catch (err) {
    toast.error(apiMessage(err, 'Could not update the record.'), 'Error')
  }
}
async function archive() {
  if (!(await alert.confirm(`Archive ${unit.value.unit_code} from the active fleet? Its history is kept.`, 'Archive ambulance'))) return
  try {
    const r = await archiveFleetUnit(id.value, 'Archived from the staff app.')
    toast.success(r.message || 'Ambulance archived.', 'Archived')
    router.replace({ name: 'StaffFleet' })
  } catch (err) {
    toast.error(apiMessage(err, 'Could not archive this ambulance.'), 'Error')
  }
}
async function load() {
  try { detail.value = await fetchFleetUnit(id.value) } catch (err) { toast.error(apiMessage(err, 'Could not load the ambulance.'), 'Error') }
}
onMounted(load)
</script>
