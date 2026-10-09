<template>
  <div class="min-h-screen bg-base-200 dark:bg-[#050e1a]">
    <ScreenHeader title="Account & security" fallback="/profile" />

    <main class="space-y-4 px-4 pb-[calc(var(--safe-bottom)+2rem)] pt-4">
      <!-- Account details — same fields and endpoint as the web Settings
           (account/edit_profile.php, password change folded into the save) -->
      <form :class="[ui.card, 'p-5']" @submit.prevent="saveAccount">
        <div class="flex items-start gap-3">
          <span class="grid h-11 w-11 flex-shrink-0 place-items-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon icon="lucide:user-round" width="20" /></span>
          <div>
            <h2 class="text-base font-black text-slate-900 dark:text-white">Account details</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-white/40">Keep your contact details accurate so responders can reach you.</p>
          </div>
        </div>

        <div class="mt-5 space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <div><label :class="ui.label">First name</label><input v-model.trim="account.first_name" required autocomplete="given-name" :class="ui.input" /></div>
            <div><label :class="ui.label">Last name</label><input v-model.trim="account.last_name" required autocomplete="family-name" :class="ui.input" /></div>
          </div>
          <div>
            <label :class="ui.label">Email address</label>
            <input :value="account.email" type="email" disabled :class="ui.input" />
            <p class="mt-1 text-[0.68rem] text-slate-400 dark:text-white/30">Email changes require a separate verification flow.</p>
          </div>
          <div>
            <label :class="ui.label">Mobile number</label>
            <input v-model.trim="account.phone" type="tel" inputmode="tel" required placeholder="09XXXXXXXXX" :class="ui.input" />
          </div>
        </div>

        <div class="mt-5 border-t border-slate-100 pt-4 dark:border-white/5">
          <button type="button" class="tap flex w-full items-center justify-between gap-2 py-1 text-left" @click="showPassword = !showPassword">
            <span class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-white/70"><Icon icon="lucide:key-round" width="16" class="text-slate-400 dark:text-white/35" /> Change password</span>
            <Icon :icon="showPassword ? 'lucide:chevron-up' : 'lucide:chevron-down'" width="16" class="text-slate-400 dark:text-white/35" />
          </button>
          <div v-if="showPassword" class="mt-4 space-y-3">
            <div><label :class="ui.label">Current password</label><input v-model="account.current_password" type="password" autocomplete="current-password" :class="ui.input" /></div>
            <div><label :class="ui.label">New password</label><input v-model="account.new_password" type="password" autocomplete="new-password" :class="ui.input" /><PasswordStrength :password="account.new_password" /></div>
            <div><label :class="ui.label">Confirm new password</label><input v-model="account.new_password_confirmation" type="password" autocomplete="new-password" :class="ui.input" /></div>
          </div>
        </div>

        <button type="submit" :disabled="savingAccount" :class="[ui.primaryButton, 'mt-5']">
          <Icon :icon="savingAccount ? 'lucide:loader-circle' : 'lucide:save'" width="18" :class="savingAccount ? 'animate-spin' : ''" />
          {{ savingAccount ? 'Saving…' : 'Save changes' }}
        </button>
      </form>

      <!-- Two-factor — same password-gated toggle as the web (account/toggle_two_factor.php) -->
      <section :class="[ui.card, 'p-5']">
        <div class="flex items-start gap-3">
          <span class="grid h-11 w-11 flex-shrink-0 place-items-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300"><Icon icon="lucide:shield-check" width="20" /></span>
          <div class="min-w-0 flex-1">
            <h2 class="text-base font-black text-slate-900 dark:text-white">Two-factor authentication</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-white/40">When enabled, DASCARE emails you a one-time verification code every time you log in.</p>
          </div>
          <button type="button" role="switch" :aria-checked="twoFactor" :disabled="savingTwoFactor" class="relative mt-1 h-7 w-12 flex-shrink-0 rounded-full transition-colors disabled:opacity-50" :class="twoFactor ? 'bg-red-600' : 'bg-slate-200 dark:bg-white/10'" @click="openTwoFactor">
            <span class="absolute top-1 h-5 w-5 rounded-full bg-base-100 shadow transition-all" :class="twoFactor ? 'left-6' : 'left-1'"></span>
          </button>
        </div>
        <p class="mt-2 text-right text-xs font-bold" :class="twoFactor ? 'text-emerald-600 dark:text-emerald-300' : 'text-slate-400 dark:text-white/35'">{{ twoFactor ? 'Enabled' : 'Disabled' }}</p>
      </section>
    </main>

    <!-- Password confirmation sheet (2FA gate) -->
    <Teleport to="body">
      <div v-if="confirmOpen" data-modal-open class="fixed inset-0 z-[100] flex items-end bg-slate-950/50" @click.self="closeConfirm">
        <form class="w-full rounded-t-3xl border-t border-base-300 bg-base-100 p-6 pb-[calc(var(--safe-bottom)+1.5rem)] shadow-xl dark:border-white/10 dark:bg-[#071829]" @submit.prevent="confirmTwoFactor">
          <div class="mx-auto mb-4 h-1.5 w-10 rounded-full bg-slate-300 dark:bg-white/20"></div>
          <h3 class="text-base font-black text-slate-900 dark:text-white">{{ pendingValue ? 'Enable two-factor authentication?' : 'Disable two-factor authentication?' }}</h3>
          <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-white/40">Confirm your password to {{ pendingValue ? 'turn on' : 'turn off' }} email verification codes at login.</p>
          <label :class="[ui.label, 'mt-4']">Current password</label>
          <input ref="confirmInput" v-model="confirmPassword" type="password" autocomplete="current-password" required :class="ui.input" />
          <div class="mt-5 grid grid-cols-2 gap-3">
            <button type="button" :class="ui.secondaryButton" @click="closeConfirm">Cancel</button>
            <button type="submit" :disabled="savingTwoFactor || !confirmPassword" :class="ui.primaryButton">
              <Icon :icon="savingTwoFactor ? 'lucide:loader-circle' : 'lucide:check'" width="18" :class="savingTwoFactor ? 'animate-spin' : ''" /> Confirm
            </button>
          </div>
        </form>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import ScreenHeader from '@/components/ScreenHeader.vue'
