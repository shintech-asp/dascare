<template>
  <div class="min-h-screen bg-base-200 dark:bg-[#050e1a]">
    <ScreenHeader title="" fallback="/welcome" />
    <main class="px-4 pb-[calc(var(--safe-bottom)+2rem)] pt-2">
      <!-- Same content as the web's OTP modal (AuthPage.vue), as a full screen -->
      <div class="rounded-3xl border border-slate-200/80 bg-base-100 p-7 shadow-xl shadow-slate-300/40 dark:border-white/10 dark:bg-[#071829] dark:shadow-black/40">
        <div class="mb-6 text-center">
          <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-red-50 dark:bg-red-500/10">
            <span class="text-4xl">{{ copy.icon }}</span>
          </div>
          <h2 class="text-xl font-black tracking-tight text-slate-950 dark:text-white">{{ copy.title }}</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-[#a9c6e8]">
            {{ copy.description }}<br />
            <span class="selectable break-all font-medium text-slate-800 dark:text-white">{{ flow.email }}</span>
          </p>
        </div>

        <input ref="codeInput" v-model="otp" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="••••••"
          class="mb-4 w-full rounded-xl border border-slate-200 bg-[#f8f3e8] py-4 text-center text-3xl font-semibold tracking-[0.5em] text-slate-800 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20 dark:border-white/10 dark:bg-white/5 dark:text-white"
          @input="otp = otp.replace(/\D/g, '').slice(0, 6)" />

        <p class="mb-4 text-center text-sm text-slate-400 dark:text-white/40">
          Code expires in
          <span class="font-semibold text-slate-800 dark:text-white">{{ Math.floor(expiresIn / 60) }}:{{ String(expiresIn % 60).padStart(2, '0') }}</span>
        </p>

        <button type="button" :disabled="otp.length !== 6 || verifying" :class="ui.primaryButton" @click="verify">
          <Icon v-if="verifying" icon="lucide:loader-circle" width="20" class="animate-spin" />
          {{ copy.button }}
        </button>

        <p class="mt-5 text-center text-xs text-slate-400 dark:text-white/40">
          Didn't receive the code?
          <span v-if="resending" class="font-semibold text-slate-300 dark:text-white/20">Sending…</span>
          <button v-else type="button" class="px-1 py-2 font-semibold" :class="cooldown === 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-300 dark:text-white/20'" :disabled="cooldown > 0" @click="resend">Resend</button>
          <br />
          <span v-if="cooldown > 0 && !resending">Can resend after <span class="font-semibold text-slate-500 dark:text-white/50">{{ cooldown }}s</span></span>
        </p>

        <button type="button" :class="[ui.secondaryButton, 'mt-4']" @click="cancel">{{ copy.cancel }}</button>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ScreenHeader from '@/components/ScreenHeader.vue'
import * as ui from '@/components/ui/styles'
import api, { apiMessage } from '@/services/api'
import { useSession } from '@/composables/useSession'
import { useAuthFlow } from '@/composables/useAuthFlow'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'

const router = useRouter()
const session = useSession()
const { flow, clear } = useAuthFlow()
const toast = useToast()
const alert = useAlert()

const otp = ref('')
const codeInput = ref(null)
const verifying = ref(false)
const resending = ref(false)
const expiresIn = ref(600)
const cooldown = ref(60)
let timer = null

const COPY = {
  register: { icon: '✉️', title: 'Verify your email', description: 'Enter the verification code sent to', button: 'Verify code', cancel: 'Cancel registration', exitTitle: 'Cancel registration?', exitMessage: 'If you exit now, your account will not be created and all progress will be lost.' },
  forgot: { icon: '🔐', title: 'Verify your reset code', description: 'Enter the password reset code sent to', button: 'Verify reset code', cancel: 'Cancel password reset', exitTitle: 'Cancel password reset?', exitMessage: "If you exit now, your password reset will be cancelled and you'll need to request a new code." },
  login_2fa: { icon: '🛡️', title: 'Verify your login', description: 'Enter the login verification code sent to', button: 'Verify login', cancel: 'Cancel login', exitTitle: 'Cancel login verification?', exitMessage: "If you exit now, your login verification will be cancelled and you'll need to log in again." },
}
const copy = computed(() => COPY[flow.context] || COPY.register)

function startTimers() {
  clearInterval(timer)
  expiresIn.value = 600
  cooldown.value = 60
  timer = setInterval(() => {
    if (expiresIn.value > 0) expiresIn.value--
    if (cooldown.value > 0) cooldown.value--
    if (expiresIn.value === 0) {
      clearInterval(timer)
      toast.error('Your verification code has expired. Please resend a new one.', 'Code expired')
    }
  }, 1000)
}

async function verify() {
  verifying.value = true
  try {
    if (flow.context === 'login_2fa') {
      const data = await session.verify2fa(flow.challenge, otp.value)
      clear()
      toast.success(`Welcome ${data.user?.name || ''}!`, 'Login verified')
      router.replace({ name: 'Home' })
      return
    }
    // register + forgot: same endpoint and payload as the web.
    const { data } = await api.post('/auth/verify_otp.php', { email: flow.email, otp: otp.value, context: flow.context })
    if (!data.success) { toast.error(data.message || 'The verification code is incorrect or expired.', 'Invalid code'); return }
    if (flow.context === 'forgot') {
      router.replace({ name: 'ResetPassword' })
      return
    }
    const email = flow.email
    clear()
    toast.success('You can now log in.', 'Email verified')
    router.replace({ name: 'Login', query: { email } })
  } catch (err) {
    const code = err.response?.data?.code
    if (code === 'CHALLENGE_EXPIRED') {
      clear()
      toast.error(err.response.data.message, 'Session expired')
      router.replace({ name: 'Login' })
      return
    }
    toast.error(apiMessage(err, 'The verification code is incorrect or expired.'), 'Invalid code')
  } finally {
    verifying.value = false
  }
}

async function resend() {
  resending.value = true
  try {
    const { data } = flow.context === 'login_2fa'
      ? await api.post('/mobile/auth/resend_2fa.php', { challenge: flow.challenge })
      : flow.context === 'forgot'
        ? await api.post('/auth/forgot_password.php', { email: flow.email })
        : await api.post('/auth/resend_otp.php', { email: flow.email })
    if (!data.success) { toast.error(data.message || 'Unable to resend code.', 'Resend failed'); return }
    toast.success('A new verification code has been sent to your email.', 'OTP resent')
    startTimers()
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to resend verification code.'), 'Resend failed')
  } finally {
    resending.value = false
  }
}

async function cancel() {
  if (!(await alert.confirm(copy.value.exitMessage, copy.value.exitTitle))) return
  if (flow.context === 'register' && flow.email) {
    // Same clean-up the web does when sign-up is abandoned.
    try { await api.post('/auth/cancel_registration.php', { email: flow.email }) } catch { /* best effort */ }
    toast.info('Your account was not created.', 'Registration cancelled')
  }
  clear()
  router.replace({ name: 'Welcome' })
}

onMounted(() => {
  if (!flow.context) { router.replace({ name: 'Welcome' }); return }
  startTimers()
  setTimeout(() => codeInput.value?.focus(), 300)
})
onBeforeUnmount(() => clearInterval(timer))
</script>
