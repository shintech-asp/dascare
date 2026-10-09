<template>
  <PullToRefresh :on-refresh="load">
    <div class="space-y-4 px-4 pt-4">
      <!-- Same content as the web's CitizenNotifications.vue -->
      <div class="flex items-center justify-between gap-3">
        <div class="flex gap-1.5">
          <button v-for="tab in tabs" :key="tab.value" type="button" class="tap flex items-center gap-1.5 rounded-full px-3.5 py-2 text-xs font-bold transition-colors"
            :class="activeTab === tab.value ? 'bg-red-600 text-white' : 'border border-base-300 bg-base-100 text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-white/45'"
            @click="activeTab = tab.value">
            {{ tab.label }}
            <span class="rounded-full px-1.5 text-[0.62rem] font-bold" :class="activeTab === tab.value ? 'bg-white/20 text-white' : 'bg-base-200 text-slate-400 dark:bg-white/10 dark:text-white/40'">{{ tab.value === 'unread' ? unread : notifications.length }}</span>
          </button>
        </div>
        <button v-if="unread > 0" type="button" class="tap flex items-center gap-1.5 rounded-xl border border-slate-200 bg-base-100 px-3 py-2 text-xs font-bold text-red-600 dark:border-white/15 dark:bg-white/5 dark:text-red-300" @click="markAll">
          <Icon icon="lucide:check-check" width="15" /> Mark all read
        </button>
      </div>

      <SkeletonList v-if="loading" :rows="5" />
      <div v-else-if="loadError" class="rounded-3xl border border-dashed border-red-200 py-14 text-center dark:border-red-500/20">
        <Icon icon="lucide:alert-triangle" width="28" class="mx-auto mb-3 text-red-400" />
        <p class="text-sm font-semibold text-slate-600 dark:text-white/50">{{ loadError }}</p>
        <button type="button" class="mt-3 py-2 text-xs font-bold text-red-600 dark:text-red-300" @click="load">Try again</button>
      </div>
      <div v-else-if="filtered.length" :class="[ui.card, 'divide-y divide-slate-100 overflow-hidden dark:divide-white/5']">
        <button v-for="n in filtered" :key="n.id" type="button" class="tap flex w-full items-start gap-3 px-5 py-4 text-left" :class="!n.read ? 'bg-red-50/40 dark:bg-red-500/5' : ''" @click="open(n)">
          <span class="mt-0.5 grid h-9 w-9 flex-shrink-0 place-items-center rounded-xl" :class="!n.read ? 'bg-red-50 dark:bg-red-500/10' : 'bg-base-200 dark:bg-white/5'">
            <Icon :icon="typeIcon(n.notification_type)" width="15" :class="!n.read ? 'text-red-600 dark:text-red-300' : 'text-slate-400 dark:text-white/40'" />
          </span>
          <span class="min-w-0 flex-1">
            <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ n.title }}</span>
            <span class="mt-0.5 block text-xs text-slate-500 dark:text-white/45">{{ n.message }}</span>
            <span class="mt-1 block text-[0.68rem] text-slate-400 dark:text-white/30">{{ relativeTime(n.created_at) }}</span>
          </span>
          <Icon v-if="isLinked(n)" icon="lucide:chevron-right" width="15" class="mt-1.5 flex-shrink-0 text-slate-300 dark:text-white/20" />
          <span v-else-if="!n.read" class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-red-500"></span>
        </button>
      </div>
      <div v-else class="rounded-3xl border border-dashed border-slate-300 px-6 py-14 text-center dark:border-white/15">
        <Icon icon="lucide:bell-off" width="28" class="mx-auto mb-3 text-slate-300 dark:text-white/20" />
        <p class="text-sm font-semibold text-slate-500 dark:text-white/40">{{ notifications.length ? 'No unread notifications' : 'No notifications yet' }}</p>
        <p class="mt-1 text-xs text-slate-400 dark:text-white/30">Updates about your requests and verification will appear here.</p>
      </div>
    </div>
  </PullToRefresh>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import PullToRefresh from '@/components/PullToRefresh.vue'
import SkeletonList from '@/components/SkeletonList.vue'
import * as ui from '@/components/ui/styles'
import api, { apiMessage } from '@/services/api'
import { useLiveRefresh } from '@/composables/useLiveRefresh'
import { useUnreadCount } from '@/composables/useUnreadCount'
import { relativeTime } from '@/utils/requestStatus'

const router = useRouter()
const badge = useUnreadCount()
const notifications = ref([])
const loading = ref(true)
const loadError = ref('')
const activeTab = ref('all')
const tabs = [{ value: 'all', label: 'All' }, { value: 'unread', label: 'Unread' }]

const unread = computed(() => notifications.value.filter((n) => !n.read).length)
const filtered = computed(() => (activeTab.value === 'unread' ? notifications.value.filter((n) => !n.read) : notifications.value))

// Same type → icon table as the web.
const TYPE_ICONS = [
  [/kyc|verif/i, 'lucide:shield-check'], [/assign/i, 'lucide:user-check'], [/dispatch|respond/i, 'lucide:siren'],
  [/scene|arriv/i, 'lucide:map-pin'], [/transport/i, 'lucide:car-front'], [/complet/i, 'lucide:circle-check'],
  [/cancel|reject|duplicate|false/i, 'lucide:x-circle'], [/security|password|login/i, 'lucide:lock'], [/system|maintenance|update/i, 'lucide:info'],
]
const typeIcon = (type) => TYPE_ICONS.find(([re]) => re.test(type || ''))?.[1] ?? 'lucide:bell'
const isLinked = (n) => (n.related_type === 'emergency_request' && n.related_id) || n.related_type === 'kyc_verification'

// Same endpoints as the web (notifications/*.php).
async function load() {
  loadError.value = ''
  try {
    const { data } = await api.get('/notifications/get.php')
    notifications.value = (Array.isArray(data) ? data : []).map((n) => ({ ...n, read: Boolean(n.read ?? n.is_read ?? n.read_at) }))
    badge.count.value = unread.value
  } catch (err) {
    loadError.value = apiMessage(err, 'Could not load notifications.')
  } finally {
    loading.value = false
  }
}

async function open(n) {
  if (!n.read) {
    n.read = true
    badge.count.value = unread.value
    api.post('/notifications/mark_read.php', { id: n.id }).catch(() => { n.read = false; badge.count.value = unread.value })
  }
  if (n.related_type === 'emergency_request' && n.related_id) router.push({ name: 'Track', params: { id: n.related_id } })
  else if (n.related_type === 'kyc_verification') router.push({ name: 'VerifyIdentity' })
}

async function markAll() {
  notifications.value.forEach((n) => { n.read = true })
  badge.count.value = 0
  try { await api.post('/notifications/mark_all_read.php', {}) } catch { load() }
}

useLiveRefresh(load, { intervalMs: 30000 })
load()
</script>
