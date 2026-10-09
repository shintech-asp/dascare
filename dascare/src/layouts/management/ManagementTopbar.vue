<template>
  <header class="dascare-management-topbar sticky top-0 z-40 flex h-16 items-center gap-3 border-b border-base-300/80 bg-base-100/90 px-4 backdrop-blur-xl dark:border-white/10 dark:bg-[#071829]/90 sm:px-5">
    <button
      class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl text-slate-500 transition-colors hover:bg-base-200 dark:text-white/50 dark:hover:bg-base-100/10 lg:hidden"
      @click="$emit('toggle-sidebar')"
    >
      <Icon :icon="sidebarOpen ? 'lucide:x' : 'lucide:menu'" width="20" />
    </button>

    <div class="hidden min-w-0 flex-1 items-center gap-2.5 sm:flex">
      <span class="inline-flex items-center gap-1.5 rounded-full border border-base-300/80 bg-base-200 py-1 pl-2.5 pr-3 dark:border-white/10 dark:bg-white/[0.06]">
        <span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>
        <span class="font-mono text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500 dark:text-white/50">{{ workspace.shortTitle }}</span>
      </span>
      <Icon icon="lucide:chevron-right" width="14" class="flex-shrink-0 text-slate-300 dark:text-white/20" />
      <span class="truncate text-[0.95rem] font-black tracking-tight text-slate-950 dark:text-white">{{ currentPage }}</span>
    </div>
    <span class="min-w-0 flex-1 truncate text-[0.95rem] font-black tracking-tight text-slate-950 dark:text-white sm:hidden">{{ currentPage }}</span>

    <div class="relative flex flex-shrink-0 items-center gap-2">
      <button
        class="flex h-10 w-10 items-center justify-center rounded-xl border border-transparent text-slate-500 transition-colors hover:border-base-300 hover:bg-base-200 dark:text-white/50 dark:hover:border-white/10 dark:hover:bg-base-100/10"
        :title="theme === 'dark' ? 'Use light mode' : 'Use dark mode'"
        @click="toggleTheme"
      >
        <Icon :icon="theme === 'dark' ? 'lucide:sun' : 'lucide:moon'" width="18" />
      </button>

      <div class="relative">
        <button
          class="relative flex h-10 w-10 items-center justify-center rounded-xl border border-transparent text-slate-500 transition-colors hover:border-base-300 hover:bg-base-200 dark:text-white/50 dark:hover:border-white/10 dark:hover:bg-base-100/10"
          title="Notifications"
          @click.stop="notificationsOpen = !notificationsOpen; accountOpen = false"
        >
          <Icon icon="lucide:bell" width="18" />
          <span
            v-if="unreadCount > 0"
            class="absolute right-1.5 top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full border-2 border-white bg-red-500 px-0.5 text-[0.6rem] font-bold text-white dark:border-[#071829]"
          >{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
        </button>

        <Transition name="dropdown-fade">
          <div
            v-if="notificationsOpen"
            class="absolute right-0 top-full z-[100] mt-2 w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-base-300/80 bg-base-100 shadow-2xl shadow-slate-300/40 dark:border-white/10 dark:bg-[#071829] dark:shadow-black/40"
            @click.stop
          >
            <div class="flex items-center justify-between border-b border-base-300/80 px-4 py-3 dark:border-white/10">
              <div>
                <p class="text-sm font-black text-slate-900 dark:text-white">Notifications</p>
                <p class="mt-0.5 text-[0.68rem] text-slate-400 dark:text-white/35">{{ unreadCount ? `${unreadCount} unread update${unreadCount === 1 ? '' : 's'}` : 'You are up to date' }}</p>
              </div>
              <button
                v-if="unreadCount"
                class="inline-flex items-center gap-1 text-[0.7rem] font-bold text-red-600 hover:underline dark:text-red-300"
                :disabled="notificationSaving"
                @click="markAllRead"
              >
                <Icon icon="lucide:check-check" width="13" /> Mark all read
              </button>
            </div>

            <div v-if="notificationsLoading" class="grid place-items-center py-10">
              <span class="h-6 w-6 animate-spin rounded-full border-2 border-red-600 border-t-transparent"></span>
            </div>

            <div v-else-if="notificationError" class="px-5 py-8 text-center">
              <Icon icon="lucide:triangle-alert" width="21" class="mx-auto text-red-400" />
              <p class="mt-2 text-xs font-semibold text-slate-500 dark:text-white/45">{{ notificationError }}</p>
              <button class="mt-2 text-xs font-bold text-red-600 hover:underline dark:text-red-300" @click="loadNotifications">Try again</button>
            </div>

            <div v-else class="max-h-96 overflow-y-auto">
              <div
                v-for="n in notifications.slice(0, 8)"
                :key="n.id"
                role="button"
                tabindex="0"
                class="group relative flex w-full cursor-pointer items-start gap-3 border-b border-base-300 px-4 py-3.5 text-left transition-colors last:border-b-0 hover:bg-base-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-400 dark:border-white/5 dark:hover:bg-base-100/5"
                :class="!n.read ? 'bg-red-50/40 dark:bg-red-500/5' : ''"
                @click="handleNotificationClick(n)"
                @keydown.enter.prevent="handleNotificationClick(n)"
                @keydown.space.prevent="handleNotificationClick(n)"
              >
                <span class="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl" :class="!n.read ? 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-300' : 'bg-base-200 text-slate-400 dark:bg-white/5 dark:text-white/40'">
                  <Icon :icon="notificationIcon(n)" width="16" />
                </span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-[0.8rem] font-bold text-slate-900 dark:text-white">{{ n.title }}</span>
                  <span class="mt-0.5 block line-clamp-2 text-[0.7rem] leading-5 text-slate-500 dark:text-white/45">{{ n.message }}</span>
                  <span class="mt-1 block text-[0.64rem] text-slate-400 dark:text-white/30">{{ relativeTime(n.created_at) }}</span>
                </span>
                <button
                  v-if="!n.read"
                  type="button"
                  title="Mark as read"
                  class="mt-0.5 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 opacity-0 transition group-hover:opacity-100 hover:bg-base-100 hover:text-red-600 dark:hover:bg-base-100/10 dark:hover:text-red-300"
                  @click.stop="markOneRead(n)"
                >
                  <Icon icon="lucide:check" width="14" />
                </button>
                <span v-if="!n.read" class="absolute right-4 top-4 h-2 w-2 rounded-full bg-red-500 group-hover:opacity-0"></span>
              </div>

              <div v-if="!notifications.length" class="px-4 py-10 text-center">
                <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-2xl bg-base-200 text-slate-300 dark:bg-white/5 dark:text-white/20">
                  <Icon icon="lucide:bell-off" width="18" />
                </span>
                <p class="mt-2 text-xs font-semibold text-slate-400 dark:text-white/35">No notifications yet</p>
              </div>
            </div>

            <div class="border-t border-base-300/80 px-4 py-2.5 text-center text-[0.65rem] font-semibold text-slate-400 dark:border-white/10 dark:text-white/30">
              Recent account and review activity appears here automatically.
            </div>
          </div>
        </Transition>
      </div>

      <div class="relative">
        <button
          class="flex items-center gap-2 rounded-xl border border-transparent py-1 pl-1 pr-2 transition-all hover:border-base-300 hover:bg-base-200 dark:hover:border-white/10 dark:hover:bg-base-100/10"
          title="Account"
          @click.stop="accountOpen = !accountOpen; notificationsOpen = false"
        >
          <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-red-600 to-red-800 text-xs font-black text-white shadow-sm">{{ initials }}</div>
          <div class="hidden max-w-36 text-left md:block">
            <p class="truncate text-[0.75rem] font-bold text-slate-900 dark:text-white">{{ user?.name || 'Account' }}</p>
            <p class="truncate text-[0.64rem] text-slate-400 dark:text-white/35">{{ user?.roleLabel || workspace.shortTitle }}</p>
          </div>
          <Icon icon="lucide:chevron-down" width="14" class="hidden text-slate-400 md:block" />
        </button>

        <Transition name="dropdown-fade">
          <div v-if="accountOpen" class="absolute right-0 top-full z-[100] mt-2 w-64 overflow-hidden rounded-2xl border border-base-300/80 bg-base-100 p-1.5 shadow-2xl shadow-slate-300/40 dark:border-white/10 dark:bg-[#071829] dark:shadow-black/40" @click.stop>
            <div class="mb-1 border-b border-base-300 px-2.5 py-2.5 dark:border-white/5">
              <p class="truncate text-[0.82rem] font-bold text-slate-900 dark:text-white">{{ user?.name }}</p>
              <p class="mt-0.5 truncate text-[0.68rem] text-slate-400 dark:text-white/35">{{ user?.email }}</p>
            </div>
            <RouterLink
              :to="`${workspace.basePath}/settings`"
              class="group flex items-center gap-3 rounded-xl px-2.5 py-2.5 text-slate-700 no-underline transition-colors hover:bg-base-200 dark:text-white/80 dark:hover:bg-base-100/5"
              @click="accountOpen = false"
            >
              <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-base-200 text-slate-400 dark:bg-white/5 dark:text-white/40"><Icon icon="lucide:settings" width="15" /></span>
              <span class="min-w-0 text-left"><span class="block text-[0.82rem] font-bold leading-tight">Settings</span><span class="block text-[0.68rem] leading-tight text-slate-400 dark:text-white/35">Manage your account</span></span>
            </RouterLink>
            <button class="group flex w-full items-center gap-3 rounded-xl px-2.5 py-2.5 transition-colors hover:bg-red-50 dark:hover:bg-red-500/10" @click="logout">
              <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-base-200 text-slate-400 transition-colors group-hover:bg-red-100 group-hover:text-red-600 dark:bg-white/5 dark:text-white/40 dark:group-hover:bg-red-500/15 dark:group-hover:text-red-300"><Icon icon="mdi:logout" width="15" /></span>
              <span class="min-w-0 text-left"><span class="block text-[0.82rem] font-bold leading-tight text-red-600 dark:text-red-300">Logout</span><span class="block text-[0.68rem] leading-tight text-red-400/70 dark:text-red-300/50">Sign out of this account</span></span>
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Icon } from '@iconify/vue'
import { useSession } from '@/composables/useSession'
import { useTheme } from '@/composables/useTheme'
import { useAlert } from '@/composables/useAlert'
import { fetchNotifications, markAllNotificationsRead, markNotificationRead } from '@/services/notifications'

