<template>
  <PageShell title="Donations & Goals" subtitle="Voluntary donations through PayMongo, fundraising goals, and the public Support page." icon="lucide:heart-handshake" eyebrow="Platform Executive">
    <!-- Totals -->
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <article v-for="t in tiles" :key="t.label" class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829]">
        <p class="text-2xl font-black dark:text-white">{{ t.value }}</p>
        <p class="mt-1 text-xs font-bold text-slate-500">{{ t.label }}</p>
      </article>
    </div>
    <div class="mt-4 rounded-2xl border px-4 py-3 text-xs leading-5" :class="onlineReady ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-200' : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-100'">
      <p class="flex items-center gap-2 font-black"><Icon :icon="onlineReady ? 'lucide:circle-check' : 'lucide:triangle-alert'" width="15" /> {{ onlineReady ? 'PayMongo is connected.' : 'PayMongo is not connected yet — donors cannot pay online.' }}</p>
      <p class="mt-1">Keys live on the server in <code>api/config/paymongo.json</code> ({"secret_key": "sk_…", "webhook_secret": "whsk_…"}). In the PayMongo dashboard add a webhook for <b>checkout_session.payment.paid</b> pointing to <code class="break-all">{{ webhookUrl }}</code>. Payouts go to the account owner of that PayMongo account.</p>
    </div>

    <div role="tablist" class="mt-6 flex flex-wrap gap-2">
      <button v-for="t in TABS" :key="t.key" type="button" class="rounded-xl px-4 py-2 text-xs font-black transition-colors" :class="tab === t.key ? 'bg-red-600 text-white' : 'bg-base-200 text-slate-600 hover:bg-base-300 dark:bg-white/5 dark:text-white/60'" @click="tab = t.key">{{ t.label }}</button>
    </div>

    <!-- Donations -->
    <section v-if="tab === 'donations'" class="mt-4 rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829]">
      <div class="flex flex-wrap items-center gap-3">
        <input v-model.trim="search" type="search" placeholder="Search reference, name or email" class="min-w-[14rem] flex-1 rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5" @input="debounced" />
        <select v-model="statusFilter" class="rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5" @change="loadDonations">
          <option value="">All statuses</option><option v-for="s in ['paid','pending','failed','cancelled','expired','refunded']" :key="s" :value="s">{{ s }}</option>
        </select>
        <button v-if="can('support.donations.create')" type="button" class="rounded-xl bg-red-600 px-4 py-2.5 text-xs font-black text-white" @click="openRecord">Record a donation</button>
      </div>
      <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Loading…</div>
      <div v-else class="mt-4 space-y-3">
        <article v-for="d in donations" :key="d.id" class="rounded-2xl border border-base-300 p-4 dark:border-white/10">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-lg font-black text-slate-900 dark:text-white">{{ formatPeso(d.amount_centavos, 2) }}</p>
              <p class="mt-0.5 break-words text-xs text-slate-500">{{ d.is_anonymous || !d.donor_name ? 'Anonymous' : d.donor_name }}<template v-if="d.donor_email"> · {{ d.donor_email }}</template></p>
              <p class="mt-0.5 text-xs text-slate-400">{{ d.reference }} · {{ d.source === 'manual' ? 'recorded manually' : 'PayMongo' }}<template v-if="d.method"> · {{ d.method }}</template><template v-if="d.campaign_title"> · {{ d.campaign_title }}</template></p>
              <p v-if="d.message" class="mt-1 break-words text-xs italic text-slate-500">“{{ d.message }}”</p>
              <p v-if="d.admin_note" class="mt-1 break-words text-xs text-amber-700">{{ d.admin_note }}</p>
            </div>
            <div class="text-right">
              <span class="rounded-full px-2.5 py-1 text-[.62rem] font-black uppercase" :class="statusClass(d.status)">{{ d.status }}</span>
              <p class="mt-1 text-xs text-slate-400">{{ shortDate(d.paid_at || d.created_at) }}</p>
            </div>
          </div>
          <div v-if="can('support.donations.update') && (d.status === 'paid' || d.status === 'pending')" class="mt-3">
            <button v-if="d.status === 'paid'" type="button" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-600 dark:border-white/10 dark:text-white/60" @click="openUpdate(d, 'refunded')">Mark refunded</button>
            <button v-else type="button" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-600 dark:border-white/10 dark:text-white/60" @click="openUpdate(d, 'cancelled')">Cancel</button>
          </div>
        </article>
        <p v-if="!donations.length" class="py-12 text-center text-sm text-slate-400">No donations yet.</p>
      </div>
    </section>

    <!-- Goals -->
    <section v-if="tab === 'goals'" class="mt-4 space-y-4">
      <div class="flex justify-end"><button v-if="can('support.campaigns.create')" type="button" class="rounded-xl bg-red-600 px-4 py-2.5 text-xs font-black text-white" @click="openGoal(null)">New goal</button></div>
      <article v-for="c in campaigns" :key="c.id" class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829]">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0"><p class="font-black text-slate-900 dark:text-white">{{ c.title }} <span class="ml-1 rounded-full px-2 py-0.5 text-[.6rem] font-black uppercase" :class="c.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">{{ c.is_active ? 'Showing' : 'Hidden' }}</span></p>
            <p class="mt-1 text-xs text-slate-500">{{ c.purpose }}</p>
            <p class="mt-1 text-[.7rem] text-slate-400">{{ periodText(c) }} · counting {{ c.window_from }} to {{ c.window_to }}</p></div>
          <button v-if="can('support.campaigns.update')" type="button" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold dark:border-white/10 dark:text-white/70" @click="openGoal(c)">Edit</button>
        </div>
        <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-base-200 dark:bg-white/10"><div class="h-full rounded-full bg-red-500" :style="{ width: c.percent + '%' }"></div></div>
        <p class="mt-1.5 text-xs text-slate-500">{{ formatPeso(c.raised_centavos) }} of {{ formatPeso(c.goal_centavos) }} ({{ c.percent }}%) · {{ c.donors }} donation{{ c.donors === 1 ? '' : 's' }}</p>
      </article>
      <p v-if="!campaigns.length && !loading" class="py-10 text-center text-sm text-slate-400">No fundraising goals yet.</p>
    </section>

    <!-- Page settings -->
    <section v-if="tab === 'page' && settings" class="mt-4 rounded-3xl border border-base-300 bg-base-100 p-6 shadow-sm dark:border-white/10 dark:bg-[#071829]">
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="flex items-center gap-3 text-sm font-bold text-slate-700 dark:text-white/75"><input v-model="settings.enabled" type="checkbox" class="toggle toggle-sm" /> Accept donations</label>
        <label class="flex items-center gap-3 text-sm font-bold text-slate-700 dark:text-white/75"><input v-model="settings.show_wall" type="checkbox" class="toggle toggle-sm" /> Show the donor thank-you wall (opt-in names only)</label>
        <label class="block text-xs font-bold text-slate-500 sm:col-span-2">Who receives the donations <HelpTip text="Shown to donors, e.g. the developer team or the organization that owns the PayMongo account. Be transparent about who the money goes to." />
          <input v-model.trim="settings.recipient" maxlength="120" class="fld" /></label>
        <label class="block text-xs font-bold text-slate-500 sm:col-span-2">Intro text<textarea v-model.trim="settings.intro" rows="3" maxlength="600" class="fld"></textarea></label>
        <label class="block text-xs font-bold text-slate-500 sm:col-span-2">Thank-you message<input v-model.trim="settings.thanks" maxlength="300" class="fld" /></label>
        <label class="block text-xs font-bold text-slate-500">Minimum donation (₱{{ limits.min_amount?.[0] }}–{{ limits.min_amount?.[1] }})<input v-model.number="settings.min_amount" type="number" min="20" max="1000" step="1" class="fld" /></label>
        <label class="block text-xs font-bold text-slate-500">Preset amounts (₱, comma separated)<input v-model="presetsText" class="fld" placeholder="50, 100, 200, 500" /></label>
      </div>
      <p class="mt-4 text-xs font-bold text-slate-500">Payment methods</p>
      <div class="mt-2 flex flex-wrap gap-4 text-sm"><label v-for="(label, key) in methodLabels" :key="key" class="flex items-center gap-2"><input v-model="settings.methods" type="checkbox" :value="key" class="checkbox checkbox-xs" /> {{ label }}</label></div>
      <p class="mt-1 text-[.7rem] text-slate-400">Only turn on methods that are activated on your PayMongo account.</p>

      <div class="mt-5 flex items-center justify-between"><p class="text-xs font-bold text-slate-500">Where the money goes <HelpTip text="Shown as bars on the Support page. Percentages must add up to 100." /></p>
        <span class="text-xs font-black" :class="breakdownSum === 100 || !settings.breakdown.length ? 'text-emerald-600' : 'text-red-600'">{{ breakdownSum }}%</span></div>
      <div class="mt-2 space-y-2"><div v-for="(b, i) in settings.breakdown" :key="i" class="flex gap-2">
        <input v-model.trim="b.label" maxlength="60" class="fld !mt-0 flex-1" placeholder="e.g. Hosting and domain" />
        <input v-model.number="b.percent" type="number" min="1" max="100" class="fld !mt-0 w-24" />
        <button type="button" class="rounded-xl px-3 text-slate-400 hover:text-red-600" aria-label="Remove" @click="settings.breakdown.splice(i, 1)"><Icon icon="lucide:x" width="16" /></button></div></div>
      <button type="button" class="mt-2 text-xs font-bold text-[#1976D2]" @click="settings.breakdown.push({ label: '', percent: 10 })">+ Add a line</button>

      <div class="mt-6 flex justify-end"><button v-if="can('support.campaigns.update')" type="button" :disabled="saving" class="rounded-xl bg-red-600 px-5 py-3 text-xs font-black text-white disabled:opacity-50" @click="saveSettings">{{ saving ? 'Saving…' : 'Save support page' }}</button></div>
    </section>

    <!-- Record -->
    <Teleport to="body"><div v-if="rec.open" class="fixed inset-0 z-[10040] grid place-items-center bg-slate-950/50 p-4" @click.self="rec.open = false">
      <form class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl dark:bg-[#0d2943]" @submit.prevent="submitRecord">
        <h3 class="text-lg font-black text-slate-900 dark:text-white">Record a donation</h3>
        <p class="mt-1 text-xs text-slate-500">For money received outside PayMongo (cash, bank transfer, GCash to the project account). Online payments record themselves.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <label class="block text-xs font-bold text-slate-500">Amount (₱)<input v-model.number="rec.amount" type="number" min="1" step="0.01" class="fld" /></label>
          <label class="block text-xs font-bold text-slate-500">Date received<input v-model="rec.paid_on" type="date" :max="today" class="fld" /></label>
          <label class="block text-xs font-bold text-slate-500">Donor name <span class="font-normal">(blank = anonymous)</span><input v-model.trim="rec.donor_name" maxlength="120" class="fld" /></label>
          <label class="block text-xs font-bold text-slate-500">How received<select v-model="rec.method" class="fld"><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="gcash_manual">GCash / Maya (direct)</option><option value="other">Other</option></select></label>
          <label class="block text-xs font-bold text-slate-500 sm:col-span-2">Goal<select v-model.number="rec.campaign_id" class="fld"><option :value="0">No specific goal</option><option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.title }}</option></select></label>
          <label class="block text-xs font-bold text-slate-500 sm:col-span-2">Note<input v-model.trim="rec.note" maxlength="250" class="fld" /></label>
        </div>
        <p v-if="rec.error" class="mt-3 text-xs font-bold text-red-600">{{ rec.error }}</p>
        <div class="mt-5 flex justify-end gap-2"><button type="button" class="rounded-xl border border-base-300 px-4 py-2.5 text-xs font-bold dark:border-white/10 dark:text-white/70" @click="rec.open = false">Cancel</button><button type="submit" :disabled="rec.saving" class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-black text-white disabled:opacity-50">{{ rec.saving ? 'Saving…' : 'Record' }}</button></div>
      </form></div></Teleport>

    <!-- Refund / cancel -->
    <Teleport to="body"><div v-if="upd.open" class="fixed inset-0 z-[10040] grid place-items-center bg-slate-950/50 p-4" @click.self="upd.open = false">
      <form class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl dark:bg-[#0d2943]" @submit.prevent="submitUpdate">
        <h3 class="text-lg font-black text-slate-900 dark:text-white">{{ upd.status === 'refunded' ? 'Mark as refunded' : 'Cancel donation' }}</h3>
        <p class="mt-1 text-xs text-slate-500">{{ upd.donation?.reference }} · {{ formatPeso(upd.donation?.amount_centavos, 2) }}<template v-if="upd.status === 'refunded'"> — refund it in the PayMongo dashboard first; this only updates our records and the goal total.</template></p>
        <label class="mt-4 block text-xs font-bold text-slate-500">Reason (required)<input v-model.trim="upd.note" maxlength="250" class="fld" /></label>
        <p v-if="upd.error" class="mt-3 text-xs font-bold text-red-600">{{ upd.error }}</p>
        <div class="mt-5 flex justify-end gap-2"><button type="button" class="rounded-xl border border-base-300 px-4 py-2.5 text-xs font-bold dark:border-white/10 dark:text-white/70" @click="upd.open = false">Back</button><button type="submit" :disabled="upd.saving" class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-black text-white disabled:opacity-50">Confirm</button></div>
      </form></div></Teleport>

    <!-- Goal editor -->
    <Teleport to="body"><div v-if="goal.open" class="fixed inset-0 z-[10040] grid place-items-center bg-slate-950/50 p-4" @click.self="goal.open = false">
      <form class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl dark:bg-[#0d2943]" @submit.prevent="submitGoal">
        <h3 class="text-lg font-black text-slate-900 dark:text-white">{{ goal.id ? 'Edit goal' : 'New fundraising goal' }}</h3>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <label class="block text-xs font-bold text-slate-500 sm:col-span-2">Name<input v-model.trim="goal.title" maxlength="120" class="fld" placeholder="e.g. Keep DASCARE running" /></label>
          <label class="block text-xs font-bold text-slate-500 sm:col-span-2">What the money is for <span class="font-normal">(donors see this)</span><textarea v-model.trim="goal.purpose" rows="3" maxlength="500" class="fld"></textarea></label>
          <label class="block text-xs font-bold text-slate-500">Goal period
            <select v-model="goal.period" class="fld"><option value="month">Per month (resets each month)</option><option value="year">Per year (resets each year)</option><option value="custom">Fundraising campaign (dates)</option></select></label>
          <label class="block text-xs font-bold text-slate-500">Goal amount (₱)<input v-model.number="goal.goal" type="number" min="100" max="10000000" step="1" class="fld" /></label>
          <template v-if="goal.period === 'custom'">
            <label class="block text-xs font-bold text-slate-500">Starts<input v-model="goal.starts_on" type="date" class="fld" /></label>
            <label class="block text-xs font-bold text-slate-500">Ends<input v-model="goal.ends_on" type="date" :min="goal.starts_on" class="fld" /></label>
          </template>
          <label class="flex items-center gap-2 text-sm font-bold text-slate-700 dark:text-white/75"><input v-model="goal.is_active" type="checkbox" class="toggle toggle-sm" /> Show on the Support page</label>
          <label class="block text-xs font-bold text-slate-500">Order (0 = first)<input v-model.number="goal.sort_order" type="number" min="0" max="1000" class="fld" /></label>
        </div>
        <p v-if="goal.error" class="mt-3 text-xs font-bold text-red-600">{{ goal.error }}</p>
        <div class="mt-5 flex justify-end gap-2"><button type="button" class="rounded-xl border border-base-300 px-4 py-2.5 text-xs font-bold dark:border-white/10 dark:text-white/70" @click="goal.open = false">Cancel</button><button type="submit" :disabled="goal.saving" class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-black text-white disabled:opacity-50">{{ goal.saving ? 'Saving…' : 'Save goal' }}</button></div>
      </form></div></Teleport>
  </PageShell>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Icon } from '@iconify/vue'
import PageShell from '@/components/management/PageShell.vue'
import HelpTip from '@/components/forms/HelpTip.vue'
import api from '@/services/api'
import { fetchCampaigns, fetchDonations, formatPeso, recordDonation, saveCampaign, saveSupportSettings, updateDonation } from '@/services/support'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'
import { isPersonName } from '@/utils/validation'

const toast = useToast()
const alert = useAlert()
const { hasPermission } = useSession()
const can = (k) => hasPermission(k)
const TABS = [{ key: 'donations', label: 'Donations' }, { key: 'goals', label: 'Goals' }, { key: 'page', label: 'Support page' }]
const tab = ref('donations')
const loading = ref(true)
const saving = ref(false)
const donations = ref([])
const stats = ref({})
const onlineReady = ref(false)
const campaigns = ref([])
const settings = ref(null)
const limits = ref({})
const methodLabels = ref({})
const search = ref('')
const statusFilter = ref('')
const today = new Date().toISOString().slice(0, 10)
const webhookUrl = computed(() => `${String(api.defaults.baseURL || '').replace(/\/$/, '')}/support/webhook.php`)

const tiles = computed(() => [
  { label: 'This month', value: formatPeso(stats.value.paid_month) },
  { label: 'This year', value: formatPeso(stats.value.paid_year) },
  { label: 'All time', value: formatPeso(stats.value.paid_total) },
  { label: 'Paid donations · pending', value: `${stats.value.paid_count ?? 0} · ${stats.value.pending_count ?? 0}` },
])
const statusClass = (s) => ({ paid: 'bg-emerald-100 text-emerald-700', pending: 'bg-amber-100 text-amber-700', refunded: 'bg-slate-200 text-slate-700', failed: 'bg-red-100 text-red-700', cancelled: 'bg-slate-100 text-slate-500', expired: 'bg-slate-100 text-slate-500' }[s] || 'bg-slate-100 text-slate-500')
const shortDate = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '')
const periodText = (c) => (c.period === 'month' ? 'Per month' : c.period === 'year' ? 'Per year' : 'Campaign')

