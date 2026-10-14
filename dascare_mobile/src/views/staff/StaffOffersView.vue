<template>
  <!-- Offers tab (staff, Phase 4): the web's Incident Offers — DSS offers
       waiting for this organization with a live countdown, Accept (then
       assign a unit) / Decline with a reason, and recent history. -->
  <PullToRefresh :on-refresh="load">
    <div class="space-y-4 px-4 pt-2">
      <div v-if="loading && !offers.length" class="space-y-3"><div v-for="n in 2" :key="n" :class="[ui.card, 'h-40 animate-pulse']"></div></div>

      <template v-else>
        <p class="px-2 text-[0.68rem] font-black uppercase tracking-[0.16em] text-slate-400 dark:text-white/35">Waiting for your response · {{ active.length }}</p>
        <article v-for="o in active" :key="o.id" :class="[ui.card, 'overflow-hidden border-amber-200 dark:border-amber-500/20']">
          <div class="flex items-center justify-between gap-3 bg-amber-50 px-4 py-2.5 dark:bg-amber-500/10">
            <span class="flex items-center gap-1.5 text-[0.65rem] font-black uppercase tracking-wider text-amber-700 dark:text-amber-300"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span> New offer · rank {{ o.rank_position || 1 }}</span>
            <span class="font-mono text-base font-black tabular-nums" :class="secondsLeft(o) <= 60 ? 'text-red-600 dark:text-red-300' : 'text-amber-700 dark:text-amber-300'">{{ countdown(o) }}</span>
          </div>
          <div class="p-4">
            <p class="font-mono text-xs font-bold text-slate-500 dark:text-white/45">{{ o.reference_number }}</p>
            <p class="mt-0.5 text-base font-black text-slate-900 dark:text-white">{{ o.category_name || 'Emergency' }} · <span class="capitalize">{{ o.severity }}</span></p>
            <p class="mt-0.5 text-sm text-slate-600 dark:text-white/60">{{ o.address_text }}<template v-if="o.barangay">, {{ o.barangay }}</template></p>
            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
              <div class="rounded-xl bg-base-200 p-2 dark:bg-white/5"><p class="text-[0.6rem] font-bold uppercase text-slate-400">Distance</p><p class="text-sm font-black text-slate-800 dark:text-white/80">{{ o.distance_km != null ? `${Number(o.distance_km).toFixed(1)} km` : '—' }}</p></div>
              <div class="rounded-xl bg-base-200 p-2 dark:bg-white/5"><p class="text-[0.6rem] font-bold uppercase text-slate-400">Unit</p><p class="truncate text-sm font-black text-slate-800 dark:text-white/80">{{ o.recommended_unit_code || '—' }}</p></div>
              <div class="rounded-xl bg-base-200 p-2 dark:bg-white/5"><p class="text-[0.6rem] font-bold uppercase text-slate-400">DSS score</p><p class="text-sm font-black text-slate-800 dark:text-white/80">{{ o.total_score != null ? Number(o.total_score).toFixed(0) : '—' }}</p></div>
            </div>
            <p v-if="o.explanation" class="mt-2 text-xs leading-5 text-slate-500 dark:text-white/45">{{ o.explanation }}</p>
            <div v-if="canRespond" class="mt-4 grid grid-cols-[1fr_auto] gap-2">
              <button type="button" :disabled="savingId === o.id" :class="ui.primaryButton" @click="accept(o)">
                <Icon :icon="savingId === o.id ? 'lucide:loader-circle' : 'lucide:badge-check'" width="18" :class="savingId === o.id ? 'animate-spin' : ''" /> Accept
              </button>
              <button type="button" :disabled="savingId === o.id" class="tap rounded-2xl border-2 border-base-300 px-5 text-sm font-bold text-slate-600 dark:border-white/10 dark:text-white/60" @click="declining = o; declineNote = ''">Decline</button>
            </div>
            <p v-else class="mt-3 text-xs text-slate-500">You can view offers; a dispatcher or admin responds to them.</p>
          </div>
        </article>
        <div v-if="!active.length" class="rounded-3xl border border-dashed border-slate-300 py-12 text-center dark:border-white/15">
          <Icon icon="lucide:inbox" width="28" class="mx-auto mb-3 text-slate-300 dark:text-white/20" />
          <p class="text-sm font-semibold text-slate-500 dark:text-white/40">No offers right now</p>
          <p class="mt-1 text-xs text-slate-400">New offers appear here instantly, with a sound on the phone.</p>
        </div>

        <template v-if="history.length">
          <p class="px-2 pt-2 text-[0.68rem] font-black uppercase tracking-[0.16em] text-slate-400 dark:text-white/35">Recent</p>
          <section :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
            <div v-for="o in history" :key="o.id" class="flex items-center justify-between gap-3 px-4 py-3">
              <div class="min-w-0">
                <p class="font-mono text-xs font-bold text-slate-700 dark:text-white/70">{{ o.reference_number }}</p>
                <p class="truncate text-xs text-slate-500 dark:text-white/45">{{ o.barangay || 'Dasmariñas' }} · {{ formatDate(o.responded_at || o.offered_at) }}</p>
              </div>
              <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[0.6rem] font-black uppercase" :class="historyBadge(o.offer_status)">{{ o.offer_status.replace('_', ' ') }}</span>
            </div>
          </section>
        </template>
      </template>
    </div>
  </PullToRefresh>

  <!-- Decline sheet -->
  <Teleport to="body">
    <div v-if="declining" class="fixed inset-0 z-[10040] flex items-end bg-black/50" @click.self="declining = null">
      <div class="w-full rounded-t-[28px] bg-base-100 p-6 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#0c1a2c]">
        <h2 class="text-lg font-black text-slate-900 dark:text-white">Decline {{ declining.reference_number }}?</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-white/50">The offer goes to the next-ranked organization right away.</p>
        <div class="mt-3 flex flex-wrap gap-1.5">
          <button v-for="r in DECLINE_REASONS" :key="r" type="button" class="rounded-full border px-3 py-1.5 text-xs font-semibold" :class="declineNote === r ? 'border-red-600 bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'border-base-300 text-slate-600 dark:border-white/10 dark:text-white/60'" @click="declineNote = r">{{ r }}</button>
        </div>
        <textarea v-model="declineNote" rows="2" maxlength="255" placeholder="Reason (optional)" :class="[ui.input, 'mt-2']"></textarea>
        <div class="mt-4 grid grid-cols-2 gap-2">
          <button type="button" :class="ui.secondaryButton" @click="declining = null">Back</button>
          <button type="button" :disabled="savingId === declining.id" class="tap flex items-center justify-center gap-2 rounded-2xl bg-red-600 py-3.5 font-bold text-white" @click="decline">Decline</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import PullToRefresh from '@/components/PullToRefresh.vue'