const props = defineProps({
  workspace: { type: Object, required: true },
  sidebarOpen: { type: Boolean, default: false },
})
defineEmits(['toggle-sidebar'])

const route = useRoute()
const router = useRouter()
const alert = useAlert()
const { user, logout: sessionLogout } = useSession()
const { theme, toggleTheme } = useTheme()
const accountOpen = ref(false)
const notificationsOpen = ref(false)
const notifications = ref([])
const unreadCount = ref(0)
const notificationsLoading = ref(false)
const notificationSaving = ref(false)
const notificationError = ref('')
let notificationInterval = null

const currentPage = computed(() => route.meta?.title || props.workspace.title)
const initials = computed(() => (user.value?.name || '?').split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]?.toUpperCase()).join(''))

async function loadNotifications() {
  notificationsLoading.value = notifications.value.length === 0
  notificationError.value = ''
  try {
    notifications.value = await fetchNotifications()
    unreadCount.value = notifications.value.filter((n) => !n.read).length
  } catch {
    notificationError.value = 'Could not load notifications.'
  } finally {
    notificationsLoading.value = false
  }
}

async function markOneRead(notification) {
  if (notification.read) return
  notification.read = true
  notification.is_read = true
  unreadCount.value = notifications.value.filter((n) => !n.read).length
  try {
    await markNotificationRead(notification.id)
    window.dispatchEvent(new Event('notifications-updated'))
  } catch (error) {
    console.error(error)
    notification.read = false
    notification.is_read = false
    unreadCount.value = notifications.value.filter((n) => !n.read).length
  }
}

