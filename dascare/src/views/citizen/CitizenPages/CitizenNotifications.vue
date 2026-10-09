<template>
  <section class="min-h-screen bg-base-200 dark:bg-[#050e1a] pb-10">

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
          <h1 class="mt-2 text-xl sm:text-2xl font-bold text-slate-900 dark:text-white truncate">Notifications</h1>
          <p class="text-sm text-slate-500 dark:text-white/45">Updates on your requests and account.</p>
        </div>

        <button
          v-if="unreadCount > 0"
          @click="markAllRead"
          class="flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-bold bg-white dark:bg-white/5 text-red-600 dark:text-red-300 border border-slate-200 dark:border-white/15 hover:border-red-200 dark:hover:border-red-500/30 transition-colors shadow-sm"
        >
          <Icon icon="lucide:check-check" width="16" /> Mark all read
        </button>
      </div>
    </section>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">

      <!-- ================= Quick stats ================= -->
      <div v-if="!loading && !loadError" class="grid grid-cols-3 gap-3 mb-6">
        <div class="rounded-2xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] px-4 py-3.5">
          <p class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white">{{ notifications.length }}</p>
          <p class="text-[0.68rem] font-semibold text-slate-400 dark:text-white/35 mt-0.5">Total</p>
        </div>
        <div class="rounded-2xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] px-4 py-3.5">
          <p class="text-xl sm:text-2xl font-bold text-red-600 dark:text-red-300 flex items-center gap-1.5">
            {{ unreadCount }}
            <span v-if="unreadCount" class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
          </p>
          <p class="text-[0.68rem] font-semibold text-slate-400 dark:text-white/35 mt-0.5">Unread</p>
        </div>
        <div class="rounded-2xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] px-4 py-3.5">
          <p class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white">{{ todayCount }}</p>
          <p class="text-[0.68rem] font-semibold text-slate-400 dark:text-white/35 mt-0.5">Today</p>
        </div>
      </div>

      <!-- ================= Read/unread filter tabs ================= -->
      <div v-if="!loading && !loadError" class="flex flex-wrap gap-1.5 mb-3">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          @click="activeTab = tab.value"
          class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold transition-colors"
          :class="activeTab === tab.value
            ? 'bg-red-600 text-white'
            : 'bg-base-100 dark:bg-white/5 text-slate-500 dark:text-white/45 border border-base-300 dark:border-white/10 hover:border-slate-300 dark:hover:border-white/20'"
        >
          {{ tab.label }}
          <span
            class="text-[0.62rem] font-bold px-1.5 rounded-full"
            :class="activeTab === tab.value ? 'bg-white/20 text-white' : 'bg-base-200 dark:bg-white/10 text-slate-400 dark:text-white/40'"
          >{{ tabCount(tab.value) }}</span>
        </button>
      </div>

      <!-- ================= Type filter chips (only shown if there's more than one type to split by) ================= -->
      <div v-if="!loading && !loadError && availableTypes.length > 1" class="flex flex-wrap gap-1.5 mb-5">
        <button
          @click="activeType = 'all'"
          class="px-3 py-1 rounded-full text-[0.68rem] font-semibold transition-colors"
          :class="activeType === 'all'
            ? 'bg-slate-800 dark:bg-white/15 text-white'
            : 'bg-base-200 dark:bg-white/5 text-slate-500 dark:text-white/40 hover:bg-slate-200 dark:hover:bg-white/10'"
        >
          All types
        </button>
        <button
          v-for="type in availableTypes"
          :key="type"
          @click="activeType = type"
          class="flex items-center gap-1 px-3 py-1 rounded-full text-[0.68rem] font-semibold transition-colors"
          :class="activeType === type
            ? 'bg-slate-800 dark:bg-white/15 text-white'
            : 'bg-base-200 dark:bg-white/5 text-slate-500 dark:text-white/40 hover:bg-slate-200 dark:hover:bg-white/10'"
        >
          <Icon :icon="typeIcon(type)" width="11" /> {{ typeLabel(type) }}
        </button>
      </div>

      <!-- ================= States ================= -->
      <div v-if="loading" class="flex justify-center py-16">
        <div class="w-8 h-8 border-4 border-red-600 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <div v-else-if="loadError" class="rounded-3xl border border-dashed border-red-200 dark:border-red-500/20 py-16 text-center">
        <Icon icon="lucide:alert-triangle" width="28" class="mx-auto text-red-400 dark:text-red-400/60 mb-3" />
        <p class="text-sm font-semibold text-slate-600 dark:text-white/50">{{ loadError }}</p>
        <button type="button" @click="fetchNotifications" class="mt-3 text-xs font-bold text-red-600 dark:text-red-300 hover:underline">
          Try again
        </button>
      </div>

      <div v-else-if="filteredNotifications.length" class="rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] overflow-hidden divide-y divide-slate-100 dark:divide-white/5">
        <div
          v-for="n in filteredNotifications"
          :key="n.id"
          @click="handleClick(n)"
          class="flex items-start gap-3 px-5 py-4 cursor-pointer hover:bg-base-200 dark:hover:bg-white/5 transition-colors"
          :class="!n.read ? 'bg-red-50/40 dark:bg-red-500/5' : ''"
        >
          <div
            class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5"
            :class="!n.read ? 'bg-red-50 dark:bg-red-500/10' : 'bg-base-200 dark:bg-white/5'"
          >
            <Icon :icon="typeIcon(n.notification_type)" width="15" :class="!n.read ? 'text-red-600 dark:text-red-300' : 'text-slate-400 dark:text-white/40'" />
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ n.title }}</p>
            <p class="text-xs text-slate-500 dark:text-white/45 mt-0.5">{{ n.message }}</p>
            <p class="text-[0.68rem] text-slate-400 dark:text-white/30 mt-1">{{ relativeTime(n.created_at) }}</p>
          </div>
          <Icon
            v-if="n.related_type === 'emergency_request' && n.related_id"
            icon="lucide:chevron-right"
            width="15"
            class="text-slate-300 dark:text-white/20 flex-shrink-0 mt-1.5"
          />
          <span v-else-if="!n.read" class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0 mt-1.5"></span>
        </div>
      </div>

      <div v-else class="rounded-3xl border border-dashed border-slate-300 dark:border-white/15 py-16 text-center">
        <Icon icon="lucide:bell-off" width="28" class="mx-auto text-slate-300 dark:text-white/20 mb-3" />
        <p class="text-sm font-semibold text-slate-500 dark:text-white/40">
          {{ notifications.length ? 'No notifications in this filter' : 'No notifications yet' }}
        </p>
        <button
          v-if="notifications.length && (activeTab !== 'all' || activeType !== 'all')"
          @click="resetFilters"
          class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 rounded-xl text-xs font-bold bg-base-200 dark:bg-white/5 text-slate-500 dark:text-white/45 hover:bg-slate-200 dark:hover:bg-white/10 transition-colors"
        >
          Clear filters
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Icon } from '@iconify/vue'
import { fetchNotifications as fetchNotificationList, markAllNotificationsRead, markNotificationRead } from '@/services/notifications'

