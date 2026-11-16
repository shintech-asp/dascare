<template>
  <PageShell title="Sponsors" subtitle="Approve and manage the sponsors shown on the website and app. Sponsors never appear on emergency screens." icon="lucide:handshake" eyebrow="Platform Executive">
    <div role="tablist" class="flex flex-wrap gap-2">
      <button v-for="t in TABS" :key="t.key" type="button" class="rounded-xl px-4 py-2 text-xs font-black transition-colors" :class="tab === t.key ? 'bg-red-600 text-white' : 'bg-base-200 text-slate-600 hover:bg-base-300 dark:bg-white/5 dark:text-white/60'" @click="tab = t.key">
        {{ t.label }}<span v-if="t.key === 'inquiries' && newInquiries" class="ml-1.5 rounded-full bg-white px-1.5 py-0.5 text-[.6rem] text-red-600">{{ newInquiries }}</span>
      </button>
    </div>

    <!-- Sponsors -->
    <section v-if="tab === 'sponsors'" class="mt-4">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="max-w-xl text-xs text-slate-500">Allowed: healthcare, pharmacy, insurers/HMOs, safety training, schools, local businesses, civic groups, government. Never: politics, gambling, alcohol, tobacco/vape, lending apps, health-claim products.<HelpTip title="Verify before showing" text="Check the sponsor's business registration and that the content is appropriate. You must confirm this each time you switch a sponsor on." /></p>
        <button v-if="can('support.sponsors.create')" type="button" class="rounded-xl bg-red-600 px-4 py-2.5 text-xs font-black text-white" @click="openSponsor(null)">Add sponsor</button>
      </div>
      <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Loading…</div>
      <div v-else class="mt-4 grid gap-3 lg:grid-cols-2">
        <article v-for="s in sponsors" :key="s.id" class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829]">
          <div class="flex items-start gap-3">
            <div class="grid h-14 w-14 flex-shrink-0 place-items-center overflow-hidden rounded-xl bg-base-200 dark:bg-white/5"><img v-if="logos[s.id]" :src="logos[s.id]" :alt="s.name" class="h-full w-full object-contain" /><Icon v-else icon="lucide:building-2" width="22" class="text-slate-400" /></div>
            <div class="min-w-0 flex-1">
              <p class="break-words font-black text-slate-900 dark:text-white">{{ s.name }}</p>
              <p class="text-xs text-slate-500">{{ categories[s.category] }} · {{ s.tier }}</p>
              <p v-if="s.tagline" class="mt-0.5 break-words text-xs text-slate-400">{{ s.tagline }}</p>
              <p class="mt-1 text-[.7rem] text-slate-400"><template v-if="s.starts_on || s.ends_on">{{ s.starts_on || '…' }} → {{ s.ends_on || 'no end' }}</template><template v-else>No end date</template></p>
            </div>
            <span class="rounded-full px-2.5 py-1 text-[.62rem] font-black uppercase" :class="statusClass(s.status)">{{ s.status }}</span>
          </div>
          <div class="mt-3 flex flex-wrap gap-2">
            <button v-if="can('support.sponsors.update')" type="button" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold dark:border-white/10 dark:text-white/70" @click="openSponsor(s)">Edit</button>
            <button v-if="can('support.sponsors.update') && s.status !== 'active'" type="button" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-black text-white" @click="openStatus(s, 'active')">Show</button>
            <button v-if="can('support.sponsors.update') && s.status === 'active'" type="button" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-black text-amber-800" @click="changeStatus(s, 'paused')">Pause</button>
            <button v-if="can('support.sponsors.update') && s.status !== 'ended'" type="button" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-500 dark:border-white/10" @click="changeStatus(s, 'ended')">Mark ended</button>
            <button v-if="can('support.sponsors.delete')" type="button" class="rounded-xl px-3 py-2 text-xs font-bold text-red-600" @click="remove(s)">Delete</button>
          </div>
        </article>
        <p v-if="!sponsors.length" class="py-12 text-center text-sm text-slate-400 lg:col-span-2">No sponsors yet.</p>
      </div>
    </section>

    <!-- Inquiries -->
    <section v-if="tab === 'inquiries'" class="mt-4 space-y-3">
      <article v-for="q in inquiries" :key="q.id" class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829]">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0"><p class="break-words font-black text-slate-900 dark:text-white">{{ q.organization_name }}</p>
            <p class="text-xs text-slate-500">{{ q.category_label }}</p>
            <p class="mt-1 break-words text-xs text-slate-500">{{ q.contact_name }} · <a :href="'mailto:' + q.email" class="text-[#1976D2]">{{ q.email }}</a><template v-if="q.phone"> · {{ q.phone }}</template></p>
            <a v-if="q.website_url" :href="q.website_url" target="_blank" rel="noopener" class="mt-0.5 block break-all text-xs text-[#1976D2]">{{ q.website_url }}</a>
            <p v-if="q.message" class="mt-2 whitespace-pre-line break-words text-sm text-slate-600 dark:text-white/60">{{ q.message }}</p>
            <p class="mt-1 text-[.7rem] text-slate-400">{{ shortDate(q.created_at) }}</p></div>
          <span class="rounded-full px-2.5 py-1 text-[.62rem] font-black uppercase" :class="inqClass(q.status)">{{ q.status }}</span>
        </div>
        <div v-if="can('support.sponsors.update')" class="mt-3 flex flex-wrap items-center gap-2">
          <select v-model="q._status" class="rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-xs dark:border-white/10 dark:bg-white/5"><option value="new">New</option><option value="contacted">Contacted</option><option value="accepted">Accepted</option><option value="declined">Declined</option></select>
          <input v-model.trim="q._note" maxlength="250" placeholder="Note (optional)" class="min-w-[10rem] flex-1 rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-xs dark:border-white/10 dark:bg-white/5" />
          <button type="button" class="rounded-xl bg-red-600 px-3 py-2 text-xs font-black text-white" :disabled="q._status === q.status && (q._note || '') === (q.admin_note || '')" @click="saveInquiry(q)">Save</button>
        </div>
      </article>
      <p v-if="!inquiries.length && !loading" class="py-12 text-center text-sm text-slate-400">No inquiries yet.</p>
    </section>

    <!-- Sponsor editor -->
    <Teleport to="body"><div v-if="ed.open" class="fixed inset-0 z-[10040] grid place-items-center bg-slate-950/50 p-4" @click.self="ed.open = false">
      <form class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl dark:bg-[#0d2943]" @submit.prevent="submitSponsor">
        <h3 class="text-lg font-black text-slate-900 dark:text-white">{{ ed.id ? 'Edit sponsor' : 'Add sponsor' }}</h3>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <label class="block text-xs font-bold text-slate-500 sm:col-span-2">Name<input v-model.trim="ed.name" maxlength="120" class="fld" /></label>
          <label class="block text-xs font-bold text-slate-500 sm:col-span-2">Tagline <span class="font-normal">(optional, shown for partners)</span><input v-model.trim="ed.tagline" maxlength="160" class="fld" /></label>
          <label class="block text-xs font-bold text-slate-500">Category<select v-model="ed.category" class="fld"><option value="" disabled>Select…</option><option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option></select></label>
          <label class="block text-xs font-bold text-slate-500">Tier<select v-model="ed.tier" class="fld"><option value="supporter">Supporter (name only)</option><option value="partner">Partner (logo + link)</option><option value="presenting">Presenting (large logo)</option></select></label>
          <label class="block text-xs font-bold text-slate-500 sm:col-span-2">Website <span class="font-normal">(optional, https://)</span><input v-model.trim="ed.website_url" maxlength="255" class="fld" /></label>
          <label class="block text-xs font-bold text-slate-500">Starts <span class="font-normal">(optional)</span><input v-model="ed.starts_on" type="date" class="fld" /></label>
          <label class="block text-xs font-bold text-slate-500">Ends <span class="font-normal">(optional)</span><input v-model="ed.ends_on" type="date" :min="ed.starts_on" class="fld" /></label>
          <label class="block text-xs font-bold text-slate-500 sm:col-span-2">Logo <span class="font-normal">(PNG/JPG/WEBP, ≤ 1 MB, at least 64 px)</span><input type="file" accept="image/png,image/jpeg,image/webp" class="mt-1.5 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-red-600 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white" @change="onLogo" /></label>
          <label v-if="ed.id && ed.has_logo" class="flex items-center gap-2 text-xs text-slate-600 sm:col-span-2"><input v-model="ed.remove_logo" type="checkbox" class="checkbox checkbox-xs" /> Remove the current logo</label>
          <label class="block text-xs font-bold text-slate-500">Order (0 = first)<input v-model.number="ed.sort_order" type="number" min="0" max="1000" class="fld" /></label>
          <label class="block text-xs font-bold text-slate-500 sm:col-span-2">Internal note <span class="font-normal">(not public)</span><input v-model.trim="ed.admin_note" maxlength="250" class="fld" /></label>
        </div>
        <p v-if="ed.error" class="mt-3 text-xs font-bold text-red-600">{{ ed.error }}</p>
        <div class="mt-5 flex justify-end gap-2"><button type="button" class="rounded-xl border border-base-300 px-4 py-2.5 text-xs font-bold dark:border-white/10 dark:text-white/70" @click="ed.open = false">Cancel</button><button type="submit" :disabled="ed.saving" class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-black text-white disabled:opacity-50">{{ ed.saving ? 'Saving…' : 'Save sponsor' }}</button></div>
      </form></div></Teleport>

    <!-- Show (verify) -->
    <Teleport to="body"><div v-if="vf.open" class="fixed inset-0 z-[10040] grid place-items-center bg-slate-950/50 p-4" @click.self="vf.open = false">
      <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl dark:bg-[#0d2943]">
        <h3 class="text-lg font-black text-slate-900 dark:text-white">Show {{ vf.sponsor?.name }} publicly?</h3>
        <label class="mt-4 flex items-start gap-2 text-sm text-slate-600 dark:text-white/65"><input v-model="vf.verified" type="checkbox" class="checkbox checkbox-sm mt-0.5" /> I verified this sponsor (business registration, category, logo and website) and it follows the sponsor policy.</label>
        <p v-if="vf.error" class="mt-3 text-xs font-bold text-red-600">{{ vf.error }}</p>
        <div class="mt-5 flex justify-end gap-2"><button type="button" class="rounded-xl border border-base-300 px-4 py-2.5 text-xs font-bold dark:border-white/10 dark:text-white/70" @click="vf.open = false">Cancel</button><button type="button" :disabled="!vf.verified || vf.saving" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-black text-white disabled:opacity-50" @click="confirmShow">Show on website and app</button></div>
      </div></div></Teleport>
  </PageShell>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { Icon } from '@iconify/vue'
import PageShell from '@/components/management/PageShell.vue'
import HelpTip from '@/components/forms/HelpTip.vue'
import { deleteSponsor, fetchInquiries, fetchSponsorLogoBlob, fetchSponsors, saveSponsor, setSponsorStatus, updateInquiry } from '@/services/support'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'
import { looksLikeText } from '@/utils/validation'

const toast = useToast()
const alert = useAlert()
const { hasPermission } = useSession()
const can = (k) => hasPermission(k)
const TABS = [{ key: 'sponsors', label: 'Sponsors' }, { key: 'inquiries', label: 'Inquiries' }]
const tab = ref('sponsors')
const loading = ref(true)
const sponsors = ref([])
const inquiries = ref([])
const categories = ref({})
const logos = reactive({})
const newInquiries = computed(() => inquiries.value.filter((q) => q.status === 'new').length)

const statusClass = (s) => ({ active: 'bg-emerald-100 text-emerald-700', pending: 'bg-amber-100 text-amber-700', paused: 'bg-slate-200 text-slate-600', ended: 'bg-slate-100 text-slate-500' }[s])
const inqClass = (s) => ({ new: 'bg-amber-100 text-amber-700', contacted: 'bg-blue-100 text-blue-700', accepted: 'bg-emerald-100 text-emerald-700', declined: 'bg-slate-100 text-slate-500' }[s])
const shortDate = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '')

async function loadLogo(s) {
  if (!s.has_logo) return
  try { logos[s.id] = URL.createObjectURL(await fetchSponsorLogoBlob(s.id)) } catch { /* placeholder */ }
}
async function load() {
  loading.value = true
  try {
    const [s, q] = await Promise.all([fetchSponsors(), fetchInquiries()])
    for (const u of Object.values(logos)) URL.revokeObjectURL(u)
    Object.keys(logos).forEach((k) => delete logos[k])
    sponsors.value = s.sponsors || []
    categories.value = s.categories || {}
    inquiries.value = (q.inquiries || []).map((x) => ({ ...x, _status: x.status, _note: x.admin_note || '' }))
    sponsors.value.forEach(loadLogo)
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Unable to load sponsors.')
  } finally {
    loading.value = false
  }
}
onBeforeUnmount(() => Object.values(logos).forEach((u) => URL.revokeObjectURL(u)))

// ---- editor
const ed = reactive({ open: false, id: 0, name: '', tagline: '', category: '', tier: 'supporter', website_url: '', starts_on: '', ends_on: '', sort_order: 0, admin_note: '', has_logo: false, remove_logo: false, logo: null, error: '', saving: false })
function openSponsor(s) {
  Object.assign(ed, { open: true, id: s?.id || 0, name: s?.name || '', tagline: s?.tagline || '', category: s?.category || '', tier: s?.tier || 'supporter', website_url: s?.website_url || '', starts_on: s?.starts_on || '', ends_on: s?.ends_on || '', sort_order: s?.sort_order || 0, admin_note: s?.admin_note || '', has_logo: !!s?.has_logo, remove_logo: false, logo: null, error: '', saving: false })
}
function onLogo(e) {
  const f = e.target.files?.[0] || null
  ed.error = ''
  if (f && f.size > 1024 * 1024) { ed.error = 'The logo must be 1 MB or smaller.'; e.target.value = ''; ed.logo = null; return }
  if (f && !/^image\/(png|jpeg|webp)$/.test(f.type)) { ed.error = 'The logo must be a PNG, JPG or WEBP image.'; e.target.value = ''; ed.logo = null; return }
  ed.logo = f
}
async function submitSponsor() {
  ed.error = ''
  if (ed.name.length < 2 || !looksLikeText(ed.name, 2)) { ed.error = "Enter the sponsor's name."; return }
  if (!ed.category) { ed.error = 'Choose an allowed category.'; return }
  if (ed.website_url && !/^https:\/\/\S+\.\S+$/i.test(ed.website_url)) { ed.error = 'The website must start with https://'; return }
  if (ed.starts_on && ed.ends_on && ed.ends_on < ed.starts_on) { ed.error = 'The end date cannot be before the start date.'; return }
  if (!(await alert.confirm(`Save ${ed.name}?`, 'Save sponsor'))) return
  ed.saving = true
  try {
    const fd = new FormData()
    for (const k of ['name', 'tagline', 'category', 'tier', 'website_url', 'starts_on', 'ends_on', 'sort_order', 'admin_note']) fd.append(k, ed[k] ?? '')
    if (ed.id) fd.append('id', ed.id)
    if (ed.remove_logo) fd.append('remove_logo', '1')
    if (ed.logo) fd.append('logo', ed.logo)
    const d = await saveSponsor(fd)
    toast.success(d.message)
    ed.open = false
    await load()
  } catch (e) {
    ed.error = e?.response?.data?.message || 'Unable to save the sponsor.'
  } finally {
    ed.saving = false
  }
}

// ---- status
const vf = reactive({ open: false, sponsor: null, verified: false, error: '', saving: false })
function openStatus(s) { Object.assign(vf, { open: true, sponsor: s, verified: false, error: '', saving: false }) }
async function confirmShow() {
  vf.saving = true
  try { const d = await setSponsorStatus({ id: vf.sponsor.id, status: 'active', verified: true }); toast.success(d.message); vf.open = false; await load() } catch (e) { vf.error = e?.response?.data?.message || 'Unable to show this sponsor.' } finally { vf.saving = false }
}
async function changeStatus(s, status) {
  if (!(await alert.confirm(`${status === 'paused' ? 'Hide' : 'Mark as ended'} ${s.name}? It disappears from the website and app.`, 'Change sponsor'))) return
  try { const d = await setSponsorStatus({ id: s.id, status }); toast.success(d.message); await load() } catch (e) { toast.error(e?.response?.data?.message || 'Unable to change that.') }
}
async function remove(s) {
  if (!(await alert.confirm(`Delete ${s.name} permanently? This cannot be undone.`, 'Delete sponsor'))) return
  try { const d = await deleteSponsor(s.id); toast.success(d.message); await load() } catch (e) { toast.error(e?.response?.data?.message || 'Unable to delete that sponsor.') }
}
async function saveInquiry(q) {
  if (!(await alert.confirm(`Mark the inquiry from ${q.organization_name} as ${q._status}?`, 'Update inquiry'))) return
  try { const d = await updateInquiry({ id: q.id, status: q._status, admin_note: q._note }); toast.success(d.message); await load() } catch (e) { toast.error(e?.response?.data?.message || 'Unable to update that inquiry.') }
}

onMounted(load)
</script>

<style scoped>
.fld { margin-top: .375rem; width: 100%; border-radius: .75rem; border: 1px solid hsl(var(--bc) / .12); background: transparent; padding: .625rem .75rem; font-size: .875rem; color: inherit; }
.fld:focus { outline: none; border-color: #dc2626; }
</style>
