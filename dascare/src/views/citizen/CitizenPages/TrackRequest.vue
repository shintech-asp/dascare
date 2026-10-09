<template>
  <section class="min-h-screen bg-base-200 dark:bg-[#050e1a] pb-10">

    <!-- ================= Header ================= -->
    <section class="bg-base-100 dark:bg-[#071829] border-b border-base-300 dark:border-white/10 relative overflow-hidden">
      <div
        class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-30"
        style="background: radial-gradient(ellipse at top left, rgba(220,38,38,0.06), transparent 55%);"
      ></div>
      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 relative">
        <RouterLink to="/citizen/requests" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 dark:text-white/45 hover:text-red-600 dark:hover:text-red-300 mb-4 no-underline">
          <Icon icon="lucide:arrow-left" width="14" /> Back to My Requests
        </RouterLink>

        <div v-if="request" class="flex flex-wrap items-start justify-between gap-4">
          <div class="flex items-start gap-4 min-w-0">
            <div class="relative flex-shrink-0">
              <div v-if="isActive" class="absolute inset-0 rounded-2xl bg-red-500/20 animate-ping-slow"></div>
              <div class="relative w-14 h-14 rounded-2xl bg-red-50 dark:bg-red-500/10 flex items-center justify-center">
                <Icon :icon="categoryIcon" width="26" class="text-red-600 dark:text-red-300" />
              </div>
            </div>
            <div class="min-w-0">
              <span class="inline-flex items-center gap-2 rounded-full bg-red-50 dark:bg-red-500/10 px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.15em] text-red-700 dark:text-red-300">
                <span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>
                {{ request.reference_number }}
              </span>
              <h1 class="mt-2 text-xl sm:text-2xl font-bold text-slate-900 dark:text-white leading-tight truncate">{{ request.emergency_category_name }}</h1>
              <div class="flex flex-wrap items-center gap-1.5 mt-2">
                <span :class="['text-[0.65rem] font-bold px-2.5 py-1 rounded-full', severityBadgeClass(request.severity)]">
                  {{ severityLabel(request.severity) }} severity
                </span>
                <span class="flex items-center gap-1 text-[0.65rem] font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-white/45">
                  <Icon :icon="request.request_mode === 'instant' ? 'lucide:zap' : 'lucide:calendar-clock'" width="11" />
                  {{ request.request_mode === 'instant' ? 'Instant Rescue' : 'Standard Request' }}
                </span>
              </div>
            </div>
          </div>

          <span :class="['text-xs font-bold px-3 py-1.5 rounded-full flex-shrink-0 whitespace-nowrap', statusBadgeClass(effectiveStatus)]">
            <template v-if="request.merged_into">Linked · </template>{{ statusLabel(effectiveStatus) }}
          </span>
        </div>

        <!-- Placeholder identity block while the request is still loading, so the
             banner doesn't collapse down to just the back link. -->
        <div v-else-if="loading" class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-2xl bg-base-200 dark:bg-white/5 animate-pulse flex-shrink-0"></div>
          <div class="space-y-2">
            <div class="h-3 w-28 bg-base-200 dark:bg-white/5 rounded animate-pulse"></div>
            <div class="h-5 w-52 bg-base-200 dark:bg-white/5 rounded animate-pulse"></div>
          </div>
        </div>
      </div>
    </section>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">

      <div v-if="loading" class="flex justify-center py-20">
        <div class="w-8 h-8 border-4 border-red-600 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <template v-else-if="request">

        <!-- Linked by duplicate detection: someone nearby already reported this
             emergency, so the citizen follows that incident's unit instead. -->
        <div v-if="request.merged_into" class="mb-5 rounded-3xl border border-red-200 dark:border-red-500/20 bg-red-50 dark:bg-red-500/5 p-4 sm:p-5">
          <div class="flex items-start gap-3">
            <Icon icon="lucide:git-merge" width="18" class="mt-0.5 flex-shrink-0 text-red-600 dark:text-red-300" />
            <div class="min-w-0">
              <p class="text-sm font-black text-red-800 dark:text-red-200">
                Linked to {{ request.merged_into.reference_number }} — {{ linkedHeadline }}
              </p>
              <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-white/55">
                Someone {{ request.merged_into.distance_m }} m away reported this emergency first, so one unit is handling both reports instead of sending two.
                Their pin: {{ request.merged_into.address_text }}<span v-if="request.merged_into.barangay && request.merged_into.barangay !== 'Unspecified'">, Brgy. {{ request.merged_into.barangay }}</span>.
              </p>
              <button type="button" :disabled="unmerging" @click="requestSeparately"
                class="mt-3 inline-flex items-center gap-1.5 rounded-xl border border-red-300 dark:border-red-500/30 bg-white dark:bg-white/5 px-3 py-2 text-xs font-bold text-red-700 dark:text-red-300 hover:bg-red-100 dark:hover:bg-red-500/10 disabled:opacity-50">
                <Icon :icon="unmerging ? 'lucide:loader-circle' : 'lucide:split'" width="14" :class="unmerging ? 'animate-spin' : ''" />
                Not the same emergency? Request separately
              </button>
            </div>
          </div>
        </div>

        <div v-if="request.attention_level && request.attention_level !== 'normal'" class="mb-5 rounded-3xl border p-4 sm:p-5" :class="request.attention_level === 'critical_overdue' ? 'border-red-200 bg-red-50 dark:border-red-500/20 dark:bg-red-500/10' : 'border-amber-200 bg-amber-50 dark:border-amber-500/20 dark:bg-amber-500/10'">
          <div class="flex items-start gap-3">
            <Icon icon="lucide:triangle-alert" width="18" class="mt-0.5 flex-shrink-0" :class="request.attention_level === 'critical_overdue' ? 'text-red-600' : 'text-amber-600'" />
            <div>
              <p class="text-sm font-black" :class="request.attention_level === 'critical_overdue' ? 'text-red-800 dark:text-red-200' : 'text-amber-800 dark:text-amber-200'">
                {{ request.attention_level === 'critical_overdue' ? 'This emergency needs urgent operational review' : 'This emergency is overdue for operational review' }}
              </p>
              <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-white/55">{{ request.attention_reason }}</p>
            </div>
          </div>
        </div>

        <!-- ================= Details ================= -->
        <div class="relative overflow-hidden rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-5 sm:p-7 mb-5">
          <!-- subtle directional glow, echoes the siren/pulse motif used across the request forms -->
          <div class="pointer-events-none absolute -top-24 -right-24 w-64 h-64 rounded-full bg-red-500/10 blur-3xl"></div>

          <p class="relative text-sm text-slate-600 dark:text-white/60 leading-relaxed">{{ request.description }}</p>

          <div class="relative grid sm:grid-cols-2 gap-3 mt-5 pt-5 border-t border-slate-100 dark:border-white/10 text-sm">
            <div class="flex items-start gap-2">
              <Icon icon="lucide:map-pin" width="15" class="text-slate-400 dark:text-white/35 mt-0.5 flex-shrink-0" />
              <span class="text-slate-600 dark:text-white/60">
                {{ request.address_text }}<span v-if="request.barangay">, Brgy. {{ request.barangay }}</span>
                <span v-if="request.landmark" class="block text-xs text-slate-400 dark:text-white/35">Near {{ request.landmark }}</span>
              </span>
            </div>
            <div class="flex items-start gap-2">
              <Icon icon="lucide:clock" width="15" class="text-slate-400 dark:text-white/35 mt-0.5 flex-shrink-0" />
              <span class="text-slate-600 dark:text-white/60">
                Submitted {{ relativeTime(request.submitted_at) }}
                <span class="block text-xs text-slate-400 dark:text-white/35">{{ formatDate(request.submitted_at) }}</span>
              </span>
            </div>
            <div v-if="request.request_mode === 'standard' && request.scheduled_for" class="flex items-start gap-2 sm:col-span-2">
              <Icon icon="lucide:calendar-clock" width="15" class="text-slate-400 dark:text-white/35 mt-0.5 flex-shrink-0" />
              <span class="text-slate-600 dark:text-white/60">Scheduled for {{ formatDate(request.scheduled_for) }}</span>
            </div>
          </div>
        </div>

        <!-- ================= Dispatch pipeline ================= -->
        <div v-if="!isTerminalAlt" class="rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-5 sm:p-7 mb-5">
          <div class="flex items-center justify-between mb-5">
            <h2 class="font-bold text-slate-900 dark:text-white text-sm">Dispatch Progress</h2>
            <span v-if="isActive" class="flex items-center gap-1.5 text-[0.65rem] font-bold text-red-600 dark:text-red-300">
              <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span> LIVE
            </span>
          </div>

          <div class="flex items-start">
            <template v-for="(stage, i) in STAGES" :key="stage.key">
              <div class="flex flex-col items-center flex-1 min-w-0">
                <div
                  class="w-9 h-9 rounded-full flex items-center justify-center border-2 transition-colors flex-shrink-0"
                  :class="stageClass(i)"
                >
                  <Icon :icon="i < stageIndex ? 'lucide:check' : stage.icon" width="15" />
                </div>
                <p class="text-[0.62rem] sm:text-[0.68rem] font-semibold mt-1.5 text-center leading-tight px-0.5"
                   :class="i <= stageIndex ? 'text-slate-700 dark:text-white/80' : 'text-slate-400 dark:text-white/30'">
                  {{ stage.label }}
                </p>
              </div>
              <div v-if="i < STAGES.length - 1" class="flex-1 h-0.5 mt-4 rounded-full transition-colors"
                   :class="i < stageIndex ? 'bg-red-500' : 'bg-slate-200 dark:bg-white/10'"></div>
            </template>
          </div>
        </div>

        <!-- Closed / non-dispatch outcome -->
        <div v-else class="rounded-3xl border p-5 sm:p-7 mb-5 flex items-center gap-3"
             :class="effectiveStatus === 'completed' ? 'border-emerald-200 dark:border-emerald-500/20 bg-emerald-50 dark:bg-emerald-500/5' : 'border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829]'">
          <Icon :icon="statusLabelIcon(effectiveStatus)" width="20" class="flex-shrink-0" :class="effectiveStatus === 'completed' ? 'text-emerald-600 dark:text-emerald-300' : 'text-slate-400 dark:text-white/40'" />
          <p class="text-sm font-semibold text-slate-700 dark:text-white/70">
            <template v-if="request.merged_into">The linked emergency was marked <strong>{{ statusLabel(effectiveStatus).toLowerCase() }}</strong>.</template>
            <template v-else>This request was marked <strong>{{ statusLabel(request.status).toLowerCase() }}</strong> — see the history below for details.</template>
          </p>
        </div>

        <div class="grid lg:grid-cols-3 gap-5">
          <!-- ================= Left: location + history ================= -->
          <div class="lg:col-span-2 space-y-5">

            <!-- Map -->
            <div v-if="request.latitude" class="rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-5 sm:p-6">
              <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold text-slate-900 dark:text-white text-sm">Location</h2>
                <div class="flex items-center gap-3 text-[0.65rem] text-slate-500 dark:text-white/40">
                  <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-600 inline-block"></span> Incident</span>
                  <span v-if="request.merged_into" class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-orange-500 inline-block"></span> Linked report</span>
                  <span v-if="ambulancePos" class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span> Ambulance</span>
                  <LiveBadge v-if="isActive" :live="live" />
                </div>
              </div>
              <div class="w-full h-64 sm:h-80 rounded-xl border border-base-300 dark:border-white/10 overflow-hidden">
                <LiveMissionMap
                  :incident="request"
                  :linked="request.merged_into"
                  :ambulance="isActive && ambulancePos ? request.ambulance : null"
                  :route="isActive ? request.ambulance?.route : null"
                />
              </div>
              <div v-if="ambulancePos" class="mt-2 flex flex-wrap items-center gap-2 text-[0.68rem] text-slate-400 dark:text-white/30">
                <span>Ambulance position updated {{ relativeTime(request.ambulance.location_at) }}<template v-if="request.ambulance.route && !request.ambulance.route.traffic"> — the ETA is a straight-line estimate</template>.</span>
                <span :class="['rounded-full px-2 py-0.5 font-bold', request.ambulance.location_stale ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300']">{{ request.ambulance.location_stale ? 'Stale GPS' : 'Live GPS' }}</span>
              </div>
            </div>

            <!-- History -->
            <div class="rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-5 sm:p-6">
              <h2 class="font-bold text-slate-900 dark:text-white text-sm mb-4">Status History</h2>
              <div class="space-y-0">
                <div v-for="(log, i) in request.status_logs" :key="i" class="flex gap-3">
                  <div class="flex flex-col items-center flex-shrink-0">
                    <span class="w-7 h-7 rounded-full flex items-center justify-center"
                          :class="i === request.status_logs.length - 1 ? 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-300' : 'bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-white/35'">
                      <Icon :icon="statusLabelIcon(log.new_status)" width="13" />
                    </span>
                    <span v-if="i < request.status_logs.length - 1" class="w-px flex-1 bg-slate-200 dark:bg-white/10 my-1"></span>
                  </div>
                  <div class="pb-5 pt-0.5">
                    <p class="text-sm font-semibold text-slate-800 dark:text-white">{{ statusLabel(log.new_status) }}</p>
                    <p class="text-xs text-slate-400 dark:text-white/35">{{ formatDate(log.created_at) }} · {{ relativeTime(log.created_at) }}</p>
                    <p v-if="log.notes" class="text-xs text-slate-500 dark:text-white/45 mt-1 bg-base-200 dark:bg-white/5 rounded-lg px-2.5 py-1.5">{{ log.notes }}</p>
                  </div>
                </div>
                <p v-if="!request.status_logs.length" class="text-xs text-slate-400 dark:text-white/30">No status updates recorded yet.</p>
              </div>
            </div>
          </div>

          <!-- ================= Right: ambulance + facts ================= -->
          <div class="space-y-5">
            <div v-if="request.ambulance" class="rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-5 sm:p-6">
              <h2 class="font-bold text-slate-900 dark:text-white text-sm mb-4">Responding Unit</h2>
              <div class="flex items-center gap-3">
                <div class="relative flex-shrink-0">
                  <div v-if="isActive" class="absolute inset-0 rounded-2xl bg-blue-500/20 animate-ping-slow"></div>
                  <div class="relative w-11 h-11 rounded-2xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center">
                    <Icon icon="lucide:truck" width="20" class="text-blue-600 dark:text-blue-300" />
                  </div>
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ request.ambulance.unit_code }}</p>
                  <p class="text-xs text-slate-500 dark:text-white/45 truncate">{{ request.ambulance.organization_name }}</p>
                </div>
              </div>
              <div class="mt-3 flex flex-wrap gap-2">
                <p class="text-xs font-bold text-blue-600 dark:text-blue-300 bg-blue-50 dark:bg-blue-500/10 rounded-lg px-2.5 py-1.5 inline-block">{{ request.ambulance.status_label }}</p>
                <p v-if="request.ambulance.location_at" :class="['text-xs font-bold rounded-lg px-2.5 py-1.5 inline-block', request.ambulance.location_stale ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300']">{{ request.ambulance.location_stale ? 'GPS stale' : 'GPS live' }}<span v-if="request.ambulance.accuracy_m != null"> · ±{{ Math.round(request.ambulance.accuracy_m) }}m</span></p>
              </div>
            </div>
            <div v-else class="rounded-3xl border border-dashed border-slate-300 dark:border-white/15 p-5 sm:p-6 text-center">
              <Icon icon="lucide:truck" width="20" class="mx-auto text-slate-300 dark:text-white/20 mb-2" />
              <p class="text-xs font-semibold text-slate-400 dark:text-white/35">No unit assigned yet</p>
              <p class="text-[0.68rem] text-slate-400 dark:text-white/25 mt-1">A dispatcher is reviewing this request.</p>
            </div>

            <!-- Life-threatening reminder — same voice as the Instant Rescue form -->
            <div v-if="isActive" class="rounded-3xl border border-red-200 dark:border-red-500/20 bg-red-50 dark:bg-red-500/5 p-4 flex items-center gap-2.5">
              <Icon icon="lucide:phone-call" width="16" class="text-red-600 dark:text-red-400 flex-shrink-0" />
              <span class="text-xs font-bold text-red-700 dark:text-red-300">Life-threatening? Call your local emergency hotline too.</span>
            </div>
          </div>
        </div>
      </template>

      <div v-else-if="loadError" class="rounded-3xl border border-dashed border-red-200 dark:border-red-500/20 py-16 text-center">
        <Icon icon="lucide:alert-triangle" width="28" class="mx-auto text-red-400 dark:text-red-400/60 mb-3" />
        <p class="text-sm font-semibold text-slate-600 dark:text-white/50">{{ loadError }}</p>
        <button type="button" @click="fetchRequest()" class="mt-3 text-xs font-bold text-red-600 dark:text-red-300 hover:underline">
          Try again
        </button>
      </div>

      <div v-else class="rounded-3xl border border-dashed border-slate-300 dark:border-white/15 py-16 text-center">
        <Icon icon="lucide:file-x" width="28" class="mx-auto text-slate-300 dark:text-white/20 mb-3" />
        <p class="text-sm font-semibold text-slate-500 dark:text-white/40">Request not found</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import axios from 'axios'
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { Icon } from '@iconify/vue'
import { unmergeOwnReport } from '@/services/dispatchOperations'
import { useAlert } from '@/composables/useAlert'
import { useLiveUpdates } from '@/composables/useLiveUpdates'
import LiveBadge from '@/components/realtime/LiveBadge.vue'
import LiveMissionMap from '@/components/maps/LiveMissionMap.vue'
import { requestChannel } from '@/services/realtime'