import PasswordStrength from '@/components/PasswordStrength.vue'
import * as ui from '@/components/ui/styles'
import api, { apiMessage } from '@/services/api'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'

const { user, fetchSession } = useSession()
const toast = useToast()

const account = reactive({ first_name: '', last_name: '', email: '', phone: '', current_password: '', new_password: '', new_password_confirmation: '' })
const showPassword = ref(false)
const savingAccount = ref(false)

function fillFromSession() {
  const names = (user.value?.name || '').split(' ')
  account.first_name = names.shift() || ''
  account.last_name = names.join(' ')
  account.email = user.value?.email || ''
  account.phone = user.value?.phone || ''
  twoFactor.value = Boolean(user.value?.hasTwoFactor)
}

async function saveAccount() {
  document.activeElement?.blur()
  if (showPassword.value && account.new_password) {
    if (account.new_password !== account.new_password_confirmation) { toast.error('New password and confirmation do not match', 'Check password'); return }
    if (!ui.meetsPasswordRules(account.new_password)) { toast.error('Password must be 10+ characters with upper, lower, a number, and a special character.', 'Password too weak'); return }
  }
  savingAccount.value = true
  try {
    const fd = new FormData()
    fd.append('first_name', account.first_name)
    fd.append('last_name', account.last_name)
    fd.append('phone', account.phone)
    if (showPassword.value && account.new_password) {
      fd.append('current_password', account.current_password)
      fd.append('new_password', account.new_password)
      fd.append('new_password_confirmation', account.new_password_confirmation)
    }
    const { data } = await api.post('/account/edit_profile.php', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    if (!data.success) throw new Error(data.message || 'Could not update your account')
    account.current_password = account.new_password = account.new_password_confirmation = ''
    showPassword.value = false
    await fetchSession()
    fillFromSession()
    toast.success(data.message || 'Account updated', 'Saved')
  } catch (err) {
    toast.error(err.response ? apiMessage(err, 'Could not update your account') : err.message, 'Not saved')
  } finally {
    savingAccount.value = false
  }
}

// Two-factor
const twoFactor = ref(false)
const savingTwoFactor = ref(false)
const confirmOpen = ref(false)
const confirmPassword = ref('')
const confirmInput = ref(null)
const pendingValue = ref(false)

async function openTwoFactor() {
  pendingValue.value = !twoFactor.value
  confirmPassword.value = ''
  confirmOpen.value = true
  await nextTick()
  setTimeout(() => confirmInput.value?.focus(), 150)
}
function closeConfirm() {
  if (savingTwoFactor.value) return
  confirmOpen.value = false
  confirmPassword.value = ''
}
async function confirmTwoFactor() {
  savingTwoFactor.value = true
  try {
    const { data } = await api.post('/account/toggle_two_factor.php', { current_password: confirmPassword.value, enable: pendingValue.value ? 1 : 0 })
    if (!data.success) throw new Error(data.message || 'Could not update two-factor authentication')
    twoFactor.value = Boolean(data.has_two_factor)
    confirmOpen.value = false
    confirmPassword.value = ''
    await fetchSession()
    toast.success(data.message || (twoFactor.value ? 'Two-factor authentication enabled' : 'Two-factor authentication disabled'), 'Security updated')
  } catch (err) {
    toast.error(err.response ? apiMessage(err, 'Could not update two-factor authentication') : err.message, 'Not changed')
  } finally {
    savingTwoFactor.value = false
  }
}

// Android back button closes the sheet first (App.vue sends this event).
const onCloseModal = () => closeConfirm()
onMounted(() => { fillFromSession(); window.addEventListener('dascare:close-modal', onCloseModal) })
onBeforeUnmount(() => window.removeEventListener('dascare:close-modal', onCloseModal))
</script>
