<template>
  <section class="min-h-screen bg-base-200 dark:bg-[#050e1a] pb-12">

    <!-- ================= Header ================= -->
    <section class="bg-base-100 dark:bg-[#071829] border-b border-base-300 dark:border-white/10 relative overflow-hidden">
      <div
        class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-30"
        style="background: radial-gradient(ellipse at top left, rgba(220,38,38,0.06), transparent 55%);"
      ></div>
      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 relative flex flex-wrap gap-4 justify-between items-center">
        <div class="min-w-0">
          <span class="inline-flex items-center gap-2 rounded-full bg-red-50 dark:bg-red-500/10 px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.15em] text-red-700 dark:text-red-300">
            <span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>
            Citizen Portal
          </span>
          <h1 class="mt-2 text-xl sm:text-2xl font-bold text-slate-900 dark:text-white truncate">Settings</h1>
          <p class="text-sm text-slate-500 dark:text-white/45">Manage your account, security, notification, and appearance preferences.</p>
        </div>

        <span class="inline-flex w-fit items-center gap-2 rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-xs font-bold text-slate-500 shadow-sm dark:border-white/15 dark:bg-white/5 dark:text-white/60">
          <Icon icon="lucide:user-round-cog" width="16" /> {{ user?.roleLabel }}
        </span>
      </div>
    </section>

    <div class="mx-auto max-w-6xl space-y-5 px-4 pt-6 sm:px-6 lg:px-8">

      <!-- ================= Account details + Two-factor ================= -->
      <!-- Side by side on desktop (account form gets the wider column),
           stacked in document order on mobile since no base grid-cols is set. -->
      <div class="grid gap-5 lg:grid-cols-3 lg:items-start">
      <form class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829] sm:p-7 lg:col-span-2" @submit.prevent="saveAccount">
        <div class="flex items-start gap-3">
          <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon icon="lucide:user-round" width="20" /></span>
          <div>
            <h2 class="text-base font-black text-slate-900 dark:text-white">Account details</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-white/40">Keep your contact details accurate for account and operational communication.</p>
          </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">First name</label>
            <input v-model.trim="account.first_name" required class="settings-input" />
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">Last name</label>
            <input v-model.trim="account.last_name" required class="settings-input" />
          </div>
          <div class="sm:col-span-2">
            <label class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">Email address</label>
            <input :value="account.email" type="email" disabled class="settings-input cursor-not-allowed bg-slate-50 text-slate-400 dark:bg-white/[0.03] dark:text-white/30" />
            <p class="mt-1 text-[0.68rem] text-slate-400 dark:text-white/30">Email changes require a separate verification flow.</p>
          </div>
          <div class="sm:col-span-2">
            <label class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">Mobile number</label>
            <input v-model.trim="account.phone" type="tel" required placeholder="09XXXXXXXXX" class="settings-input" />
          </div>
        </div>

        <!-- Password change — folded into the same save, off by default -->
        <div class="mt-6 border-t border-slate-100 pt-5 dark:border-white/5">
          <button
            type="button"
            class="flex w-full items-center justify-between gap-2 text-left"
            @click="showPasswordFields = !showPasswordFields"
          >
            <span class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-white/70">
              <Icon icon="lucide:key-round" width="16" class="text-slate-400 dark:text-white/35" />
              Change password
            </span>
            <Icon :icon="showPasswordFields ? 'lucide:chevron-up' : 'lucide:chevron-down'" width="16" class="text-slate-400 dark:text-white/35" />
          </button>

          <div v-if="showPasswordFields" class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <label class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">Current password</label>
              <input v-model="account.current_password" type="password" autocomplete="current-password" class="settings-input" />
            </div>
            <div>
              <label class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">New password</label>
              <input v-model="account.new_password" type="password" autocomplete="new-password" minlength="10" class="settings-input" />
            </div>
            <div>
              <label class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">Confirm new password</label>
              <input v-model="account.new_password_confirmation" type="password" autocomplete="new-password" minlength="10" class="settings-input" />
            </div>
            <p class="text-[0.68rem] text-slate-400 dark:text-white/30 sm:col-span-2">
              Use at least 10 characters with upper and lowercase letters, a number, and a symbol.
            </p>
          </div>
        </div>

        <button type="submit" :disabled="savingAccount" class="mt-6 inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white transition-colors hover:bg-red-700 disabled:opacity-50">
          <Icon :icon="savingAccount ? 'lucide:loader-circle' : 'lucide:save'" width="16" :class="savingAccount ? 'animate-spin' : ''" />
          {{ savingAccount ? 'Saving…' : 'Save changes' }}
        </button>
      </form>

      <!-- ================= Two-factor authentication ================= -->
      <section class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829] sm:p-7 lg:col-span-1">
        <div class="flex flex-col gap-5">
          <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300"><Icon icon="lucide:shield-check" width="20" /></span>
            <div>
              <h2 class="text-base font-black text-slate-900 dark:text-white">Two-factor authentication</h2>
              <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-white/40">
                When enabled, DASCARE emails you a one-time verification code every time you log in.
              </p>
            </div>
          </div>
          <button
            type="button"
            :disabled="savingTwoFactor"
            class="relative h-7 w-12 flex-shrink-0 rounded-full transition-colors disabled:opacity-50"
            :class="twoFactorEnabled ? 'bg-red-600' : 'bg-slate-200 dark:bg-white/10'"
            @click="openTwoFactorConfirm"
          >
            <span class="absolute top-1 h-5 w-5 rounded-full bg-base-100 shadow transition-all" :class="twoFactorEnabled ? 'left-6' : 'left-1'"></span>
          </button>
          <span class="text-xs font-bold" :class="twoFactorEnabled ? 'text-emerald-600 dark:text-emerald-300' : 'text-slate-400 dark:text-white/35'">
            {{ twoFactorEnabled ? 'Enabled' : 'Disabled' }}
          </span>
        </div>
      </section>
      </div>

      <!-- ================= Appearance + Notifications ================= -->
      <div class="grid gap-5 lg:grid-cols-2">
        <section class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829] sm:p-7">
          <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300"><Icon icon="lucide:palette" width="20" /></span>
            <div>
              <h2 class="text-base font-black text-slate-900 dark:text-white">Appearance</h2>
              <p class="mt-1 text-xs text-slate-500 dark:text-white/40">Theme preference is shared across every DASCARE dashboard.</p>
            </div>
          </div>
          <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <button type="button" class="rounded-2xl border p-4 text-left transition-all" :class="themePreference === 'light' ? 'border-red-300 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10' : 'border-base-300 hover:bg-base-200 dark:border-white/10 dark:hover:bg-white/5'" @click="applyTheme('light')">
              <Icon icon="lucide:sun" width="20" class="text-amber-500" />
              <p class="mt-3 text-sm font-black text-slate-900 dark:text-white">Light</p>
              <p class="mt-1 text-xs text-slate-400 dark:text-white/30">Default DASCARE theme</p>
            </button>
            <button type="button" class="rounded-2xl border p-4 text-left transition-all" :class="themePreference === 'dark' ? 'border-red-300 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10' : 'border-base-300 hover:bg-base-200 dark:border-white/10 dark:hover:bg-white/5'" @click="applyTheme('dark')">
              <Icon icon="lucide:moon" width="20" class="text-blue-500" />
              <p class="mt-3 text-sm font-black text-slate-900 dark:text-white">Dark</p>
              <p class="mt-1 text-xs text-slate-400 dark:text-white/30">Reduced glare for long shifts</p>
            </button>
            <button type="button" class="rounded-2xl border p-4 text-left transition-all" :class="themePreference === 'system' ? 'border-red-300 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10' : 'border-base-300 hover:bg-base-200 dark:border-white/10 dark:hover:bg-white/5'" @click="applyTheme('system')">
              <Icon icon="lucide:monitor-cog" width="20" class="text-slate-500 dark:text-white/60" />
              <p class="mt-3 text-sm font-black text-slate-900 dark:text-white">System</p>
              <p class="mt-1 text-xs text-slate-400 dark:text-white/30">Follow your device preference</p>
            </button>
          </div>
        </section>

        <section class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829] sm:p-7">
          <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300"><Icon icon="lucide:bell-ring" width="20" /></span>
            <div>
              <h2 class="text-base font-black text-slate-900 dark:text-white">Notifications</h2>
              <p class="mt-1 text-xs text-slate-500 dark:text-white/40">Choose how this dashboard should surface account and operational updates.</p>
            </div>
          </div>
          <div class="mt-5 divide-y divide-slate-100 dark:divide-white/5">
            <label v-for="option in notificationOptions" :key="option.key" class="flex cursor-pointer items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
              <span>
                <span class="block text-sm font-bold text-slate-800 dark:text-white/70">{{ option.label }}</span>
                <span class="mt-0.5 block text-xs text-slate-400 dark:text-white/30">{{ option.description }}</span>
              </span>
              <input v-model="preferences[option.key]" type="checkbox" class="toggle toggle-sm border-slate-300 bg-slate-200 text-red-600 [--tglbg:white] checked:border-red-600 checked:bg-red-600" @change="savePreferences" />
            </label>
          </div>
        </section>
      </div>
    </div>

    <!-- ================= Password confirmation modal (2FA gate) ================= -->
    <div v-if="twoFactorConfirmOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4" @click.self="closeTwoFactorConfirm">
      <div class="w-full max-w-sm rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl dark:border-white/10 dark:bg-[#071829]">
        <div class="flex items-start gap-3">
          <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300">
            <Icon icon="lucide:shield-check" width="18" />
          </span>
          <div>
            <h3 class="text-sm font-black text-slate-900 dark:text-white">
              {{ pendingTwoFactorValue ? 'Enable two-factor authentication?' : 'Disable two-factor authentication?' }}
            </h3>
            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-white/40">
              Confirm your password to {{ pendingTwoFactorValue ? 'turn on' : 'turn off' }} email verification codes at login.
            </p>
          </div>
        </div>

        <form class="mt-5" @submit.prevent="confirmTwoFactor">
          <label class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">Current password</label>
          <input
            v-model="twoFactorPassword"
            type="password"
            autocomplete="current-password"
            required
            autofocus
            class="settings-input"
          />

          <div class="mt-5 flex gap-3">
            <button type="button" class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 transition-colors hover:bg-base-200 dark:border-white/10 dark:text-white/60 dark:hover:bg-white/5" @click="closeTwoFactorConfirm">
              Cancel
            </button>
            <button
              type="submit"
              :disabled="savingTwoFactor || !twoFactorPassword"
              class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-red-700 disabled:opacity-50"
            >
              <Icon :icon="savingTwoFactor ? 'lucide:loader-circle' : 'lucide:check'" width="16" :class="savingTwoFactor ? 'animate-spin' : ''" />
              {{ savingTwoFactor ? 'Confirming…' : 'Confirm' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>
</template>

<script setup>
import axios from 'axios'
import { onMounted, reactive, ref } from 'vue'
import { Icon } from '@iconify/vue'
import { useSession } from '@/composables/useSession'
import { useTheme } from '@/composables/useTheme'
import { useAlert } from '@/composables/useAlert'

// This page used to just delegate to <AccountSettingsPanel>. It's inlined
// directly here now — this component IS the citizen settings page, not a
// pass-through — so the workspace label and storage key below are fixed
// to the citizen portal instead of being passed in as props.
const WORKSPACE_LABEL = 'Citizen Portal'
const PREFERENCE_KEY = 'dascare-settings-citizen'

const API_BASE = import.meta.env.VITE_API_BASE_URL

const alert = useAlert()
const { user, fetchSession } = useSession()
const { theme, themePreference, applyTheme } = useTheme()

// ------------------------------------------------------------------
// Account details (profile + optional password change, one endpoint)
// ------------------------------------------------------------------
const account = reactive({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
})
const showPasswordFields = ref(false)
const savingAccount = ref(false)

const preferences = reactive({ operationalAlerts: true, accountAlerts: true, emailUpdates: true })
const notificationOptions = [
  { key: 'operationalAlerts', label: 'Operational alerts', description: 'Mission, review, assignment, or system activity relevant to your role.' },
  { key: 'accountAlerts', label: 'Account and security alerts', description: 'Password, login, verification, and permission changes.' },
  { key: 'emailUpdates', label: 'Email notifications', description: 'Also send supported notifications to your verified email.' },
]

const load = () => {
  const names = (user.value?.name || '').split(' ')
  account.first_name = names.shift() || ''
  account.last_name = names.join(' ')
  account.email = user.value?.email || ''
  account.phone = user.value?.phone || ''
  twoFactorEnabled.value = Boolean(user.value?.hasTwoFactor)
  try {
    Object.assign(preferences, JSON.parse(localStorage.getItem(PREFERENCE_KEY) || '{}'))
  } catch { /* keep defaults */ }
}

const savePreferences = () => {
  try { localStorage.setItem(PREFERENCE_KEY, JSON.stringify(preferences)) } catch { /* storage is optional */ }
  alert.success('Notification preferences saved')
}

const saveAccount = async () => {
  if (showPasswordFields.value && account.new_password && account.new_password !== account.new_password_confirmation) {
    alert.error('New password and confirmation do not match')
    return
  }

  savingAccount.value = true
  try {
    const form = new FormData()
    form.append('first_name', account.first_name)
    form.append('last_name', account.last_name)
    form.append('phone', account.phone)
    if (showPasswordFields.value) {
      form.append('current_password', account.current_password)
      form.append('new_password', account.new_password)
      form.append('new_password_confirmation', account.new_password_confirmation)
    }

    const { data } = await axios.post(`${API_BASE}/account/edit_profile.php`, form, { withCredentials: true })
    if (!data.success) throw new Error(data.message || 'Could not update your account')

    account.current_password = ''
    account.new_password = ''
    account.new_password_confirmation = ''
    showPasswordFields.value = false

    await fetchSession(true)
    alert.success(data.message || 'Account updated')
  } catch (error) {
    alert.error(error.response?.data?.message || error.message || 'Could not update your account')
  } finally {
    savingAccount.value = false
  }
}

// ------------------------------------------------------------------
// Two-factor authentication — password-gated toggle
// ------------------------------------------------------------------
const twoFactorEnabled = ref(false)
const savingTwoFactor = ref(false)
const twoFactorConfirmOpen = ref(false)
const twoFactorPassword = ref('')
const pendingTwoFactorValue = ref(false)

const openTwoFactorConfirm = () => {
  pendingTwoFactorValue.value = !twoFactorEnabled.value
  twoFactorPassword.value = ''
  twoFactorConfirmOpen.value = true
}

const closeTwoFactorConfirm = () => {
  if (savingTwoFactor.value) return
  twoFactorConfirmOpen.value = false
  twoFactorPassword.value = ''
}

const confirmTwoFactor = async () => {
  savingTwoFactor.value = true
  try {
    const { data } = await axios.post(
      `${API_BASE}/account/toggle_two_factor.php`,
      { current_password: twoFactorPassword.value, enable: pendingTwoFactorValue.value ? 1 : 0 },
      { withCredentials: true }
    )
    if (!data.success) throw new Error(data.message || 'Could not update two-factor authentication')

    twoFactorEnabled.value = Boolean(data.has_two_factor)
    twoFactorConfirmOpen.value = false
    twoFactorPassword.value = ''
    await fetchSession(true)
    alert.success(data.message || (twoFactorEnabled.value ? 'Two-factor authentication enabled' : 'Two-factor authentication disabled'))
  } catch (error) {
    alert.error(error.response?.data?.message || error.message || 'Could not update two-factor authentication')
  } finally {
    savingTwoFactor.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.settings-input {
  width: 100%;
  border-radius: 0.75rem;
  border: 1px solid rgb(226 232 240);
  background: white;
  padding: 0.7rem 0.875rem;
  font-size: 0.875rem;
  color: rgb(15 23 42);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.settings-input:focus {
  outline: none;
  border-color: rgb(239 68 68);
  box-shadow: 0 0 0 3px rgb(239 68 68 / 0.14);
}
:global([data-theme='dark']) .settings-input {
  border-color: rgb(255 255 255 / 0.1);
  background: rgb(255 255 255 / 0.05);
  color: white;
}
</style>