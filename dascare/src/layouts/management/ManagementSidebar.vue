<template>
  <Transition name="overlay-fade">
    <div v-if="isOpen" class="fixed inset-0 z-[55] bg-black/40 backdrop-blur-sm lg:hidden" @click="isOpen = false" />
  </Transition>

  <aside
    class="fixed left-0 top-0 z-[60] flex h-screen flex-col overflow-hidden border-r border-base-300 bg-base-100 transition-[width] duration-300 ease-[cubic-bezier(.4,0,.2,1)] dark:border-white/10 dark:bg-[#071829] lg:sticky lg:z-auto max-lg:!w-[288px] max-lg:shadow-2xl max-lg:transition-transform"
    :class="[
      collapsed ? 'w-[76px]' : 'w-[272px]',
      isOpen ? 'max-lg:translate-x-0' : 'max-lg:-translate-x-full',
    ]"
  >
    <div class="relative flex-shrink-0 border-b border-base-300 bg-base-100 dark:border-white/5 dark:bg-transparent dark:bg-gradient-to-br dark:from-white/[0.04] dark:to-transparent">
      <div class="relative flex items-center justify-between px-4 pb-4 pt-4">
        <div class="flex min-w-0 items-center gap-2.5 overflow-hidden">
          <div class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-xl bg-base-100 shadow-sm ring-1 ring-black/5">
            <img src="../../../img/logo-name-bg.jpg" alt="DASCARE Logo" class="h-full w-full object-cover" />
          </div>
          <Transition name="label-fade">
            <div v-if="!collapsed" class="min-w-0">
              <p class="whitespace-nowrap text-[0.95rem] font-black leading-tight tracking-tight text-slate-900 dark:text-white">DASCARE</p>
              <p class="-mt-0.5 truncate text-[0.68rem] font-medium text-slate-400 dark:text-white/40">{{ workspace.shortTitle }}</p>
            </div>
          </Transition>
        </div>

        <button
          v-if="!collapsed"
          class="hidden h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-base-200 hover:text-slate-600 dark:text-white/40 dark:hover:bg-base-100/10 dark:hover:text-white lg:flex"
          title="Collapse sidebar"
          @click="collapsed = true"
        >
          <Icon icon="lucide:panel-left-close" width="16" />
        </button>
        <button
          class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-base-200 dark:text-white/50 dark:hover:bg-base-100/10 lg:hidden"
          @click="isOpen = false"
        >
          <Icon icon="lucide:x" width="18" />
        </button>
      </div>
    </div>

    <button
      v-if="collapsed"
      class="mx-auto -mt-1 mb-2 hidden h-6 w-9 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-base-200 dark:text-white/40 dark:hover:bg-base-100/10 lg:flex"
      title="Expand sidebar"
      @click="collapsed = false"
    >
      <Icon icon="lucide:panel-left-open" width="15" />
    </button>

    <div
      class="relative mt-3 mb-3 flex flex-shrink-0 items-center gap-3 overflow-hidden rounded-2xl border border-base-300 bg-base-200 dark:border-white/10 dark:bg-white/[0.05]"
      :class="collapsed ? 'self-center w-fit p-2 justify-center' : 'mx-3 p-3'"
    >
      <div class="relative flex-shrink-0">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-red-600 to-red-700 text-[0.8rem] font-bold text-white shadow-sm">
          {{ initials }}
        </div>
        <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-500 dark:border-[#071829]"></span>
      </div>
      <Transition name="label-fade">
        <div v-if="!collapsed" class="min-w-0 flex-1">
          <p class="truncate text-[0.83rem] font-bold leading-tight text-slate-900 dark:text-white">{{ user?.name || 'Loading account…' }}</p>
          <p class="mt-0.5 truncate text-[0.7rem] text-slate-400 dark:text-white/40">{{ accountSubtitle }}</p>
        </div>
      </Transition>
    </div>

    <div v-if="primaryAction" class="flex-shrink-0 px-3 pb-3">
      <RouterLink
        :to="primaryAction.to"
        class="group flex items-center gap-3 rounded-2xl bg-red-600 text-white no-underline shadow-lg shadow-red-600/25 transition-colors hover:bg-red-700 dark:shadow-red-950/40"
        :class="collapsed ? 'justify-center px-0 py-3' : 'px-3.5 py-3'"
        @click="isOpen = false"
      >
        <span class="relative flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-base-100/15">
          <Icon :icon="primaryAction.icon" width="18" />
          <span
            v-if="badgeFor(primaryAction.to) > 0"
            class="absolute -right-1.5 -top-1.5 min-w-[18px] rounded-full bg-white px-1 text-center text-[0.6rem] font-black leading-[18px] text-red-600 shadow-sm ring-2 ring-red-600"
          >{{ formatBadge(badgeFor(primaryAction.to)) }}</span>
        </span>
        <Transition name="label-fade">
          <div v-if="!collapsed" class="min-w-0 text-left">
            <p class="whitespace-nowrap text-[0.85rem] font-bold leading-tight">{{ primaryAction.label }}</p>
            <p class="whitespace-nowrap text-[0.7rem] leading-tight text-white/70">{{ primaryAction.description }}</p>
          </div>
        </Transition>
      </RouterLink>
    </div>

    <nav class="flex flex-1 flex-col gap-4 overflow-y-auto px-3 pb-3">
      <section v-for="group in visibleGroups" :key="group.label">
        <Transition name="label-fade">
          <p v-if="!collapsed" class="mb-2 px-1 text-[0.65rem] font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-white/30">{{ group.label }}</p>
        </Transition>

        <div class="space-y-1.5">
          <RouterLink v-for="item in group.items" :key="item.to" :to="item.to" custom v-slot="{ href, navigate }">
            <a
              :href="href"
              :title="collapsed ? item.label : undefined"
              class="group relative flex items-center gap-3 overflow-hidden rounded-2xl border px-2.5 py-2.5 no-underline transition-all duration-150"
              :class="[
                collapsed ? 'justify-center' : '',
                isActive(item.to)
                  ? 'border-red-100 bg-red-50 dark:border-red-500/20 dark:bg-red-500/10'
                  : 'border-transparent hover:border-base-300 hover:bg-base-200 dark:hover:border-white/10 dark:hover:bg-base-100/5',
              ]"
              @click="handleNav($event, navigate)"
            >
              <span v-if="isActive(item.to)" class="absolute left-0 top-1/2 h-6 w-[3px] -translate-y-1/2 rounded-full bg-red-600"></span>
              <span
                class="relative flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl transition-all"
                :class="isActive(item.to)
                  ? 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-300'
                  : 'bg-base-200 text-slate-400 group-hover:text-slate-600 dark:bg-white/5 dark:text-white/40 dark:group-hover:text-white/70'"
              >
                <Icon :icon="item.icon" width="18" />
                <span
                  v-if="collapsed && badgeFor(item.to) > 0"
                  class="absolute -right-1.5 -top-1.5 min-w-[18px] rounded-full bg-red-600 px-1 text-center text-[0.58rem] font-black leading-[18px] text-white shadow-sm ring-2 ring-base-100 dark:ring-[#071829]"
                >{{ formatBadge(badgeFor(item.to)) }}</span>
              </span>
              <Transition name="label-fade">
                <div v-if="!collapsed" class="min-w-0 flex flex-1 items-center justify-between gap-2">
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-[0.85rem] font-bold leading-tight" :class="isActive(item.to) ? 'text-red-700 dark:text-red-300' : 'text-slate-800 dark:text-white/90'">{{ item.label }}</p>
                    <p class="mt-0.5 truncate text-[0.7rem] leading-tight" :class="isActive(item.to) ? 'text-red-600/70 dark:text-red-300/60' : 'text-slate-400 dark:text-white/35'">{{ navDescription(item) }}</p>
                  </div>
                  <span
                    v-if="badgeFor(item.to) > 0"
                    class="flex-shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-[0.62rem] font-black text-red-600 dark:bg-red-500/20 dark:text-red-300"
                  >{{ formatBadge(badgeFor(item.to)) }}</span>
                </div>
              </Transition>
            </a>
          </RouterLink>
        </div>
      </section>
    </nav>

    <div class="flex flex-shrink-0 flex-col gap-2 border-t border-base-300 px-3 pb-3 pt-2 dark:border-white/10">
      <div v-if="!collapsed" class="rounded-2xl border border-base-300 bg-base-200/80 p-3 dark:border-white/10 dark:bg-white/[0.03]">
        <p class="text-[0.64rem] font-black uppercase tracking-[0.12em] text-slate-400 dark:text-white/30">Current scope</p>
        <p class="mt-1 truncate text-[0.72rem] font-semibold text-slate-600 dark:text-white/60">{{ scopeLabel }}</p>
      </div>

      <RouterLink
        v-if="showSettings"
        :to="`${workspace.basePath}/settings`"
        class="group flex items-center gap-3 rounded-2xl border border-transparent px-2.5 py-2.5 text-slate-600 no-underline transition-colors hover:border-base-300 hover:bg-base-200 dark:text-white/60 dark:hover:border-white/10 dark:hover:bg-base-100/5"
        :class="collapsed ? 'justify-center' : ''"
        @click="isOpen = false"
      >
        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-base-200 text-slate-400 transition-colors group-hover:text-slate-700 dark:bg-white/5 dark:text-white/35 dark:group-hover:text-white/70">
          <Icon icon="lucide:settings" width="17" />
        </span>
        <Transition name="label-fade"><span v-if="!collapsed" class="text-[0.82rem] font-bold">Settings</span></Transition>
      </RouterLink>

      <button
        class="group flex items-center gap-3 rounded-2xl border border-transparent px-2.5 py-2.5 text-slate-500 transition-colors hover:border-red-100 hover:bg-red-50 hover:text-red-600 dark:text-white/50 dark:hover:border-red-500/20 dark:hover:bg-red-500/10 dark:hover:text-red-300"
        :class="collapsed ? 'justify-center' : ''"
        @click="logout"
      >
        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-base-200 text-slate-400 transition-colors group-hover:bg-red-100 group-hover:text-red-600 dark:bg-white/5 dark:text-white/35 dark:group-hover:bg-red-500/15 dark:group-hover:text-red-300">
          <Icon icon="mdi:logout" width="17" />
        </span>
        <Transition name="label-fade"><span v-if="!collapsed" class="text-[0.82rem] font-bold">Logout</span></Transition>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Icon } from '@iconify/vue'
