<template>
  <!-- Incident Records (staff, Phase 4) — the web's Incident Records: every
       incident this organization handled, search/filter, details, edit the
       requester details (incidents.emergency_requests.update) and review /
       unlink duplicate reports linked to an incident. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader title="Incident Records" fallback="/staff/more" />
    <main class="space-y-3 px-4 pt-3">
      <div class="grid grid-cols-3 gap-2">
        <div v-for="s in statTiles" :key="s.label" :class="[ui.card, 'p-3 text-center']">
          <p class="text-xl font-black text-slate-950 dark:text-white">{{ s.value }}</p>
          <p class="text-[0.65rem] font-semibold text-slate-500 dark:text-white/45">{{ s.label }}</p>
        </div>
      </div>
      <div class="relative">
        <Icon icon="lucide:search" width="18" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input v-model="search" :class="[ui.input, 'pl-10']" placeholder="Reference, name, barangay…" />
      </div>
      <div class="flex gap-1.5">
        <button v-for="f in FILTERS" :key="f.value" type="button" class="rounded-full border px-3 py-1.5 text-xs font-bold"
          :class="filter === f.value ? 'border-red-600 bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'border-base-300 text-slate-600 dark:border-white/10 dark:text-white/60'" @click="filter = f.value">{{ f.label }}</button>
      </div>

      <div v-if="loading" class="space-y-2"><div v-for="n in 3" :key="n" :class="[ui.card, 'h-20 animate-pulse']"></div></div>
      <article v-for="i in shown" :key="i.id" :class="[ui.card, 'overflow-hidden']">
        <button type="button" class="tap block w-full p-4 text-left" @click="openId = openId === i.id ? null : i.id">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="font-mono text-xs font-bold text-slate-500 dark:text-white/45">{{ i.reference_number }}<template v-if="i.unit_code"> · {{ i.unit_code }}</template></p>
              <p class="mt-0.5 truncate text-sm font-black text-slate-900 dark:text-white">{{ i.category_name }} · <span class="capitalize">{{ i.severity }}</span></p>
              <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-white/45">{{ i.barangay || '—' }} · {{ format(i.submitted_at) }}</p>
            </div>
            <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[0.6rem] font-black uppercase" :class="isClosed(i.status) ? 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-white/45' : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'">{{ pretty(i.status) }}</span>
          </div>
          <p v-if="i.linked_count" class="mt-2 inline-flex rounded-full bg-red-50 px-2 py-0.5 text-[0.62rem] font-black text-red-600 dark:bg-red-500/10 dark:text-red-300">+{{ i.linked_count }} linked</p>
        </button>
        <div v-if="openId === i.id" class="space-y-2 border-t border-base-300 p-4 text-sm dark:border-white/10">
          <p class="selectable text-slate-700 dark:text-white/70">{{ i.address_text }}<template v-if="i.landmark"> · near {{ i.landmark }}</template></p>
          <p v-if="i.description" class="selectable whitespace-pre-line text-slate-600 dark:text-white/60">{{ i.description }}</p>
          <p class="text-xs text-slate-500 dark:text-white/45">Requester: <b>{{ i.requester_name || 'Guest requester' }}</b><template v-if="i.requester_phone"> · <a :href="`tel:${i.requester_phone}`" class="font-bold text-emerald-600">{{ i.requester_phone }}</a></template></p>
          <p v-if="i.completed_at" class="text-xs text-slate-500 dark:text-white/45">Completed {{ format(i.completed_at) }}</p>
          <div class="flex flex-wrap gap-2 pt-1">
            <button v-if="canUpdate && !isClosed(i.status)" type="button" class="tap rounded-xl bg-base-200 px-3 py-2 text-xs font-bold text-slate-700 dark:bg-white/5 dark:text-white/70" @click="openEdit(i)"><Icon icon="lucide:pencil" width="13" class="mr-1 inline" />Edit details</button>
            <button v-if="i.linked_count" type="button" class="tap rounded-xl bg-base-200 px-3 py-2 text-xs font-bold text-slate-700 dark:bg-white/5 dark:text-white/70" @click="loadLinked(i)"><Icon icon="lucide:git-merge" width="13" class="mr-1 inline" />Linked reports</button>
          </div>
          <div v-if="linked[i.id]" class="space-y-2 pt-1">
            <div v-for="r in linked[i.id]" :key="r.id" class="rounded-xl bg-base-200 p-3 dark:bg-white/5">
              <p class="font-mono text-xs font-bold text-slate-700 dark:text-white/70">{{ r.reference_number }} <span class="font-sans font-normal text-slate-500">· {{ r.distance_m }} m · {{ Math.round((r.gap_seconds || 0) / 60) }} min later</span></p>
              <p v-if="(r.warnings || []).length" class="mt-1 text-[0.7rem] text-amber-700 dark:text-amber-300">{{ r.warnings.map((w) => w.label || w).join(' · ') }}</p>
              <button v-if="canUpdate && !isClosed(i.status)" type="button" class="mt-2 text-xs font-bold text-red-600 dark:text-red-300" @click="unlink(i, r)">Unlink — different emergency</button>
            </div>
          </div>
        </div>
      </article>
      <p v-if="!loading && !shown.length" class="py-10 text-center text-sm text-slate-500">No incidents match.</p>
    </main>

    <!-- Edit details sheet -->
    <Teleport to="body">
      <div v-if="editOpen" class="fixed inset-0 z-[10040] flex items-end bg-black/50" @click.self="editOpen = false">
        <form class="max-h-[90vh] w-full space-y-3 overflow-y-auto rounded-t-[28px] bg-base-100 p-6 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#0c1a2c]" @submit.prevent="save">
          <h2 class="text-lg font-black text-slate-900 dark:text-white">Edit {{ form.reference_number }}</h2>
          <div><label :class="ui.label">Requester name</label><input v-model="form.requester_name" :class="ui.input" /></div>
          <div><label :class="ui.label">Mobile number</label><input v-model="form.requester_phone" inputmode="tel" :class="ui.input" placeholder="09XXXXXXXXX" /><p v-if="phoneInvalid" class="mt-1 text-xs text-red-500">Enter a valid PH mobile number</p></div>
          <div><label :class="ui.label">Barangay</label><input v-model="form.barangay" :class="ui.input" /></div>
          <div><label :class="ui.label">Address</label><input v-model="form.address_text" :class="ui.input" /></div>
          <div><label :class="ui.label">Landmark</label><input v-model="form.landmark" :class="ui.input" /></div>
          <div><label :class="ui.label">Description</label><textarea v-model="form.description" rows="3" :class="ui.input"></textarea></div>
          <div class="grid grid-cols-2 gap-2">
            <button type="button" :class="ui.secondaryButton" @click="editOpen = false">Cancel</button>
            <button type="submit" :disabled="saving || phoneInvalid" :class="ui.primaryButton">Save</button>
          </div>
        </form>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import ScreenHeader from '@/components/ScreenHeader.vue'
