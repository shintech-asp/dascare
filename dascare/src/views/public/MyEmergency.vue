<template>
  <!-- Temporary (guest) account home — reusables/temporary_accounts.php.
       Signed out: "log in with your request key". Signed in: the guest's
       requests, when the account is deleted, and (once a request is done)
       the prompt to complete it into a full account. -->
  <section class="min-h-[calc(100vh-5rem)] bg-base-200 px-4 py-10 dark:bg-[#050e1a]">
    <div class="mx-auto max-w-2xl">
      <div v-if="loading" class="grid place-items-center py-24"><span class="h-8 w-8 animate-spin rounded-full border-4 border-red-600 border-t-transparent"></span></div>

      <!-- ===== Key login ===== -->
      <div v-else-if="!temporary" class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-sm sm:p-8 dark:border-white/10 dark:bg-[#071829]">
        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon icon="lucide:key-round" width="24" /></span>
        <h1 class="mt-4 text-2xl font-black text-slate-900 dark:text-white">Log in with your request key</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-white/50">
          Sent an emergency request without an account? You got a <b class="text-slate-700 dark:text-white/75">login key</b> like <span class="font-mono">K7MQ-2XRT-9PHD</span>.
          Enter it to follow your request on this device.
        </p>
        <form class="mt-6" @submit.prevent="submitKey">
          <label class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55" for="temp-key">Login key</label>
          <input id="temp-key" v-model="keyInput" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="16" placeholder="XXXX-XXXX-XXXX"
            class="w-full rounded-xl border border-slate-200 bg-[#f8f3e8] px-4 py-3.5 text-center font-mono text-xl font-bold tracking-[0.2em] text-slate-800 placeholder:text-slate-300 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-white/20"
            @input="formatKey" />
          <p v-if="error" class="mt-2 text-sm font-semibold text-red-600 dark:text-red-400">{{ error }}</p>
          <button type="submit" :disabled="submitting || keyChars.length !== 12"
            class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 py-3.5 text-base font-bold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">
            <Icon v-if="submitting" icon="lucide:loader-circle" width="18" class="animate-spin" /> Open my emergency
          </button>
        </form>
        <div class="mt-6 border-t border-base-300 pt-5 text-sm text-slate-500 dark:border-white/10 dark:text-white/45">
          Have a regular account? <RouterLink to="/login" class="font-bold text-red-600 dark:text-red-300">Log in</RouterLink> ·
          Need help now? <RouterLink to="/" class="font-bold text-red-600 dark:text-red-300">Request rescue</RouterLink>
        </div>
      </div>

      <!-- ===== Signed in to a temporary account ===== -->
      <template v-else>
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.2em] text-red-600 dark:text-red-300">Temporary account</p>
            <h1 class="mt-1 text-2xl font-black text-slate-900 dark:text-white">My emergency</h1>
          </div>
          <button type="button" class="rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-base-200 dark:border-white/10 dark:bg-white/5 dark:text-white/60" @click="signOut">
            <Icon icon="lucide:log-out" width="14" class="mr-1 inline" />Sign out
          </button>
        </div>

        <!-- Complete the account once a request is done -->
        <div v-if="temporary.canComplete" class="mt-5 rounded-3xl border border-emerald-200 bg-emerald-50 p-5 sm:p-6 dark:border-emerald-500/20 dark:bg-emerald-500/5">
          <p class="flex items-center gap-2 text-base font-black text-emerald-800 dark:text-emerald-200"><Icon icon="lucide:circle-check-big" width="20" /> Your request is done. Keep your history?</p>
          <p class="mt-1.5 text-sm leading-6 text-emerald-900/70 dark:text-emerald-100/60">
            Complete your account with your name, email and a password. Your emergency requests move into it, and next time dispatch can see your saved emergency information.
            <template v-if="temporary.deletesAt"> Otherwise this temporary account is deleted on <b>{{ formatDay(temporary.deletesAt) }}</b>.</template>
          </p>
          <RouterLink to="/register?from=temporary" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white no-underline hover:bg-emerald-700">
            <Icon icon="lucide:user-round-plus" width="16" /> Complete my account
          </RouterLink>
        </div>

        <!-- Requests -->
        <div class="mt-5 overflow-hidden rounded-3xl border border-base-300 bg-base-100 dark:border-white/10 dark:bg-[#071829]">
          <p class="px-5 pt-4 text-xs font-black uppercase tracking-wider text-slate-400 dark:text-white/35">Your requests</p>
          <RouterLink v-for="r in temporary.requests" :key="r.id" :to="{ name: 'GuestTrack', params: { id: r.id } }"
            class="flex items-center gap-3 border-b border-base-300 px-5 py-3.5 no-underline last:border-b-0 hover:bg-base-200/60 dark:border-white/5 dark:hover:bg-white/[0.03]">
            <span class="h-2.5 w-2.5 flex-shrink-0 rounded-full" :class="r.finished ? 'bg-slate-300 dark:bg-white/20' : 'animate-pulse bg-red-500'"></span>
            <span class="min-w-0 flex-1">
              <span class="block font-mono text-sm font-bold text-slate-800 dark:text-white/85">{{ r.reference_number }}</span>
              <span class="block truncate text-xs text-slate-500 dark:text-white/40"><template v-if="r.merged_into_reference">Linked to {{ r.merged_into_reference }} · </template>{{ statusText(r.effective_status) }} · {{ formatWhen(r.submitted_at) }}</span>
            </span>
            <span class="flex items-center gap-0.5 text-xs font-bold text-red-600 dark:text-red-300">{{ r.finished ? 'View' : 'Track' }} <Icon icon="lucide:chevron-right" width="14" /></span>
          </RouterLink>
          <p v-if="!temporary.requests.length" class="px-5 pb-5 pt-2 text-sm text-slate-500">No requests yet.</p>
        </div>

        <!-- Key + retention -->
        <div class="mt-5 rounded-3xl border border-base-300 bg-base-100 p-5 text-sm leading-6 text-slate-600 dark:border-white/10 dark:bg-[#071829] dark:text-white/55">
          <p class="flex items-center gap-2 font-bold text-slate-800 dark:text-white/80"><Icon icon="lucide:key-round" width="16" /> Login key <span class="font-mono text-slate-500 dark:text-white/45">••••-••••-{{ temporary.keyHint }}</span></p>
          <p class="mt-1">You need it to sign in again — on another phone or computer, or on this one after 30 minutes without activity or once the browser is closed. For your safety it isn't shown again, so keep it somewhere safe.</p>
          <p class="mt-3 flex items-start gap-2"><Icon icon="lucide:clock" width="16" class="mt-1 flex-shrink-0" />
            <span v-if="temporary.deletesAt">If you don't complete it, this account is deleted on <b>{{ formatDay(temporary.deletesAt) }}</b>. The emergency records stay with dispatch, without your name or number.</span>
            <span v-else>Kept while your request is active, then {{ temporary.retentionDays }} days after it ends unless you complete it.</span>
          </p>
        </div>
        <p class="mt-4 text-center text-xs text-slate-400">Need help again? <RouterLink to="/" class="font-bold text-red-600 dark:text-red-300">Request rescue</RouterLink> — it's added to this account.</p>
      </template>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Icon } from '@iconify/vue'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const toast = useToast()
