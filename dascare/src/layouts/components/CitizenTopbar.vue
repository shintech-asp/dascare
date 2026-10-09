<template>
  <header
    class="dascare-topbar sticky top-0 z-40 h-16 flex items-center gap-3 px-4 sm:px-5
           bg-base-100/90 dark:bg-[#071829]/90 backdrop-blur-xl border-b border-slate-200/80 dark:border-white/10"
  >
    <!-- Mobile hamburger — opens the sidebar in the parent layout -->
    <button
      class="lg:hidden w-9 h-9 flex items-center justify-center rounded-xl
             text-slate-500 dark:text-white/50 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors flex-shrink-0"
      @click="$emit('toggle-sidebar')"
    >
      <Icon :icon="sidebarOpen ? 'lucide:x' : 'lucide:menu'" width="20" />
    </button>

    <!-- Breadcrumb — pill treatment so the topbar reads as its own
         distinct surface rather than a plain bar -->
    <div class="hidden sm:flex items-center gap-2.5 flex-1 min-w-0">
      <span class="inline-flex items-center gap-1.5 pl-2.5 pr-3 py-1 rounded-full
                   bg-slate-100 dark:bg-white/[0.06] border border-slate-200/80 dark:border-white/10">
        <span class="w-1.5 h-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>
        <span class="font-mono text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500 dark:text-white/50">
          Citizen
        </span>
      </span>
      <Icon icon="lucide:chevron-right" width="14" class="text-slate-300 dark:text-white/20 flex-shrink-0" />
      <span class="text-[0.95rem] font-black tracking-tight text-slate-950 dark:text-white truncate">{{ currentPageLabel }}</span>
    </div>
    <span class="lg:hidden flex-1 text-[0.95rem] font-black tracking-tight text-slate-950 dark:text-white truncate">
      {{ currentPageLabel }}
    </span>

    <!-- Right actions -->
    <div class="flex items-center gap-2 flex-shrink-0 relative">
      <!-- Request assistance — quick access even when scrolled deep into a page -->
      <RouterLink
        to="/citizen/request"
        class="hidden sm:flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-red-600 hover:bg-red-700
               text-white text-[0.78rem] font-bold shadow-lg shadow-red-600/25 transition-colors whitespace-nowrap no-underline dark:shadow-red-950/40"
      >
        <Icon icon="lucide:siren" width="15" />
        Request Assistance
      </RouterLink>

      <!-- Notifications -->
      <div class="relative">
        <button
          @click.stop="notificationsOpen = !notificationsOpen; accountOpen = false"
          class="relative w-10 h-10 flex items-center justify-center rounded-xl border border-transparent
                 text-slate-500 dark:text-white/50 hover:bg-slate-100 dark:hover:bg-white/10 hover:border-slate-200 dark:hover:border-white/10 transition-colors"
          title="Notifications"
        >
          <Icon icon="lucide:bell" width="18" />
          <span
            v-if="unreadCount > 0"
            class="absolute top-1.5 right-1.5 min-w-4 h-4 bg-red-500 rounded-full text-[0.6rem] font-bold text-white
                   flex items-center justify-center px-0.5 border-2 border-white dark:border-[#071829]"
          >
            {{ unreadCount > 9 ? '9+' : unreadCount }}
          </span>
        </button>

        <Transition name="dropdown-fade">
          <div
            v-if="notificationsOpen"
            class="absolute right-0 top-full mt-2 w-80 max-w-[90vw] bg-base-100 dark:bg-[#071829]
                   border border-slate-200/80 dark:border-white/10 rounded-2xl shadow-2xl shadow-slate-300/40 overflow-hidden z-[100] dark:shadow-black/40"
          >
            <div class="px-4 py-3 border-b border-slate-200/80 dark:border-white/10 flex items-center justify-between">
              <h3 class="text-sm font-black tracking-tight text-slate-950 dark:text-white">Notifications</h3>
              <button
                v-if="unreadCount > 0"
                @click.stop="markAllRead"
                class="text-[11px] font-bold text-red-600 dark:text-red-300 hover:underline"
              >
                Mark all read
              </button>
              <span v-else class="text-xs text-slate-400 dark:text-white/35">Up to date</span>
            </div>

            <div class="max-h-96 overflow-y-auto">
              <div
                v-for="n in notifications"
                :key="n.id"
                @click="handleNotificationClick(n)"
                class="group w-full text-left px-4 py-3 border-b border-slate-100 dark:border-white/5 last:border-b-0
                       hover:bg-slate-50 dark:hover:bg-white/5 transition-colors relative cursor-pointer flex items-start gap-2"
                :class="!n.read ? 'bg-red-50/40 dark:bg-red-500/5' : ''"
              >
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-semibold text-slate-900 dark:text-white truncate pr-4">{{ n.title }}</p>
                  <p class="text-xs text-slate-500 dark:text-white/45 mt-1 line-clamp-2 pr-4">{{ n.message }}</p>
                </div>
                <button
                  v-if="!n.read"
                  @click.stop="markOneRead(n)"
                  title="Mark as read"
                  class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity p-1 rounded-full
                         text-slate-400 hover:text-red-600 dark:hover:text-red-300 hover:bg-white dark:hover:bg-white/10"
                >
                  <Icon icon="lucide:check" width="14" />
                </button>
                <span
                  v-if="!n.read"
                  class="absolute top-3.5 right-3.5 w-2 h-2 rounded-full bg-red-500 group-hover:opacity-0 transition-opacity pointer-events-none"
                ></span>
              </div>

              <div v-if="!notifications.length" class="px-4 py-8 text-center text-sm text-slate-400 dark:text-white/35">
                No notifications
              </div>
            </div>

            <button
              @click="goToAllNotifications"
              class="w-full text-center px-4 py-2.5 font-mono text-[11px] font-semibold uppercase tracking-[0.2em]
                     text-red-600 dark:text-red-300 hover:bg-slate-50 dark:hover:bg-white/5 border-t border-slate-200/80 dark:border-white/10 transition-colors"
            >
              See all notifications
            </button>
          </div>
        </Transition>
      </div>

      <!-- Avatar / Account -->
      <div class="relative">
        <button
          class="flex items-center gap-2 pl-1 pr-2 py-1 rounded-xl border border-transparent
                 hover:bg-slate-100 dark:hover:bg-white/10 hover:border-slate-200 dark:hover:border-white/10 transition-all"
          title="Account"
          @click.stop="accountOpen = !accountOpen; notificationsOpen = false"
        >
          <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-[#1976D2] to-[#0F2A43] flex items-center justify-center shadow-sm">
            <Icon icon="lucide:user" width="15" class="text-white" />
          </div>
          <Icon icon="lucide:chevron-down" width="14" class="hidden sm:block text-slate-400 dark:text-white/35" />
        </button>

        <Transition name="dropdown-fade">
          <div
            v-if="accountOpen"
            class="absolute right-0 top-full mt-2 w-64 bg-base-100 dark:bg-[#071829]
                   border border-slate-200/80 dark:border-white/10 rounded-2xl shadow-2xl shadow-slate-300/40 overflow-hidden z-[100] p-1.5 dark:shadow-black/40"
          >
            <RouterLink
              to="/citizen/settings"
              class="group flex items-center gap-3 px-2.5 py-2.5 rounded-xl text-slate-700 dark:text-white/80
                     hover:bg-blue-50 dark:hover:bg-[#1976D2]/10 transition-colors no-underline"
              @click="accountOpen = false"
            >
              <span class="flex items-center justify-center w-8 h-8 rounded-lg flex-shrink-0
                           bg-slate-100 dark:bg-white/5 text-slate-400 dark:text-white/40
                           group-hover:bg-[#1976D2]/10 group-hover:text-[#1976D2] dark:group-hover:text-[#7fb3ec] transition-colors">
                <Icon icon="lucide:settings" width="15" />
              </span>
              <span class="min-w-0 text-left">
                <span class="block text-[0.82rem] font-bold leading-tight group-hover:text-[#1976D2] dark:group-hover:text-[#7fb3ec]">Settings</span>
                <span class="block text-[0.68rem] leading-tight text-slate-400 dark:text-white/35">Manage your account</span>
              </span>
            </RouterLink>
            <button
              @click="logout"
              class="group w-full flex items-center gap-3 px-2.5 py-2.5 rounded-xl
                     hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
            >
              <span class="flex items-center justify-center w-8 h-8 rounded-lg flex-shrink-0
                           bg-slate-100 dark:bg-white/5 text-slate-400 dark:text-white/40
                           group-hover:bg-red-100 dark:group-hover:bg-red-500/15 group-hover:text-red-600 dark:group-hover:text-red-300 transition-colors">
                <Icon icon="mdi:logout" width="15" />
              </span>
              <span class="min-w-0 text-left">
                <span class="block text-[0.82rem] font-bold leading-tight text-red-600 dark:text-red-300">Logout</span>
                <span class="block text-[0.68rem] leading-tight text-red-400/70 dark:text-red-300/50">Sign out of this account</span>
              </span>
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Icon } from '@iconify/vue'
import { useAlert } from '@/composables/useAlert'
import { useSession } from '@/composables/useSession'
import { fetchNotifications, markAllNotificationsRead, markNotificationRead } from '@/services/notifications'

defineProps({ sidebarOpen: { type: Boolean, default: false } })
defineEmits(['toggle-sidebar'])

const alert = useAlert()
const { logout: sessionLogout } = useSession()
const route = useRoute()
const router = useRouter()

const notificationsOpen = ref(false)
const accountOpen = ref(false)
const notifications = ref([])
const unreadCount = ref(0)
let notificationInterval = null

const currentPageLabel = computed(() => route.meta?.title || 'Dashboard')

// NOTE: same notifications/get.php + mark_read.php + mark_all_read.php
// endpoints referenced in ArtisanTopbar.vue — the `notifications` table
// (user_id, notification_type, title, message, read_at) is generic
// enough to serve citizens through the same endpoints, no schema change
// needed. Swap the base path if DASCARE scopes them separately later.
const loadNotifications = async () => {
  try {
    notifications.value = await fetchNotifications()
    unreadCount.value = notifications.value.filter((n) => !n.read).length
  } catch {
    notifications.value = []
    unreadCount.value = 0
  }
}

const markOneRead = async (n) => {
  if (n.read) return
  n.read = true
  n.is_read = true
  unreadCount.value = notifications.value.filter((x) => !x.read).length
  try {
    await markNotificationRead(n.id)
    window.dispatchEvent(new Event('notifications-updated'))
  } catch (err) {
    console.error('Failed to mark notification as read:', err)
    n.read = false
    n.is_read = false
    unreadCount.value = notifications.value.filter((x) => !x.read).length
  }
}

const markAllRead = async () => {
  const unread = notifications.value.filter((n) => !n.read)
  if (!unread.length) return
  unread.forEach((n) => { n.read = true; n.is_read = true })
  unreadCount.value = 0
  try {
    await markAllNotificationsRead()
    window.dispatchEvent(new Event('notifications-updated'))
  } catch (err) {
    console.error('Failed to mark all notifications as read:', err)
    unread.forEach((n) => { n.read = false; n.is_read = false })
    unreadCount.value = notifications.value.filter((x) => !x.read).length
  }
}

const handleNotificationClick = (n) => {
  notificationsOpen.value = false
  markOneRead(n)
  if (n.related_type === 'emergency_request' && n.related_id) {
    router.push(`/citizen/requests/${n.related_id}`)
  } else if (n.related_type === 'kyc_verification') {
    router.push('/citizen/verify')
  }
}

const goToAllNotifications = () => {
  notificationsOpen.value = false
  router.push('/citizen/notifications')
}

const logout = async () => {
  accountOpen.value = false
  const confirmed = await alert.confirm('Are you sure you want to log out?', 'Log out')
  if (!confirmed) return

  try {
    await sessionLogout()
    alert.success('Logged out successfully')
    router.push('/login')
  } catch (err) {
    console.error(err)
    alert.error('Logout failed')
  }
}

const handleWindowClick = () => {
  notificationsOpen.value = false
  accountOpen.value = false
}

onMounted(() => {
  loadNotifications()
  notificationInterval = setInterval(loadNotifications, 15_000)
  window.addEventListener('click', handleWindowClick)
  window.addEventListener('notifications-updated', loadNotifications)
})

onUnmounted(() => {
  clearInterval(notificationInterval)
  window.removeEventListener('click', handleWindowClick)
  window.removeEventListener('notifications-updated', loadNotifications)
})
</script>

<style scoped>
.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: all 0.18s ease;
}
.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/*
  Match the site's medical-blue focus ring (see Footer.vue) instead of the
  browser default, so keyboard focus feels consistent everywhere.
*/
.dascare-topbar :focus-visible {
  outline-color: rgba(25, 118, 210, 0.55);
}
</style>