const API_BASE = import.meta.env.VITE_API_BASE_URL
const route = useRoute()

const loading = ref(true)
const request = ref(null)
const loadError = ref('')

// ------------------------------------------------------------------
// Labels / badges
// ------------------------------------------------------------------
const STATUS_LABELS = {
  submitted: 'Submitted', validating: 'Validating', verified: 'Verified', assigned: 'Assigned',
  acknowledged: 'Acknowledged', responding: 'Responding', on_scene: 'On Scene', transporting: 'Transporting',
  completed: 'Completed', cancelled: 'Cancelled', rejected: 'Rejected', duplicate: 'Duplicate', false_alarm: 'False Alarm',
}
const statusLabel = (status) => STATUS_LABELS[status] ?? status

const STATUS_ICONS = {
  submitted: 'lucide:file-text', validating: 'lucide:search', verified: 'lucide:shield-check',
  assigned: 'lucide:truck', acknowledged: 'lucide:check', responding: 'lucide:navigation',
  on_scene: 'lucide:map-pin-check', transporting: 'lucide:activity', completed: 'lucide:check-circle-2',
  cancelled: 'lucide:x-circle', rejected: 'lucide:x-circle', duplicate: 'lucide:copy-x', false_alarm: 'lucide:shield-x',
}
const statusLabelIcon = (status) => STATUS_ICONS[status] ?? 'lucide:circle'

