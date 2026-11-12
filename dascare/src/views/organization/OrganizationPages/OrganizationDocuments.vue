<template>
  <PageShell title="Documents & Credentials" subtitle="Keep your organization's documents current. Upload a replacement before one expires." icon="lucide:files" eyebrow="Organization Management">
    <!-- Summary -->
    <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
      <article v-for="s in statTiles" :key="s.label" class="rounded-3xl border border-base-300 bg-base-100 p-4 shadow-sm dark:border-white/10 dark:bg-[#071829]">
        <p class="text-2xl font-black" :class="s.warn && s.value ? 'text-amber-600' : 'dark:text-white'">{{ s.value }}</p>
        <p class="mt-1 text-xs font-bold text-slate-500">{{ s.label }}</p>
      </article>
    </div>

    <div v-if="alerts.length" class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-100">
      <p class="flex items-center gap-2 font-black"><Icon icon="lucide:triangle-alert" width="16" /> Needs your attention</p>
      <ul class="mt-2 list-disc space-y-1 pl-5 text-xs leading-5">
        <li v-for="a in alerts" :key="a">{{ a }}</li>
      </ul>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.4fr_.9fr]">
      <!-- Documents -->
      <section class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829]">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="font-black dark:text-white">Organization documents<HelpTip title="Replacing a document" text="Upload the new copy with its expiry date. It goes to the platform for review; your current document stays valid until the new one is approved." /></h2>
          <button v-if="canUpload" type="button" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-600 dark:border-white/10 dark:text-white/60" @click="openUpload(null)"><Icon icon="lucide:plus" width="14" class="mr-1 inline" />Other document</button>
        </div>
        <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Loading documents…</div>
        <div v-else class="mt-4 space-y-3">
          <article v-for="row in rows" :key="row.key" class="rounded-2xl border border-base-300 p-4 dark:border-white/10">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-black text-slate-800 dark:text-white/80">{{ row.label }} <span v-if="row.required" class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[.6rem] font-black uppercase text-slate-500 dark:bg-white/10">Required</span></p>
                <p v-if="row.doc" class="mt-1 break-words text-xs text-slate-400">{{ row.doc.original_name || 'Submitted file' }} · uploaded {{ shortDate(row.doc.created_at) }}</p>
                <p v-else class="mt-1 text-xs text-slate-400">{{ row.hint || 'Not uploaded yet.' }}</p>
              </div>
              <div v-if="row.doc" class="flex flex-wrap items-center gap-1.5">
                <span class="rounded-full px-2.5 py-1 text-[.62rem] font-black uppercase" :class="statusClass(row.doc.status)">{{ row.doc.status }}</span>
                <span v-if="row.doc.expires_at" class="rounded-full px-2.5 py-1 text-[.62rem] font-black uppercase" :class="expiryClass(row.doc)">{{ expiryText(row.doc) }}</span>
              </div>
              <span v-else class="rounded-full bg-red-50 px-2.5 py-1 text-[.62rem] font-black uppercase text-red-700 dark:bg-red-500/10 dark:text-red-300">Missing</span>
            </div>

            <p v-if="row.doc?.status === 'rejected' && row.doc.rejection_reason" class="mt-2 rounded-xl bg-red-50 p-2.5 text-xs text-red-700 dark:bg-red-500/10 dark:text-red-300">Rejected: {{ row.doc.rejection_reason }}</p>
            <p v-if="row.pending" class="mt-2 rounded-xl bg-amber-50 p-2.5 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">Replacement uploaded {{ shortDate(row.pending.created_at) }} — waiting for platform review. Your current document stays valid.</p>
            <p v-if="row.rejectedReplacement" class="mt-2 rounded-xl bg-red-50 p-2.5 text-xs text-red-700 dark:bg-red-500/10 dark:text-red-300">Your replacement was rejected: {{ row.rejectedReplacement.rejection_reason }}. Your current document stays valid — upload a corrected copy.</p>

            <div class="mt-3 flex flex-wrap gap-2">
              <button v-if="row.doc" type="button" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-700 dark:border-white/10 dark:text-white/70" :disabled="viewing === row.doc.id" @click="view(row.doc)"><Icon icon="lucide:eye" width="14" class="mr-1 inline" />View</button>
              <button v-if="canUpload && !row.pending" type="button" class="rounded-xl bg-red-600 px-3 py-2 text-xs font-black text-white" @click="openUpload(row)"><Icon icon="lucide:upload" width="14" class="mr-1 inline" />{{ row.doc ? 'Replace' : 'Upload' }}</button>
              <button v-if="row.history.length" type="button" class="rounded-xl px-3 py-2 text-xs font-bold text-slate-500 dark:text-white/50" @click="historyFor = row">History ({{ row.history.length }})</button>
            </div>
          </article>
          <p v-if="!rows.length" class="py-10 text-center text-sm text-slate-400">No documents recorded.</p>
        </div>
      </section>

      <!-- Fleet credentials -->
      <section class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829]">
        <h2 class="font-black dark:text-white">Ambulance credentials<HelpTip text="Registration and inspection dates of each ambulance. Units with expired papers can't be dispatched. Update them on Fleet." /></h2>
        <div class="mt-4 space-y-3">
          <article v-for="u in fleet" :key="u.id" class="rounded-2xl bg-base-200 p-4 dark:bg-white/[.035]">
            <p class="text-sm font-black dark:text-white/75">{{ u.unit_code }} · {{ u.plate_number }}</p>
            <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-slate-500"><span>Registration<br><b>{{ u.registration_expiry || 'Not recorded' }}</b></span><span>Inspection<br><b>{{ u.inspection_expiry || 'Not recorded' }}</b></span></div>
            <p v-for="a in u.credential_alerts" :key="a.label" class="mt-2 text-xs font-bold" :class="a.expired ? 'text-red-600' : 'text-amber-600'">{{ a.label }} {{ a.expired ? 'expired' : 'expires' }} {{ a.date }}</p>
          </article>
          <p v-if="!fleet.length && !loading" class="py-10 text-center text-sm text-slate-400">No fleet credentials recorded.</p>
        </div>
      </section>
    </div>

    <!-- Upload -->
    <Teleport to="body">
      <div v-if="upload.open" class="fixed inset-0 z-[10040] grid place-items-center bg-slate-950/50 p-4" @click.self="closeUpload">
        <form class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl dark:bg-[#0d2943]" @submit.prevent="submitUpload">
          <h3 class="text-lg font-black text-slate-900 dark:text-white">{{ upload.replacing ? 'Replace document' : 'Upload document' }}</h3>
          <label v-if="!upload.fixedType" class="mt-4 block text-xs font-bold text-slate-500">Document type
            <select v-model="upload.doc_type" class="mt-1.5 w-full rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5">
              <option value="">Select…</option>
              <option v-for="r in requirements" :key="r.key" :value="r.key">{{ r.label }}</option>
              <option value="other_supporting">Other supporting document</option>
            </select>
          </label>
          <p v-else class="mt-1 text-sm font-bold text-slate-600 dark:text-white/60">{{ upload.label }}</p>
          <label class="mt-4 block text-xs font-bold text-slate-500">File (PDF, JPG, PNG or WEBP, up to 8 MB)
            <input ref="fileInput" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" class="mt-1.5 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-red-600 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white" @change="onFile" />
          </label>
          <label class="mt-4 block text-xs font-bold text-slate-500">Expiry date <span class="font-normal">(if the document has one)</span><HelpTip text="Permits and licenses usually print a validity date. We remind your admins before it expires. Leave blank for documents that don't expire." />
            <input v-model="upload.expires_at" type="date" :min="today" class="mt-1.5 w-full rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5" />
          </label>
          <p v-if="upload.error" class="mt-3 text-xs font-bold text-red-600">{{ upload.error }}</p>
          <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="rounded-xl border border-base-300 px-4 py-2.5 text-xs font-bold text-slate-600 dark:border-white/10 dark:text-white/60" @click="closeUpload">Cancel</button>
            <button type="submit" :disabled="upload.saving" class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-black text-white disabled:opacity-50">{{ upload.saving ? 'Uploading…' : 'Upload' }}</button>
          </div>
        </form>
      </div>
    </Teleport>

    <!-- History -->
    <Teleport to="body">
      <div v-if="historyFor" class="fixed inset-0 z-[10040] grid place-items-center bg-slate-950/50 p-4" @click.self="historyFor = null">
        <div class="max-h-[80vh] w-full max-w-md overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl dark:bg-[#0d2943]">
          <h3 class="text-lg font-black text-slate-900 dark:text-white">{{ historyFor.label }} — history</h3>
          <ul class="mt-4 space-y-3">
            <li v-for="h in historyFor.history" :key="h.id" class="rounded-2xl border border-base-300 p-3 text-xs dark:border-white/10">
              <p class="break-words font-bold text-slate-700 dark:text-white/75">{{ h.original_name || 'File' }}</p>
              <p class="mt-1 text-slate-400">Uploaded {{ shortDate(h.created_at) }}<template v-if="h.expires_at"> · expiry {{ h.expires_at }}</template> · {{ h.status }}</p>
              <button type="button" class="mt-2 text-xs font-bold text-[#1976D2]" @click="view(h)">View</button>
            </li>
          </ul>
          <div class="mt-5 text-right"><button type="button" class="rounded-xl border border-base-300 px-4 py-2 text-xs font-bold dark:border-white/10 dark:text-white/70" @click="historyFor = null">Close</button></div>
        </div>
      </div>
    </Teleport>
  </PageShell>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Icon } from '@iconify/vue'
