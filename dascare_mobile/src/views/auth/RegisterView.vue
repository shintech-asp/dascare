<template>
  <AuthScreen title="Create your account" subtitle="Public sign-up creates a citizen account." tag="sign up">
    <form class="mt-5 flex flex-col" @submit.prevent="confirmDetails">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label :class="ui.label">First name</label>
          <input v-model.trim="firstName" required autocomplete="given-name" placeholder="Juan" :class="ui.input" />
        </div>
        <div>
          <label :class="ui.label">Last name</label>
          <input v-model.trim="lastName" required autocomplete="family-name" placeholder="Dela Cruz" :class="ui.input" />
        </div>
      </div>

      <label :class="[ui.label, 'mt-4']">Email</label>
      <input v-model.trim="email" type="email" inputmode="email" autocomplete="email" required placeholder="you@example.com" :class="ui.input" />
      <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">We'll send a verification code to this address.</p>

      <label :class="[ui.label, 'mt-4']">Mobile number</label>
      <input v-model.trim="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="09XXXXXXXXX" :class="ui.input" />
      <p v-if="phone && !phoneValid" class="mt-1 text-xs text-red-500">Enter a valid PH mobile number (09XXXXXXXXX or +639XXXXXXXXX).</p>

      <label :class="[ui.label, 'mt-4']">Password</label>
      <div class="relative">
        <input v-model="password" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" required placeholder="Create a password" :class="[ui.input, 'pr-12']" />
        <button type="button" class="absolute right-2 top-2 grid h-11 w-11 place-items-center text-slate-400 dark:text-white/40" aria-label="Show password" @click="showPassword = !showPassword">
          <Icon :icon="showPassword ? 'line-md:watch' : 'line-md:watch-off'" width="22" height="22" />
        </button>
      </div>
      <PasswordStrength :password="password" />

      <label :class="[ui.label, 'mt-4']">Confirm password</label>
      <div class="relative">
        <input v-model="confirmPassword" :type="showConfirm ? 'text' : 'password'" autocomplete="new-password" required placeholder="Confirm password" :class="[ui.input, 'pr-12']" />
        <button type="button" class="absolute right-2 top-2 grid h-11 w-11 place-items-center text-slate-400 dark:text-white/40" aria-label="Show password" @click="showConfirm = !showConfirm">
          <Icon :icon="showConfirm ? 'line-md:watch' : 'line-md:watch-off'" width="22" height="22" />
        </button>
      </div>
      <div v-if="confirmPassword.length > 0" class="mt-2 flex items-center gap-2">
        <Icon :icon="passwordsMatch ? 'mdi:check-circle' : 'mdi:close-circle'" class="h-4 w-4" :class="passwordsMatch ? 'text-emerald-500' : 'text-red-500'" />
        <span class="text-xs" :class="passwordsMatch ? 'text-emerald-500' : 'text-red-500'">{{ passwordsMatch ? 'Passwords match' : 'Passwords do not match' }}</span>
      </div>

      <label class="mt-5 flex items-start gap-3 text-sm text-slate-500 dark:text-white/50">
        <input v-model="agree" type="checkbox" class="mt-0.5 h-5 w-5 flex-shrink-0 rounded border-slate-300 accent-red-600 dark:border-white/20" />
        <span class="leading-relaxed">I agree to the
          <RouterLink :to="{ name: 'Legal', params: { slug: 'terms-of-service' } }" class="font-semibold text-red-600 no-underline dark:text-red-400" @click.stop>Terms of Service</RouterLink>
          and
          <RouterLink :to="{ name: 'Legal', params: { slug: 'privacy-policy' } }" class="font-semibold text-red-600 no-underline dark:text-red-400" @click.stop>Privacy Policy</RouterLink>.
        </span>
      </label>

      <button type="submit" :disabled="!agree || sending" :class="[ui.primaryButton, 'mt-5']">
        <Icon v-if="sending" icon="lucide:loader-circle" width="20" class="animate-spin" />
        {{ sending ? 'Sending email…' : 'Sign Up' }}
      </button>

      <p class="mt-6 text-center text-xs text-slate-400 dark:text-white/40">
        Already have an account?
        <RouterLink to="/login" replace class="font-semibold text-red-600 no-underline dark:text-red-400">Log in</RouterLink>
      </p>
    </form>
  </AuthScreen>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import AuthScreen from './AuthScreen.vue'
import PasswordStrength from '@/components/PasswordStrength.vue'
import * as ui from '@/components/ui/styles'
import api, { apiMessage } from '@/services/api'
import { useAuthFlow } from '@/composables/useAuthFlow'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'

const router = useRouter()
const authFlow = useAuthFlow()
const toast = useToast()
const alert = useAlert()

const firstName = ref('')
const lastName = ref('')
const email = ref('')
const phone = ref('')
const password = ref('')
const confirmPassword = ref('')
const showPassword = ref(false)
const showConfirm = ref(false)
const agree = ref(false)
const sending = ref(false)

const phoneValid = computed(() => ui.PH_MOBILE.test(phone.value.replace(/[\s-]/g, '')))
const passwordsMatch = computed(() => password.value === confirmPassword.value && confirmPassword.value.length > 0)

async function confirmDetails() {
  document.activeElement?.blur() // close the keyboard
  if (!phoneValid.value) { toast.error('Please enter a valid PH mobile number.', 'Invalid phone'); return }
  if (!passwordsMatch.value) { toast.error('Passwords do not match', 'Registration failed'); return }
  if (!ui.meetsPasswordRules(password.value)) {
    toast.error('Password must be 10+ characters with upper, lower, a number, and a special character.', 'Password too weak')
    return
  }
  const summary = `Name: ${firstName.value} ${lastName.value}\nEmail: ${email.value}\nMobile: ${phone.value}`
  if (await alert.confirm(summary, 'Confirm your details')) await register()
}

// Same endpoint and payload as the web sign-up (auth/register.php).
async function register() {
  sending.value = true
  try {
    const { data } = await api.post('/auth/register.php', {
      first_name: firstName.value,
      last_name: lastName.value,
      email: email.value,
      phone: phone.value,
      password: password.value,
    })
    if (!data.success) { toast.error(data.message, 'Registration failed'); return }
    authFlow.start('register', email.value)
    toast.success('We sent a 6-digit verification code to your email.', 'Verify your email')
    router.push({ name: 'VerifyCode' })
  } catch (err) {
    toast.error(apiMessage(err, 'Registration failed.'), 'Registration failed')
  } finally {
    sending.value = false
  }
}
</script>
