<template>
  <!-- Pop-up for crew: shown on any organization page as soon as this member is
       assigned to a mission that nobody has acknowledged yet. "Acknowledge"
       is the crew's accept (missions/status.php → acknowledged). -->
  <Teleport to="body">
    <div v-if="current" class="fixed inset-0 z-[10030] flex items-end justify-center bg-slate-950/60 backdrop-blur-sm sm:items-center sm:p-4">
      <div class="w-full max-w-md overflow-hidden rounded-t-3xl bg-base-100 shadow-2xl sm:rounded-3xl dark:bg-[#0c1a2c]" role="alertdialog" aria-labelledby="mission-alert-title">
        <div class="flex h-1 w-full" aria-hidden="true"><span class="siren flex-1 bg-[#1976d2]"></span><span class="siren siren-late flex-1 bg-red-600"></span></div>
        <div class="p-6">
          <div class="flex items-start gap-3">
            <span class="relative grid h-12 w-12 flex-shrink-0 place-items-center rounded-full bg-red-600 text-white shadow-lg shadow-red-600/30">
              <span class="absolute inset-0 animate-ping rounded-full bg-red-500/40"></span>
              <Icon icon="lucide:siren" width="24" class="relative" />
            </span>
            <div class="min-w-0">
              <p class="font-mono text-[0.62rem] font-bold uppercase tracking-[0.22em] text-red-600 dark:text-red-400">New mission</p>
              <h2 id="mission-alert-title" class="mt-0.5 text-lg font-black leading-tight text-slate-900 dark:text-white">You're assigned to {{ current.reference_number }}</h2>
              <p class="mt-1 text-sm text-slate-500 dark:text-white/50">Role: <span class="font-bold capitalize text-slate-700 dark:text-white/80">{{ myRole(current) }}</span> · Unit <span class="font-bold text-slate-700 dark:text-white/80">{{ current.unit_code }}</span></p>
            </div>
          </div>

          <dl class="mt-5 space-y-2 rounded-2xl bg-base-200/60 p-4 text-sm dark:bg-white/5">
            <div class="flex gap-2"><dt class="w-20 flex-shrink-0 text-xs font-bold text-slate-400">Emergency</dt><dd class="text-slate-800 dark:text-white/80">{{ current.category_name }} · <span class="capitalize">{{ current.severity }}</span></dd></div>
            <div class="flex gap-2"><dt class="w-20 flex-shrink-0 text-xs font-bold text-slate-400">Location</dt><dd class="text-slate-800 dark:text-white/80">{{ current.address_text }}<template v-if="current.barangay">, {{ current.barangay }}</template><span v-if="current.landmark" class="block text-xs text-slate-500">Near {{ current.landmark }}</span></dd></div>
            <div v-if="crewNames(current)" class="flex gap-2"><dt class="w-20 flex-shrink-0 text-xs font-bold text-slate-400">Crew</dt><dd class="text-slate-800 dark:text-white/80">{{ crewNames(current) }}</dd></div>
          </dl>

          <div class="mt-5 space-y-2">
            <button type="button" :disabled="busy" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-red-600 py-3.5 text-base font-bold text-white shadow-lg shadow-red-600/25 hover:bg-red-700 disabled:opacity-60" @click="acknowledge">
              <Icon :icon="busy ? 'lucide:loader-circle' : 'lucide:check-circle-2'" width="18" :class="busy ? 'animate-spin' : ''" /> Acknowledge mission
            </button>
            <div class="grid gap-2" :class="canOpenMissions ? 'grid-cols-2' : 'grid-cols-1'">
              <button v-if="canOpenMissions" type="button" class="rounded-2xl border border-base-300 py-3 text-sm font-bold text-slate-600 hover:bg-base-200 dark:border-white/10 dark:text-white/60" @click="openMission">View details</button>
              <button type="button" class="rounded-2xl border border-base-300 py-3 text-sm font-bold text-slate-600 hover:bg-base-200 dark:border-white/10 dark:text-white/60" @click="later">Later</button>
            </div>
          </div>
          <p v-if="error" class="mt-3 text-center text-xs font-semibold text-red-600 dark:text-red-400">{{ error }}</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Icon } from '@iconify/vue'
import { fetchMyPendingMissions, updateMissionStatus } from '@/services/dispatchOperations'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useLiveUpdates } from '@/composables/useLiveUpdates'
import { realtimeChannels } from '@/services/realtime'

const router = useRouter()
const toast = useToast()
const { hasPermission } = useSession()

const pending = ref([]) // missions waiting for MY acknowledgement
const later_ = ref(new Set()) // dismissed with "Later" (this page load only)
const busy = ref(false)
const error = ref('')

const current = computed(() => pending.value.find((m) => !later_.value.has(m.id)) || null)
const canOpenMissions = computed(() => hasPermission('dispatch.dispatch_assignments.read'))
const myRole = (m) => String(m.my_role || 'crew').replace('_', ' ')
const crewNames = (m) => (m.crew || []).map((c) => `${c.first_name} ${c.last_name}`.trim()).filter(Boolean).join(', ')

async function load() {
  try {
    pending.value = await fetchMyPendingMissions()
  } catch { /* keep the last state; the next refresh retries */ }
}

async function acknowledge() {
  const m = current.value
  if (!m) return
  busy.value = true
  error.value = ''
  try {
    await updateMissionStatus(m.id, 'acknowledged')
    toast.success(`${m.reference_number} acknowledged. Head out when ready.`, 'Mission acknowledged')
    pending.value = pending.value.filter((x) => x.id !== m.id)
  } catch (e) {
    error.value = e?.response?.data?.message || e.message || 'Unable to acknowledge. Open the mission instead.'
    await load()
  } finally {
    busy.value = false
  }
}

function openMission() {
  const m = current.value
  if (m) later_.value = new Set([...later_.value, m.id])
  router.push('/organization/missions')
}
function later() {
  if (current.value) later_.value = new Set([...later_.value, current.value.id])
}

// A short two-tone alert + vibration when a NEW mission pops up. Browsers only
// allow sound after the user has interacted with the page — fail silently.
let audio = null
function alertSound() {
  try {
    audio = audio || new (window.AudioContext || window.webkitAudioContext)()
    const t = audio.currentTime
    ;[[880, 0], [660, 0.22], [880, 0.44], [660, 0.66]].forEach(([freq, at]) => {
      const osc = audio.createOscillator(); const gain = audio.createGain()
      osc.type = 'square'; osc.frequency.value = freq
      gain.gain.setValueAtTime(0.06, t + at); gain.gain.exponentialRampToValueAtTime(0.0001, t + at + 0.2)
      osc.connect(gain).connect(audio.destination); osc.start(t + at); osc.stop(t + at + 0.21)
    })
  } catch { /* no audio */ }
  try { navigator.vibrate?.([300, 120, 300]) } catch { /* no vibration */ }
}
watch(() => current.value?.id, (id, old) => { if (id && id !== old) alertSound() })

// New assignments arrive as a notification on the member's own channel and a
// mission update on the org channel; polls every 15 s when live updates are off.
useLiveUpdates(load, {
  channels: () => [realtimeChannels.value?.user, realtimeChannels.value?.org],
  onEvent: (msg) => (['ambulance.location', 'route.updated', 'fleet.updated'].includes(msg.name) ? false : undefined),
})
onMounted(load)
</script>

<style scoped>
.siren { animation: siren 1.2s steps(1) infinite; }
.siren-late { animation-delay: 0.6s; }
@keyframes siren { 0%, 49% { opacity: 1; } 50%, 100% { opacity: 0.25; } }
</style>
