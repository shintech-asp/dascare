<template>
  <!-- Mobile overlay -->
  <Transition name="overlay-fade">
    <div v-if="isOpen" @click="isOpen = false" class="lg:hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-[55]" />
  </Transition>

  <!-- Sidebar -->
  <aside class="fixed lg:sticky top-0 left-0 h-screen z-[60] lg:z-auto flex flex-col
           border-r border-slate-200 dark:border-white/10 overflow-hidden bg-base-100 dark:bg-[#071829]
           transition-[width] duration-300 ease-[cubic-bezier(.4,0,.2,1)]
           max-lg:transition-transform max-lg:duration-300 max-lg:ease-[cubic-bezier(.4,0,.2,1)]
           max-lg:!w-[288px] max-lg:shadow-2xl" :class="[
            collapsed ? 'w-[76px]' : 'w-[272px]',
            isOpen ? 'max-lg:translate-x-0' : 'max-lg:-translate-x-full',
          ]">

    <!-- Header — logo sits on its own accent strip so the sidebar reads
         as its own surface instead of a plain panel -->
    <div class="relative flex-shrink-0 bg-base-100 dark:bg-transparent dark:bg-gradient-to-br dark:from-white/[0.04] dark:to-transparent border-b border-slate-100 dark:border-white/5">
      <div class="relative flex items-center justify-between px-4 pt-4 pb-4">
        <div class="flex items-center gap-2.5 min-w-0 overflow-hidden">
          <div class="w-10 h-10 flex-shrink-0 rounded-xl overflow-hidden bg-base-100 ring-1 ring-black/5 shadow-sm">
            <img src="../../../img/logo-name-bg.jpg" alt="DASCARE Logo" class="w-full h-full object-cover" />
          </div>

          <Transition name="label-fade">
            <div v-if="!collapsed" class="min-w-0">
              <p class="text-[0.95rem] font-black text-slate-900 dark:text-white whitespace-nowrap tracking-tight leading-tight">
                DASCARE
              </p>
              <p class="text-[0.68rem] font-medium text-slate-400 dark:text-white/40 whitespace-nowrap -mt-0.5">
                Citizen Portal
              </p>
            </div>
          </Transition>
        </div>

        <!-- Collapse toggle (desktop only) -->
        <button v-if="!collapsed" @click="collapsed = true"
          class="hidden lg:flex w-7 h-7 flex-shrink-0 items-center justify-center rounded-lg
                   text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10 dark:text-white/40 hover:text-slate-600 dark:hover:text-white transition-colors">
          <Icon icon="lucide:panel-left-close" width="16" />
        </button>

        <!-- Mobile close -->
        <button @click="isOpen = false" class="lg:hidden w-8 h-8 flex-shrink-0 flex items-center justify-center rounded-lg
                   text-slate-400 hover:bg-slate-100 dark:text-white/50 dark:hover:bg-white/10 transition-colors">
          <Icon icon="lucide:x" width="18" />
        </button>
      </div>
    </div>

    <!-- Re-expand handle when collapsed -->
    <button v-if="collapsed" @click="collapsed = false" title="Expand sidebar" class="hidden lg:flex mx-auto -mt-1 mb-2 w-9 h-6 flex-shrink-0 items-center justify-center rounded-lg
             text-slate-400 hover:bg-slate-100 dark:text-white/40 dark:hover:bg-white/10 transition-colors">
      <Icon icon="lucide:panel-left-open" width="15" />
    </button>

    <!-- User mini profile -->
    <div class="relative flex items-center gap-3 mt-3 mb-3 rounded-2xl overflow-hidden flex-shrink-0
             bg-base-200 dark:bg-white/[0.05] border border-slate-200 dark:border-white/10"
      :class="collapsed ? 'self-center w-fit p-2 justify-center' : 'mx-3 p-3'">
      <template v-if="currentUser">
        <div class="relative flex-shrink-0">
          <div
            class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-600 to-red-700 flex items-center justify-center shadow-sm">
            <span class="text-[0.8rem] font-bold text-white">{{ initials }}</span>
          </div>
          <span
            class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-white dark:border-[#071829] bg-emerald-500"></span>
        </div>

        <Transition name="label-fade">
          <div v-if="!collapsed" class="min-w-0 flex-1">
            <p class="text-[0.83rem] font-bold text-slate-900 dark:text-white truncate leading-tight">
              {{ currentUser.name }}
            </p>
            <p class="text-[0.7rem] text-slate-400 dark:text-white/40 truncate">Citizen account</p>
          </div>
        </Transition>
      </template>

      <template v-else>
        <div class="w-10 h-10 rounded-xl bg-slate-200 dark:bg-white/10 animate-pulse flex-shrink-0"></div>
        <Transition name="label-fade">
          <div v-if="!collapsed" class="flex flex-col gap-1.5 flex-1 min-w-0">
            <div class="h-3 bg-slate-200 dark:bg-white/10 rounded animate-pulse w-24"></div>
            <div class="h-2.5 bg-slate-200/70 dark:bg-white/5 rounded animate-pulse w-16"></div>
          </div>
        </Transition>
      </template>
    </div>

    <!-- Primary action — always visible, never buried in the nav list.
         This is the one thing a citizen most urgently needs to find. -->
    <div class="px-3 pb-3 flex-shrink-0">
      <RouterLink to="/citizen/request" class="group flex items-center gap-3 rounded-2xl bg-red-600 hover:bg-red-700 text-white
               shadow-lg shadow-red-600/25 dark:shadow-red-950/40 transition-colors no-underline"
        :class="collapsed ? 'justify-center px-0 py-3' : 'px-3.5 py-3'" @click="isOpen = false">
        <span class="flex items-center justify-center w-9 h-9 rounded-xl bg-white/15 flex-shrink-0">
          <Icon icon="lucide:siren" width="18" />
        </span>
        <Transition name="label-fade">
          <div v-if="!collapsed" class="min-w-0 text-left">
            <p class="text-[0.85rem] font-bold leading-tight whitespace-nowrap">Request Assistance</p>
            <p class="text-[0.7rem] text-white/70 leading-tight whitespace-nowrap">Report an emergency now</p>
          </div>
        </Transition>
      </RouterLink>
    </div>

    <div class="px-3 pb-2 flex-shrink-0">
      <p v-if="!collapsed" class="px-1 text-[0.65rem] font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-white/30">
        Menu
      </p>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-3 pb-3 flex flex-col gap-1.5">
      <RouterLink v-for="item in navItems" :key="item.to" :to="item.to" custom v-slot="{ navigate, href }">
        <a :href="href" @click="handleNavClick($event, navigate)" :title="collapsed ? item.label : undefined"
          class="group flex items-center gap-3 px-2.5 py-2.5 rounded-2xl no-underline
                 overflow-hidden relative transition-all duration-150 border"
          :class="[
            collapsed ? 'justify-center' : '',
            isActive(item.to)
              ? 'bg-red-50 dark:bg-red-500/10 border-red-100 dark:border-red-500/20'
              : 'border-transparent hover:bg-slate-50 dark:hover:bg-white/5 hover:border-slate-200 dark:hover:border-white/10',
          ]">
          <span v-if="isActive(item.to)"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-6 rounded-full bg-red-600"></span>

          <span class="relative flex items-center justify-center w-10 h-10 rounded-xl flex-shrink-0 transition-all"
            :class="isActive(item.to)
              ? 'bg-red-100 dark:bg-red-500/15 text-red-600 dark:text-red-300'
              : 'bg-slate-100 dark:bg-white/5 text-slate-400 dark:text-white/40 group-hover:text-slate-600 dark:group-hover:text-white/70'">
            <Icon :icon="item.icon" width="18" />
            <span v-if="collapsed && badgeFor(item.to) > 0"
              class="absolute -right-1.5 -top-1.5 min-w-[18px] rounded-full bg-red-600 px-1 text-center text-[0.58rem] font-black leading-[18px] text-white shadow-sm ring-2 ring-white dark:ring-[#071829]">
              {{ formatBadge(badgeFor(item.to)) }}
            </span>
          </span>

          <Transition name="label-fade">
            <div v-if="!collapsed" class="flex-1 min-w-0 flex items-center justify-between gap-2">
              <div class="min-w-0">
                <p class="text-[0.85rem] font-bold leading-tight truncate"
                  :class="isActive(item.to) ? 'text-red-700 dark:text-red-300' : 'text-slate-800 dark:text-white/90'">
                  {{ item.label }}
                </p>
                <p class="text-[0.7rem] leading-tight truncate mt-0.5"
                  :class="isActive(item.to) ? 'text-red-600/70 dark:text-red-300/60' : 'text-slate-400 dark:text-white/35'">
                  {{ item.description }}
                </p>
              </div>

              <span v-if="badgeFor(item.to) > 0"
                class="text-[0.65rem] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0 bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-300">
                {{ formatBadge(badgeFor(item.to)) }}
              </span>
            </div>
          </Transition>
        </a>
      </RouterLink>
    </nav>

    <!-- Footer: emergency hotline reminder, then Settings + Logout grouped
         together as the account-management pair. -->
    <div class="px-3 pb-3 pt-2 border-t border-slate-200 dark:border-white/10 flex-shrink-0 flex flex-col gap-2">
      <!-- Emergency hotline card -->
      <div v-if="!collapsed"
        class="rounded-2xl border border-red-200 bg-red-50/70 p-3 dark:border-red-500/25 dark:bg-red-500/10">
        <div class="flex items-start gap-3">
          <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-300">
            <Icon icon="lucide:phone-call" width="17" />
          </span>

          <div class="min-w-0 flex-1">
            <p class="text-[0.72rem] font-black uppercase tracking-[0.08em] text-red-600 dark:text-red-300">
              Emergency?
            </p>
            <p class="mt-0.5 text-[0.68rem] leading-snug text-slate-600 dark:text-white/50">
              Life-threatening situation?
            </p>

            <a href="tel:911"
              class="mt-2.5 flex w-full items-center justify-center gap-2 rounded-full bg-red-600 px-4 py-2 text-[0.72rem] font-bold text-white no-underline shadow-md shadow-red-600/20 transition-colors hover:bg-red-700 dark:shadow-red-950/30">
              <Icon icon="lucide:phone" width="14" />
              Call 911 Now
            </a>
          </div>
        </div>
      </div>

      <RouterLink to="/citizen/settings" custom v-slot="{ navigate, href }">
        <a :href="href" @click="handleNavClick($event, navigate)" :title="collapsed ? 'Settings' : undefined"
          class="group flex items-center gap-3 rounded-2xl no-underline border border-slate-200 dark:border-white/10
                 bg-base-200 dark:bg-white/[0.03] hover:bg-blue-50 dark:hover:bg-[#1976D2]/10
                 hover:border-blue-200 dark:hover:border-[#1976D2]/30 transition-all duration-150"
          :class="collapsed ? 'justify-center px-0 py-2.5' : 'px-2.5 py-2.5'">
          <span class="flex items-center justify-center w-9 h-9 rounded-xl flex-shrink-0
                       bg-white dark:bg-white/5 text-slate-400 dark:text-white/40
                       group-hover:bg-[#1976D2]/10 group-hover:text-[#1976D2] dark:group-hover:text-[#7fb3ec] transition-colors">
            <Icon icon="lucide:settings" width="16" />
          </span>
          <Transition name="label-fade">
            <div v-if="!collapsed" class="min-w-0 flex-1 text-left">
              <p class="text-[0.82rem] font-bold leading-tight text-slate-800 dark:text-white/90 group-hover:text-[#1976D2] dark:group-hover:text-[#7fb3ec]">
                Settings
              </p>
              <p class="text-[0.68rem] leading-tight text-slate-400 dark:text-white/35 truncate">
                Manage your account details
              </p>
            </div>
          </Transition>
          <Icon v-if="!collapsed" icon="lucide:chevron-right" width="15"
            class="flex-shrink-0 text-slate-300 dark:text-white/20 group-hover:text-[#1976D2] dark:group-hover:text-[#7fb3ec] transition-colors" />
        </a>
      </RouterLink>

      <button @click="logout" :title="collapsed ? 'Logout' : undefined"
        class="group flex items-center gap-3 rounded-2xl border border-slate-200 dark:border-white/10
               bg-base-200 dark:bg-white/[0.03] hover:bg-red-50 dark:hover:bg-red-500/10
               hover:border-red-200 dark:hover:border-red-500/30 transition-all duration-150"
        :class="collapsed ? 'justify-center px-0 py-2.5' : 'px-2.5 py-2.5'">
        <span class="flex items-center justify-center w-9 h-9 rounded-xl flex-shrink-0
                     bg-white dark:bg-white/5 text-slate-400 dark:text-white/40
                     group-hover:bg-red-100 dark:group-hover:bg-red-500/15 group-hover:text-red-600 dark:group-hover:text-red-300 transition-colors">
          <Icon icon="mdi:logout" width="16" />
        </span>
        <Transition name="label-fade">
          <div v-if="!collapsed" class="min-w-0 flex-1 text-left">
            <p class="text-[0.82rem] font-bold leading-tight text-slate-800 dark:text-white/90 group-hover:text-red-600 dark:group-hover:text-red-300">
              Logout
            </p>
            <p class="text-[0.68rem] leading-tight text-slate-400 dark:text-white/35 truncate">
              Sign out of this account
            </p>
          </div>
        </Transition>
        <Icon v-if="!collapsed" icon="lucide:chevron-right" width="15"
          class="flex-shrink-0 text-slate-300 dark:text-white/20 group-hover:text-red-500 dark:group-hover:text-red-300 transition-colors" />
      </button>
    </div>
  </aside>


</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Icon } from '@iconify/vue'
import { getSession, useSession } from '@/composables/useSession'
import { useAlert } from '@/composables/useAlert'
import { useToast } from '@/composables/useToast'
import { fetchSidebarBadges } from '@/services/sidebarBadges'

const props = defineProps({ open: { type: Boolean, default: false } })
const emit = defineEmits(['update:open'])

const isOpen = computed({
  get: () => props.open,
  set: (v) => emit('update:open', v),
})

const route = useRoute()
const router = useRouter()
const alert = useAlert()
const toast = useToast()
const { logout: sessionLogout } = useSession()

const collapsed = ref(false)
const currentUser = ref(null)
const badges = ref({})
let badgeInterval = null

// Settings lives in the footer now (grouped with Logout), so it's no
// longer part of the main nav list.
const navItems = [
  { label: 'Dashboard', description: 'Overview & quick actions', to: '/citizen', icon: 'lucide:layout-dashboard' },
  { label: 'My Requests', description: 'Track your submitted reports', to: '/citizen/requests', icon: 'lucide:clipboard-list' },
  { label: 'Emergency Information', description: 'Emergency details responders may need', to: '/citizen/medical-records', icon: 'lucide:heart-pulse' },
  { label: 'Notifications', description: 'Updates on your requests', to: '/citizen/notifications', icon: 'lucide:bell' },
]

// Exact match for the dashboard root so it doesn't stay highlighted while
// on /citizen/requests etc.; startsWith for everything else.
const isActive = (to) => (to === '/citizen' ? route.path === '/citizen' : route.path.startsWith(to))

const handleNavClick = (e, navigate) => {
  navigate(e)
  isOpen.value = false
}

const initials = computed(() => {
  const name = currentUser.value?.name || ''
  return name.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]?.toUpperCase()).join('') || '?'
})

const fetchCurrentUser = async () => {
  try {
    const session = await getSession()
    currentUser.value = session.loggedIn ? session.user : null
  } catch {
    currentUser.value = null
  }
}

const badgeFor = (path) => Number(badges.value?.[path] || 0)
const formatBadge = (count) => count > 99 ? '99+' : String(count)

const fetchBadgeCounts = async () => {
  try {
    badges.value = await fetchSidebarBadges()
  } catch {
    badges.value = {}
  }
}

const logout = async () => {
  const confirmed = await alert.confirm('Are you sure you want to log out?', 'Log out')
  if (!confirmed) return

  try {
    await sessionLogout()
    toast.success('Logged out successfully')
    router.push('/login')
  } catch (err) {
    console.error(err)
    alert.error('Logout failed')
  }
}

onMounted(() => {
  fetchCurrentUser()
  fetchBadgeCounts()
  badgeInterval = setInterval(fetchBadgeCounts, 15_000)
  window.addEventListener('notifications-updated', fetchBadgeCounts)
  window.addEventListener('sidebar-badges-updated', fetchBadgeCounts)
})

onUnmounted(() => {
  if (badgeInterval) clearInterval(badgeInterval)
  window.removeEventListener('notifications-updated', fetchBadgeCounts)
  window.removeEventListener('sidebar-badges-updated', fetchBadgeCounts)
})

defineExpose({ collapsed })
</script>

<style scoped>
.label-fade-enter-active,
.label-fade-leave-active {
  transition: opacity 0.18s ease, max-width 0.2s ease;
}

.label-fade-enter-from,
.label-fade-leave-to {
  opacity: 0;
  max-width: 0;
}

.label-fade-enter-to {
  max-width: 220px;
}

.overlay-fade-enter-active,
.overlay-fade-leave-active {
  transition: opacity 0.2s ease;
}

.overlay-fade-enter-from,
.overlay-fade-leave-to {
  opacity: 0;
}

nav::-webkit-scrollbar {
  width: 4px;
}

nav::-webkit-scrollbar-track {
  background: transparent;
}

nav::-webkit-scrollbar-thumb {
  background: rgba(100, 116, 139, 0.35);
  border-radius: 10px;
}

nav::-webkit-scrollbar-thumb:hover {
  background: rgba(100, 116, 139, 0.55);
}
</style>