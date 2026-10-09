<template>
  <section class="min-h-screen bg-base-200 pb-10 dark:bg-[#050e1a]">
    <section class="relative overflow-hidden border-b border-base-300 bg-base-100 dark:border-white/10 dark:bg-[#071829]">
      <div
        class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-30"
        style="background: radial-gradient(ellipse at top left, rgba(220,38,38,0.06), transparent 55%);"
      ></div>
      <div class="relative mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-6 sm:px-6 lg:px-8">
        <div class="min-w-0">
          <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.15em] text-red-700 dark:bg-red-500/10 dark:text-red-300">
            <span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>
            Platform Executive
          </span>
          <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white sm:text-2xl">Citizen Verifications</h1>
          <p class="text-sm text-slate-500 dark:text-white/45">Review identity submissions and return clear decisions to citizens.</p>
        </div>

        <button
          type="button"
          :disabled="loading"
          class="inline-flex items-center gap-2 rounded-xl border border-base-300 bg-base-100 px-4 py-2.5 text-xs font-bold text-slate-600 transition-colors hover:bg-base-200 disabled:opacity-50 dark:border-white/10 dark:bg-white/5 dark:text-white/60 dark:hover:bg-base-100/10"
          @click="loadQueue"
        >
          <Icon icon="lucide:refresh-cw" width="15" :class="loading ? 'animate-spin' : ''" />
          Refresh
        </button>
      </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <article
          v-for="card in statCards"
          :key="card.key"
          class="rounded-2xl border border-base-300 bg-base-100 px-4 py-4 dark:border-white/10 dark:bg-[#071829]"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-2xl font-black text-slate-900 dark:text-white">{{ stats[card.key] ?? 0 }}</p>
              <p class="mt-0.5 text-[0.68rem] font-semibold text-slate-400 dark:text-white/35">{{ card.label }}</p>
            </div>
            <span class="flex h-9 w-9 items-center justify-center rounded-xl" :class="card.iconClass">
              <Icon :icon="card.icon" width="16" />
            </span>
          </div>
        </article>
      </div>

      <section class="mt-6 rounded-3xl border border-base-300 bg-base-100 dark:border-white/10 dark:bg-[#071829]">
        <div class="border-b border-base-300 p-4 dark:border-white/10 sm:p-5">
          <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                class="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors"
                :class="activeStatus === tab.value
                  ? 'bg-red-600 text-white'
                  : 'border border-base-300 bg-base-100 text-slate-500 hover:border-slate-300 dark:border-white/10 dark:bg-white/5 dark:text-white/45 dark:hover:border-white/20'"
                @click="setStatus(tab.value)"
              >
                {{ tab.label }}
                <span
                  class="rounded-full px-1.5 text-[0.62rem] font-bold"
                  :class="activeStatus === tab.value ? 'bg-base-100/20 text-white' : 'bg-base-200 text-slate-400 dark:bg-white/10 dark:text-white/40'"
                >{{ tabCount(tab.value) }}</span>
              </button>
            </div>

            <div class="relative w-full xl:w-80">
              <Icon icon="lucide:search" width="15" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 dark:text-white/30" />
              <input
                v-model="searchInput"
                type="search"
                placeholder="Search name, email, phone, ID type..."
                class="w-full rounded-xl border border-base-300 bg-base-100 py-2.5 pl-10 pr-4 text-sm text-slate-800 outline-none transition focus:border-red-300 focus:ring-2 focus:ring-red-100 dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:border-red-500/40 dark:focus:ring-red-500/10"
                @keyup.enter="applySearch"
              />
            </div>
          </div>
        </div>

        <div v-if="loading" class="flex justify-center py-20">
          <div class="h-8 w-8 animate-spin rounded-full border-4 border-red-600 border-t-transparent"></div>
        </div>

        <div v-else-if="loadError" class="px-5 py-16 text-center">
          <Icon icon="lucide:triangle-alert" width="28" class="mx-auto mb-3 text-red-400" />
          <p class="text-sm font-semibold text-slate-600 dark:text-white/50">{{ loadError }}</p>
          <button type="button" class="mt-3 text-xs font-bold text-red-600 hover:underline dark:text-red-300" @click="loadQueue">Try again</button>
        </div>

        <div v-else-if="items.length" class="overflow-x-auto">
          <table class="w-full min-w-[920px] text-left">
            <thead class="bg-base-200/60 text-[0.68rem] font-black uppercase tracking-[0.08em] text-slate-400 dark:bg-white/[0.025] dark:text-white/30">
              <tr>
                <th class="px-5 py-3.5">Applicant</th>
                <th class="px-5 py-3.5">ID Type</th>
                <th class="px-5 py-3.5">Submitted</th>
                <th class="px-5 py-3.5">Location</th>
                <th class="px-5 py-3.5">Status</th>
                <th class="px-5 py-3.5 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-base-300 dark:divide-white/5">
              <tr v-for="item in items" :key="item.user_id" class="transition-colors hover:bg-base-200/60 dark:hover:bg-base-100/[0.025]">
                <td class="px-5 py-4">
                  <p class="text-sm font-bold text-slate-800 dark:text-white/80">{{ item.applicant_name || 'Citizen' }}</p>
                  <p class="mt-0.5 text-xs text-slate-400 dark:text-white/35">{{ item.email }}</p>
                  <p class="mt-0.5 text-[0.68rem] text-slate-400 dark:text-white/30">{{ item.phone_number || item.account_phone }}</p>
                </td>
                <td class="px-5 py-4 text-sm text-slate-600 dark:text-white/55">{{ item.id_type || '—' }}</td>
                <td class="px-5 py-4 text-sm text-slate-600 dark:text-white/55">{{ formatDate(item.submitted_at) }}</td>
                <td class="px-5 py-4 text-sm text-slate-600 dark:text-white/55">{{ [item.barangay, item.city].filter(Boolean).join(', ') || '—' }}</td>
                <td class="px-5 py-4">
                  <span class="inline-flex rounded-full px-2.5 py-1 text-[0.65rem] font-bold" :class="statusBadgeClass(item.status)">
                    {{ statusLabel(item.status) }}
                  </span>
                </td>
                <td class="px-5 py-4 text-right">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-600 transition-colors hover:border-red-200 hover:bg-red-50 hover:text-red-700 dark:border-white/10 dark:text-white/55 dark:hover:border-red-500/20 dark:hover:bg-red-500/10 dark:hover:text-red-300"
                    @click="openReview(item.user_id)"
                  >
                    <Icon icon="lucide:scan-search" width="14" />
                    {{ item.status === 1 ? 'Review' : 'View' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="px-5 py-20 text-center">
          <Icon icon="lucide:shield-check" width="30" class="mx-auto mb-3 text-slate-300 dark:text-white/20" />
          <p class="text-sm font-semibold text-slate-500 dark:text-white/40">No verification submissions match this filter.</p>
        </div>

        <div v-if="!loading && pagination.total > 0" class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 px-5 py-4 dark:border-white/10">
          <p class="text-xs text-slate-400 dark:text-white/30">
            Page {{ pagination.page }} of {{ pagination.pages }} · {{ pagination.total }} result{{ pagination.total === 1 ? '' : 's' }}
          </p>
          <div class="flex gap-2">
            <button type="button" :disabled="pagination.page <= 1" class="rounded-lg border border-base-300 px-3 py-1.5 text-xs font-bold text-slate-500 disabled:opacity-40 dark:border-white/10 dark:text-white/45" @click="goPage(pagination.page - 1)">Previous</button>
            <button type="button" :disabled="pagination.page >= pagination.pages" class="rounded-lg border border-base-300 px-3 py-1.5 text-xs font-bold text-slate-500 disabled:opacity-40 dark:border-white/10 dark:text-white/45" @click="goPage(pagination.page + 1)">Next</button>
          </div>
        </div>
      </section>
    </div>

    <Teleport to="body">
      <div v-if="reviewOpen" class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-5">
        <button type="button" aria-label="Close review" class="absolute inset-0 bg-slate-950/65 backdrop-blur-sm" @click="closeReview"></button>

        <section class="relative flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-2xl dark:border-white/10 dark:bg-[#071829]">
          <header class="flex items-center justify-between gap-4 border-b border-base-300 px-5 py-4 dark:border-white/10 sm:px-6">
            <div class="min-w-0">
              <p class="text-[0.65rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">Verification Review</p>
              <h2 class="mt-0.5 truncate text-lg font-black text-slate-900 dark:text-white">{{ detail?.applicant_name || 'Citizen submission' }}</h2>
            </div>
            <button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl border border-base-300 text-slate-500 hover:bg-base-200 dark:border-white/10 dark:text-white/45 dark:hover:bg-base-100/5" @click="closeReview">
              <Icon icon="lucide:x" width="18" />
            </button>
          </header>

          <div v-if="detailLoading" class="flex min-h-80 items-center justify-center">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-red-600 border-t-transparent"></div>
          </div>

          <div v-else-if="detailError" class="px-6 py-16 text-center">
            <Icon icon="lucide:triangle-alert" width="28" class="mx-auto mb-3 text-red-400" />
            <p class="text-sm font-semibold text-slate-600 dark:text-white/50">{{ detailError }}</p>
          </div>

          <template v-else-if="detail">
            <div class="overflow-y-auto p-5 sm:p-6">
              <div class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
                <div class="space-y-4">
                  <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-200 dark:border-white/10 dark:bg-white/[0.03]">
                    <div class="flex items-center justify-between border-b border-base-300 px-4 py-3 dark:border-white/10">
                      <div>
                        <p class="text-xs font-black text-slate-700 dark:text-white/65">Government ID</p>
                        <p class="text-[0.68rem] text-slate-400 dark:text-white/30">{{ detail.id_type || 'Unspecified ID' }}</p>
                      </div>
                      <span class="rounded-full px-2.5 py-1 text-[0.62rem] font-bold" :class="statusBadgeClass(detail.status)">{{ statusLabel(detail.status) }}</span>
                    </div>
                    <div class="flex min-h-64 items-center justify-center p-3">
                      <div v-if="imageLoading" class="h-7 w-7 animate-spin rounded-full border-4 border-red-600 border-t-transparent"></div>
                      <img v-else-if="imageUrl" :src="imageUrl" alt="Submitted government ID" class="max-h-[420px] w-full rounded-xl object-contain" />
                      <div v-else class="py-16 text-center text-sm text-slate-400 dark:text-white/35">ID image unavailable.</div>
                    </div>
                  </div>

                  <div v-if="detail.latitude !== null && detail.longitude !== null" class="rounded-2xl border border-base-300 bg-base-200 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                    <p class="text-[0.65rem] font-black uppercase tracking-[0.12em] text-slate-400 dark:text-white/30">Submitted location</p>
                    <p class="mt-1 text-sm font-semibold text-slate-700 dark:text-white/65">{{ detail.latitude.toFixed(6) }}, {{ detail.longitude.toFixed(6) }}</p>
                  </div>
                </div>

                <div class="space-y-5">
                  <div class="rounded-2xl border border-base-300 p-4 dark:border-white/10 sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <p class="text-[0.65rem] font-black uppercase tracking-[0.12em] text-red-600 dark:text-red-300">Applicant</p>
                        <h3 class="mt-1 text-base font-black text-slate-900 dark:text-white">{{ detail.applicant_name }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400 dark:text-white/35">Citizen #{{ detail.user_id }}</p>
                      </div>
                      <span class="rounded-full bg-base-200 px-2.5 py-1 text-[0.65rem] font-bold text-slate-500 dark:bg-white/5 dark:text-white/40">{{ detail.account_status }}</span>
                    </div>

                    <dl class="mt-5 grid gap-x-5 gap-y-4 sm:grid-cols-2">
                      <div v-for="field in detailFields" :key="field.label">
                        <dt class="text-[0.65rem] font-bold uppercase tracking-wide text-slate-400 dark:text-white/30">{{ field.label }}</dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-slate-700 dark:text-white/65">{{ field.value || '—' }}</dd>
                      </div>
                    </dl>
                  </div>

                  <div class="rounded-2xl border border-base-300 p-4 dark:border-white/10 sm:p-5">
                    <p class="text-[0.65rem] font-black uppercase tracking-[0.12em] text-slate-400 dark:text-white/30">Review history</p>
                    <div class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                      <div>
                        <p class="text-xs text-slate-400 dark:text-white/30">Submitted</p>
                        <p class="mt-0.5 font-semibold text-slate-700 dark:text-white/65">{{ formatDate(detail.submitted_at) }}</p>
                      </div>
                      <div>
                        <p class="text-xs text-slate-400 dark:text-white/30">Previous review attempts</p>
                        <p class="mt-0.5 font-semibold text-slate-700 dark:text-white/65">{{ detail.rejection_count }}</p>
                      </div>
                      <div v-if="detail.reviewer_name">
                        <p class="text-xs text-slate-400 dark:text-white/30">Last reviewer</p>
                        <p class="mt-0.5 font-semibold text-slate-700 dark:text-white/65">{{ detail.reviewer_name }}</p>
                      </div>
                      <div v-if="detail.verified_at || detail.last_rejected_at">
                        <p class="text-xs text-slate-400 dark:text-white/30">Last decision</p>
                        <p class="mt-0.5 font-semibold text-slate-700 dark:text-white/65">{{ formatDate(detail.verified_at || detail.last_rejected_at) }}</p>
                      </div>
                    </div>
                    <div v-if="detail.verification_note" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                      {{ detail.verification_note }}
                    </div>
                  </div>

                  <div v-if="detail.status === 1" class="rounded-2xl border border-red-200 bg-red-50/50 p-4 dark:border-red-500/20 dark:bg-red-500/5 sm:p-5">
                    <p class="text-sm font-black text-slate-900 dark:text-white">Decision</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-white/40">Approval can include an optional internal/citizen note. Reject and resubmission decisions require a reason.</p>

                    <textarea
                      v-model="decisionNote"
                      rows="4"
                      maxlength="1500"
                      placeholder="Add review notes or explain what needs to be corrected..."
                      class="mt-4 w-full resize-none rounded-xl border border-base-300 bg-base-100 px-3.5 py-3 text-sm text-slate-800 outline-none transition focus:border-red-300 focus:ring-2 focus:ring-red-100 dark:border-white/10 dark:bg-[#081b2e] dark:text-white dark:focus:border-red-500/40 dark:focus:ring-red-500/10"
                    ></textarea>
                    <div class="mt-4 grid gap-2 sm:grid-cols-3">
                      <button type="button" :disabled="reviewSaving" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs font-bold text-amber-700 transition hover:bg-amber-100 disabled:opacity-50 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200" @click="chooseDecision('resubmit')">
                        <Icon icon="lucide:rotate-ccw" width="14" /> Request Resubmission
                      </button>
                      <button type="button" :disabled="reviewSaving" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-xs font-bold text-red-700 transition hover:bg-red-100 disabled:opacity-50 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300" @click="chooseDecision('reject')">
                        <Icon icon="lucide:x-circle" width="14" /> Reject
                      </button>
                      <button type="button" :disabled="reviewSaving" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2.5 text-xs font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50" @click="chooseDecision('approve')">
                        <Icon icon="lucide:shield-check" width="14" /> Approve
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </section>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="confirmDecision" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
        <button type="button" aria-label="Cancel decision" class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" @click="confirmDecision = ''"></button>
        <div class="relative w-full max-w-md rounded-2xl border border-base-300 bg-base-100 p-6 shadow-2xl dark:border-white/10 dark:bg-[#0f2a43]">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl" :class="confirmIconClass">
            <Icon :icon="confirmIcon" width="20" />
          </div>
          <h3 class="mt-4 text-lg font-black text-slate-900 dark:text-white">{{ confirmTitle }}</h3>
          <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-white/45">{{ confirmText }}</p>
          <div v-if="decisionNote.trim()" class="mt-4 rounded-xl bg-base-200 p-3 text-sm leading-6 text-slate-600 dark:bg-white/5 dark:text-white/55">{{ decisionNote.trim() }}</div>
          <div class="mt-5 flex gap-3">
            <button type="button" :disabled="reviewSaving" class="flex-1 rounded-xl border border-base-300 py-2.5 text-sm font-bold text-slate-600 dark:border-white/10 dark:text-white/55" @click="confirmDecision = ''">Cancel</button>
            <button type="button" :disabled="reviewSaving" class="flex-1 rounded-xl py-2.5 text-sm font-bold text-white disabled:opacity-50" :class="confirmButtonClass" @click="saveDecision">
              {{ reviewSaving ? 'Saving…' : 'Confirm' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Icon } from '@iconify/vue'
import { useToast } from '@/composables/useToast'
import { fetchKycDetail, fetchKycImage, fetchKycQueue, submitKycReview } from '@/services/platformKyc'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const loading = ref(true)
const loadError = ref('')
const items = ref([])
const stats = ref({ pending: 0, approved: 0, rejected: 0, resubmission: 0, total: 0 })
const pagination = ref({ page: 1, per_page: 20, total: 0, pages: 1 })
const activeStatus = ref('pending')
const searchInput = ref('')
const appliedSearch = ref('')

const reviewOpen = ref(false)
const detailLoading = ref(false)
const detailError = ref('')
const detail = ref(null)
const imageUrl = ref('')
const imageLoading = ref(false)
const decisionNote = ref('')
const confirmDecision = ref('')
const reviewSaving = ref(false)

const tabs = [
  { value: 'pending', label: 'Pending' },
  { value: 'resubmission', label: 'Needs Resubmission' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'all', label: 'All' },
]

const statCards = [
  { key: 'pending', label: 'Pending review', icon: 'lucide:clock-3', iconClass: 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300' },
  { key: 'resubmission', label: 'Needs resubmission', icon: 'lucide:rotate-ccw', iconClass: 'bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-300' },
  { key: 'approved', label: 'Approved', icon: 'lucide:shield-check', iconClass: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300' },
  { key: 'rejected', label: 'Rejected', icon: 'lucide:x-circle', iconClass: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300' },
  { key: 'total', label: 'Total submissions', icon: 'lucide:files', iconClass: 'bg-base-200 text-slate-500 dark:bg-white/5 dark:text-white/40' },
]

const tabCount = (value) => value === 'all' ? stats.value.total : (stats.value[value] ?? 0)

const STATUS_LABELS = {
  0: 'Unverified',
  1: 'Pending',
  2: 'Approved',
  3: 'Rejected',
  4: 'Resubmission Requested',
}
const statusLabel = (status) => STATUS_LABELS[Number(status)] ?? 'Unknown'
const statusBadgeClass = (status) => {
  const s = Number(status)
  if (s === 2) return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
  if (s === 1) return 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
  if (s === 4) return 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300'
  if (s === 3) return 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'
  return 'bg-base-200 text-slate-500 dark:bg-white/5 dark:text-white/40'
}

const formatDate = (value) => {
  if (!value) return '—'
  return new Date(value).toLocaleString('en-PH', {
    month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit',
  })
}

const formatBirthdate = (value) => {
  if (!value) return '—'
  const [year, month, day] = value.split('-').map(Number)
  return new Date(year, month - 1, day).toLocaleDateString('en-PH', {
    month: 'long', day: 'numeric', year: 'numeric',
  })
}

const detailFields = computed(() => {
  if (!detail.value) return []
  return [
    { label: 'Email', value: detail.value.email },
    { label: 'Phone', value: detail.value.phone_number || detail.value.account_phone },
    { label: 'Birthdate', value: formatBirthdate(detail.value.birthdate) },
    { label: 'ID Type', value: detail.value.id_type },
    { label: 'Address', value: detail.value.address },
    { label: 'Barangay', value: detail.value.barangay },
    { label: 'City / Province', value: [detail.value.city, detail.value.province].filter(Boolean).join(', ') },
    { label: 'ZIP Code', value: detail.value.zip_code },
  ]
})

const confirmTitle = computed(() => ({
  approve: 'Approve this verification?',
  reject: 'Reject this verification?',
  resubmit: 'Request a new submission?',
}[confirmDecision.value] || 'Confirm decision'))

const confirmText = computed(() => ({
  approve: 'The citizen will be marked verified immediately and receive a notification.',
  reject: 'The citizen will be notified that the verification was rejected and will see your reason.',
  resubmit: 'The citizen will be asked to correct the submitted information and send a new verification.',
}[confirmDecision.value] || ''))

const confirmIcon = computed(() => ({
  approve: 'lucide:shield-check', reject: 'lucide:x-circle', resubmit: 'lucide:rotate-ccw',
}[confirmDecision.value] || 'lucide:circle-help'))

const confirmIconClass = computed(() => ({
  approve: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300',
  reject: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300',
  resubmit: 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300',
}[confirmDecision.value] || 'bg-base-200 text-slate-500'))

const confirmButtonClass = computed(() => ({
  approve: 'bg-emerald-600 hover:bg-emerald-700',
  reject: 'bg-red-600 hover:bg-red-700',
  resubmit: 'bg-amber-600 hover:bg-amber-700',
}[confirmDecision.value] || 'bg-slate-700'))

async function loadQueue() {
  loading.value = true
  loadError.value = ''
  try {
    const data = await fetchKycQueue({
      status: activeStatus.value,
      search: appliedSearch.value,
      page: pagination.value.page,
      per_page: pagination.value.per_page,
    })
    items.value = data.items ?? []
    stats.value = data.stats ?? stats.value
    pagination.value = data.pagination ?? pagination.value
  } catch (err) {
    items.value = []
    loadError.value = err.response?.data?.message || 'Could not load citizen verification submissions.'
  } finally {
    loading.value = false
  }
}

function setStatus(value) {
  activeStatus.value = value
  pagination.value.page = 1
  loadQueue()
}

function applySearch() {
  appliedSearch.value = searchInput.value.trim()
  pagination.value.page = 1
  loadQueue()
}

function goPage(page) {
  pagination.value.page = Math.max(1, page)
  loadQueue()
}

function revokeImage() {
  if (imageUrl.value) URL.revokeObjectURL(imageUrl.value)
  imageUrl.value = ''
}

async function openReview(userId, updateQuery = true) {
  reviewOpen.value = true
  if (updateQuery && String(route.query.user || '') !== String(userId)) router.replace({ query: { ...route.query, user: String(userId) } })
  detailLoading.value = true
  detailError.value = ''
  detail.value = null
  decisionNote.value = ''
  confirmDecision.value = ''
  revokeImage()

  try {
    const data = await fetchKycDetail(userId)
    detail.value = data.item
    imageLoading.value = true
    try {
      const blob = await fetchKycImage(userId)
      imageUrl.value = URL.createObjectURL(blob)
    } catch {
      imageUrl.value = ''
    } finally {
      imageLoading.value = false
    }
  } catch (err) {
    detailError.value = err.response?.data?.message || 'Could not load this verification submission.'
  } finally {
    detailLoading.value = false
  }
}

function closeReview() {
  if (reviewSaving.value) return
  reviewOpen.value = false
  confirmDecision.value = ''
  revokeImage()
  if (route.query.user) {
    const query = { ...route.query }
    delete query.user
    router.replace({ query })
  }
}

function chooseDecision(action) {
  if (['reject', 'resubmit'].includes(action) && decisionNote.value.trim().length < 5) {
    toast.warning('Add a clear reason before choosing this decision.', { title: 'Review note required' })
    return
  }
  confirmDecision.value = action
}

async function saveDecision() {
  if (!detail.value || !confirmDecision.value) return
  reviewSaving.value = true
  try {
    const data = await submitKycReview({
      user_id: detail.value.user_id,
      action: confirmDecision.value,
      note: decisionNote.value.trim(),
    })
    toast.success(data.message || 'Verification decision saved.', { title: 'Review saved' })
    confirmDecision.value = ''
    reviewOpen.value = false
    revokeImage()
    if (route.query.user) { const query = { ...route.query }; delete query.user; await router.replace({ query }) }
    await loadQueue()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Unable to save the verification decision.', { title: 'Review failed' })
  } finally {
    reviewSaving.value = false
  }
}

watch(() => route.query.user, async (value) => { if (value && !reviewOpen.value) await openReview(Number(value), false) })

onMounted(async () => {
  await loadQueue()
  if (route.query.user) await openReview(Number(route.query.user), false)
})
onBeforeUnmount(revokeImage)
</script>