import { useSession } from '@/composables/useSession'
import { useAlert } from '@/composables/useAlert'
import { fetchSidebarBadges } from '@/services/sidebarBadges'
import { useLiveUpdates } from '@/composables/useLiveUpdates'
import { ownRequestChannels, realtimeChannels } from '@/services/realtime'

const props = defineProps({
  workspace: { type: Object, required: true },
  open: { type: Boolean, default: false },
})
const emit = defineEmits(['update:open'])

const route = useRoute()
const router = useRouter()
const alert = useAlert()
const { user, organization, platform, logout: sessionLogout, hasAnyPermission, hasAllPermissions } = useSession()
const collapsed = ref(false)
const badges = ref({})


const badgeFor = (path) => Number(badges.value?.[path] || 0)
const formatBadge = (count) => count > 99 ? '99+' : String(count)

const refreshBadges = async () => {
  try {
    badges.value = await fetchSidebarBadges()
  } catch {
    // Sidebar counters are convenience UI; navigation should remain usable
    // if a count request temporarily fails.
    badges.value = {}
  }
}

const isOpen = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value),
})

const canSee = (item) => {
  if (!item.permissions?.length) return true
  return item.permissionMode === 'all'
    ? hasAllPermissions(...item.permissions)
    : hasAnyPermission(...item.permissions)
}

