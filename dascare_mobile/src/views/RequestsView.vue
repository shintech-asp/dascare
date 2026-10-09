<template>
  <PullToRefresh :on-refresh="load">
    <div class="space-y-4 px-4 pt-4">
      <!-- Same rule as the web (router requiresKyc): the history list unlocks
           once the ID is approved. SOS and live tracking are never locked. -->
      <template v-if="!isVerified">
        <KycCard :status="kycStatus" feature="your request history" />
        <div class="rounded-3xl border border-dashed border-slate-300 px-6 py-12 text-center dark:border-white/15">
          <Icon icon="lucide:lock-keyhole" width="26" class="mx-auto text-slate-300 dark:text-white/20" />
          <p class="mt-3 text-sm font-semibold text-slate-500 dark:text-white/40">Request history is locked</p>
          <p class="mt-1 text-xs text-slate-400 dark:text-white/30">Verify your identity to see past requests here. Your active request is always on Home.</p>
        </div>
      </template>

      <template v-else>
        <!-- Quick stats + filter, as on the web's My Requests -->
        <div class="grid grid-cols-3 gap-3">
          <div :class="[ui.card, 'px-4 py-3.5']"><p class="text-xl font-bold text-slate-900 dark:text-white">{{ requests.length }}</p><p class="mt-0.5 text-[0.68rem] font-semibold text-slate-400 dark:text-white/35">Total filed</p></div>
          <div :class="[ui.card, 'px-4 py-3.5']"><p class="flex items-center gap-1.5 text-xl font-bold text-red-600 dark:text-red-300">{{ activeCount }}<span v-if="activeCount" class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500"></span></p><p class="mt-0.5 text-[0.68rem] font-semibold text-slate-400 dark:text-white/35">In progress</p></div>
          <div :class="[ui.card, 'px-4 py-3.5']"><p class="text-xl font-bold text-emerald-600 dark:text-emerald-300">{{ completedCount }}</p><p class="mt-0.5 text-[0.68rem] font-semibold text-slate-400 dark:text-white/35">Completed</p></div>
        </div>

        <div class="flex gap-1.5">
          <button v-for="tab in tabs" :key="tab.value" type="button" class="tap flex items-center gap-1.5 rounded-full px-3.5 py-2 text-xs font-bold transition-colors"
            :class="activeTab === tab.value ? 'bg-red-600 text-white' : 'border border-base-300 bg-base-100 text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-white/45'"
            @click="activeTab = tab.value">
            {{ tab.label }}
            <span class="rounded-full px-1.5 text-[0.62rem] font-bold" :class="activeTab === tab.value ? 'bg-white/20 text-white' : 'bg-base-200 text-slate-400 dark:bg-white/10 dark:text-white/40'">{{ tabCount(tab.value) }}</span>
          </button>
        </div>

        <div v-if="loading" class="flex justify-center py-16"><div class="h-8 w-8 animate-spin rounded-full border-4 border-red-600 border-t-transparent"></div></div>
        <div v-else-if="loadError" class="rounded-3xl border border-dashed border-red-200 py-14 text-center dark:border-red-500/20">
          <Icon icon="lucide:alert-triangle" width="28" class="mx-auto mb-3 text-red-400" />
          <p class="text-sm font-semibold text-slate-600 dark:text-white/50">{{ loadError }}</p>
          <button type="button" class="mt-3 py-2 text-xs font-bold text-red-600 dark:text-red-300" @click="load">Try again</button>
        </div>
        <div v-else-if="filtered.length" :class="[ui.card, 'overflow-hidden']">
          <RequestRow v-for="r in filtered" :key="r.id" :request="r" />
        </div>
        <div v-else class="rounded-3xl border border-dashed border-slate-300 py-14 text-center dark:border-white/15">
          <Icon icon="lucide:inbox" width="28" class="mx-auto mb-3 text-slate-300 dark:text-white/20" />
          <p class="text-sm font-semibold text-slate-500 dark:text-white/40">{{ requests.length ? 'No requests in this filter' : "You haven't filed any requests yet" }}</p>
          <RouterLink v-if="!requests.length" to="/sos" class="tap mt-4 inline-flex items-center gap-1.5 rounded-xl bg-red-600 px-4 py-2.5 text-xs font-bold text-white no-underline"><Icon icon="lucide:siren" width="13" /> Request help</RouterLink>
        </div>
      </template>
    </div>
  </PullToRefresh>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import KycCard from '@/components/KycCard.vue'
import PullToRefresh from '@/components/PullToRefresh.vue'
import RequestRow from '@/components/RequestRow.vue'
import * as ui from '@/components/ui/styles'
import api, { apiMessage } from '@/services/api'
import { useSession } from '@/composables/useSession'
import { useLiveRefresh } from '@/composables/useLiveRefresh'
import { followLinked, isActiveStatus } from '@/utils/requestStatus'

const { kycStatus, isVerified } = useSession()
const requests = ref([])
const loading = ref(true)
const loadError = ref('')
const activeTab = ref('all')
const tabs = [{ value: 'all', label: 'All' }, { value: 'active', label: 'Active' }, { value: 'completed', label: 'Completed' }]

const activeCount = computed(() => requests.value.filter((r) => isActiveStatus(r.status)).length)
const completedCount = computed(() => requests.value.filter((r) => r.status === 'completed').length)
const tabCount = (v) => (v === 'active' ? activeCount.value : v === 'completed' ? completedCount.value : requests.value.length)
const filtered = computed(() => (activeTab.value === 'active' ? requests.value.filter((r) => isActiveStatus(r.status))
  : activeTab.value === 'completed' ? requests.value.filter((r) => r.status === 'completed') : requests.value))

// Same endpoint as the web's My Requests (citizen/list.php).
async function load() {
  if (!isVerified.value) { loading.value = false; return }
  loadError.value = ''
  try {
    const { data } = await api.get('/citizen/list.php')
    requests.value = Array.isArray(data) ? data.map(followLinked) : []
  } catch (err) {
    loadError.value = apiMessage(err, 'Could not load your requests. Please try again.')
  } finally {
    loading.value = false
  }
}

useLiveRefresh(load)
watch(isVerified, load, { immediate: true })
</script>
