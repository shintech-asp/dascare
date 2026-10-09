<template>
  <AuthScreen title="Forgot password" subtitle="Enter your email and we'll send a verification code." tag="reset" fallback="/login">
    <form class="mt-6 flex flex-col" @submit.prevent="send">
      <label :class="ui.label">Email</label>
      <div class="relative">
        <Icon icon="line-md:email" width="18" height="18" class="absolute left-3.5 top-4 text-slate-400 dark:text-white/30" />
        <input v-model.trim="email" type="email" inputmode="email" autocomplete="email" required placeholder="you@example.com" :class="ui.inputWithIcon" />
      </div>
      <button type="submit" :disabled="sending || !email" :class="[ui.primaryButton, 'mt-6']">
        <Icon v-if="sending" icon="lucide:loader-circle" width="20" class="animate-spin" />
        {{ sending ? 'Sending email…' : 'Send code' }}
      </button>
    </form>
  </AuthScreen>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthScreen from './AuthScreen.vue'
import * as ui from '@/components/ui/styles'
import api, { apiMessage } from '@/services/api'
import { useAuthFlow } from '@/composables/useAuthFlow'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const authFlow = useAuthFlow()
const toast = useToast()

const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const sending = ref(false)

// Same flow as the web: forgot_password.php emails a code → verify_otp.php
// (context "forgot") → reset_password.php.
async function send() {
  document.activeElement?.blur() // close the keyboard
  sending.value = true
  try {
    const { data } = await api.post('/auth/forgot_password.php', { email: email.value })
    if (!data.success) { toast.error(data.message || 'Unable to send code', 'Error'); return }
    authFlow.start('forgot', email.value)
    toast.success('We sent a password reset code to your email.', 'Verification sent')
    router.push({ name: 'VerifyCode' })
  } catch (err) {
    toast.error(apiMessage(err, 'Email not found or unable to send code'), 'Error')
  } finally {
    sending.value = false
  }
}
</script>
