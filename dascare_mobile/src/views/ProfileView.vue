<template>
  <div class="space-y-4 px-4 pt-4">
    <section :class="[ui.card, 'p-5']">
      <div class="flex items-center gap-4">
        <span class="grid h-14 w-14 flex-shrink-0 place-items-center rounded-2xl bg-red-50 text-lg font-black text-red-600 dark:bg-red-500/10 dark:text-red-300">{{ initials }}</span>
        <div class="min-w-0">
          <p class="truncate text-lg font-black text-slate-950 dark:text-white">{{ user?.name }}</p>
          <p class="truncate text-xs text-slate-500 dark:text-white/45">{{ user?.roleLabel || 'Citizen' }}</p>
        </div>
      </div>
      <dl class="mt-4 space-y-2 border-t border-base-300 pt-4 text-sm dark:border-white/10">
        <div class="flex justify-between gap-4"><dt class="text-slate-400">Email</dt><dd class="selectable min-w-0 truncate font-semibold text-slate-700 dark:text-white/70">{{ user?.email }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-slate-400">Mobile</dt><dd class="selectable font-semibold text-slate-700 dark:text-white/70">{{ user?.phone || '—' }}</dd></div>
      </dl>
    </section>

    <!-- Emergency information + account -->
    <section :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
      <RouterLink :to="{ name: 'MedicalRecords' }" class="tap flex items-center gap-3 p-4 no-underline">
        <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon icon="lucide:heart-pulse" width="20" /></span>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-bold text-slate-900 dark:text-white">Emergency information</p>
          <p class="text-xs text-slate-500 dark:text-white/45">Blood type, allergies, medications, emergency contact</p>
        </div>
        <Icon :icon="kycStatus === 2 ? 'lucide:chevron-right' : 'lucide:lock'" width="18" class="text-slate-400" />
      </RouterLink>
      <RouterLink :to="{ name: 'Account' }" class="tap flex items-center gap-3 p-4 no-underline">
        <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-white/60"><Icon icon="lucide:user-cog" width="20" /></span>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-bold text-slate-900 dark:text-white">Account & security</p>
          <p class="text-xs text-slate-500 dark:text-white/45">Name, mobile number, password, two-factor</p>
        </div>
        <Icon icon="lucide:chevron-right" width="18" class="text-slate-400" />
      </RouterLink>
    </section>

    <!-- Identity verification -->
    <RouterLink :to="{ name: 'VerifyIdentity' }" :class="[ui.card, 'tap flex items-center gap-3 p-4 no-underline']">
      <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-xl" :class="kyc.tile"><Icon :icon="kyc.icon" width="20" /></span>
      <div class="min-w-0 flex-1">
        <p class="text-sm font-bold text-slate-900 dark:text-white">Identity verification</p>
        <p class="text-xs" :class="kyc.text">{{ kyc.label }}</p>
      </div>
      <Icon icon="lucide:chevron-right" width="18" class="text-slate-400" />
    </RouterLink>

    <!-- Appearance — same Light / Dark / System choice as the web settings -->
    <section :class="[ui.card, 'p-4']">
      <p class="text-sm font-bold text-slate-900 dark:text-white">Appearance</p>
      <div class="mt-3 grid grid-cols-3 gap-2 rounded-2xl bg-base-200 p-1 dark:bg-white/5">
        <button v-for="opt in themeOptions" :key="opt.value" type="button" class="tap flex items-center justify-center gap-1.5 rounded-xl py-2.5 text-xs font-bold transition-colors"
          :class="themePreference === opt.value ? 'bg-base-100 text-red-600 shadow-sm dark:bg-[#0d2943] dark:text-red-300' : 'text-slate-500 dark:text-white/50'"
          @click="applyTheme(opt.value)">
          <Icon :icon="opt.icon" width="15" /> {{ opt.label }}
        </button>
      </div>
    </section>

    <section :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
      <RouterLink :to="{ name: 'Legal', params: { slug: 'terms-of-service' } }" class="tap flex items-center gap-3 p-4 no-underline">
        <Icon icon="lucide:scroll-text" width="18" class="text-slate-400" />
        <span class="flex-1 text-sm font-semibold text-slate-700 dark:text-white/70">Terms of Service</span>
        <Icon icon="lucide:chevron-right" width="18" class="text-slate-400" />
      </RouterLink>
      <RouterLink :to="{ name: 'Legal', params: { slug: 'privacy-policy' } }" class="tap flex items-center gap-3 p-4 no-underline">
        <Icon icon="lucide:lock" width="18" class="text-slate-400" />
        <span class="flex-1 text-sm font-semibold text-slate-700 dark:text-white/70">Privacy Policy</span>
        <Icon icon="lucide:chevron-right" width="18" class="text-slate-400" />
      </RouterLink>
      <RouterLink to="/diagnostics" class="tap flex items-center gap-3 p-4 no-underline">
        <Icon icon="lucide:server" width="18" class="text-slate-400" />
        <span class="flex-1 text-sm font-semibold text-slate-700 dark:text-white/70">Server connection</span>
        <Icon icon="lucide:chevron-right" width="18" class="text-slate-400" />
      </RouterLink>
      <button type="button" class="tap flex w-full items-center gap-3 p-4 text-left" :disabled="signingOut" @click="signOut">
        <Icon :icon="signingOut ? 'lucide:loader-circle' : 'lucide:log-out'" width="18" class="text-red-600 dark:text-red-400" :class="signingOut ? 'animate-spin' : ''" />
        <span class="flex-1 text-sm font-bold text-red-600 dark:text-red-400">Log out</span>
      </button>
    </section>

    <p class="pb-2 text-center text-[0.65rem] font-semibold text-slate-400 dark:text-white/30">DASCARE mobile v1.0.0</p>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import * as ui from '@/components/ui/styles'
import { useSession } from '@/composables/useSession'
import { useTheme } from '@/composables/useTheme'
import { useAlert } from '@/composables/useAlert'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const { user, kycStatus, logout } = useSession()
const { themePreference, applyTheme } = useTheme()
const alert = useAlert()
const toast = useToast()
const signingOut = ref(false)

const initials = computed(() => (user.value?.name || '?').split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase())
const themeOptions = [
  { value: 'light', label: 'Light', icon: 'lucide:sun' },
  { value: 'dark', label: 'Dark', icon: 'lucide:moon' },
  { value: 'system', label: 'System', icon: 'lucide:smartphone' },
]
const kyc = computed(() => ({
  0: { label: 'Not verified — tap to verify', icon: 'lucide:shield-alert', tile: 'bg-[#1976D2]/10 text-[#1976D2] dark:text-[#7fb3ec]', text: 'text-[#1976D2] dark:text-[#7fb3ec]' },
  1: { label: 'Under review', icon: 'lucide:hourglass', tile: 'bg-yellow-50 text-yellow-600 dark:bg-yellow-500/10 dark:text-yellow-400', text: 'text-yellow-700 dark:text-yellow-300' },
  2: { label: 'Verified', icon: 'lucide:shield-check', tile: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400', text: 'text-emerald-700 dark:text-emerald-300' },
  3: { label: 'Not approved — tap to re-submit', icon: 'lucide:x-circle', tile: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400', text: 'text-red-600 dark:text-red-300' },
  4: { label: 'Resubmission requested', icon: 'lucide:rotate-ccw', tile: 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400', text: 'text-amber-700 dark:text-amber-300' },
}[kycStatus.value] ?? {}))

async function signOut() {
  if (!(await alert.confirm('You can still send an emergency SOS without logging in.', 'Log out of DASCARE?'))) return
  signingOut.value = true
  await logout()
  signingOut.value = false
  toast.info('You have been logged out.', 'Signed out')
  router.replace({ name: 'Welcome' })
}
</script>
