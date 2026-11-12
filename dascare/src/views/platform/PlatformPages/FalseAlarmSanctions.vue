<template>
  <!-- Platform → False Alarm Sanctions: the configurable rules, citizens'
       appeals, every strike (remove a wrong one) and accounts suspended for
       false alarms (lift). dascare_api/platform/sanctions/*,
       reusables/false_alarm_sanctions.php -->
  <PageShell title="False Alarm Sanctions" subtitle="Strikes from requests confirmed as false alarms, the sanctions they trigger, and citizens' appeals." icon="lucide:gavel" eyebrow="Citizen governance">
    <div v-if="loading && !data" class="py-20 text-center text-sm text-slate-400">Loading…</div>
    <div v-else-if="data" class="space-y-6">
      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <article v-for="c in cards" :key="c.label" class="min-w-0 rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829]">
          <p class="text-[.68rem] font-black uppercase tracking-wide text-slate-400 dark:text-white/35">{{ c.label }}</p>
          <p class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ c.value }}</p>
        </article>
      </div>

      <!-- Rules -->
      <section class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829] sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="flex items-center gap-2 text-sm font-black text-slate-900 dark:text-white"><Icon icon="lucide:sliders-horizontal" width="16" class="text-red-600" /> Sanction rules</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-white/45">Changes apply to new strikes. A suspended citizen can still send SOS as a guest unless their number is blocked below.</p>
          </div>
          <label class="flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-white/70">
            <input v-model="form.enabled" type="checkbox" :disabled="!canEditSettings" class="toggle toggle-sm border-slate-300 bg-slate-200 text-red-600 [--tglbg:white] checked:border-red-600 checked:bg-red-600" /> Sanctions on
          </label>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-2" :class="form.enabled ? '' : 'opacity-50'">
          <div>
            <p class="text-[0.68rem] font-black uppercase tracking-wide text-slate-400">Citizen accounts</p>
            <div class="mt-2 grid grid-cols-2 gap-3">
              <label v-for="f in accountFields" :key="f.key" class="block min-w-0">
                <span class="mb-1 block text-xs font-bold text-slate-600 dark:text-white/55">{{ f.label }}</span>
                <span class="flex items-center gap-2">
                  <input v-model.number="form[f.key]" type="number" :min="limits[f.key]?.[0]" :max="limits[f.key]?.[1]" :disabled="!canEditSettings" class="w-full min-w-0 rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                  <span class="flex-shrink-0 text-xs text-slate-400">{{ f.unit }}</span>
                </span>
              </label>
            </div>
            <p class="mt-3 rounded-xl bg-base-200/60 px-3 py-2 text-xs leading-5 text-slate-600 dark:bg-white/5 dark:text-white/55">
              Within {{ form.window_days }} days: {{ form.warn_at }} {{ plural(form.warn_at, 'strike') }} → warning · {{ form.suspend_at }} → suspended {{ form.suspend_days }} days · {{ form.review_at }} → suspended until an admin lifts it.
            </p>
          </div>
          <div>
            <label class="flex items-center gap-2 text-[0.68rem] font-black uppercase tracking-wide text-slate-400">
              <input v-model="form.guest_enabled" type="checkbox" :disabled="!canEditSettings" class="checkbox checkbox-xs" /> Guests (by phone number)
            </label>
            <div class="mt-2 grid grid-cols-2 gap-3" :class="form.guest_enabled ? '' : 'opacity-50'">
              <label v-for="f in guestFields" :key="f.key" class="block min-w-0">
                <span class="mb-1 block text-xs font-bold text-slate-600 dark:text-white/55">{{ f.label }}</span>
                <span class="flex items-center gap-2">
                  <input v-model.number="form[f.key]" type="number" :min="limits[f.key]?.[0]" :max="limits[f.key]?.[1]" :disabled="!canEditSettings || !form.guest_enabled" class="w-full min-w-0 rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                  <span class="flex-shrink-0 text-xs text-slate-400">{{ f.unit }}</span>
                </span>
              </label>
            </div>
            <p class="mt-3 rounded-xl bg-base-200/60 px-3 py-2 text-xs leading-5 text-slate-600 dark:bg-white/5 dark:text-white/55">
              {{ form.guest_block_at }} {{ plural(form.guest_block_at, 'false alarm') }} from one number within {{ form.guest_window_days }} days → guest requests from it paused for {{ form.guest_block_days }} days.
            </p>
          </div>
        </div>
        <div v-if="canEditSettings" class="mt-5 flex justify-end">
          <button type="button" :disabled="saving || !dirty" class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-black text-white disabled:opacity-50" @click="saveSettings">{{ saving ? 'Saving…' : 'Save rules' }}</button>
        </div>
      </section>

      <!-- Appeals -->
      <section class="overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#071829]">
        <header class="flex items-center gap-2 border-b border-base-300 px-5 py-4 dark:border-white/10"><Icon icon="lucide:message-square-reply" width="16" class="text-red-600" /><h2 class="text-sm font-black text-slate-900 dark:text-white">Appeals</h2><span v-if="data.pending_appeals" class="rounded-full bg-amber-100 px-2 py-0.5 text-[0.6rem] font-black text-amber-700">{{ data.pending_appeals }} pending</span></header>
        <div class="divide-y divide-base-300 dark:divide-white/5">
          <article v-for="a in data.appeals" :key="a.id" class="px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="break-words text-sm font-black text-slate-800 dark:text-white/80">{{ a.citizen_name }} <span class="font-normal text-slate-400">· {{ a.citizen_email }}</span></p>
                <p class="mt-0.5 break-words text-xs text-slate-500 dark:text-white/45">{{ a.reference_number }} · marked false alarm by {{ a.closed_by || 'a platform admin' }}<template v-if="a.strike_reason"> — “{{ a.strike_reason }}”</template></p>
                <p class="mt-2 whitespace-pre-line break-words rounded-xl bg-base-200/60 p-3 text-sm text-slate-700 dark:bg-white/5 dark:text-white/70">{{ a.message }}</p>
                <p v-if="a.status !== 'pending'" class="mt-1 break-words text-xs text-slate-500">{{ a.status === 'accepted' ? 'Accepted' : 'Rejected' }} by {{ a.reviewed_by || '—' }}<template v-if="a.review_note"> — {{ a.review_note }}</template></p>
              </div>
              <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[0.6rem] font-black uppercase" :class="a.status === 'pending' ? 'bg-amber-100 text-amber-700' : a.status === 'accepted' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">{{ a.status }}</span>
            </div>
            <div v-if="a.status === 'pending' && canUpdate" class="mt-3 flex flex-wrap gap-2">
              <button type="button" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-black text-white" @click="openAction('accept', a)">Accept — remove strike</button>
              <button type="button" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-600 dark:border-white/10 dark:text-white/60" @click="openAction('reject', a)">Reject</button>
            </div>
          </article>
          <p v-if="!data.appeals.length" class="py-10 text-center text-sm text-slate-400">No appeals.</p>
        </div>
      </section>

      <!-- Suspended -->
      <section v-if="data.suspended.length" class="overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#071829]">
        <header class="flex items-center gap-2 border-b border-base-300 px-5 py-4 dark:border-white/10"><Icon icon="lucide:ban" width="16" class="text-red-600" /><h2 class="text-sm font-black text-slate-900 dark:text-white">Suspended for false alarms</h2></header>
        <div class="divide-y divide-base-300 dark:divide-white/5">
          <article v-for="u in data.suspended" :key="u.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
            <div class="min-w-0">
              <p class="break-words text-sm font-black text-slate-800 dark:text-white/80">{{ u.name }} <span class="font-normal text-slate-400">· {{ u.email }}</span></p>
              <p class="mt-0.5 text-xs text-slate-500">{{ u.strikes }} active {{ plural(Number(u.strikes), 'strike') }} · {{ u.suspended_until ? `until ${stamp(u.suspended_until)}` : 'until review' }}</p>
            </div>
            <button v-if="canUpdate" type="button" class="rounded-xl border border-emerald-300 px-3 py-2 text-xs font-black text-emerald-700 dark:border-emerald-500/30 dark:text-emerald-300" @click="openAction('lift', u)">Lift suspension</button>
          </article>
        </div>
      </section>

      <!-- Strikes -->
      <section class="overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#071829]">
        <header class="flex items-center gap-2 border-b border-base-300 px-5 py-4 dark:border-white/10"><Icon icon="lucide:list-x" width="16" class="text-red-600" /><h2 class="text-sm font-black text-slate-900 dark:text-white">Strikes</h2></header>
        <div class="divide-y divide-base-300 dark:divide-white/5">
          <article v-for="s in data.strikes" :key="s.id" class="flex flex-wrap items-start justify-between gap-3 px-5 py-3.5" :class="s.status === 'removed' ? 'opacity-60' : ''">
            <div class="min-w-0 flex-1">
              <p class="break-words text-sm font-black text-slate-800 dark:text-white/80">
                {{ s.guest ? `Guest ${s.phone || (s.temporary_account_id ? '(temporary account)' : '(no number)')}` : s.citizen_name }}
                <span class="font-mono text-xs font-bold text-slate-400">· {{ s.reference_number }}</span>
              </p>
              <p class="mt-0.5 break-words text-xs text-slate-500 dark:text-white/45">{{ stamp(s.created_at) }} · by {{ s.closed_by || 'a platform admin' }} · sanction: {{ sanctionLabel(s.sanction_applied) }}<template v-if="s.reason"> — “{{ s.reason }}”</template></p>
              <p v-if="s.status === 'removed'" class="mt-0.5 break-words text-xs text-emerald-700 dark:text-emerald-300">Removed: {{ s.removed_reason }}</p>
            </div>
            <div class="flex flex-shrink-0 items-center gap-2">
              <span class="rounded-full px-2 py-0.5 text-[0.6rem] font-black uppercase" :class="s.status === 'removed' ? 'bg-emerald-100 text-emerald-700' : s.in_window ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-500'">{{ s.status === 'removed' ? 'removed' : s.in_window ? 'counts' : 'expired' }}</span>
              <button v-if="s.status === 'active' && canUpdate" type="button" class="rounded-xl border border-base-300 px-3 py-1.5 text-xs font-bold text-slate-600 dark:border-white/10 dark:text-white/60" @click="openAction('remove', s)">Remove</button>
            </div>
          </article>
          <p v-if="!data.strikes.length" class="py-10 text-center text-sm text-slate-400">No false-alarm strikes yet.</p>
        </div>
      </section>
    </div>

    <!-- Reason / note dialog for accept / reject / remove / lift -->
    <Teleport to="body">
      <div v-if="action" class="fixed inset-0 z-[95] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" @click.self="action = null">
        <form class="w-full max-w-md rounded-3xl border border-base-300 bg-base-100 p-5 shadow-2xl dark:border-white/10 dark:bg-[#071829]" @submit.prevent="submitAction">
          <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ actionTitle }}</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-white/45">{{ actionHint }}</p>
          <textarea v-model.trim="note" rows="3" maxlength="500" class="mt-4 w-full rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" :placeholder="action.kind === 'accept' ? 'Note to the citizen (optional)' : 'Reason (shown to the citizen)'"></textarea>
          <div class="mt-4 flex justify-end gap-2">
            <button type="button" class="rounded-xl border border-base-300 px-4 py-2.5 text-sm font-bold text-slate-600 dark:border-white/10 dark:text-white/60" @click="action = null">Cancel</button>
            <button type="submit" :disabled="saving || (action.kind !== 'accept' && note.length < 5)" class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50">Confirm</button>
          </div>
        </form>
      </div>
    </Teleport>
  </PageShell>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Icon } from '@iconify/vue'
import PageShell from '@/components/management/PageShell.vue'
import { fetchSanctionsOverview, liftSanctionSuspension, removeSanctionStrike, reviewSanctionAppeal, saveSanctionSettings } from '@/services/sanctions'
import { useAlert } from '@/composables/useAlert'
import { useToast } from '@/composables/useToast'
import { useSession } from '@/composables/useSession'

const alert = useAlert(), toast = useToast()
// Cosmetic only — the PHP endpoints enforce the same permissions.
const { hasPermission } = useSession()
const canEditSettings = computed(() => hasPermission('system.system_settings.update'))
const canUpdate = computed(() => hasPermission('citizens.citizens.update'))

const data = ref(null), loading = ref(false), saving = ref(false)
const form = reactive({}), limits = ref({})
let saved = ''
const dirty = computed(() => JSON.stringify(form) !== saved)

const accountFields = [
  { key: 'window_days', label: 'Count strikes over', unit: 'days' },
  { key: 'warn_at', label: 'Warning at', unit: 'strikes' },
  { key: 'suspend_at', label: 'Suspend at', unit: 'strikes' },
  { key: 'suspend_days', label: 'Suspend for', unit: 'days' },
  { key: 'review_at', label: 'Suspend until review at', unit: 'strikes' },
]
const guestFields = [
  { key: 'guest_window_days', label: 'Count over', unit: 'days' },
  { key: 'guest_block_at', label: 'Pause at', unit: 'false alarms' },
  { key: 'guest_block_days', label: 'Pause for', unit: 'days' },
]
const plural = (n, w) => (Number(n) === 1 ? w : `${w}s`)
const stamp = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—')
const sanctionLabel = (v) => ({ none: 'none', warning: 'warning', suspended: 'timed suspension', review: 'suspended until review', guest_block: 'number paused', disabled: 'sanctions off' })[v] || v

const cards = computed(() => [
  { label: 'Pending appeals', value: data.value?.pending_appeals ?? 0 },
  { label: 'Suspended now', value: data.value?.suspended.length ?? 0 },
  { label: 'Strikes counting', value: data.value?.strikes.filter((s) => s.status === 'active' && s.in_window).length ?? 0 },
  { label: 'Strikes removed', value: data.value?.strikes.filter((s) => s.status === 'removed').length ?? 0 },
])

async function load() {
  loading.value = true
  try {
    const d = await fetchSanctionsOverview()
    data.value = d
    limits.value = d.limits || {}
    Object.assign(form, d.settings)
    saved = JSON.stringify(form)
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Unable to load false alarm sanctions.')
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  const ok = await alert.confirm(`Save these sanction rules? Within ${form.window_days} days: warning at ${form.warn_at}, ${form.suspend_days}-day suspension at ${form.suspend_at}, suspension until review at ${form.review_at}${form.guest_enabled ? `; guest numbers paused ${form.guest_block_days} days after ${form.guest_block_at}` : '; guests not sanctioned'}${form.enabled ? '' : ' (sanctions are OFF)'}.`, 'Change sanction rules')
  if (!ok) return
  saving.value = true
  try {
    const d = await saveSanctionSettings({ ...form })
    toast.success(d.message)
    await load()
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Unable to save the rules.')
  } finally {
    saving.value = false
  }
}

const action = ref(null), note = ref('')
const actionTitle = computed(() => ({ accept: 'Accept appeal', reject: 'Reject appeal', remove: 'Remove strike', lift: 'Lift suspension' })[action.value?.kind] || '')
const actionHint = computed(() => ({
  accept: 'The strike is removed and the citizen\'s sanction is recalculated (it can only get lighter).',
  reject: 'The strike stays. The citizen can\'t appeal it again.',
  remove: 'Use for a wrong call. The sanction is recalculated (it can only get lighter).',
  lift: 'The account becomes active again. Its strikes stay on record.',
})[action.value?.kind] || '')
function openAction(kind, item) { action.value = { kind, item }; note.value = '' }
async function submitAction() {
  const { kind, item } = action.value
  if (!(await alert.confirm(`${actionTitle.value}? ${actionHint.value}`, actionTitle.value))) return
  saving.value = true
  try {
    const d = kind === 'lift' ? await liftSanctionSuspension(item.id, note.value)
      : kind === 'remove' ? await removeSanctionStrike(item.id, note.value)
      : await reviewSanctionAppeal(item.id, kind, note.value)
    toast.success(d.message)
    action.value = null
    await load()
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Unable to complete this action.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
