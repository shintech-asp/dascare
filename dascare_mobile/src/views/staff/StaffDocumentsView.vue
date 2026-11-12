<template>
  <!-- Documents & Credentials (staff) — same data as the web page: each required
       document with its review status and expiry, upload / replace, history, and
       each ambulance's registration/inspection dates. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader title="Documents & Credentials" fallback="/staff/more" />
    <main class="space-y-3 px-4 pt-3">
      <div class="grid grid-cols-3 gap-2">
        <div v-for="s in statTiles" :key="s.label" :class="[ui.card, 'p-2.5 text-center']">
          <p class="text-lg font-black" :class="s.warn && s.value ? 'text-amber-600' : 'text-slate-950 dark:text-white'">{{ s.value }}</p>
          <p class="text-[0.6rem] font-semibold leading-tight text-slate-500 dark:text-white/45">{{ s.label }}</p>
        </div>
      </div>

      <div v-if="alerts.length" class="rounded-2xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-100">
        <p class="flex items-center gap-1.5 font-black"><Icon icon="lucide:triangle-alert" width="14" /> Needs attention</p>
        <ul class="mt-1.5 list-disc space-y-1 pl-4 leading-5"><li v-for="a in alerts" :key="a">{{ a }}</li></ul>
      </div>

      <div class="flex items-center justify-between px-2 pt-1">
        <p class="text-[0.68rem] font-black uppercase tracking-[0.16em] text-slate-400 dark:text-white/35">Organization documents<HelpTip title="Replacing a document" text="Upload the new copy with its expiry date. It goes to the platform for review; your current document stays valid until the new one is approved." /></p>
        <button v-if="canUpload" type="button" class="tap text-xs font-bold text-[#1976D2]" @click="openUpload(null)">+ Other</button>
      </div>
      <section :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
        <div v-for="row in rows" :key="row.key" class="p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-sm font-bold text-slate-900 dark:text-white">{{ row.label }} <span v-if="row.required" class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[0.55rem] font-black uppercase text-slate-500 dark:bg-white/10">Required</span></p>
              <p v-if="row.doc" class="truncate text-xs text-slate-500 dark:text-white/45">{{ row.doc.original_name || 'Submitted file' }}</p>
              <p v-else class="text-xs text-slate-500 dark:text-white/45">{{ row.hint || 'Not uploaded yet.' }}</p>
            </div>
            <div class="flex flex-shrink-0 flex-col items-end gap-1">
              <span v-if="row.doc" class="rounded-full px-2 py-0.5 text-[0.62rem] font-black uppercase" :class="statusClass(row.doc.status)">{{ row.doc.status }}</span>
              <span v-else class="rounded-full bg-red-50 px-2 py-0.5 text-[0.62rem] font-black uppercase text-red-700 dark:bg-red-500/15 dark:text-red-300">Missing</span>
              <span v-if="row.doc?.expires_at" class="rounded-full px-2 py-0.5 text-[0.62rem] font-black uppercase" :class="expiryClass(row.doc)">{{ expiryText(row.doc) }}</span>
            </div>
          </div>
          <p v-if="row.doc?.status === 'rejected' && row.doc.rejection_reason" class="mt-1 text-xs text-red-600 dark:text-red-300">Rejected: {{ row.doc.rejection_reason }}</p>
          <p v-if="row.pending" class="mt-1 text-xs text-amber-700 dark:text-amber-300">Replacement waiting for platform review — your current document stays valid.</p>
          <p v-if="row.rejectedReplacement" class="mt-1 text-xs text-red-600 dark:text-red-300">Replacement rejected: {{ row.rejectedReplacement.rejection_reason }}</p>
          <div class="mt-2 flex flex-wrap gap-2">
            <button v-if="row.doc" type="button" class="tap rounded-xl border border-base-300 px-3 py-1.5 text-xs font-bold text-slate-700 dark:border-white/10 dark:text-white/70" @click="view(row.doc)">View</button>
            <button v-if="canUpload && !row.pending" type="button" class="tap rounded-xl bg-red-600 px-3 py-1.5 text-xs font-black text-white" @click="openUpload(row)">{{ row.doc ? 'Replace' : 'Upload' }}</button>
          </div>
        </div>
        <p v-if="loaded && !rows.length" class="p-6 text-center text-sm text-slate-500">No organization documents recorded.</p>
      </section>

      <p class="px-2 pt-2 text-[0.68rem] font-black uppercase tracking-[0.16em] text-slate-400 dark:text-white/35">Fleet credentials</p>
      <section :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
        <div v-for="u in fleet" :key="u.id" class="p-4">
          <p class="text-sm font-bold text-slate-900 dark:text-white">{{ u.unit_code }} · {{ u.plate_number }}</p>
          <div class="mt-1 grid grid-cols-2 gap-2 text-xs text-slate-500 dark:text-white/45"><span>Registration<br><b class="text-slate-700 dark:text-white/70">{{ u.registration_expiry || 'Not recorded' }}</b></span><span>Inspection<br><b class="text-slate-700 dark:text-white/70">{{ u.inspection_expiry || 'Not recorded' }}</b></span></div>
          <p v-for="a in u.credential_alerts || []" :key="a.label" class="mt-1 text-xs font-bold" :class="a.expired ? 'text-red-600 dark:text-red-300' : 'text-amber-600 dark:text-amber-300'">{{ a.label }} {{ a.expired ? 'expired' : 'expires' }} {{ a.date }}</p>
        </div>
        <p v-if="loaded && !fleet.length" class="p-6 text-center text-sm text-slate-500">No fleet credentials yet.</p>
      </section>
    </main>

    <!-- Upload -->
    <div v-if="upload.open" class="fixed inset-0 z-[10040] flex items-end bg-slate-950/50" @click.self="upload.open = false">
      <form class="w-full rounded-t-3xl bg-white p-5 pb-[calc(var(--safe-bottom)+1.25rem)] shadow-2xl dark:bg-[#0d2943]" @submit.prevent="submitUpload">
        <h3 class="text-lg font-black text-slate-900 dark:text-white">{{ upload.replacing ? 'Replace document' : 'Upload document' }}</h3>
        <div v-if="!upload.fixedType" class="mt-3">
          <label :class="ui.label">Document type</label>
          <select v-model="upload.doc_type" :class="ui.input">
            <option value="">Select…</option>
            <option v-for="r in requirements" :key="r.key" :value="r.key">{{ r.label }}</option>
            <option value="other_supporting">Other supporting document</option>
          </select>
        </div>
        <p v-else class="mt-1 text-sm font-bold text-slate-600 dark:text-white/60">{{ upload.label }}</p>
        <div class="mt-3">
          <label :class="ui.label">File (PDF, JPG, PNG or WEBP, up to 8 MB)</label>
          <input type="file" accept="application/pdf,image/jpeg,image/png,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-red-600 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white" @change="onFile" />
        </div>
        <div class="mt-3">
          <label :class="ui.label">Expiry date (if it has one)<HelpTip text="Permits and licenses usually print a validity date. We remind your admins before it expires. Leave blank for documents that don't expire." /></label>
          <input v-model="upload.expires_at" type="date" :min="today" :class="ui.input" />
        </div>
        <p v-if="upload.error" class="mt-3 text-xs font-bold text-red-600">{{ upload.error }}</p>
        <div class="mt-5 flex gap-2">
          <button type="button" :class="[ui.secondaryButton, 'flex-1']" @click="upload.open = false">Cancel</button>
          <button type="submit" :disabled="upload.saving" :class="[ui.primaryButton, 'flex-1']">{{ upload.saving ? 'Uploading…' : 'Upload' }}</button>
        </div>
      </form>
    </div>

    <!-- Image viewer -->
    <div v-if="viewer" class="fixed inset-0 z-[10070] flex items-center justify-center bg-black/90 p-4" @click="closeViewer">
      <img :src="viewer" alt="Document" class="max-h-full max-w-full rounded-xl object-contain" />
      <button type="button" class="absolute right-4 top-[calc(var(--safe-top,0px)+1rem)] grid h-11 w-11 place-items-center rounded-full bg-white/15 text-white" aria-label="Close"><Icon icon="lucide:x" width="22" /></button>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Icon } from '@iconify/vue'
