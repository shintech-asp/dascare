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
          <h1 class="mt-2 text-xl sm:text-2xl font-bold text-slate-900 dark:text-white truncate">My Requests</h1>
          <p class="text-sm text-slate-500 dark:text-white/45">All emergency requests you've filed.</p>
        </div>

        <RouterLink
          to="/citizen/request"
          class="flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-bold bg-red-600 hover:bg-red-700
                 text-white shadow-lg shadow-red-200 dark:shadow-red-950/40 transition-colors no-underline"
        >
          <Icon icon="lucide:siren" width="17" />
          New Request
        </RouterLink>
      </div>
    </section>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">

      <!-- ================= Quick stats ================= -->
      <div v-if="!loading && !loadError" class="grid grid-cols-3 gap-3 mb-6">
        <div class="rounded-2xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] px-4 py-3.5">
          <p class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white">{{ requests.length }}</p>
          <p class="text-[0.68rem] font-semibold text-slate-400 dark:text-white/35 mt-0.5">Total filed</p>
        </div>
        <div class="rounded-2xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] px-4 py-3.5">
          <p class="text-xl sm:text-2xl font-bold text-red-600 dark:text-red-300 flex items-center gap-1.5">
            {{ activeCount }}
            <span v-if="activeCount" class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
          </p>
          <p class="text-[0.68rem] font-semibold text-slate-400 dark:text-white/35 mt-0.5">In progress</p>
        </div>
        <div class="rounded-2xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] px-4 py-3.5">
          <p class="text-xl sm:text-2xl font-bold text-emerald-600 dark:text-emerald-300">{{ completedCount }}</p>
          <p class="text-[0.68rem] font-semibold text-slate-400 dark:text-white/35 mt-0.5">Completed</p>
        </div>
      </div>

      <!-- ================= Filter tabs ================= -->
      <div class="flex flex-wrap gap-1.5 mb-5">
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

      <!-- ================= States ================= -->
      <div v-if="loading" class="flex justify-center py-16">
        <div class="w-8 h-8 border-4 border-red-600 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <div v-else-if="loadError" class="rounded-3xl border border-dashed border-red-200 dark:border-red-500/20 py-16 text-center">
        <Icon icon="lucide:alert-triangle" width="28" class="mx-auto text-red-400 dark:text-red-400/60 mb-3" />
        <p class="text-sm font-semibold text-slate-600 dark:text-white/50">{{ loadError }}</p>
        <button type="button" @click="fetchRequests" class="mt-3 text-xs font-bold text-red-600 dark:text-red-300 hover:underline">
          Try again
        </button>
      </div>

      <div v-else-if="filteredRequests.length" class="rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] overflow-hidden divide-y divide-slate-100 dark:divide-white/5">
        <RouterLink
          v-for="r in filteredRequests"
          :key="r.id"
          :to="`/citizen/requests/${r.id}`"
          class="flex items-center gap-3 px-5 sm:px-6 py-4 hover:bg-base-200 dark:hover:bg-white/5 transition-colors no-underline group"
        >
          <div class="relative flex-shrink-0">
            <div v-if="isActive(r.status)" class="absolute inset-0 rounded-xl bg-red-500/15 animate-ping-slow"></div>
            <div class="relative w-10 h-10 rounded-xl bg-red-50 dark:bg-red-500/10 flex items-center justify-center">
              <Icon :icon="categoryIcon(r.emergency_category_name)" width="17" class="text-red-600 dark:text-red-300" />
            </div>
          </div>

          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5">
              <p class="text-sm font-semibold text-slate-800 dark:text-white truncate">{{ r.emergency_category_name }}</p>
              <Icon v-if="r.request_mode === 'instant'" icon="lucide:zap" width="12" class="text-red-500 dark:text-red-300 flex-shrink-0" />
              <span :class="['w-1.5 h-1.5 rounded-full flex-shrink-0', severityDotClass(r.severity)]"></span>
            </div>
            <p class="text-xs text-slate-400 dark:text-white/40 truncate mt-0.5">
              <span class="font-mono">{{ r.reference_number }}</span> · {{ r.address_text }} · {{ formatDate(r.submitted_at) }}
            </p>
          </div>

          <div class="flex flex-shrink-0 flex-col items-end gap-1">
            <span :class="['text-[0.65rem] font-bold px-2.5 py-1 rounded-full', statusBadgeClass(r.status)]"><template v-if="r.merged_into_reference">Linked · </template>{{ statusLabel(r.status) }}</span>
            <span v-if="r.attention_level && r.attention_level !== 'normal'" :class="['text-[0.58rem] font-black px-2 py-0.5 rounded-full uppercase', attentionClass(r.attention_level)]">
              {{ r.attention_level === 'critical_overdue' ? 'Needs urgent review' : 'Needs review' }}
            </span>
          </div>
          <Icon icon="lucide:chevron-right" width="16" class="text-slate-300 dark:text-white/20 flex-shrink-0 group-hover:translate-x-0.5 transition-transform" />
        </RouterLink>
      </div>

      <div v-else class="rounded-3xl border border-dashed border-slate-300 dark:border-white/15 py-16 text-center">
        <Icon icon="lucide:inbox" width="28" class="mx-auto text-slate-300 dark:text-white/20 mb-3" />
        <p class="text-sm font-semibold text-slate-500 dark:text-white/40">
          {{ requests.length ? 'No requests in this filter' : "You haven't filed any requests yet" }}
        </p>
        <RouterLink
          v-if="!requests.length"
          to="/citizen/request"
          class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 rounded-xl text-xs font-bold bg-red-600 hover:bg-red-700 text-white transition-colors no-underline"
        >
          <Icon icon="lucide:siren" width="13" /> File your first request
        </RouterLink>
      </div>
    </div>
  </section>