let timer = null
const debounced = () => { clearTimeout(timer); timer = setTimeout(loadDonations, 300) }
async function loadDonations() {
  try { const d = await fetchDonations({ status: statusFilter.value, search: search.value }); donations.value = d.items || []; stats.value = d.stats || {}; onlineReady.value = !!d.online_ready } catch (e) { toast.error(e?.response?.data?.message || 'Unable to load donations.') }
}
async function loadGoals() {
  try { const d = await fetchCampaigns(); campaigns.value = d.campaigns || []; settings.value = d.settings; limits.value = d.limits || {}; methodLabels.value = d.methods || {}; onlineReady.value = !!d.online_ready; presetsText.value = (d.settings?.presets || []).join(', ') } catch (e) { toast.error(e?.response?.data?.message || 'Unable to load goals.') }
}
async function load() { loading.value = true; await Promise.all([loadDonations(), loadGoals()]); loading.value = false }

// ---- record
const rec = reactive({ open: false, amount: null, paid_on: today, donor_name: '', method: 'cash', campaign_id: 0, note: '', error: '', saving: false })
function openRecord() { Object.assign(rec, { open: true, amount: null, paid_on: today, donor_name: '', method: 'cash', campaign_id: campaigns.value[0]?.id || 0, note: '', error: '', saving: false }) }
async function submitRecord() {
  rec.error = ''
  if (!(rec.amount >= 1)) { rec.error = 'Enter the amount received.'; return }
  if (rec.donor_name && !isPersonName(rec.donor_name)) { rec.error = 'Donor name can only contain letters, spaces, apostrophes, periods and hyphens.'; return }
  if (!(await alert.confirm(`Record ${formatPeso(rec.amount * 100, 2)} received${rec.donor_name ? ' from ' + rec.donor_name : ''}?`, 'Record donation'))) return
  rec.saving = true
  try { const d = await recordDonation({ amount: rec.amount, paid_on: rec.paid_on, donor_name: rec.donor_name, method: rec.method, campaign_id: rec.campaign_id || null, note: rec.note }); toast.success(d.message); rec.open = false; await load() } catch (e) { rec.error = e?.response?.data?.message || 'Unable to record the donation.' } finally { rec.saving = false }
}

