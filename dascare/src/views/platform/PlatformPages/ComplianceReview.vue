<template>
  <PageShell title="Document Management" subtitle="Review organization documents and replacements, and watch what is expiring across the city." icon="lucide:file-check-2" eyebrow="Platform Executive">
    <!-- Summary (tap to filter) -->
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
      <button v-for="t in tiles" :key="t.key" type="button" class="rounded-3xl border p-4 text-left shadow-sm transition-colors" :class="filter === t.key ? 'border-red-300 bg-red-50 dark:border-red-500/40 dark:bg-red-500/10' : 'border-base-300 bg-base-100 hover:border-red-200 dark:border-white/10 dark:bg-[#071829]'" @click="setFilter(t.key)">
        <p class="text-2xl font-black" :class="t.warn && t.value ? 'text-amber-600' : 'dark:text-white'">{{ t.value }}</p>
        <p class="mt-1 text-xs font-bold text-slate-500">{{ t.label }}</p>
      </button>
    </div>

    <div class="mt-5 flex flex-wrap items-center gap-3">
      <div class="relative min-w-[14rem] flex-1">
        <Icon icon="lucide:search" width="15" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input v-model.trim="search" type="search" placeholder="Search organization or document" class="w-full rounded-xl border border-base-300 bg-base-100 py-2.5 pl-10 pr-3 text-sm dark:border-white/10 dark:bg-white/5" @input="debouncedLoad" />
      </div>
      <HelpTip title="Document review" text="Replacements uploaded by organizations wait here. Approving one retires the old copy; rejecting it keeps the old copy valid and tells the organization why. Applications under review are decided on the Applications page." />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.4fr_.8fr]">
      <section class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829]">
        <h2 class="text-lg font-black dark:text-white">{{ filterTitle }}</h2>
        <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Loading documents…</div>
        <div v-else class="mt-4 space-y-3">
          <article v-for="d in items" :key="d.id" class="rounded-2xl border border-base-300 p-4 dark:border-white/10">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="break-words text-sm font-black text-slate-800 dark:text-white/80">{{ labelFor(d.doc_type) }}</p>
                <p class="mt-0.5 break-words text-xs font-bold text-slate-500">{{ d.organization_name }}</p>
                <p class="mt-1 break-words text-xs text-slate-400">{{ d.original_name || 'File' }} · uploaded {{ shortDate(d.created_at) }}</p>
              </div>
              <div class="flex flex-wrap items-center gap-1.5">
                <span v-if="d.is_replacement" class="rounded-full bg-blue-50 px-2.5 py-1 text-[.62rem] font-black uppercase text-blue-700">Replacement</span>
                <span class="rounded-full px-2.5 py-1 text-[.62rem] font-black uppercase" :class="statusClass(d.status)">{{ d.status }}</span>
                <span v-if="d.expires_at" class="rounded-full px-2.5 py-1 text-[.62rem] font-black uppercase" :class="expiryClass(d)">{{ expiryText(d) }}</span>
              </div>
            </div>
            <p v-if="d.rejection_reason" class="mt-2 text-xs text-red-600">Rejected: {{ d.rejection_reason }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
              <button type="button" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-700 dark:border-white/10 dark:text-white/70" :disabled="viewing === d.id" @click="view(d)"><Icon icon="lucide:eye" width="14" class="mr-1 inline" />View</button>
              <template v-if="d.can_review && canReview">
                <button type="button" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-black text-white" @click="openReview(d, 'approve')">Approve</button>
                <button type="button" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-black text-red-700" @click="openReview(d, 'reject')">Reject</button>
              </template>
              <p v-else-if="d.status === 'pending' && d.organization_status !== 'active'" class="self-center text-[.7rem] text-slate-400">Reviewed with the organization's application.</p>
            </div>
          </article>
          <p v-if="!items.length" class="py-12 text-center text-sm text-slate-400">Nothing here.</p>
        </div>
      </section>

      <section class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829]">
        <h2 class="text-lg font-black dark:text-white">Ambulance credential alerts<HelpTip text="Registration or inspection dates that are expired or close to expiring. Units with expired papers are not dispatched." /></h2>
        <div class="mt-4 space-y-3">
          <article v-for="a in alerts" :key="a.id" class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
            <p class="font-black text-slate-800 dark:text-white/75">{{ a.unit_code }} · {{ a.plate_number }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-white/45">{{ a.organization_name }}</p>
            <p class="mt-1 text-xs text-slate-600 dark:text-white/55">Registration {{ a.registration_expiry || '—' }} · Inspection {{ a.inspection_expiry || '—' }}</p>
          </article>
          <p v-if="!alerts.length && !loading" class="py-10 text-center text-sm text-slate-400">No ambulance credential alerts.</p>
        </div>
      </section>
    </div>

    <!-- Decision -->
    <Teleport to="body">
      <div v-if="review" class="fixed inset-0 z-[10040] grid place-items-center bg-slate-950/50 p-4" @click.self="review = null">
        <form class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl dark:bg-[#0d2943]" @submit.prevent="submitReview">
          <h3 class="text-lg font-black text-slate-900 dark:text-white">{{ review.action === 'approve' ? 'Approve document' : 'Reject document' }}</h3>
          <p class="mt-1 break-words text-sm text-slate-500">{{ labelFor(review.doc.doc_type) }} · {{ review.doc.organization_name }}</p>
          <label v-if="review.action === 'approve'" class="mt-4 block text-xs font-bold text-slate-500">Expiry date <span class="font-normal">(as printed on the document)</span>
            <input v-model="review.expires_at" type="date" :min="today" class="mt-1.5 w-full rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5" />
          </label>
          <label class="mt-4 block text-xs font-bold text-slate-500">{{ review.action === 'approve' ? 'Note to the organization (optional)' : 'Reason (required — tell them what to fix)' }}
            <textarea v-model.trim="review.note" rows="3" maxlength="500" class="mt-1.5 w-full rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5" :placeholder="review.action === 'reject' ? 'e.g. The license is blurry and the date is unreadable.' : ''"></textarea>
          </label>
          <p v-if="review.error" class="mt-3 text-xs font-bold text-red-600">{{ review.error }}</p>
          <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="rounded-xl border border-base-300 px-4 py-2.5 text-xs font-bold text-slate-600 dark:border-white/10 dark:text-white/60" @click="review = null">Cancel</button>
            <button type="submit" :disabled="review.saving" class="rounded-xl px-5 py-2.5 text-xs font-black text-white disabled:opacity-50" :class="review.action === 'approve' ? 'bg-emerald-600' : 'bg-red-600'">{{ review.saving ? 'Saving…' : review.action === 'approve' ? 'Approve' : 'Reject' }}</button>
          </div>
        </form>
      </div>
    </Teleport>
  </PageShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import PageShell from '@/components/management/PageShell.vue'
import HelpTip from '@/components/forms/HelpTip.vue'
import { fetchPlatformCompliance, fetchPlatformDocuments, fetchPlatformDocumentBlob, reviewPlatformDocument } from '@/services/adminCompletion'
import { organizationDocumentLabel } from '@/config/organizationDocuments'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'

const toast = useToast()
const alert = useAlert()
const { hasPermission } = useSession()
const canReview = computed(() => hasPermission('organizations.org_documents.approve'))
const today = new Date().toISOString().slice(0, 10)

const loading = ref(true)
const items = ref([])
const stats = ref({})
const alerts = ref([])
const filter = ref('pending')
const search = ref('')
const viewing = ref(null)
const review = ref(null)

const FILTERS = {
  pending: { status: 'pending', title: 'Needs review' },
  expiring: { expiry: 'expiring', title: 'Expiring soon' },
  expired: { expiry: 'expired', title: 'Expired' },
  rejected: { status: 'rejected', title: 'Rejected' },
  all: { title: 'All documents' },
}
const filterTitle = computed(() => FILTERS[filter.value].title)
const tiles = computed(() => [
  { key: 'pending', label: 'Needs review', value: stats.value.pending ?? 0, warn: true },
  { key: 'expiring', label: 'Expiring soon', value: stats.value.expiring ?? 0, warn: true },
  { key: 'expired', label: 'Expired', value: stats.value.expired ?? 0, warn: true },
  { key: 'rejected', label: 'Rejected', value: stats.value.rejected ?? 0 },
  { key: 'all', label: 'All documents', value: stats.value.total ?? 0 },
])

const labelFor = (type) => organizationDocumentLabel(type)
const shortDate = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '')
const statusClass = (s) => (s === 'approved' ? 'bg-emerald-100 text-emerald-700' : s === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700')
const expiryClass = (d) => (d.state === 'expired' ? 'bg-red-100 text-red-700' : d.state === 'expiring' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-white/60')
const expiryText = (d) => (d.state === 'expired' ? `Expired ${d.expires_at}` : d.state === 'expiring' ? `Expires in ${d.days_left}d` : `Valid to ${d.expires_at}`)

let timer = null
const debouncedLoad = () => { clearTimeout(timer); timer = setTimeout(load, 300) }
function setFilter(k) { filter.value = k; load() }

async function load() {
  loading.value = true
  try {
    const f = FILTERS[filter.value]
    const [d, c] = await Promise.all([
      fetchPlatformDocuments({ status: f.status || '', expiry: f.expiry || '', search: search.value }),
      alerts.value.length ? Promise.resolve(null) : fetchPlatformCompliance().catch(() => null),
    ])
    items.value = d.items || []
    stats.value = d.stats || {}
    if (c) alerts.value = c.credential_alerts || []
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Unable to load documents.')
  } finally {
    loading.value = false
  }
}

async function view(doc) {
  viewing.value = doc.id
  try {
    const blob = await fetchPlatformDocumentBlob(doc.id)
    const url = URL.createObjectURL(blob)
    window.open(url, '_blank', 'noopener')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch {
    toast.error('Unable to open that document.')
  } finally {
    viewing.value = null
  }
}

function openReview(doc, action) { review.value = { doc, action, note: '', expires_at: doc.expires_at || '', error: '', saving: false } }
async function submitReview() {
  const r = review.value
  if (r.action === 'reject' && r.note.length < 5) { r.error = 'Give the organization a short reason (at least 5 characters).'; return }
  if (r.expires_at && r.expires_at < today) { r.error = 'That expiry date has already passed.'; return }
  const ok = await alert.confirm(`${r.action === 'approve' ? 'Approve' : 'Reject'} ${labelFor(r.doc.doc_type)} from ${r.doc.organization_name}? The organization will be notified.`, r.action === 'approve' ? 'Approve document' : 'Reject document')
  if (!ok) return
  r.saving = true
  r.error = ''
  try {
    const d = await reviewPlatformDocument({ document_id: r.doc.id, action: r.action, note: r.note, ...(r.action === 'approve' ? { expires_at: r.expires_at || '' } : {}) })
    toast.success(d.message)
    review.value = null
    await load()
  } catch (e) {
    r.error = e?.response?.data?.message || 'Unable to save the decision.'
    r.saving = false
  }
}

onMounted(load)
</script>
