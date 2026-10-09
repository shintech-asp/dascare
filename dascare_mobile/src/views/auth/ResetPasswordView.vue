<template>
  <AuthScreen title="Reset your password" subtitle="Choose a strong password you haven't used before." tag="reset" fallback="/login">
    <form class="mt-6 flex flex-col" @submit.prevent="submit">
      <label :class="ui.label">New password</label>
      <div class="relative">
        <input v-model="password" :type="show ? 'text' : 'password'" autocomplete="new-password" placeholder="Enter new password" :class="[ui.input, 'pr-12']" />
        <button type="button" class="absolute right-2 top-2 grid h-11 w-11 place-items-center text-slate-400 dark:text-white/40" aria-label="Show password" @click="show = !show">
          <Icon :icon="show ? 'line-md:watch' : 'line-md:watch-off'" width="22" height="22" />
        </button>
      </div>
      <PasswordStrength :password="password" />

      <label :class="[ui.label, 'mt-4']">Confirm password</label>
      <input v-model="confirm" :type="show ? 'text' : 'password'" autocomplete="new-password" placeholder="Re-enter new password" :class="ui.input" />
      <div v-if="confirm.length > 0" class="mt-2 flex items-center gap-2">
        <Icon :icon="matches ? 'mdi:check-circle' : 'mdi:close-circle'" class="h-4 w-4" :class="matches ? 'text-emerald-500' : 'text-red-500'" />
        <span class="text-xs" :class="matches ? 'text-emerald-500' : 'text-red-500'">{{ matches ? 'Passwords match' : 'Passwords do not match' }}</span>
      </div>

      <button type="submit" :disabled="!ui.meetsPasswordRules(password) || !matches || saving" :class="[ui.primaryButton, 'mt-6']">
        <Icon v-if="saving" icon="lucide:loader-circle" width="20" class="animate-spin" />
        Reset password
      </button>
    </form>
  </AuthScreen>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AuthScreen from './AuthScreen.vue'
import PasswordStrength from '@/components/PasswordStrength.vue'
import * as ui from '@/components/ui/styles'
import api, { apiMessage } from '@/services/api'
import { useAuthFlow } from '@/composables/useAuthFlow'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const { flow, clear } = useAuthFlow()
const toast = useToast()

const password = ref('')
const confirm = ref('')
const show = ref(false)
const saving = ref(false)
const matches = computed(() => password.value === confirm.value && confirm.value.length > 0)

async function submit() {
  document.activeElement?.blur() // close the keyboard
  saving.value = true
  try {
    const { data } = await api.post('/auth/reset_password.php', { email: flow.email, password: password.value })
    if (data?.success === false) { toast.error(data.message || 'Unable to reset password', 'Error'); return }
    const email = flow.email
    clear()
    toast.success('You can now log in with your new password.', 'Password reset')
    router.replace({ name: 'Login', query: { email } })
  } catch (err) {
    toast.error(apiMessage(err, 'Something went wrong while resetting your password.'), 'Error')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  if (flow.context !== 'forgot' || !flow.email) router.replace({ name: 'Login' })
})
</script>