// ---- refund / cancel
const upd = reactive({ open: false, donation: null, status: '', note: '', error: '', saving: false })
function openUpdate(d, status) { Object.assign(upd, { open: true, donation: d, status, note: '', error: '', saving: false }) }
async function submitUpdate() {
  if (upd.note.length < 5) { upd.error = 'Add a short reason (at least 5 characters).'; return }
  if (!(await alert.confirm(`${upd.status === 'refunded' ? 'Mark this donation as refunded' : 'Cancel this pending donation'}? This changes the totals.`, 'Confirm'))) return
  upd.saving = true
  try { const d = await updateDonation({ id: upd.donation.id, status: upd.status, note: upd.note }); toast.success(d.message); upd.open = false; await load() } catch (e) { upd.error = e?.response?.data?.message || 'Unable to update the donation.' } finally { upd.saving = false }
}

// ---- goals
const goal = reactive({ open: false, id: 0, title: '', purpose: '', period: 'month', goal: null, starts_on: '', ends_on: '', is_active: true, sort_order: 0, error: '', saving: false })
function openGoal(c) {
  Object.assign(goal, { open: true, id: c?.id || 0, title: c?.title || '', purpose: c?.purpose || '', period: c?.period || 'month', goal: c ? c.goal_centavos / 100 : null, starts_on: c?.starts_on || '', ends_on: c?.ends_on || '', is_active: c ? c.is_active : true, sort_order: 0, error: '', saving: false })
}
async function submitGoal() {
  goal.error = ''
  if (goal.title.length < 3) { goal.error = 'Give the goal a name.'; return }
  if (goal.purpose.length < 10) { goal.error = 'Say what the money is for (at least a short sentence).'; return }
  if (!(goal.goal >= 100)) { goal.error = 'The goal must be at least ₱100.'; return }
  if (goal.period === 'custom' && (!goal.starts_on || !goal.ends_on || goal.ends_on < goal.starts_on)) { goal.error = 'Choose a start date and an end date after it.'; return }
  if (!(await alert.confirm(`Save the goal “${goal.title}” (${formatPeso(goal.goal * 100)} ${goal.period === 'month' ? 'per month' : goal.period === 'year' ? 'per year' : 'campaign'})? Donors see it on the Support page${goal.is_active ? '' : ' once shown'}.`, 'Save goal'))) return
  goal.saving = true
  try { const d = await saveCampaign({ id: goal.id, title: goal.title, purpose: goal.purpose, period: goal.period, goal: goal.goal, starts_on: goal.starts_on, ends_on: goal.ends_on, is_active: goal.is_active, sort_order: goal.sort_order }); toast.success(d.message); goal.open = false; await load() } catch (e) { goal.error = e?.response?.data?.message || 'Unable to save the goal.' } finally { goal.saving = false }
}