import * as ui from '@/components/ui/styles'
import { apiMessage } from '@/services/api'
import { fetchOrganizationOffers, respondToIncidentOffer } from '@/services/org/dispatchOperations'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'
import { useLiveUpdates } from '@/composables/useLiveUpdates'
import { realtimeChannels } from '@/services/realtime'

const router = useRouter()
const toast = useToast()
const alert = useAlert()
const { hasPermission } = useSession()

const offers = ref([])
const canRespond = ref(false)
const loading = ref(true)
const savingId = ref(null)
const declining = ref(null)
const declineNote = ref('')
const now = ref(Date.now())
const DECLINE_REASONS = ['No unit available', 'Unit on another mission', 'Outside our coverage', 'Crew not available']

const active = computed(() => offers.value.filter((o) => o.offer_status === 'sent'))
const history = computed(() => offers.value.filter((o) => o.offer_status !== 'sent').slice(0, 15))
const secondsLeft = (o) => Math.max(0, Math.floor((new Date(String(o.expires_at).replace(' ', 'T')).getTime() - now.value) / 1000))
const countdown = (o) => { const s = secondsLeft(o); return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}` }
const formatDate = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—')
const historyBadge = (s) => ({ accepted: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', declined: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' }[s] || 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-white/45')

async function load() {
  try {
    const d = await fetchOrganizationOffers()
    offers.value = d.offers || []
    canRespond.value = !!d.can_respond
  } catch { /* keep the last list */ } finally {
    loading.value = false
  }
}

async function accept(o) {
  if (!(await alert.confirm(`Accept ${o.reference_number}? Your organization takes responsibility and assigns a unit next.`, 'Accept incident'))) return
  savingId.value = o.id
  try {
    const d = await respondToIncidentOffer(o.id, 'accept')
    toast.success(d.message || 'Incident accepted.', 'Accepted')
    await load()
    if (hasPermission('dispatch.crew_assignments.create')) router.push({ name: 'StaffAssign', query: { request: o.emergency_request_id } })
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to accept the incident.'), 'Not accepted')
    await load()
  } finally {
    savingId.value = null
  }
}

async function decline() {
  const o = declining.value
  savingId.value = o.id
  try {
    const d = await respondToIncidentOffer(o.id, 'decline', declineNote.value)
    toast.info(d.message || 'Offer declined.', 'Declined')
    declining.value = null
    await load()
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to decline the offer.'), 'Error')
  } finally {
    savingId.value = null
  }
}

// Offers arrive/expire on the org channel. When a countdown hits zero the
// list reloads (the server expires the offer and escalates it).
let lastZero = 0
const tick = setInterval(() => {
  now.value = Date.now()
  if (active.value.some((o) => secondsLeft(o) === 0) && Date.now() - lastZero > 5000) { lastZero = Date.now(); load() }
}, 1000)
onBeforeUnmount(() => clearInterval(tick))
useLiveUpdates(load, { channels: () => [realtimeChannels.value?.org, realtimeChannels.value?.user], onEvent: (msg) => (['ambulance.location', 'route.updated', 'fleet.updated'].includes(msg.name) ? false : undefined) })
onMounted(load)
</script>
