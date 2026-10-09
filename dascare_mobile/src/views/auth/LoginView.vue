<template>
  <AuthScreen title="Welcome back" subtitle="Log in to track your requests and keep your info on file." tag="log in">
    <form class="mt-6 flex flex-col" @submit.prevent="submit">
      <label :class="ui.label">Email</label>
      <div class="relative">
        <Icon icon="line-md:email" width="18" height="18" class="absolute left-3.5 top-4 text-slate-400 dark:text-white/30" />
        <input v-model.trim="email" type="email" inputmode="email" autocomplete="email" required placeholder="you@example.com" :class="ui.inputWithIcon" />
      </div>

      <label :class="[ui.label, 'mt-4']">Password</label>
      <div class="relative">
        <Icon icon="mdi:lock-outline" width="20" height="20" class="absolute left-3 top-4 text-slate-400 dark:text-white/30" />
        <input v-model="password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required placeholder="••••••••" :class="[ui.inputWithIcon, 'pr-12']" />
        <button type="button" class="absolute right-2 top-2 grid h-11 w-11 place-items-center text-slate-400 dark:text-white/40" :aria-label="showPassword ? 'Hide password' : 'Show password'" @click="showPassword = !showPassword">
          <Icon :icon="showPassword ? 'line-md:watch' : 'line-md:watch-off'" width="22" height="22" />
        </button>
      </div>

      <div class="mt-3 flex items-center justify-between">
        <label class="flex items-center gap-2 text-xs text-slate-500 dark:text-white/50">
          <input v-model="remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 accent-red-600 dark:border-white/20" />
          Remember my email
        </label>
        <RouterLink :to="{ name: 'ForgotPassword', query: { email } }" class="py-2 text-xs font-semibold text-red-600 no-underline dark:text-red-400">Forgot password?</RouterLink>
      </div>

      <button type="submit" :disabled="loading" :class="[ui.primaryButton, 'mt-5']">
        <Icon v-if="loading" icon="lucide:loader-circle" width="20" class="animate-spin" />
        {{ loading ? 'Checking account…' : 'Log In' }}
      </button>

      <p class="mt-6 text-center text-xs text-slate-400 dark:text-white/40">
        Don't have an account?
        <RouterLink to="/register" replace class="font-semibold text-red-600 no-underline dark:text-red-400">Sign up</RouterLink>
      </p>
    </form>

    <template #after>
      <RouterLink to="/sos" class="tap mt-4 flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-red-200 bg-red-50/60 py-3.5 text-sm font-bold text-red-700 no-underline dark:border-red-500/25 dark:bg-red-500/5 dark:text-red-300">
        <Icon icon="lucide:siren" width="16" /> Emergency? Request help without logging in
      </RouterLink>
    </template>
  </AuthScreen>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthScreen from './AuthScreen.vue'
import * as ui from '@/components/ui/styles'
import api, { apiMessage } from '@/services/api'
import { getItem, removeItem, setItem } from '@/services/storage'
import { useSession } from '@/composables/useSession'
import { useAuthFlow } from '@/composables/useAuthFlow'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'

const REMEMBERED_EMAIL_KEY = 'dascare.rememberedEmail'
const router = useRouter()
const route = useRoute()
const session = useSession()
const authFlow = useAuthFlow()
const toast = useToast()
const alert = useAlert()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const remember = ref(false)
const loading = ref(false)

onMounted(async () => {
  const saved = await getItem(REMEMBERED_EMAIL_KEY)
  if (saved) { email.value = saved; remember.value = true }
  if (typeof route.query.email === 'string' && route.query.email) email.value = route.query.email
})

async function submit() {
  document.activeElement?.blur() // close the keyboard
  loading.value = true
  try {
    if (remember.value) await setItem(REMEMBERED_EMAIL_KEY, email.value)
    else await removeItem(REMEMBERED_EMAIL_KEY)

    const data = await session.login(email.value, password.value)
    if (data.requires_2fa) {
      authFlow.start('login_2fa', email.value, data.challenge)
      toast.info('Your account has two-factor authentication enabled — check your email for a code.', 'Two-factor authentication')
      router.push({ name: 'VerifyCode' })
      return
    }
    toast.success(`Welcome ${data.user?.name || ''}!`, 'Login successful')
    router.replace({ name: 'Home' })
  } catch (err) {
    const data = err.response?.data || {}
    if (data.code === 'STAFF_USE_WEB') {
      alert.warning(data.message, 'Use the web portal')
    } else if (data.code === 'EMAIL_NOT_VERIFIED') {
      const ok = await alert.confirm('Your email isn’t verified yet. Send a new verification code to finish setting up your account?', 'Verify your email')
      if (ok) await resendVerification()
    } else {
      toast.error(apiMessage(err, 'Unable to log in.'), 'Login failed')
    }
  } finally {
    loading.value = false
  }
}

async function resendVerification() {
  try {
    const { data } = await api.post('/auth/resend_otp.php', { email: email.value })
    if (!data.success) { toast.error(data.message || 'Unable to send code.', 'Resend failed'); return }
    authFlow.start('register', email.value)
    toast.success('We sent a 6-digit verification code to your email.', 'Verify your email')
    router.push({ name: 'VerifyCode' })
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to send code.'), 'Resend failed')
  }
}
</script>
