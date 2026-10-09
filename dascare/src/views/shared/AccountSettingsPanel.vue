<template>
  <section class="min-h-screen bg-slate-50 pb-12 dark:bg-[#081b2e]">
    <div class="mx-auto max-w-4xl space-y-6 px-4 py-7 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">{{ workspaceLabel }}</p>
          <h1 class="mt-1 text-3xl font-black tracking-tight text-slate-950 dark:text-white">Settings</h1>
          <p class="mt-2 text-sm text-slate-500 dark:text-white/45">Manage the same account, security, notification, and appearance preferences from this workspace.</p>
        </div>
        <span class="inline-flex w-fit items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-500 shadow-sm dark:border-white/10 dark:bg-[#0d2943] dark:text-white/45">
          <Icon icon="lucide:user-round-cog" width="16" /> {{ user?.roleLabel }}
        </span>
      </div>

      <form class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-7" @submit.prevent="saveProfile">
        <div class="flex items-start gap-3">
          <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon icon="lucide:user-round" width="20" /></span>
          <div>
            <h2 class="text-base font-black text-slate-900 dark:text-white">Profile</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-white/40">Keep your contact details accurate for account and operational communication.</p>
          </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">First name</label>
            <input v-model.trim="profile.first_name" required class="settings-input" />
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">Last name</label>
            <input v-model.trim="profile.last_name" required class="settings-input" />
          </div>
          <div class="sm:col-span-2">
            <label class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">Email address</label>
            <input :value="profile.email" type="email" disabled class="settings-input cursor-not-allowed bg-slate-50 text-slate-400 dark:bg-white/[0.03] dark:text-white/30" />
            <p class="mt-1 text-[0.68rem] text-slate-400 dark:text-white/30">Email changes require a separate verification flow.</p>
          </div>
          <div class="sm:col-span-2">
            <label class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">Mobile number</label>
            <input v-model.trim="profile.phone" type="tel" required placeholder="09XXXXXXXXX" class="settings-input" />
          </div>
        </div>

        <button type="submit" :disabled="savingProfile" class="mt-5 inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white transition-colors hover:bg-red-700 disabled:opacity-50">
          <Icon :icon="savingProfile ? 'lucide:loader-circle' : 'lucide:save'" width="16" :class="savingProfile ? 'animate-spin' : ''" />
          {{ savingProfile ? 'Saving…' : 'Save profile' }}
        </button>
      </form>

      <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-7">
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

        <section class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-7">
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

      <section class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-7">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300"><Icon icon="lucide:shield-check" width="20" /></span>
            <div>
              <h2 class="text-base font-black text-slate-900 dark:text-white">Two-factor authentication</h2>
              <p class="mt-1 max-w-xl text-xs leading-5 text-slate-500 dark:text-white/40">When enabled, DASCARE sends an email verification code during login.</p>
            </div>
          </div>
          <button type="button" :disabled="savingTwoFactor" class="relative h-7 w-12 flex-shrink-0 rounded-full transition-colors disabled:opacity-50" :class="twoFactorEnabled ? 'bg-red-600' : 'bg-slate-200 dark:bg-white/10'" @click="toggleTwoFactor">
            <span class="absolute top-1 h-5 w-5 rounded-full bg-white shadow transition-all" :class="twoFactorEnabled ? 'left-6' : 'left-1'"></span>
          </button>
        </div>
      </section>

      <form class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-7" @submit.prevent="changePassword">
        <div class="flex items-start gap-3">
          <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-white/55"><Icon icon="lucide:key-round" width="20" /></span>
          <div>
            <h2 class="text-base font-black text-slate-900 dark:text-white">Change password</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-white/40">Use at least 10 characters with upper and lowercase letters, a number, and a symbol.</p>
          </div>
        </div>
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">Current password</label>
            <input v-model="passwordForm.current_password" type="password" required class="settings-input" />
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">New password</label>
            <input v-model="passwordForm.new_password" type="password" required minlength="10" class="settings-input" />
          </div>
        </div>
        <button type="submit" :disabled="savingPassword" class="mt-5 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white transition-colors hover:bg-slate-800 disabled:opacity-50 dark:bg-white/10 dark:hover:bg-white/15">
          <Icon :icon="savingPassword ? 'lucide:loader-circle' : 'lucide:key-round'" width="16" :class="savingPassword ? 'animate-spin' : ''" />
          {{ savingPassword ? 'Updating…' : 'Update password' }}
        </button>
      </form>
    </div>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import api from '@/services/api'
