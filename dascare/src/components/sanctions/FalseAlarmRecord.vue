<template>
  <!-- A citizen's false-alarm record: suspension notice, the rules, each
       strike with its appeal. Used in Settings (session) and on the login
       screen for a suspended account (`token` from the login refusal).
       dascare_api/reusables/false_alarm_sanctions.php.
       Same file in dascare_mobile/src/components/sanctions/FalseAlarmRecord.vue — keep in sync. -->
  <div class="space-y-4">
    <div v-if="loading && !record" class="h-24 animate-pulse rounded-2xl bg-base-200 dark:bg-white/5"></div>
    <template v-else-if="record">
      <div v-if="record.suspension" class="rounded-2xl border border-red-200 bg-red-50 p-4 dark:border-red-500/20 dark:bg-red-500/10">
        <p class="flex items-center gap-2 text-sm font-black text-red-800 dark:text-red-200"><Icon icon="lucide:ban" width="16" class="flex-shrink-0" /> Account suspended</p>
        <p class="mt-1 text-xs leading-5 text-red-800/80 dark:text-red-200/70">
          {{ record.suspension.until_review ? 'Until a DASCARE administrator reviews it.' : `Until ${stamp(record.suspension.until)} — it lifts by itself.` }}
          In a real emergency you can still send SOS as a guest, or call 911.
        </p>
      </div>

      <p class="text-xs leading-5 text-slate-500 dark:text-white/45">
        <b class="text-slate-700 dark:text-white/70">{{ record.active_count }}</b> false-alarm {{ record.active_count === 1 ? 'strike' : 'strikes' }} in the last {{ record.rules.window_days }} days.
        {{ record.rules.warn_at }} = warning · {{ record.rules.suspend_at }} = suspended {{ record.rules.suspend_days }} days · {{ record.rules.review_at }} = suspended until review.
      </p>

      <article v-for="s in record.strikes" :key="s.id" class="rounded-2xl border border-base-300 p-4 dark:border-white/10" :class="s.status === 'removed' || !s.counts ? 'opacity-60' : ''">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="break-words font-mono text-xs font-bold text-slate-800 dark:text-white/80">{{ s.reference_number }}</p>
            <p class="mt-0.5 break-words text-xs text-slate-500 dark:text-white/45">Marked a false alarm by {{ s.closed_by }} · {{ stamp(s.created_at) }}</p>
            <p v-if="s.reason" class="mt-1 break-words text-xs text-slate-600 dark:text-white/60">“{{ s.reason }}”</p>
          </div>
          <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[0.6rem] font-black uppercase" :class="badge(s)">{{ badgeText(s) }}</span>
        </div>
        <p v-if="s.status === 'removed'" class="mt-2 break-words text-xs text-emerald-700 dark:text-emerald-300">Removed: {{ s.removed_reason }}</p>
        <p v-else-if="s.appeal" class="mt-2 break-words text-xs" :class="s.appeal.status === 'rejected' ? 'text-red-600 dark:text-red-300' : 'text-slate-500 dark:text-white/45'">
          Appeal {{ s.appeal.status }}<template v-if="s.appeal.note"> — {{ s.appeal.note }}</template>
        </p>
        <template v-if="s.can_appeal">
          <button v-if="appealing !== s.id" type="button" class="mt-3 inline-flex items-center gap-1.5 rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-700 dark:border-white/10 dark:text-white/70" @click="appealing = s.id; message = ''">
            <Icon icon="lucide:message-square-reply" width="14" /> Appeal this strike
          </button>
          <form v-else class="mt-3 space-y-2" @submit.prevent="sendAppeal(s)">
            <textarea v-model.trim="message" rows="3" maxlength="1500" class="w-full rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-sm text-slate-800 outline-none focus:border-red-500 dark:border-white/10 dark:bg-white/5 dark:text-white" placeholder="Tell the administrator what happened — it was a real emergency, a mistake, someone else used your phone…"></textarea>
            <div class="flex justify-end gap-2">
              <button type="button" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-600 dark:border-white/10 dark:text-white/60" @click="appealing = null">Cancel</button>
              <button type="submit" :disabled="sending || message.length < 10" class="rounded-xl bg-red-600 px-4 py-2 text-xs font-black text-white disabled:opacity-50">{{ sending ? 'Sending…' : 'Send appeal' }}</button>
            </div>
          </form>
        </template>
      </article>
      <p v-if="!record.strikes.length" class="rounded-2xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">No false alarms on your account.</p>
    </template>
    <p v-else-if="error" class="text-sm text-red-600">{{ error }}</p>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import { appealStrike, fetchMySanctions } from '@/services/sanctions'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  token: { type: String, default: null }, // suspended citizen (login refusal)
  initial: { type: Object, default: null }, // record already returned by the login refusal
})
const toast = useToast()
const record = ref(props.initial)
const loading = ref(false), error = ref(''), appealing = ref(null), message = ref(''), sending = ref(false)

const stamp = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '')
const badgeText = (s) => (s.status === 'removed' ? 'Removed' : s.appeal?.status === 'pending' ? 'Appeal pending' : s.counts ? 'Strike' : 'Expired')
const badge = (s) => (s.status === 'removed' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
  : s.appeal?.status === 'pending' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
  : s.counts ? 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-white/50')

async function load() {
  loading.value = true
  try { record.value = await fetchMySanctions(props.token) } catch (e) { error.value = e?.response?.data?.message || 'Unable to load your false alarm record.' } finally { loading.value = false }
}
async function sendAppeal(s) {
  sending.value = true
  try {
    const d = await appealStrike(s.id, message.value, props.token)
    toast.success(d.message, 'Appeal sent')
    appealing.value = null
    await load()
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Unable to send your appeal.', 'Not sent')
  } finally {
    sending.value = false
  }
}
defineExpose({ load })
onMounted(() => { if (!props.initial) load() })
</script>