const { temporary, fetchSession, temporaryLogin, logout } = useSession()

const loading = ref(true)
const keyInput = ref('')
const submitting = ref(false)
const error = ref('')
const keyChars = computed(() => keyInput.value.replace(/[^A-Za-z0-9]/g, ''))

// Type or paste in any form: "k7mq2xrt9phd" → "K7MQ-2XRT-9PHD".
function formatKey() {
  const chars = keyChars.value.toUpperCase().slice(0, 12)
  keyInput.value = chars.match(/.{1,4}/g)?.join('-') || ''
}

async function submitKey() {
  submitting.value = true
  error.value = ''
  try {
    const data = await temporaryLogin(keyInput.value)
    if (!data.success) error.value = data.message || 'That key didn\'t work.'
    else toast.success('You\'re signed in to your temporary account.', 'Welcome back')
  } catch (e) {
    error.value = e?.response?.status === 429
      ? 'Too many tries. Wait a few minutes and try again.'
      : (e?.response?.data?.message || 'Something went wrong. Please try again.')
  } finally {
    submitting.value = false
  }
}

async function signOut() {
  await logout()
  router.push('/')
}

const STATUS_TEXT = {
  submitted: 'Submitted', validating: 'Being reviewed', verified: 'Verified', assigned: 'Ambulance assigned',
  acknowledged: 'Crew acknowledged', responding: 'Ambulance on the way', on_scene: 'Ambulance on scene',
  transporting: 'Transporting to hospital', completed: 'Completed', cancelled: 'Cancelled', rejected: 'Rejected',
  duplicate: 'Linked to a nearby report', false_alarm: 'Closed (false alarm)',
}
const statusText = (s) => STATUS_TEXT[s] || s
const toDate = (v) => new Date(String(v).replace(' ', 'T'))
const formatDay = (v) => toDate(v).toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' })
const formatWhen = (v) => toDate(v).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })

onMounted(async () => {
  await fetchSession(true)
  loading.value = false
})
</script>