const statusBadgeClass = (status) => {
  if (status === 'completed') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
  if (['cancelled', 'rejected', 'duplicate', 'false_alarm'].includes(status)) return 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-white/40'
  return 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'
}

const SEVERITY_LABELS = { low: 'Low', moderate: 'Moderate', high: 'High', critical: 'Critical' }
const severityLabel = (s) => SEVERITY_LABELS[s] ?? s ?? 'Unspecified'
const severityBadgeClass = (s) => ({
  low: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
  moderate: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
  high: 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
  critical: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
}[s] ?? 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-white/40')

// Mirrors the category icon set from DetailedRequestForm.vue, matched
// loosely against the category name so it survives minor copy changes.
const CATEGORY_ICONS = [
  [/medical/i, 'lucide:stethoscope'],
  [/road|accident|vehicle/i, 'lucide:car-front'],
  [/trauma|injury/i, 'lucide:bandage'],
  [/maternal|pregnan/i, 'lucide:baby'],
  [/cardiac|heart/i, 'lucide:heart-pulse'],
  [/fire/i, 'lucide:flame'],
  [/disaster/i, 'lucide:cloud-lightning'],
]
const categoryIcon = computed(() => {
  const name = request.value?.emergency_category_name || ''
  return CATEGORY_ICONS.find(([re]) => re.test(name))?.[1] ?? 'lucide:siren'
})

const formatDate = (s) => (s ? new Date(s).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—')

// Ticks every 30s so relativeTime() below stays fresh without a full refetch.
const now = ref(Date.now())
let clockTimer = null

const relativeTime = (s) => {
  if (!s) return ''
  const diffMs = now.value - new Date(s).getTime()
  const mins = Math.round(diffMs / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins} min${mins === 1 ? '' : 's'} ago`
  const hrs = Math.round(mins / 60)
  if (hrs < 24) return `${hrs} hr${hrs === 1 ? '' : 's'} ago`
  const days = Math.round(hrs / 24)
  return `${days} day${days === 1 ? '' : 's'} ago`
}

// ------------------------------------------------------------------
// Dispatch pipeline stepper
// ------------------------------------------------------------------
const STAGES = [
  { key: 'submitted', label: 'Submitted', icon: 'lucide:file-text', match: ['submitted', 'validating'] },
  { key: 'verified', label: 'Verified', icon: 'lucide:shield-check', match: ['verified'] },
  { key: 'assigned', label: 'Assigned', icon: 'lucide:truck', match: ['assigned', 'acknowledged'] },
  { key: 'responding', label: 'Responding', icon: 'lucide:navigation', match: ['responding'] },
  { key: 'on_scene', label: 'On Scene', icon: 'lucide:map-pin-check', match: ['on_scene'] },
  { key: 'transporting', label: 'Transporting', icon: 'lucide:activity', match: ['transporting'] },
  { key: 'completed', label: 'Completed', icon: 'lucide:check-circle-2', match: ['completed'] },
]
const TERMINAL_ALT = ['cancelled', 'rejected', 'duplicate', 'false_alarm']

// A report linked by duplicate detection follows the linked incident's status.
const effectiveStatus = computed(() => request.value?.merged_into?.status ?? request.value?.status)
const isTerminalAlt = computed(() => TERMINAL_ALT.includes(effectiveStatus.value))
const stageIndex = computed(() => {
  if (!request.value) return 0
  const i = STAGES.findIndex((s) => s.match.includes(effectiveStatus.value))
  return i === -1 ? 0 : i
})
const isActive = computed(() => request.value && !isTerminalAlt.value && effectiveStatus.value !== 'completed')

const linkedHeadline = computed(() => {
  const s = effectiveStatus.value
  if (['submitted', 'validating'].includes(s)) return 'dispatch is already finding a unit'
  if (['verified', 'assigned', 'acknowledged'].includes(s)) return 'a unit has been assigned'
  if (s === 'responding') return 'a unit is already on the way'
  if (['on_scene', 'transporting'].includes(s)) return 'a unit is already on scene'
  if (s === 'completed') return 'that response has been completed'
  return `that report was marked ${statusLabel(s).toLowerCase()}`
})

// "Not the same emergency?" — unlinks this report so it gets its own unit.
const alert = useAlert()
const unmerging = ref(false)
async function requestSeparately() {
  const ok = await alert.confirm(
    `Only do this if your emergency is different from ${request.value.merged_into.reference_number} — for example, a different person or a different place. Dispatch will find a separate unit for you.`,
    'Request separately?'
  )
  if (!ok) return
  unmerging.value = true
  try {
    const data = await unmergeOwnReport(request.value.id)
    alert.success(data.message, 'Requested separately')
    await fetchRequest(true)
  } catch (err) {
    alert.error(err.response?.data?.message || err.message || 'Something went wrong. Please try again.', 'Error')
  } finally {
    unmerging.value = false
  }
}

const stageClass = (i) => {
  if (i < stageIndex.value) return 'bg-red-600 border-red-600 text-white'
  if (i === stageIndex.value) return 'bg-red-50 border-red-600 text-red-600 dark:bg-red-500/10 dark:text-red-300'
  return 'bg-white border-slate-200 text-slate-300 dark:bg-white/5 dark:border-white/10 dark:text-white/25'
}

// ------------------------------------------------------------------
// Map — components/maps/LiveMissionMap.vue (MapLibre): incident, linked
// report, the ambulance gliding between GPS fixes, road route + ETA.
// ------------------------------------------------------------------
const ambulancePos = computed(() => {
  const a = request.value?.ambulance
  return a?.latitude != null && a?.longitude != null ? [a.latitude, a.longitude] : null
})

// ------------------------------------------------------------------
// Fetch + live polling while the mission is active
// ------------------------------------------------------------------
const fetchRequest = async (silent = false) => {
  if (!silent) loading.value = true
  loadError.value = ''
  try {
    const res = await axios.get(`${API_BASE}/citizen/detail.php`, {
      params: { id: route.params.id },
      withCredentials: true,
    })
    request.value = res.data
  } catch (err) {
    console.error(err)
    if (!silent) request.value = null
    // 404 just means "not found / not yours" — the existing empty state
    // already covers that. Anything else (network/server error) gets its
    // own message so it isn't mistaken for a bad request id.
    if (err.response?.status !== 404) {
      loadError.value = err.response?.data?.message || 'Could not load this request. Please try again.'
    }
  } finally {
    loading.value = false
  }
}

// Live updates: the ambulance's GPS moves the pin directly; any other event
// on this request re-fetches it. Falls back to polling every 15 s while live
// updates are unavailable (60 s while connected), only while still active.
// A dedup-linked report also follows the incident it was linked to.
function applyAmbulanceLocation(data) {
  const shownRequestId = Number(request.value?.merged_into?.id ?? request.value?.id)
  const amb = request.value?.ambulance
  if (!amb || Number(data.request_id) !== shownRequestId) return false
  Object.assign(amb, {
    latitude: data.latitude,
    longitude: data.longitude,
    accuracy_m: data.accuracy_m,
    location_at: new Date(data.sent_at || Date.now()).toISOString(),
    location_stale: false,
  })
  now.value = Date.now()
  return true
}

// New road route / ETA (reusables/routing.php) for the unit shown here.
function applyRoute(data) {
  const shownRequestId = Number(request.value?.merged_into?.id ?? request.value?.id)
  const amb = request.value?.ambulance
  if (!amb || Number(data.request_id) !== shownRequestId) return false
  amb.route = data.route
  return true
}

const { live } = useLiveUpdates(() => fetchRequest(true), {
  channels: () => [requestChannel(route.params.id), requestChannel(request.value?.merged_into?.id)],
  enabled: () => isActive.value,
  onEvent: (message) => {
    if (message.name === 'ambulance.location' && applyAmbulanceLocation(message.data)) return false
    if (message.name === 'route.updated' && applyRoute(message.data)) return false
  },
})

onMounted(async () => {
  await fetchRequest()
  clockTimer = setInterval(() => { now.value = Date.now() }, 30000)
})

onBeforeUnmount(() => {
  clearInterval(clockTimer)
})
</script>

<style scoped>
@keyframes ping-slow {
  0% { transform: scale(0.9); opacity: 0.7; }
  100% { transform: scale(1.5); opacity: 0; }
}
.animate-ping-slow {
  animation: ping-slow 2.2s cubic-bezier(0, 0, 0.2, 1) infinite;
}
</style>