import PageShell from '@/components/management/PageShell.vue'
import HelpTip from '@/components/forms/HelpTip.vue'
import { fetchOrganizationDocuments, fetchOrganizationDocumentBlob, uploadOrganizationDocument } from '@/services/organizationCompletion'
import { organizationDocumentLabel } from '@/config/organizationDocuments'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'

const toast = useToast()
const alert = useAlert()
const loading = ref(true)
const documents = ref([])
const history = ref([])
const requirements = ref([])
const fleet = ref([])
const stats = ref({})
const canUpload = ref(false)
const viewing = ref(null)
const historyFor = ref(null)
const fileInput = ref(null)
const today = new Date().toISOString().slice(0, 10)
const upload = reactive({ open: false, doc_type: '', fixedType: false, label: '', replacing: false, file: null, expires_at: '', error: '', saving: false })

const labelFor = (type) => requirements.value.find((r) => r.key === type)?.label || organizationDocumentLabel(type)
const shortDate = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '')

// One row per required type (even when missing) + one per other uploaded type.
const rows = computed(() => {
  const byType = {}
  for (const d of documents.value) (byType[d.doc_type] ||= []).push(d)
  const hist = (type) => history.value.filter((h) => h.doc_type === type)
  const build = (key, label, required, hint) => {
    const list = byType[key] || []
    const approved = list.find((d) => d.status === 'approved')
    const pending = list.find((d) => d.status === 'pending' && d.replaces_document_id)
    const doc = approved || list.find((d) => d.status === 'pending') || list[0] || null
    const rejectedReplacement = approved ? list.find((d) => d.status === 'rejected' && d.replaces_document_id && d.id > approved.id) : null
    return { key, label, required, hint, doc, pending: approved ? pending : null, rejectedReplacement, history: hist(key) }
  }
  const out = requirements.value.map((r) => build(r.key, r.label, r.required, r.hint))
  const known = new Set(requirements.value.map((r) => r.key))
  for (const type of Object.keys(byType)) if (!known.has(type)) out.push(build(type, labelFor(type), false, ''))
  return out
})