// ---- page settings
const presetsText = ref('')
const breakdownSum = computed(() => (settings.value?.breakdown || []).reduce((n, b) => n + (Number(b.percent) || 0), 0))
async function saveSettings() {
  const s = settings.value
  const presets = presetsText.value.split(',').map((x) => Number(x.trim())).filter((x) => x)
  if (!presets.length || presets.some((p) => !Number.isInteger(p) || p < s.min_amount)) { toast.error('Preset amounts must be whole pesos, at least the minimum donation.', 'Check the presets'); return }
  if (s.breakdown.length && breakdownSum.value !== 100) { toast.error(`"Where the money goes" must add up to 100% (now ${breakdownSum.value}%).`, 'Check the percentages'); return }
  if (!(await alert.confirm('Save the Support page? The change is visible to the public immediately.', 'Save support page'))) return
  saving.value = true
  try { const d = await saveSupportSettings({ ...s, presets }); toast.success(d.message); await loadGoals() } catch (e) { toast.error(e?.response?.data?.message || 'Unable to save the support page.') } finally { saving.value = false }
}

onMounted(load)
</script>

<style scoped>
.fld { margin-top: .375rem; width: 100%; border-radius: .75rem; border: 1px solid hsl(var(--bc) / .12); background: transparent; padding: .625rem .75rem; font-size: .875rem; color: inherit; }
.fld:focus { outline: none; border-color: #dc2626; }
</style>
