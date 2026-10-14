<template>
  <!-- Staff "More" tab: every organization section this member can open
       (same groups and permission keys as the web sidebar), then account,
       appearance and sign out. -->
  <div class="space-y-3 px-4 pt-2">
    <section :class="[ui.card, 'p-5']">
      <div class="flex items-center gap-4">
        <span class="grid h-14 w-14 flex-shrink-0 place-items-center rounded-2xl bg-red-50 text-lg font-black text-red-600 dark:bg-red-500/10 dark:text-red-300">{{ initials }}</span>
        <div class="min-w-0">
          <p class="truncate text-lg font-black text-slate-950 dark:text-white">{{ user?.name }}</p>
          <p class="truncate text-xs text-slate-500 dark:text-white/45">{{ roleLabel }}</p>
          <p class="truncate text-xs font-semibold text-slate-600 dark:text-white/60">{{ organization?.name }}</p>
        </div>
      </div>
    </section>

    <template v-for="g in groups" :key="g.group">
      <p class="px-2 pt-2 text-[0.68rem] font-black uppercase tracking-[0.16em] text-slate-400 dark:text-white/35">{{ g.group }}</p>
      <section :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
        <component :is="s.route ? 'RouterLink' : 'div'" v-for="s in g.items" :key="s.key" :to="s.route ? { name: s.route } : undefined"
          class="tap flex items-center gap-3 p-4 no-underline" :class="s.route ? '' : 'opacity-50'">
          <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon :icon="s.icon" width="20" /></span>
          <span class="min-w-0 flex-1 text-sm font-bold text-slate-900 dark:text-white">{{ s.label }}</span>
          <Icon v-if="s.route" icon="lucide:chevron-right" width="18" class="text-slate-400" />
          <span v-else class="rounded-full bg-base-200 px-2 py-0.5 text-[0.6rem] font-bold uppercase text-slate-500 dark:bg-white/5 dark:text-white/40">Next step</span>
        </component>
      </section>
    </template>

    <p class="px-2 pt-2 text-[0.68rem] font-black uppercase tracking-[0.16em] text-slate-400 dark:text-white/35">Account</p>
    <section :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
      <RouterLink :to="{ name: 'Account' }" class="tap flex items-center gap-3 p-4 no-underline">
        <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-white/60"><Icon icon="lucide:user-cog" width="20" /></span>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-bold text-slate-900 dark:text-white">Account & security</p>
          <p class="text-xs text-slate-500 dark:text-white/45">Name, mobile number, password, two-factor</p>
        </div>
        <Icon icon="lucide:chevron-right" width="18" class="text-slate-400" />
      </RouterLink>
    </section>

    <p class="px-2 pt-2 text-[0.68rem] font-black uppercase tracking-[0.16em] text-slate-400 dark:text-white/35">Appearance</p>
    <section :class="[ui.card, 'p-2']">
      <div class="grid grid-cols-3 gap-2 rounded-2xl bg-base-200 p-1 dark:bg-white/5">
        <button v-for="opt in themeOptions" :key="opt.value" type="button" class="tap flex items-center justify-center gap-1.5 rounded-xl py-2.5 text-xs font-bold transition-colors"
          :class="themePreference === opt.value ? 'bg-base-100 text-red-600 shadow-sm dark:bg-[#0d2943] dark:text-red-300' : 'text-slate-500 dark:text-white/50'"
          @click="applyTheme(opt.value)">
          <Icon :icon="opt.icon" width="15" /> {{ opt.label }}
        </button>
      </div>
    </section>

    <section :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
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
    <p class="pb-2 text-center text-[0.65rem] font-semibold text-slate-400 dark:text-white/30">DASCARE mobile v{{ appVersion }} · staff</p>
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
import { STAFF_SECTIONS, STAFF_SECTION_ROUTES } from '@/config/staffSections'

const appVersion = __APP_VERSION__
const router = useRouter()
const { user, organization, isOrgAdmin, hasPermission, logout } = useSession()
const { themePreference, applyTheme } = useTheme()
const alert = useAlert()
const toast = useToast()
const signingOut = ref(false)

const initials = computed(() => (user.value?.name || '?').split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase())
const roleLabel = computed(() => (isOrgAdmin.value ? 'Organization admin' : (organization.value?.roleNames || []).join(' · ') || 'Organization staff'))
const themeOptions = [
  { value: 'light', label: 'Light', icon: 'lucide:sun' },
  { value: 'dark', label: 'Dark', icon: 'lucide:moon' },
  { value: 'system', label: 'System', icon: 'lucide:smartphone' },
]

const groups = computed(() => STAFF_SECTIONS
  .map((g) => ({ group: g.group, items: g.items.filter((s) => hasPermission(...s.permissions)).map((s) => ({ ...s, route: STAFF_SECTION_ROUTES[s.key] || null })) }))
  .filter((g) => g.items.length))

async function signOut() {
  if (!(await alert.confirm('Missions assigned to you will still show on the web portal.', 'Log out of DASCARE?'))) return
  signingOut.value = true
  await logout()
  signingOut.value = false
  toast.info('You have been logged out.', 'Signed out')
  router.replace({ name: 'Welcome' })
}
</script>