</template>

<script setup>
import axios from 'axios'
import { ref, computed, onMounted } from 'vue'
import { Icon } from '@iconify/vue'

const API_BASE = import.meta.env.VITE_API_BASE_URL

const loading = ref(true)
const requests = ref([])
const activeTab = ref('all')
const loadError = ref('')

const ACTIVE_STATUSES = ['submitted', 'validating', 'verified', 'assigned', 'acknowledged', 'responding', 'on_scene', 'transporting']
const isActive = (status) => ACTIVE_STATUSES.includes(status)

const tabs = [
  { value: 'all', label: 'All' },
  { value: 'active', label: 'Active' },
  { value: 'completed', label: 'Completed' },
]

const filteredRequests = computed(() => {
  if (activeTab.value === 'active') return requests.value.filter((r) => isActive(r.status))
  if (activeTab.value === 'completed') return requests.value.filter((r) => r.status === 'completed')
  return requests.value
})

const activeCount = computed(() => requests.value.filter((r) => isActive(r.status)).length)
const completedCount = computed(() => requests.value.filter((r) => r.status === 'completed').length)
const tabCount = (value) => {
  if (value === 'active') return activeCount.value
  if (value === 'completed') return completedCount.value
  return requests.value.length
}

const STATUS_LABELS = {
  submitted: 'Submitted', validating: 'Validating', verified: 'Verified', assigned: 'Assigned',
  acknowledged: 'Acknowledged', responding: 'Responding', on_scene: 'On Scene', transporting: 'Transporting',
  completed: 'Completed', cancelled: 'Cancelled', rejected: 'Rejected', duplicate: 'Duplicate', false_alarm: 'False Alarm',
}
const statusLabel = (status) => STATUS_LABELS[status] ?? status
// A report linked by duplicate detection follows the linked incident's
// status; the reference it's linked to is kept for the "Linked ·" label.
const followLinked = (r) => (r && r.linked_status ? { ...r, own_status: r.status, status: r.linked_status } : r)

const statusBadgeClass = (status) => {
  if (status === 'completed') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
  if (['cancelled', 'rejected', 'duplicate', 'false_alarm'].includes(status)) return 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-white/40'
  return 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'
}

const attentionClass = (v) => v === 'critical_overdue' ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'

const severityDotClass = (s) => ({
  low: 'bg-emerald-500',
  moderate: 'bg-amber-500',
  high: 'bg-orange-500',
  critical: 'bg-red-500',
}[s] ?? 'bg-slate-300 dark:bg-white/20')

// Mirrors the category icon set from DetailedRequestForm.vue / TrackRequest.vue,
// matched loosely against the category name so it survives minor copy changes.
const CATEGORY_ICONS = [
  [/medical/i, 'lucide:stethoscope'],
  [/road|accident|vehicle/i, 'lucide:car-front'],
  [/trauma|injury/i, 'lucide:bandage'],
  [/maternal|pregnan/i, 'lucide:baby'],
  [/cardiac|heart/i, 'lucide:heart-pulse'],
  [/fire/i, 'lucide:flame'],
  [/disaster/i, 'lucide:cloud-lightning'],
]
const categoryIcon = (name) => CATEGORY_ICONS.find(([re]) => re.test(name || ''))?.[1] ?? 'lucide:siren'

const formatDate = (s) =>
  s ? new Date(s).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'

// Backed by citizen/emergency_requests/list.php — requester_user_id-scoped
// rows from `emergency_requests`, newest first.
const fetchRequests = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const res = await axios.get(`${API_BASE}/citizen/list.php`, { withCredentials: true })
    requests.value = Array.isArray(res.data) ? res.data.map(followLinked) : []
  } catch (err) {
    console.error(err)
    requests.value = []
    loadError.value = err.response?.data?.message || 'Could not load your requests. Please try again.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchRequests)
</script>

<style scoped>
@keyframes ping-slow {
  0% { transform: scale(0.9); opacity: 0.7; }
  100% { transform: scale(1.4); opacity: 0; }
}
.animate-ping-slow {
  animation: ping-slow 2.2s cubic-bezier(0, 0, 0.2, 1) infinite;
}
</style>