import { useSession } from '@/composables/useSession'
import { useTheme } from '@/composables/useTheme'
import { useAlert } from '@/composables/useAlert'

const props = defineProps({
  workspaceLabel: { type: String, required: true },
  preferenceKey: { type: String, required: true },
})

const alert = useAlert()
const { user, fetchSession } = useSession()
const { theme, themePreference, applyTheme } = useTheme()
const profile = reactive({ first_name: '', last_name: '', email: '', phone: '' })
const passwordForm = reactive({ current_password: '', new_password: '' })
const twoFactorEnabled = ref(false)
const savingProfile = ref(false)
const savingPassword = ref(false)
const savingTwoFactor = ref(false)
const preferences = reactive({ operationalAlerts: true, accountAlerts: true, emailUpdates: true })

const notificationOptions = [
  { key: 'operationalAlerts', label: 'Operational alerts', description: 'Mission, review, assignment, or system activity relevant to your role.' },
  { key: 'accountAlerts', label: 'Account and security alerts', description: 'Password, login, verification, and permission changes.' },
  { key: 'emailUpdates', label: 'Email notifications', description: 'Also send supported notifications to your verified email.' },
]

const load = () => {
  const names = (user.value?.name || '').split(' ')
  profile.first_name = names.shift() || ''
  profile.last_name = names.join(' ')
  profile.email = user.value?.email || ''
  profile.phone = user.value?.phone || ''
  twoFactorEnabled.value = Boolean(user.value?.hasTwoFactor)
  try {
    Object.assign(preferences, JSON.parse(localStorage.getItem(props.preferenceKey) || '{}'))
  } catch { /* keep defaults */ }
}

const savePreferences = () => {
  try { localStorage.setItem(props.preferenceKey, JSON.stringify(preferences)) } catch { /* storage is optional */ }
  alert.success('Notification preferences saved')
}

const saveProfile = async () => {
  savingProfile.value = true
  try {
    const { data } = await api.post('/account/profile/update.php', profile)
    if (!data.success) throw new Error(data.message || 'Could not update profile')
    await fetchSession(true)
    alert.success('Profile updated')
  } catch (error) {
    alert.error(error.response?.data?.message || error.message || 'Could not update profile')
  } finally {
    savingProfile.value = false
  }
}

const toggleTwoFactor = async () => {
  savingTwoFactor.value = true
  const next = !twoFactorEnabled.value
  try {
    const { data } = await api.post('/account/security/two_factor.php', { enabled: next })
    if (!data.success) throw new Error(data.message || 'Could not update two-factor authentication')
    twoFactorEnabled.value = next
    await fetchSession(true)
    alert.success(next ? 'Two-factor authentication enabled' : 'Two-factor authentication disabled')
  } catch (error) {
    alert.error(error.response?.data?.message || error.message || 'Could not update two-factor authentication')
  } finally {
    savingTwoFactor.value = false
  }
}

const changePassword = async () => {
  savingPassword.value = true
  try {
    const { data } = await api.post('/account/profile/change_password.php', passwordForm)
    if (!data.success) throw new Error(data.message || 'Could not update password')
    passwordForm.current_password = ''
    passwordForm.new_password = ''
    alert.success('Password updated')
  } catch (error) {
    alert.error(error.response?.data?.message || error.message || 'Could not update password')
  } finally {
    savingPassword.value = false
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