const visibleGroups = computed(() => {
  if (props.workspace.key === 'organization' && organization.value?.status !== 'active') {
    return [{
      label: 'Onboarding',
      items: [{ label: 'Application Status', description: 'Track the LGU review decision', to: '/organization/application-status', icon: 'lucide:clipboard-check' }],
    }]
  }

  return props.workspace.navGroups
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => !item.to.endsWith('/settings') && canSee(item)),
    }))
    .filter((group) => group.items.length)
})

const showSettings = computed(() => !(props.workspace.key === 'organization' && organization.value?.status !== 'active'))

const primaryAction = computed(() => {
  if (props.workspace.key === 'organization' && organization.value?.status !== 'active') return null
  return props.workspace.primaryAction || null
})

const initials = computed(() => (user.value?.name || '?')
  .split(' ')
  .filter(Boolean)
  .slice(0, 2)
  .map((part) => part[0]?.toUpperCase())
  .join(''))

const accountSubtitle = computed(() => {
  if (props.workspace.key === 'organization' && organization.value?.status !== 'active') {
    const appStatus = organization.value?.applicationStatus
    if (appStatus === 'revision_requested') return 'Application needs revision'
    if (appStatus === 'rejected') return 'Application rejected'
    return 'Application under review'
  }
  if (organization.value?.roleNames?.length) return organization.value.roleNames.join(' • ')
  return user.value?.roleLabel || props.workspace.shortTitle
})