const router = useRouter()

const loading = ref(true)
const loadError = ref('')
const notifications = ref([])
const unreadCount = ref(0)

/* ================= Filters ================= */
const activeTab = ref('all')   // all | unread | read
const activeType = ref('all')  // all | <notification_type value>

const tabs = [
  { value: 'all', label: 'All' },
  { value: 'unread', label: 'Unread' },
  { value: 'read', label: 'Read' },
]

// Distinct notification_type values actually present in the fetched data —
// built dynamically rather than a hardcoded list, since notification_type
// is free-form (varchar, no enum/seed values in the schema) and driven by
// whatever backend events start emitting notifications over time.
const availableTypes = computed(() => {
  const seen = new Set()
  notifications.value.forEach((n) => { if (n.notification_type) seen.add(n.notification_type) })
  return [...seen].sort()
})

const filteredNotifications = computed(() => {
  let list = notifications.value
  if (activeTab.value === 'unread') list = list.filter((n) => !n.read)
  if (activeTab.value === 'read') list = list.filter((n) => n.read)
  if (activeType.value !== 'all') list = list.filter((n) => n.notification_type === activeType.value)
  return list
})

const tabCount = (value) => {
  if (value === 'unread') return notifications.value.filter((n) => !n.read).length
  if (value === 'read') return notifications.value.filter((n) => n.read).length
  return notifications.value.length
}

