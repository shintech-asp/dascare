<template>
  <section class="min-h-screen bg-base-200 dark:bg-[#050e1a] pb-10">

    <!-- ===== LOADING ===== -->
    <div v-if="loading" class="flex flex-col items-center justify-center min-h-[60vh] gap-4">
      <div class="w-12 h-12 border-4 border-red-600 dark:border-red-400 border-t-transparent rounded-full animate-spin"></div>
      <p class="text-slate-500 dark:text-white/40 text-sm">Loading dashboard...</p>
    </div>

    <div v-else-if="dashboardError" class="max-w-xl mx-auto px-4 pt-16">
      <div class="rounded-3xl border border-red-200 bg-base-100 p-8 text-center shadow-sm dark:border-red-500/20 dark:bg-[#071829]">
        <Icon icon="lucide:triangle-alert" width="30" class="mx-auto text-red-500" />
        <h2 class="mt-4 text-xl font-black text-slate-900 dark:text-white">Dashboard data is temporarily unavailable</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-white/45">{{ dashboardError }}</p>
        <div class="mt-5 flex flex-col justify-center gap-2 sm:flex-row">
          <button type="button" class="rounded-xl border border-base-300 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-base-200 dark:border-white/10 dark:text-white/60" @click="fetchDashboard">Try Again</button>
          <RouterLink to="/citizen/request" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white no-underline hover:bg-red-700">Request Emergency Assistance</RouterLink>
        </div>
      </div>
    </div>

    <!-- ===== KYC WALL =====
         Ported from Likhavite's Home.vue verification-wall pattern: block
         the dashboard behind a verification prompt for any citizen who
         isn't fully approved (kyc_status !== 2) yet. Requesting emergency
         assistance without a verified identity is a bigger risk here than
         it is on a marketplace, so this fully replaces the dashboard
         rather than just blurring/peeking it. -->
    <div v-else-if="showVerificationWall" class="max-w-lg mx-auto px-4 pt-16">
      <div class="relative overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-xl shadow-slate-200/60 dark:border-white/10 dark:bg-[#071829] dark:shadow-black/30">
        <div
          class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-30"
          style="background: radial-gradient(ellipse at top left, rgba(220,38,38,0.08), transparent 55%);"
        ></div>

        <div class="relative p-8 sm:p-10 text-center">
          <div class="flex justify-center mb-6">
            <div class="w-20 h-20 rounded-2xl flex items-center justify-center"
              :class="verificationStatus === 'pending'
                ? 'bg-yellow-50 dark:bg-yellow-500/10'
                : verificationStatus === 'rejected'
                ? 'bg-red-50 dark:bg-red-500/10'
                : 'bg-[#1976D2]/10'">
              <Icon
                :icon="verificationStatus === 'pending' ? 'lucide:hourglass' : verificationStatus === 'rejected' ? 'lucide:x-circle' : 'lucide:shield-alert'"
                width="32"
                :class="verificationStatus === 'pending'
                  ? 'text-yellow-600 dark:text-yellow-400'
                  : verificationStatus === 'rejected'
                  ? 'text-red-600 dark:text-red-400'
                  : 'text-[#1976D2] dark:text-[#7fb3ec]'" />
            </div>
          </div>

          <span class="inline-flex items-center gap-2 rounded-full bg-red-50 dark:bg-red-500/10 px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-red-700 dark:text-red-300">
            <span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>
            Account Access
          </span>

          <h2 class="mt-4 text-xl sm:text-2xl font-bold text-slate-900 dark:text-white mb-2">
            <template v-if="verificationStatus === 'pending'">Verification Under Review</template>
            <template v-else-if="verificationStatus === 'rejected'">Verification Rejected</template>
            <template v-else>Verify Your Account to Continue</template>
          </h2>

          <p class="text-sm text-slate-500 dark:text-white/45 mb-6 leading-relaxed max-w-sm mx-auto">
            <template v-if="verificationStatus === 'pending'">
              Your submission is being reviewed. This usually takes
              <span class="font-semibold text-yellow-600 dark:text-yellow-400">24–48 hours</span>.
              You'll be notified once approved.
            </template>
            <template v-else-if="verificationStatus === 'rejected'">
              Your last verification was rejected. Please re-submit with a valid government ID to regain access to your dashboard.
            </template>
            <template v-else>
              Verification unlocks persistent account features such as dashboard history and profile tools. Emergency assistance remains available even before approval.
            </template>
          </p>

          <div class="bg-base-200 dark:bg-white/[0.03] border border-base-300 dark:border-white/10 rounded-xl p-4 mb-6 text-left space-y-2.5">
            <div class="flex items-start gap-3">
              <Icon icon="lucide:shield-check" width="15" class="mt-0.5 text-slate-400 dark:text-white/35 flex-shrink-0" />
              <p class="text-xs text-slate-600 dark:text-white/50">Verification keeps emergency dispatch trustworthy and prevents false or duplicate reports.</p>
            </div>
            <div class="flex items-start gap-3">
              <Icon icon="lucide:siren" width="15" class="mt-0.5 text-slate-400 dark:text-white/35 flex-shrink-0" />
              <p class="text-xs text-slate-600 dark:text-white/50">Verification unlocks persistent request history and other verified-account features.</p>
            </div>
            <div class="flex items-start gap-3">
              <Icon icon="lucide:file-text" width="15" class="mt-0.5 text-slate-400 dark:text-white/35 flex-shrink-0" />
              <p class="text-xs text-slate-600 dark:text-white/50">You'll need a valid government ID. Setup takes a few minutes; review takes 24–48 hours.</p>
            </div>
          </div>

          <router-link
            :to="{ name: 'CitizenVerification' }"
            class="inline-flex items-center justify-center gap-2 w-full py-3.5 rounded-xl font-bold text-white shadow-lg transition-all active:scale-95 hover:opacity-90"
            :class="verificationStatus === 'pending'
              ? 'bg-yellow-500 shadow-yellow-200 dark:shadow-yellow-950/40'
              : verificationStatus === 'rejected'
              ? 'bg-red-600 shadow-red-200 dark:shadow-red-950/40'
              : 'bg-red-600 shadow-red-200 dark:shadow-red-950/40'"
          >
            <Icon icon="lucide:shield-check" width="16" />
            <span>
              {{ verificationStatus === 'pending'
                ? 'View Verification Status'
                : verificationStatus === 'rejected'
                ? 'Re-submit Verification'
                : 'Verify My Account' }}
            </span>
          </router-link>
        </div>
      </div>
    </div>

    <!-- ===== NORMAL DASHBOARD (verified citizens only) ===== -->
    <template v-else>
      <!-- ===== HEADER ===== -->
      <section class="bg-base-100 dark:bg-[#071829] border-b border-base-300 dark:border-white/10 relative overflow-hidden">
        <div
          class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-30"
          style="background: radial-gradient(ellipse at top left, rgba(220,38,38,0.06), transparent 55%);"
        ></div>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 relative flex flex-wrap gap-4 justify-between items-center">
          <div class="min-w-0">
            <span class="inline-flex items-center gap-2 rounded-full bg-red-50 dark:bg-red-500/10 px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.15em] text-red-700 dark:text-red-300">
              <span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>
              Citizen Dashboard
            </span>
            <h1 class="mt-2 text-xl sm:text-2xl font-bold text-slate-900 dark:text-white truncate">
              Welcome back, {{ user?.name || 'there' }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-white/45">
              Here's the status of your emergency requests.
            </p>
          </div>

          <RouterLink
            to="/citizen/request"
            class="flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-bold bg-red-600 hover:bg-red-700
                   text-white shadow-lg shadow-red-200 dark:shadow-red-950/40 transition-colors no-underline"
          >
            <Icon icon="lucide:siren" width="17" />
            Request Emergency Assistance
          </RouterLink>
        </div>
      </section>

      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-6">

        <!-- ===== ACTIVE REQUEST TRACKER ===== -->
        <div v-if="activeRequest" class="rounded-3xl border border-red-200 dark:border-red-500/20 bg-base-100 dark:bg-[#071829] shadow-sm p-5 sm:p-6">
          <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-11 h-11 rounded-2xl bg-red-100 dark:bg-red-500/15 flex items-center justify-center flex-shrink-0 animate-pulse">
                <Icon icon="lucide:activity" width="20" class="text-red-600 dark:text-red-300" />
              </div>
              <div class="min-w-0">
                <h3 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base">
                  Active Request — {{ activeRequest.reference_number }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-white/45">
                  {{ activeRequest.emergency_category_name }} · Submitted {{ formatDate(activeRequest.submitted_at) }}
                </p>
              </div>
            </div>
            <span :class="['text-xs font-bold px-2.5 py-1 rounded-full flex-shrink-0', statusBadgeClass(activeRequest.status)]">
              <template v-if="activeRequest.merged_into_reference">Linked · </template>{{ statusLabel(activeRequest.status) }}
            </span>
          </div>

          <div v-if="activeRequest.attention_level && activeRequest.attention_level !== 'normal'" class="mb-4 rounded-2xl border px-4 py-3" :class="activeRequest.attention_level === 'critical_overdue' ? 'border-red-200 bg-red-50 dark:border-red-500/20 dark:bg-red-500/10' : 'border-amber-200 bg-amber-50 dark:border-amber-500/20 dark:bg-amber-500/10'">
            <div class="flex items-start gap-2.5">
              <Icon icon="lucide:triangle-alert" width="16" class="mt-0.5" :class="activeRequest.attention_level === 'critical_overdue' ? 'text-red-600' : 'text-amber-600'" />
              <div>
                <p class="text-xs font-black" :class="activeRequest.attention_level === 'critical_overdue' ? 'text-red-800 dark:text-red-200' : 'text-amber-800 dark:text-amber-200'">{{ activeRequest.attention_level === 'critical_overdue' ? 'This request needs urgent operational review' : 'This request is overdue for operational review' }}</p>
                <p class="mt-1 text-[0.68rem] leading-5 text-slate-500 dark:text-white/45">{{ activeRequest.attention_reason }}</p>
              </div>
            </div>
          </div>

          <!-- Status timeline -->
          <div class="flex items-center justify-between relative mb-2">
            <div class="absolute left-0 right-0 top-3 h-0.5 bg-base-200 dark:bg-white/10 -z-0"></div>
            <div
              class="absolute left-0 top-3 h-0.5 bg-red-500 -z-0 transition-all duration-500"
              :style="{ width: timelineProgress(activeRequest.status) + '%' }"
            ></div>
            <div v-for="step in TIMELINE_STEPS" :key="step.key" class="relative z-10 flex flex-col items-center gap-1.5 flex-1">
              <div
                class="w-6 h-6 rounded-full flex items-center justify-center border-2 transition-colors"
                :class="stepReached(activeRequest.status, step.key)
                  ? 'bg-red-600 border-red-600 text-white'
                  : 'bg-base-100 dark:bg-[#071829] border-slate-200 dark:border-white/15 text-slate-300 dark:text-white/20'"
              >
                <Icon icon="lucide:check" width="12" v-if="stepReached(activeRequest.status, step.key)" />
              </div>
              <span class="text-[0.62rem] text-center leading-tight text-slate-400 dark:text-white/35 max-w-[64px]">
                {{ step.label }}
              </span>
            </div>
          </div>

          <div class="flex justify-end mt-3">
            <RouterLink
              :to="`/citizen/requests/${activeRequest.id}`"
              class="text-xs font-bold text-red-600 dark:text-red-300 hover:underline inline-flex items-center gap-1"
            >
              View full details <Icon icon="lucide:arrow-right" width="13" />
            </RouterLink>
          </div>
        </div>

        <!-- ===== NO ACTIVE REQUEST ===== -->
        <div
          v-else
          class="rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-6 flex items-center gap-4 flex-wrap"
        >
          <div class="w-11 h-11 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
            <Icon icon="lucide:shield-check" width="20" class="text-emerald-600 dark:text-emerald-400" />
          </div>
          <div class="flex-1 min-w-[200px]">
            <p class="text-sm font-bold text-slate-900 dark:text-white">No active requests</p>
            <p class="text-xs text-slate-500 dark:text-white/45">
              You don't have an ongoing emergency request right now. If that changes, request assistance any time.
            </p>
          </div>
        </div>

        <!-- ===== QUICK STATS ===== -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div v-for="stat in stats" :key="stat.label" class="rounded-2xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-4">
            <div class="flex items-center gap-2 mb-1.5">
              <Icon :icon="stat.icon" width="15" class="text-slate-400 dark:text-white/35" />
              <span class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-400 dark:text-white/35">{{ stat.label }}</span>
            </div>
            <p class="text-xl font-bold text-slate-900 dark:text-white">{{ stat.value }}</p>
          </div>
        </div>

        <!-- ===== RECENT REQUESTS ===== -->
        <div class="rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] overflow-hidden">
          <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-slate-100 dark:border-white/10">
            <h3 class="font-bold text-slate-900 dark:text-white text-sm">Recent Requests</h3>
            <RouterLink to="/citizen/requests" class="text-xs font-bold text-red-600 dark:text-red-300 hover:underline">
              View all
            </RouterLink>
          </div>

          <div v-if="recentRequests.length" class="divide-y divide-slate-100 dark:divide-white/5">
            <RouterLink
              v-for="r in recentRequests"
              :key="r.id"
              :to="`/citizen/requests/${r.id}`"
              class="flex items-center gap-3 px-5 sm:px-6 py-3.5 hover:bg-base-200 dark:hover:bg-white/5 transition-colors no-underline"
            >
              <div class="w-9 h-9 rounded-xl bg-base-200 dark:bg-white/5 flex items-center justify-center flex-shrink-0">
                <Icon icon="lucide:file-text" width="15" class="text-slate-400 dark:text-white/40" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-slate-800 dark:text-white truncate">{{ r.reference_number }} · {{ r.emergency_category_name }}</p>
                <p class="text-xs text-slate-400 dark:text-white/40">{{ formatDate(r.submitted_at) }}</p>
              </div>
              <div class="flex flex-shrink-0 flex-col items-end gap-1">
                <span :class="['text-[0.65rem] font-bold px-2 py-1 rounded-full', statusBadgeClass(r.status)]"><template v-if="r.merged_into_reference">Linked · </template>{{ statusLabel(r.status) }}</span>
                <span v-if="r.attention_level && r.attention_level !== 'normal'" class="rounded-full bg-amber-100 px-2 py-0.5 text-[0.55rem] font-black uppercase text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">Needs review</span>
              </div>
            </RouterLink>
          </div>

          <div v-else class="px-6 py-10 text-center text-sm text-slate-400 dark:text-white/35">
            No requests filed yet.
          </div>
        </div>
      </div>
    </template>
  </section>
</template>

<script setup>
import axios from 'axios'
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Icon } from '@iconify/vue'
import { getSession } from '@/composables/useSession'
import { setPostLoginRedirect } from '@/utils/postLoginRedirect'

const router = useRouter()
const API_BASE = import.meta.env.VITE_API_BASE_URL

const loading = ref(true)
const dashboardError = ref('')
const user = ref(null)
const activeRequest = ref(null)
const recentRequests = ref([])
const totalRequests = ref(0)

/* ===== KYC WALL =====
   null = still loading (prevents flash), 0 = not submitted, 1 = pending,
   2 = approved, 3 = rejected. Every citizen who reaches this dashboard
   must be fully approved (2) to see it — there's no staff-role exemption
   here the way Likhavite's KYC_EXEMPT_ROLES has, since only the `citizen`
   role ever lands on /citizen/* routes (see the router's role guard). */
const kycStatus = ref(null)

const verificationStatus = computed(() => {
  const map = { 0: 'not_submitted', 1: 'pending', 2: 'approved', 3: 'rejected' }
  return map[kycStatus.value] ?? 'not_submitted'
})

const showVerificationWall = computed(() => {
  if (kycStatus.value === null) return false // still loading — don't flash the wall
  return kycStatus.value !== 2
})

/* ===== TIMELINE ===== */
// Mirrors emergency_requests.status enum from dascare.sql, collapsed to
// the handful of stages worth showing a citizen (validating/verified fold
// into "Verified"; terminal states like cancelled/rejected/duplicate/
// false_alarm never reach this component since fetchDashboard() only
// treats non-terminal statuses as "active" below).
const TIMELINE_STEPS = [
  { key: 'submitted', label: 'Submitted' },
  { key: 'verified', label: 'Verified' },
  { key: 'assigned', label: 'Assigned' },
  { key: 'responding', label: 'Responding' },
  { key: 'on_scene', label: 'On Scene' },
  { key: 'completed', label: 'Completed' },
]
const STEP_ORDER = TIMELINE_STEPS.map((s) => s.key)
// Collapses the finer-grained backend statuses onto the steps above.
const STATUS_TO_STEP = {
  submitted: 'submitted',
  validating: 'submitted',
  verified: 'verified',
  assigned: 'assigned',
  acknowledged: 'assigned',
  responding: 'responding',
  on_scene: 'on_scene',
  transporting: 'on_scene',
  completed: 'completed',
}
const stepIndex = (status) => STEP_ORDER.indexOf(STATUS_TO_STEP[status] ?? 'submitted')
const stepReached = (status, key) => stepIndex(status) >= STEP_ORDER.indexOf(key)
const timelineProgress = (status) => (stepIndex(status) / (STEP_ORDER.length - 1)) * 100

const TERMINAL_STATUSES = ['completed', 'cancelled', 'rejected', 'duplicate', 'false_alarm']

const STATUS_LABELS = {
  submitted: 'Submitted',
  validating: 'Validating',
  verified: 'Verified',
  assigned: 'Assigned',
  acknowledged: 'Acknowledged',
  responding: 'Responding',
  on_scene: 'On Scene',
  transporting: 'Transporting',
  completed: 'Completed',
  cancelled: 'Cancelled',
  rejected: 'Rejected',
  duplicate: 'Duplicate',
  false_alarm: 'False Alarm',
}
const statusLabel = (status) => STATUS_LABELS[status] ?? status
// A report linked by duplicate detection follows the linked incident's
// status; the reference it's linked to is kept for the "Linked ·" label.
const followLinked = (r) => (r && r.linked_status ? { ...r, own_status: r.status, status: r.linked_status } : r)

const statusBadgeClass = (status) => {
  if (TERMINAL_STATUSES.includes(status)) {
    return status === 'completed'
      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
      : 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-white/40'
  }
  return 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'
}

const stats = computed(() => [
  { label: 'Total Requests', value: totalRequests.value, icon: 'lucide:list' },
  { label: 'Completed', value: recentRequests.value.filter((r) => r.status === 'completed').length, icon: 'lucide:circle-check' },
  { label: 'Active', value: activeRequest.value ? 1 : 0, icon: 'lucide:activity' },
  { label: 'This Month', value: countThisMonth(recentRequests.value), icon: 'lucide:calendar' },
])

function countThisMonth(list) {
  const now = new Date()
  return list.filter((r) => {
    const d = new Date(r.submitted_at)
    return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear()
  }).length
}

const formatDate = (s) =>
  s ? new Date(s).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'

/* ===== FETCH ===== */
const fetchDashboard = async () => {
  loading.value = true
  dashboardError.value = ''
  try {
    const res = await axios.get(`${API_BASE}/citizen/dashboard.php`, { withCredentials: true })
    user.value = res.data.user
    activeRequest.value = followLinked(res.data.active_request ?? null)
    recentRequests.value = (res.data.recent_requests ?? []).map(followLinked)
    totalRequests.value = res.data.total_requests ?? recentRequests.value.length
  } catch (err) {
    const status = err.response?.status
    if (status === 401) {
      setPostLoginRedirect(router.currentRoute.value.fullPath)
      router.push('/login')
      return
    }
    user.value = null
    activeRequest.value = null
    recentRequests.value = []
    totalRequests.value = 0
    dashboardError.value = err.response?.data?.message || 'DASCARE could not load your request summary. No sample or fabricated operational data is shown.'
  } finally {
    loading.value = false
  }
}

/* ===== FETCH KYC STATUS =====
   Reads kyc_status off the session, same as Verification.vue and the
   router guard do — session.php needs to expose kyc_status (joined from
   the new kyc_verifications table) for this to reflect real data. Falls
   back to 0 (not submitted) if the session doesn't carry it yet, so the
   wall is the safe default rather than silently granting access. */
const fetchKycStatus = async () => {
  try {
    const session = await getSession()
    kycStatus.value = session.user?.kyc_status ?? 0
  } catch (err) {
    console.error('Failed to load KYC status', err)
    kycStatus.value = 0
  }
}

onMounted(async () => {
  loading.value = true
  await fetchKycStatus()
  // Skip the dashboard fetch entirely while walled off — no point loading
  // request data the citizen isn't allowed to see yet.
  if (kycStatus.value === 2) {
    await fetchDashboard()
  }
  loading.value = false
})
</script>