import ScreenHeader from '@/components/ScreenHeader.vue'
import HelpTip from '@/components/HelpTip.vue'
import * as ui from '@/components/ui/styles'
import { fetchOrganizationDocuments, fetchOrganizationDocumentBlob, uploadOrganizationDocument } from '@/services/org/organizationCompletion'
import { organizationDocumentLabel } from '@/config/organizationDocuments'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'
import { apiMessage } from '@/services/api'

const toast = useToast()
const alert = useAlert()
const documents = ref([])
const history = ref([])
const requirements = ref([])
const fleet = ref([])
const stats = ref({})
const canUpload = ref(false)
const loaded = ref(false)
const viewer = ref(null)
const today = new Date().toISOString().slice(0, 10)
const upload = reactive({ open: false, doc_type: '', fixedType: false, label: '', replacing: false, file: null, expires_at: '', error: '', saving: false })

const labelFor = (type) => requirements.value.find((r) => r.key === type)?.label || organizationDocumentLabel(type)

const rows = computed(() => {
  const byType = {}
  for (const d of documents.value) (byType[d.doc_type] ||= []).push(d)
  const build = (key, label, required, hint) => {
    const list = byType[key] || []
    const approved = list.find((d) => d.status === 'approved')
    const pending = list.find((d) => d.status === 'pending' && d.replaces_document_id)
    const doc = approved || list.find((d) => d.status === 'pending') || list[0] || null
    const rejectedReplacement = approved ? list.find((d) => d.status === 'rejected' && d.replaces_document_id && d.id > approved.id) : null
    return { key, label, required, hint, doc, pending: approved ? pending : null, rejectedReplacement }
  }
  const out = requirements.value.map((r) => build(r.key, r.label, r.required, r.hint))
  const known = new Set(requirements.value.map((r) => r.key))
  for (const type of Object.keys(byType)) if (!known.has(type)) out.push(build(type, labelFor(type), false, ''))
  return out
})
const statTiles = computed(() => [
  { label: 'Documents', value: stats.value.documents ?? 0 },
  { label: 'Pending', value: stats.value.pending ?? 0 },
  { label: 'Expiring / expired', value: (stats.value.expiring ?? 0) + (stats.value.expired ?? 0), warn: true },
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
const statusClass = (s) => (s === 'approved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : s === 'rejected' ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300')
const expiryClass = (d) => (d.state === 'expired' ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : d.state === 'expiring' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-white/60')
const expiryText = (d) => (d.state === 'expired' ? `Expired ${d.expires_at}` : d.state === 'expiring' ? `Expires in ${d.days_left}d` : `Valid to ${d.expires_at}`)

async function load() {
  try {
    const d = await fetchOrganizationDocuments()
    documents.value = d.documents || []
    history.value = d.history || []
    requirements.value = d.requirements || []
    fleet.value = d.fleet_credentials || []
    stats.value = d.stats || {}
    canUpload.value = !!d.can_upload
  } catch (e) {
    toast.error(apiMessage(e, 'Unable to load documents.'), 'Error')
  } finally {
    loaded.value = true
  }
}

async function view(doc) {
  try {
    const blob = await fetchOrganizationDocumentBlob(doc.id)
    if (!String(blob.type).startsWith('image/')) {
      toast.info('PDF documents open on the DASCARE website (Documents & Credentials).', 'PDF')
      return
    }
    viewer.value = URL.createObjectURL(blob)
  } catch (e) {
    toast.error('Unable to open that document.', 'Error')
  }
}
function closeViewer() { if (viewer.value) URL.revokeObjectURL(viewer.value); viewer.value = null }

function openUpload(row) {
  Object.assign(upload, { open: true, doc_type: row?.key || '', fixedType: !!row, label: row?.label || '', replacing: !!row?.doc, file: null, expires_at: '', error: '', saving: false })
}
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
  if (!(await alert.confirm(`${upload.replacing ? 'Upload this replacement for' : 'Upload'} ${upload.fixedType ? upload.label : labelFor(upload.doc_type)}? It will be sent to the platform for review.`, upload.replacing ? 'Replace document' : 'Upload document'))) return
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
    upload.error = apiMessage(e, 'Unable to upload the document.')
  } finally {
    upload.saving = false
  }
}

onMounted(load)
</script>