const scopeLabel = computed(() => organization.value?.name || platform.value?.roleLabel || 'DASCARE Platform')
const isActive = (to) => (to === props.workspace.basePath ? route.path === to : route.path.startsWith(to))

const descriptions = {
  '/platform': 'City-wide operational overview',
  '/platform/applications': 'Review rescue organization applicants',
  '/platform/organizations': 'Manage participating organizations',
  '/platform/compliance': 'Review documents and credentials',
  '/platform/citizen-verifications': 'Approve citizen identity submissions',
  '/platform/citizens': 'Review registered citizen accounts',
  '/platform/incidents': 'Monitor city-wide rescue demand',
  '/platform/fleet-availability': 'See ambulance readiness across the city',
  '/platform/escalations': 'Handle cases needing LGU intervention',
  '/platform/reviews': 'Review cancellations and complaints',
  '/platform/false-alarms': 'Review suspected misuse safely',
  '/platform/heatmaps': 'Map demand and coverage patterns',
  '/platform/analytics': 'Review response performance',
  '/platform/dss-settings': 'Configure operational decision settings',
  '/platform/audit': 'Trace sensitive platform actions',
  '/platform/policies': 'Maintain platform operating policy',
  '/system': 'Infrastructure and service overview',
  '/organization': 'Organization operational overview',
}
const navDescription = (item) => item.description || descriptions[item.to] || 'Open this workspace'

const handleNav = (event, navigate) => {
  navigate(event)
  isOpen.value = false
}

const logout = async () => {
  const confirmed = await alert.confirm('Are you sure you want to log out?', 'Log out')
  if (!confirmed) return
  try {
    await sessionLogout()
    alert.success('Logged out successfully')
    router.push('/login')
  } catch {
    alert.error('Logout failed')
  }
}

// Live updates: offers/missions (org), city incidents + review queues
// (platform) and notifications re-count the badges (GPS pings and fleet edits
// ignored). The 60 s poll while live also keeps the server's lazy overdue
// flags moving; 15 s without live updates.
useLiveUpdates(() => refreshBadges(), {
  channels: () => [realtimeChannels.value?.user, realtimeChannels.value?.org, realtimeChannels.value?.platform],
  onEvent: (msg) => (['ambulance.location', 'fleet.updated'].includes(msg.name) ? false : undefined),
  debounceMs: 800, pollMs: 15000, livePollMs: 60000,
})

onMounted(() => {
  refreshBadges()
  window.addEventListener('sidebar-badges-updated', refreshBadges)
})

onUnmounted(() => {
  window.removeEventListener('sidebar-badges-updated', refreshBadges)
})
</script>

<style scoped>
.label-fade-enter-active,
.label-fade-leave-active { transition: opacity 0.18s ease, max-width 0.2s ease; }
.label-fade-enter-from,
.label-fade-leave-to { opacity: 0; max-width: 0; }
.label-fade-enter-to { max-width: 230px; }
.overlay-fade-enter-active,
.overlay-fade-leave-active { transition: opacity 0.2s ease; }
.overlay-fade-enter-from,
.overlay-fade-leave-to { opacity: 0; }
nav::-webkit-scrollbar { width: 4px; }
nav::-webkit-scrollbar-track { background: transparent; }
nav::-webkit-scrollbar-thumb { background: rgba(100, 116, 139, 0.32); border-radius: 999px; }
</style>