import * as ui from '@/components/ui/styles'
import { apiMessage } from '@/services/api'
import { fetchOrganizationIncidents, updateIncidentDetails } from '@/services/org/organizationCompletion'
import { fetchOrganizationLinkedReports, unmergeOrganizationReport } from '@/services/org/dispatchOperations'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'

const { hasPermission } = useSession()
const toast = useToast()
const alert = useAlert()
const items = ref([])
const stats = ref({})
const loading = ref(true)
const search = ref('')
const filter = ref('all')
const openId = ref(null)
const linked = ref({})
const FILTERS = [{ value: 'all', label: 'All' }, { value: 'active', label: 'Active' }, { value: 'closed', label: 'Closed' }]
const canUpdate = computed(() => hasPermission('incidents.emergency_requests.update'))
const statTiles = computed(() => [{ label: 'Total', value: stats.value.total ?? 0 }, { label: 'Active', value: stats.value.active ?? 0 }, { label: 'Completed', value: stats.value.completed ?? 0 }])
const isClosed = (s) => ['completed', 'cancelled', 'rejected', 'duplicate', 'false_alarm'].includes(s)
const pretty = (v) => String(v || '').replaceAll('_', ' ')
const format = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—')
const shown = computed(() => {
  const q = search.value.trim().toLowerCase()
  return items.value.filter((i) => (filter.value === 'all' || (filter.value === 'closed') === isClosed(i.status))
    && (!q || [i.reference_number, i.requester_name, i.barangay, i.address_text].some((v) => String(v || '').toLowerCase().includes(q))))
})

// Placeholders create.php fills in — shown blank in the editor.
const PLACEHOLDERS = ['Unspecified', 'Pinned location (no address provided)', 'EMERGENCY REQUEST. No details provided — confirm with requester on contact.']
const unplaceholder = (v) => (PLACEHOLDERS.includes((v || '').trim()) ? '' : (v || ''))
const editOpen = ref(false)
const saving = ref(false)
const form = reactive({ id: null, reference_number: '', requester_name: '', requester_phone: '', barangay: '', address_text: '', landmark: '', description: '' })
const phoneInvalid = computed(() => { const p = (form.requester_phone || '').replace(/[\s-]/g, ''); return p !== '' && !/^(\+639\d{9}|09\d{9})$/.test(p) })
function openEdit(i) {
  Object.assign(form, { id: i.id, reference_number: i.reference_number, requester_name: i.requester_name === 'Guest requester' ? '' : (i.requester_name || ''), requester_phone: i.requester_phone || '', barangay: unplaceholder(i.barangay), address_text: unplaceholder(i.address_text), landmark: i.landmark || '', description: unplaceholder(i.description) })
  editOpen.value = true
}
async function save() {
  saving.value = true
  try {
    const { id, reference_number, ...fields } = form
    await updateIncidentDetails({ emergency_request_id: id, ...fields })
    toast.success('Incident details updated.', 'Saved')
    editOpen.value = false
    await load()
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to update the incident.'), 'Not saved')
  } finally {
    saving.value = false
  }
}
async function loadLinked(i) {
  try { linked.value = { ...linked.value, [i.id]: await fetchOrganizationLinkedReports(i.id) } } catch (err) { toast.error(apiMessage(err, 'Unable to load linked reports.'), 'Error') }
}
async function unlink(i, r) {
  if (!(await alert.confirm(`Unlink ${r.reference_number}? It becomes its own incident and gets its own unit.`, 'Different emergency'))) return
  try {
    const d = await unmergeOrganizationReport(r.id, 'Unlinked from the staff app.')
    toast.success(d.message || 'Report unlinked.', 'Unlinked')
    await Promise.all([load(), loadLinked(i)])
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to unlink the report.'), 'Error')
  }
}
async function load() {
  try {
    const d = await fetchOrganizationIncidents()
    items.value = d.items || []
    stats.value = d.stats || {}
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to load incidents.'), 'Error')
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>