const todayCount = computed(() => {
  const now = new Date()
  return notifications.value.filter((n) => {
    if (!n.created_at) return false
    const d = new Date(n.created_at)
    return d.toDateString() === now.toDateString()
  }).length
})

const resetFilters = () => {
  activeTab.value = 'all'
  activeType.value = 'all'
}

/* ================= Type → icon/label =================
   Loosely matched against notification_type, same tolerant-regex approach
   MyRequests.vue uses for emergency_category_name — survives new type
   strings being introduced backend-side without the UI going blank. */
const TYPE_ICONS = [
  [/kyc|verif/i, 'lucide:shield-check'],
  [/assign/i, 'lucide:user-check'],
  [/dispatch|respond/i, 'lucide:siren'],
  [/scene|arriv/i, 'lucide:map-pin'],
  [/transport/i, 'lucide:car-front'],
  [/complet/i, 'lucide:circle-check'],
  [/cancel|reject|duplicate|false/i, 'lucide:x-circle'],
  [/security|password|login/i, 'lucide:lock'],
  [/system|maintenance|update/i, 'lucide:info'],
]
const typeIcon = (type) => TYPE_ICONS.find(([re]) => re.test(type || ''))?.[1] ?? 'lucide:bell'
const typeLabel = (type) =>
  (type || '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())

/* ================= Time formatting ================= */
const relativeTime = (s) => {
  if (!s) return '—'
  const then = new Date(s)
  const diffSec = Math.floor((Date.now() - then.getTime()) / 1000)
  if (diffSec < 60) return 'Just now'
  if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m ago`
  if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h ago`
  if (diffSec < 86400 * 7) return `${Math.floor(diffSec / 86400)}d ago`
  return then.toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
}

/* ================= Fetch / actions =================
   Same endpoints as before — this is a UI redesign only, no API changes. */
const fetchNotifications = async () => {
  loading.value = true
  loadError.value = ''
  try {
    notifications.value = await fetchNotificationList()
    unreadCount.value = notifications.value.filter((n) => !n.read).length
  } catch (err) {
    console.error(err)
    notifications.value = []
    loadError.value = err.response?.data?.message || 'Could not load your notifications. Please try again.'
  } finally {
    loading.value = false
  }
}

const markAllRead = async () => {
  try {
    await markAllNotificationsRead()
    notifications.value.forEach((n) => { n.read = true; n.is_read = true })
    unreadCount.value = 0
    window.dispatchEvent(new Event('notifications-updated'))
  } catch (err) {
    console.error(err)
  }
}

const handleClick = async (n) => {
  if (!n.read) {
    n.read = true
    n.is_read = true
    unreadCount.value = notifications.value.filter((x) => !x.read).length
    try {
      await markNotificationRead(n.id)
      window.dispatchEvent(new Event('notifications-updated'))
    } catch (err) {
      console.error(err)
      n.read = false
      n.is_read = false
      unreadCount.value = notifications.value.filter((x) => !x.read).length
      return
    }
  }
  if (n.related_type === 'emergency_request' && n.related_id) {
    router.push(`/citizen/requests/${n.related_id}`)
  } else if (n.related_type === 'kyc_verification') {
    router.push('/citizen/verify')
  }
}

onMounted(fetchNotifications)
</script>