async function markAllRead() {
  if (!unreadCount.value || notificationSaving.value) return
  const unread = notifications.value.filter((n) => !n.read)
  unread.forEach((n) => { n.read = true; n.is_read = true })
  unreadCount.value = 0
  notificationSaving.value = true
  try {
    await markAllNotificationsRead()
    window.dispatchEvent(new Event('notifications-updated'))
  } catch (error) {
    console.error(error)
    unread.forEach((n) => { n.read = false; n.is_read = false })
    unreadCount.value = notifications.value.filter((n) => !n.read).length
  } finally {
    notificationSaving.value = false
  }
}

function notificationTarget(notification) {
  if (props.workspace.key === 'platform') {
    if (notification.related_type === 'organization_application' && notification.related_id) return `/platform/applications?organization=${notification.related_id}`
    if (notification.related_type === 'kyc_verification' && notification.related_id) return `/platform/citizen-verifications?user=${notification.related_id}`
  }
  if (props.workspace.key === 'organization') {
    if (notification.related_type === 'organization_application') return '/organization/application-status'
    if (notification.related_type === 'incident_offer' && notification.related_id) return `/organization/offers?offer=${notification.related_id}`
    if (notification.related_type === 'dispatch_assignment' && notification.related_id) return '/organization/missions'
    if (notification.related_type === 'emergency_request' && notification.related_id) return '/organization/incidents'
  }
  return null
}