const statTiles = computed(() => [
  { label: 'Documents', value: stats.value.documents ?? 0 },
  { label: 'Approved', value: stats.value.approved ?? 0 },
  { label: 'Pending review', value: stats.value.pending ?? 0 },
  { label: 'Expiring soon', value: stats.value.expiring ?? 0, warn: true },
  { label: 'Expired', value: stats.value.expired ?? 0, warn: true },
  { label: 'Credential alerts', value: stats.value.credential_alerts ?? 0, warn: true },
])
const alerts = computed(() => {
  const out = []
  for (const r of rows.value) {
    if (r.required && !r.doc) out.push(`${r.label} has not been uploaded.`)
    else if (r.doc?.status === 'rejected' && !r.pending) out.push(`${r.label} was rejected — upload a corrected copy.`)
    else if (r.doc?.status === 'approved' && r.doc.state === 'expired' && !r.pending) out.push(`${r.label} expired on ${r.doc.expires_at}.`)
    else if (r.doc?.status === 'approved' && r.doc.state === 'expiring' && !r.pending) out.push(`${r.label} expires in ${r.doc.days_left} day${r.doc.days_left === 1 ? '' : 's'}.`)
  }
  return out
})

const statusClass = (s) => (s === 'approved' ? 'bg-emerald-100 text-emerald-700' : s === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700')
const expiryClass = (d) => (d.state === 'expired' ? 'bg-red-100 text-red-700' : d.state === 'expiring' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-white/60')
const expiryText = (d) => (d.state === 'expired' ? `Expired ${d.expires_at}` : d.state === 'expiring' ? `Expires in ${d.days_left}d` : `Valid to ${d.expires_at}`)

async function load() {
  loading.value = true
  try {
    const d = await fetchOrganizationDocuments()
    documents.value = d.documents || []
    history.value = d.history || []
    requirements.value = d.requirements || []
    fleet.value = d.fleet_credentials || []
    stats.value = d.stats || {}
    canUpload.value = !!d.can_upload
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Unable to load documents.')
  } finally {
    loading.value = false
  }
}

async function view(doc) {
  viewing.value = doc.id
  try {
    const blob = await fetchOrganizationDocumentBlob(doc.id)
    const url = URL.createObjectURL(blob)
    window.open(url, '_blank', 'noopener')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch {
    toast.error('Unable to open that document.')
  } finally {
    viewing.value = null
  }
}

function openUpload(row) {
  Object.assign(upload, { open: true, doc_type: row?.key || '', fixedType: !!row, label: row?.label || '', replacing: !!row?.doc, file: null, expires_at: '', error: '', saving: false })
}
function closeUpload() { upload.open = false }
function onFile(e) {
  const f = e.target.files?.[0] || null
  upload.error = ''
  if (f && f.size > 8 * 1024 * 1024) { upload.error = 'The file must be 8 MB or smaller.'; e.target.value = ''; upload.file = null; return }
  if (f && !/^(application\/pdf|image\/(jpeg|png|webp))$/.test(f.type)) { upload.error = 'Choose a PDF, JPG, PNG or WEBP file.'; e.target.value = ''; upload.file = null; return }
  upload.file = f
}
async function submitUpload() {
  if (!upload.doc_type) { upload.error = 'Choose which document this is.'; return }
  if (!upload.file) { upload.error = 'Choose a file to upload.'; return }
  if (upload.expires_at && upload.expires_at < today) { upload.error = 'That date has already passed — upload a current document.'; return }
  const ok = await alert.confirm(`${upload.replacing ? 'Upload this replacement for' : 'Upload'} ${upload.fixedType ? upload.label : labelFor(upload.doc_type)}? It will be sent to the platform for review.`, upload.replacing ? 'Replace document' : 'Upload document')
  if (!ok) return
  upload.saving = true
  upload.error = ''
  try {
    const fd = new FormData()
    fd.append('doc_type', upload.doc_type)
    fd.append('file', upload.file)
    if (upload.expires_at) fd.append('expires_at', upload.expires_at)
    const d = await uploadOrganizationDocument(fd)
    toast.success(d.message, 'Uploaded')
    upload.open = false
    await load()
  } catch (e) {
    upload.error = e?.response?.data?.message || 'Unable to upload the document.'
  } finally {
    upload.saving = false
  }
}

onMounted(load)
</script>
