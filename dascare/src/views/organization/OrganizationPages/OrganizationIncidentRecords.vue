<template>
  <PageShell title="Incident Records" subtitle="Incidents offered to or handled by this organization. Fill in requester details here once your crew makes contact." icon="lucide:clipboard-list" eyebrow="Organization Operations">
    <div class="grid gap-3 sm:grid-cols-3">
      <Stat label="Incident records" :value="stats.total"/>
      <Stat label="Active" :value="stats.active"/>
      <Stat label="Completed" :value="stats.completed"/>
    </div>

    <section class="mt-6 overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#071829]">
      <div v-if="loading" class="py-20 text-center text-sm text-slate-400">Loading incidents...</div>
      <div v-else-if="!items.length" class="py-20 text-center text-sm text-slate-400">No incident records are connected to this organization yet.</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[980px] text-left text-sm">
          <thead class="bg-base-200/70 text-[.65rem] font-black uppercase text-slate-400">
            <tr>
              <th class="px-5 py-3">Reference</th>
              <th class="px-5 py-3">Incident</th>
              <th class="px-5 py-3">Requester</th>
              <th class="px-5 py-3">Resource</th>
              <th class="px-5 py-3">Status</th>
              <th class="px-5 py-3">Submitted</th>
              <th v-if="canUpdate" class="px-5 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-base-300">
            <tr v-for="i in items" :key="i.id">
              <td class="px-5 py-4">
                <p class="font-black text-red-600">{{ i.reference_number }}</p>
                <button v-if="i.linked_count" class="mt-1 rounded-full bg-red-50 px-2 py-0.5 text-[.62rem] font-black uppercase text-red-600 hover:bg-red-100 dark:bg-red-500/10 dark:text-red-300" @click="openLinked(i)">
                  <Icon icon="lucide:git-merge" width="11" class="mr-0.5 inline"/>+{{ i.linked_count }} linked
                </button>
              </td>
              <td class="px-5 py-4"><p class="font-black dark:text-white/75">{{ i.category_name }}</p><p class="mt-1 text-xs text-slate-400">{{ i.address_text }}</p></td>
              <td class="px-5 py-4"><p class="font-bold text-slate-600 dark:text-white/60">{{ i.requester_name||'Guest' }}</p><p class="text-xs text-slate-400">{{ i.requester_phone||'No contact provided' }}</p></td>
              <td class="px-5 py-4 text-xs">{{ i.unit_code?`${i.unit_code} · ${i.plate_number}`:'Not assigned' }}</td>
              <td class="px-5 py-4"><span class="rounded-full bg-base-200 px-2.5 py-1 text-[.65rem] font-black">{{ pretty(i.status) }}</span></td>
              <td class="px-5 py-4 text-xs text-slate-400">{{ format(i.submitted_at) }}</td>
              <td v-if="canUpdate" class="px-5 py-4 text-right">
                <button v-if="!isClosed(i.status)" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-base-200 dark:border-white/10 dark:text-white/50" @click="openEdit(i)">
                  <Icon icon="lucide:pencil" width="13" class="mr-1 inline"/> Edit details
                </button>
                <span v-else class="text-[.65rem] text-slate-300 dark:text-white/20">Closed</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Linked duplicate reports — same modal shell as the editor below -->
    <div v-if="linkedOpen" class="fixed inset-0 z-[95] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" @click.self="linkedOpen=false">
      <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl border border-base-300 bg-base-100 shadow-2xl dark:border-white/10 dark:bg-[#071829]">
        <header class="flex items-start justify-between border-b border-base-300 p-5 dark:border-white/10 sm:p-6">
          <div>
            <p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">{{ linkedFor?.reference_number }}</p>
            <h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">Linked Reports</h2>
            <p class="mt-1 text-xs text-slate-400">Other reports of this emergency. Unlink any that turn out to be a different emergency.</p>
          </div>
          <button class="rounded-xl p-2 text-slate-400 hover:bg-base-200 dark:hover:bg-white/5" @click="linkedOpen=false"><Icon icon="lucide:x" width="18"/></button>
        </header>
        <div class="p-5 sm:p-6">
          <div v-if="linkedLoading" class="py-10 text-center text-sm text-slate-400">Loading linked reports...</div>
          <p v-else-if="!linkedReports.length" class="py-10 text-center text-sm text-slate-400">No reports are linked to this incident anymore.</p>
          <LinkedReportsPanel v-else :reports="linkedReports" :can-unmerge="canUpdate && !isClosed(linkedFor?.status)" :busy-id="unmergingId" @unmerge="unmergeLinked" />
        </div>
      </div>
    </div>

    <!-- Edit requester details — same modal styling as the Fleet editor -->
    <div v-if="editOpen" class="fixed inset-0 z-[95] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" @click.self="editOpen=false">
      <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-3xl border border-base-300 bg-base-100 shadow-2xl dark:border-white/10 dark:bg-[#071829]">
        <header class="flex items-start justify-between border-b border-base-300 p-5 dark:border-white/10 sm:p-6">
          <div>
            <p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">{{ form.reference_number }}</p>
            <h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">Edit Incident Details</h2>
            <p class="mt-1 text-xs text-slate-400">Fill in what the requester couldn't — leave anything you don't know blank.</p>
          </div>
          <button class="rounded-xl p-2 text-slate-400 hover:bg-base-200 dark:hover:bg-white/5" @click="editOpen=false"><Icon icon="lucide:x" width="18"/></button>
        </header>

        <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
          <label class="block"><span :class="labelClass">Requester name</span><input v-model.trim="form.requester_name" :class="fieldClass" placeholder="Juan Dela Cruz" /></label>
          <label class="block"><span :class="labelClass">Contact number</span><input v-model.trim="form.requester_phone" :class="fieldClass" placeholder="09XXXXXXXXX" /></label>
          <label class="block"><span :class="labelClass">Barangay</span><input v-model.trim="form.barangay" :class="fieldClass" placeholder="Barangay" /></label>
          <label class="block"><span :class="labelClass">Landmark</span><input v-model.trim="form.landmark" :class="fieldClass" placeholder="Nearest known landmark" /></label>
          <label class="block sm:col-span-2"><span :class="labelClass">Address</span><input v-model.trim="form.address_text" :class="fieldClass" placeholder="House/unit number, street, subdivision" /></label>
          <label class="block sm:col-span-2"><span :class="labelClass">Situation description</span><textarea v-model.trim="form.description" rows="4" :class="fieldClass" placeholder="What's happening — visible injuries, hazards, anything the crew should know."></textarea></label>
          <p v-if="phoneInvalid" class="sm:col-span-2 -mt-1 text-xs text-red-500">Enter a valid PH mobile number (09XXXXXXXXX or +639XXXXXXXXX), or leave it blank.</p>
        </div>

        <footer class="flex items-center justify-end gap-2 border-t border-base-300 p-5 dark:border-white/10 sm:p-6">
          <button class="rounded-xl border border-base-300 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-base-200 dark:border-white/10 dark:text-white/60" @click="editOpen=false">Cancel</button>
          <button class="rounded-xl bg-[#1976D2] px-5 py-2.5 text-sm font-bold text-white hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed" :disabled="saving||phoneInvalid" @click="save">
            <Icon v-if="saving" icon="lucide:loader-2" width="14" class="mr-1 inline animate-spin"/>{{ saving ? 'Saving…' : 'Save details' }}
          </button>
        </footer>
      </div>
    </div>
  </PageShell>
</template>

<script setup>
import { defineComponent, h, onMounted, ref, reactive, computed } from 'vue'
import { Icon } from '@iconify/vue'
import PageShell from '@/components/management/PageShell.vue'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { fetchOrganizationIncidents, updateIncidentDetails } from '@/services/organizationCompletion'
import { fetchOrganizationLinkedReports, unmergeOrganizationReport } from '@/services/dispatchOperations'
import LinkedReportsPanel from '@/components/incidents/LinkedReportsPanel.vue'

const { hasPermission } = useSession()
const toast = useToast()

const items = ref([])
const stats = ref({})
const loading = ref(false)

const canUpdate = computed(() => hasPermission('incidents.emergency_requests.update'))

const Stat = defineComponent({
  props: { label: String, value: [String, Number] },
  setup: p => () => h('article', { class: 'rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829]' }, [
    h('p', { class: 'text-2xl font-black dark:text-white' }, String(p.value ?? 0)),
    h('p', { class: 'mt-1 text-xs font-bold text-slate-500' }, p.label),
  ]),
})

const fieldClass = 'w-full rounded-xl border border-base-300 bg-base-100 px-3.5 py-2.5 text-sm text-slate-800 outline-none transition focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/10 dark:border-white/10 dark:bg-[#071829] dark:text-white'
const labelClass = 'mb-1.5 block text-[0.68rem] font-black uppercase tracking-wide text-slate-400 dark:text-white/30'

const pretty = v => String(v || '').replaceAll('_', ' ').replace(/\b\w/g, m => m.toUpperCase())
const format = v => v ? new Date(String(v).replace(' ', 'T')).toLocaleString() : '—'
const isClosed = s => ['completed', 'cancelled', 'rejected', 'duplicate', 'false_alarm'].includes(s)

// Placeholders create.php / the edit endpoint fall back to — shown as blank in
// the editor so the org sees empty fields to fill, not boilerplate text.
const PLACEHOLDERS = [
  'Unspecified',
  'Pinned location (no address provided)',
  'EMERGENCY REQUEST. No details provided — confirm with requester on contact.',
]
const unplaceholder = v => PLACEHOLDERS.includes((v || '').trim()) ? '' : (v || '')

const editOpen = ref(false)
const saving = ref(false)
const form = reactive({ id: null, reference_number: '', requester_name: '', requester_phone: '', barangay: '', address_text: '', landmark: '', description: '' })

const phoneInvalid = computed(() => {
  const p = (form.requester_phone || '').replace(/[\s-]/g, '')
  return p !== '' && !/^(\+639\d{9}|09\d{9})$/.test(p)
})

function openEdit(i) {
  form.id = i.id
  form.reference_number = i.reference_number
  form.requester_name = i.requester_name === 'Guest requester' ? '' : (i.requester_name || '')
  form.requester_phone = i.requester_phone || ''
  form.barangay = unplaceholder(i.barangay)
  form.address_text = unplaceholder(i.address_text)
  form.landmark = i.landmark || ''
  form.description = unplaceholder(i.description)
  editOpen.value = true
}

async function save() {
  if (phoneInvalid.value || saving.value) return
  saving.value = true
  try {
    await updateIncidentDetails({
      emergency_request_id: form.id,
      requester_name: form.requester_name,
      requester_phone: form.requester_phone,
      barangay: form.barangay,
      address_text: form.address_text,
      landmark: form.landmark,
      description: form.description,
    })
    toast.success('Incident details updated.', 'Saved')
    editOpen.value = false
    await load()
  } catch (e) {
    toast.error(e?.response?.data?.message || e.message || 'Unable to update incident details.', 'Error')
  } finally {
    saving.value = false
  }
}

// --- Duplicate reports dedup linked to an incident ---
const linkedOpen = ref(false)
const linkedLoading = ref(false)
const linkedFor = ref(null)
const linkedReports = ref([])
const unmergingId = ref(null)

async function openLinked(i) {
  linkedFor.value = i
  linkedReports.value = []
  linkedOpen.value = true
  linkedLoading.value = true
  try {
    linkedReports.value = await fetchOrganizationLinkedReports(i.id)
  } catch (e) {
    toast.error(e?.response?.data?.message || e.message || 'Unable to load linked reports.', 'Error')
  } finally {
    linkedLoading.value = false
  }
}

async function unmergeLinked({ report, reason }) {
  unmergingId.value = report.id
  try {
    const d = await unmergeOrganizationReport(report.id, reason)
    toast.success(d.message, 'Report unlinked')
    linkedReports.value = await fetchOrganizationLinkedReports(linkedFor.value.id)
    await load()
  } catch (e) {
    toast.error(e?.response?.data?.message || e.message || 'Unable to unlink this report.', 'Error')
  } finally {
    unmergingId.value = null
  }
}

async function load() {
  loading.value = true
  try {
    const d = await fetchOrganizationIncidents()
    items.value = d.items || []
    stats.value = d.stats || {}
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