async function handleNotificationClick(notification) {
  await markOneRead(notification)
  notificationsOpen.value = false
  const target = notificationTarget(notification)
  if (target) router.push(target)
}

function notificationIcon(notification) {
  if (/organization/i.test(notification.related_type || notification.notification_type || '')) return 'lucide:building-2'
  if (/kyc|verification/i.test(notification.related_type || notification.notification_type || '')) return 'lucide:shield-check'
  if (/security|login|password/i.test(notification.notification_type || '')) return 'lucide:lock'
  if (/dispatch|mission|emergency|request/i.test(notification.related_type || notification.notification_type || '')) return 'lucide:siren'
  return 'lucide:bell'
}

function relativeTime(value) {
  if (!value) return '—'
  const then = new Date(String(value).replace(' ', 'T'))
  const seconds = Math.max(0, Math.floor((Date.now() - then.getTime()) / 1000))
  if (seconds < 60) return 'Just now'
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`
  if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`
  return then.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}

const closeMenus = () => {
  accountOpen.value = false
  notificationsOpen.value = false
}

const logout = async () => {
  accountOpen.value = false
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

onMounted(() => {
  loadNotifications()
  notificationInterval = setInterval(loadNotifications, 15_000)
  window.addEventListener('click', closeMenus)
  window.addEventListener('notifications-updated', loadNotifications)
})

onUnmounted(() => {
  clearInterval(notificationInterval)
  window.removeEventListener('click', closeMenus)
  window.removeEventListener('notifications-updated', loadNotifications)
})
</script>

<style scoped>
.dropdown-fade-enter-active,
.dropdown-fade-leave-active { transition: all 0.18s ease; }
.dropdown-fade-enter-from,
.dropdown-fade-leave-to { opacity: 0; transform: translateY(-6px); }
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.dascare-management-topbar :focus-visible { outline-color: rgba(220, 38, 38, 0.5); }
</style>
