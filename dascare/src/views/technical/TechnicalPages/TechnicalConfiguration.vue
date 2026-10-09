<template>
  <PageShell title="System Configuration" subtitle="Maintain non-secret operational settings that are safe to expose through the Technical Admin workspace." icon="lucide:sliders-horizontal" eyebrow="Technical Super Admin">
    <div class="max-w-4xl space-y-4">
      <!-- Duplicate-request detection (reusables/dispatch_dedup.php) -->
      <article class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829]">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="font-mono text-xs font-black text-red-600">dispatch.dedup.*</p>
            <h2 class="mt-1 text-base font-black text-slate-900 dark:text-white">Duplicate-request detection</h2>
            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-white/45">When several people report the same emergency, later reports inside the radius and time window are linked to the first one instead of dispatching another ambulance.</p>
          </div>
          <label class="flex cursor-pointer items-center gap-2 rounded-xl bg-base-200 px-3 py-2 text-xs font-black text-slate-600 dark:bg-white/5 dark:text-white/60">
            <input v-model="dedup.enabled" type="checkbox" class="toggle toggle-sm border-slate-300 bg-slate-200 text-red-600 [--tglbg:white] checked:border-red-600 checked:bg-red-600" />
            {{ dedup.enabled ? 'Enabled' : 'Disabled' }}
          </label>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-3" :class="dedup.enabled ? '' : 'opacity-50'">
          <label v-for="f in dedupFields" :key="f.key" class="rounded-2xl bg-base-200 p-4 dark:bg-white/[0.03]">
            <span class="text-xs font-black uppercase text-slate-400">{{ f.label }}</span>
            <div class="mt-2 flex items-center gap-2">
              <input v-model.number="dedup[f.key]" type="number" :min="limits[f.key]?.[0]" :max="limits[f.key]?.[1]" :step="f.step" :disabled="!dedup.enabled"
                class="w-full rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 text-sm font-black dark:bg-white/5 dark:text-white" />
              <span class="text-xs font-bold text-slate-400">{{ f.unit }}</span>
            </div>
            <p class="mt-2 text-[.65rem] leading-4 text-slate-400">{{ f.help }} <span class="whitespace-nowrap">({{ limits[f.key]?.[0] }}–{{ limits[f.key]?.[1] }})</span></p>
          </label>
        </div>

        <div class="mt-4 rounded-2xl border border-base-300 p-4 text-xs leading-5 text-slate-500 dark:border-white/10 dark:text-white/45">
          <template v-if="dedup.enabled">
            A new report is linked when it is within <strong class="text-slate-800 dark:text-white/80">{{ dedup.radius_meters }} m</strong> of an active report made in the last <strong class="text-slate-800 dark:text-white/80">{{ windowLabel }}</strong>,
            <template v-if="dedup.min_requests <= 2">starting from the second report.</template>
            <template v-else>once <strong class="text-slate-800 dark:text-white/80">{{ dedup.min_requests }}</strong> reports cluster there — the first {{ dedup.min_requests - 1 }} each get their own unit.</template>
          </template>
          <template v-else>Every report gets its own DSS cycle and unit. No reports are linked.</template>
        </div>

        <div class="mt-4 flex justify-end">
          <button class="rounded-xl bg-red-600 px-5 py-3 text-xs font-black text-white disabled:opacity-50" :disabled="savingDedup" @click="saveDedup">
            {{ savingDedup ? 'Saving...' : 'Save duplicate detection' }}
          </button>
        </div>
      </article>

      <!-- Live updates (reusables/realtime.php, services/realtime.js) -->
      <article class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829]">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="font-mono text-xs font-black text-red-600">realtime.*</p>
            <h2 class="mt-1 text-base font-black text-slate-900 dark:text-white">Live updates</h2>
            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-white/45">Open screens update the moment something changes (via Ably) instead of waiting for the next 15-second refresh. If it is off or unavailable, screens simply keep refreshing on their own.</p>
          </div>
          <label class="flex items-center gap-2 rounded-xl bg-base-200 px-3 py-2 text-xs font-black text-slate-600 dark:bg-white/5 dark:text-white/60" :class="savingRealtime ? 'opacity-60' : 'cursor-pointer'">
            <input :checked="realtime.enabled" type="checkbox" :disabled="savingRealtime" class="toggle toggle-sm border-slate-300 bg-slate-200 text-red-600 [--tglbg:white] checked:border-red-600 checked:bg-red-600" @change="toggleRealtime($event.target.checked)" />
            {{ realtime.enabled ? 'Enabled' : 'Disabled' }}
          </label>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-3">
          <div class="rounded-2xl bg-base-200 p-4 dark:bg-white/[0.03]">
            <span class="text-xs font-black uppercase text-slate-400">Ably key</span>
            <p class="mt-2 flex items-center gap-2 text-sm font-black" :class="realtime.configured ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300'">
              <Icon :icon="realtime.configured ? 'lucide:circle-check-big' : 'lucide:circle-alert'" width="16" />
              {{ realtime.configured ? 'Configured' : 'Not set up' }}
            </p>
            <p class="mt-2 break-all font-mono text-[.65rem] leading-4 text-slate-400">{{ realtime.configured ? realtime.key_name : 'dascare_api/config/ably.json' }}</p>
          </div>
          <div class="rounded-2xl bg-base-200 p-4 dark:bg-white/[0.03]">
            <span class="text-xs font-black uppercase text-slate-400">This browser</span>
            <p class="mt-2 flex items-center gap-2 text-sm font-black capitalize" :class="realtimeState === 'connected' ? 'text-emerald-600 dark:text-emerald-300' : 'text-slate-600 dark:text-white/60'">
              <span class="h-2 w-2 rounded-full" :class="realtimeState === 'connected' ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-white/20'"></span>
              {{ stateLabel }}
            </p>
            <p class="mt-2 text-[.65rem] leading-4 text-slate-400">Connection to the live updates service.</p>
          </div>
          <div class="rounded-2xl bg-base-200 p-4 dark:bg-white/[0.03]">
            <span class="text-xs font-black uppercase text-slate-400">Round trip</span>
            <p class="mt-2 text-sm font-black" :class="testResult ? (testResult.ok ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300') : 'text-slate-600 dark:text-white/60'">
              {{ testResult ? (testResult.ok ? `${testResult.ms} ms` : 'Failed') : '—' }}
            </p>
            <p class="mt-2 text-[.65rem] leading-4 text-slate-400">{{ testResult?.message || 'Server publishes a test event; time until this browser receives it.' }}</p>
          </div>
        </div>

        <div v-if="!realtime.configured" class="mt-4 rounded-2xl border border-base-300 p-4 text-xs leading-5 text-slate-500 dark:border-white/10 dark:text-white/45">
          To set it up, create a free Ably app, then save its API key as <span class="font-mono text-slate-700 dark:text-white/70">{"key": "…"}</span> in <span class="font-mono text-slate-700 dark:text-white/70">dascare_api/config/ably.json</span> on the server. The key is never shown here or sent to browsers.
        </div>

        <div class="mt-4 flex justify-end">
          <button class="rounded-xl bg-red-600 px-5 py-3 text-xs font-black text-white disabled:opacity-50" :disabled="testing || !realtime.configured || !realtime.enabled" @click="testRealtime">
            {{ testing ? 'Testing...' : 'Send test event' }}
          </button>
        </div>
      </article>

      <article v-for="item in otherItems" :key="item.key" class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829]">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="font-mono text-xs font-black text-red-600">{{ item.key }}</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-white/45">{{ item.description }}</p>
          </div>
          <span class="text-[.65rem] font-bold text-slate-400">Updated {{ format(item.updated_at) }}</span>
        </div>
        <div class="mt-4 flex gap-2">
          <input v-model="drafts[item.key]" class="min-w-0 flex-1 rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 font-mono text-xs dark:bg-white/5 dark:text-white" />
          <button class="rounded-xl bg-red-600 px-4 text-xs font-black text-white" @click="save(item.key)">Save</button>
        </div>
        <p class="mt-2 text-[.65rem] text-slate-400">Use valid JSON for objects/booleans/numbers. Plain text is stored as a string.</p>
      </article>
      <p v-if="!otherItems.length" class="rounded-3xl border border-base-300 bg-base-100 p-10 text-center text-sm text-slate-400">No editable technical settings were found.</p>
    </div>
  </PageShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import PageShell from '@/components/management/PageShell.vue'
import { fetchTechnicalConfiguration, saveTechnicalConfiguration, saveTechnicalConfigurationBatch } from '@/services/adminCompletion'
import { useToast } from '@/composables/useToast'
import { Icon } from '@iconify/vue'
import { realtimeState, resetRealtime, runRealtimeTest } from '@/services/realtime'

const toast = useToast(), items = ref([]), drafts = ref({})
const format = v => v ? new Date(String(v).replace(' ', 'T')).toLocaleString() : '—'
function encode(v) { return typeof v === 'string' ? v : JSON.stringify(v) }
function decode(v) { try { return JSON.parse(v) } catch { return v } }

// --- Duplicate-request detection: structured editor for the dispatch.dedup.* keys ---
const DEDUP_KEYS = { enabled: 'dispatch.dedup.enabled', radius_meters: 'dispatch.dedup.radius_meters', window_seconds: 'dispatch.dedup.window_seconds', min_requests: 'dispatch.dedup.min_requests' }
const dedupFields = [
  { key: 'radius_meters', label: 'Radius', unit: 'm', step: 25, help: 'How close another report must be to count as the same emergency.' },
  { key: 'window_seconds', label: 'Time window', unit: 'sec', step: 30, help: 'Measured from the first report. Once it passes, new reports are dispatched separately.' },
  { key: 'min_requests', label: 'Minimum reports', unit: 'reports', step: 1, help: 'Reports within the radius needed before linking starts. 2 links every duplicate.' },
]
const dedup = ref({ enabled: true, radius_meters: 150, window_seconds: 300, min_requests: 2 })
const limits = ref({ radius_meters: [25, 2000], window_seconds: [60, 3600], min_requests: [2, 20] })
const savingDedup = ref(false)
const otherItems = computed(() => items.value.filter(i => !Object.values(DEDUP_KEYS).includes(i.key) && i.key !== REALTIME_KEY))

// --- Live updates (Ably) ---
const REALTIME_KEY = 'realtime.enabled'
const realtime = ref({ configured: false, enabled: true, key_name: null })
const savingRealtime = ref(false), testing = ref(false), testResult = ref(null)
const stateLabel = computed(() => ({ idle: 'Not connected', disabled: 'Off', initialized: 'Starting', connecting: 'Connecting', connected: 'Connected', disconnected: 'Reconnecting', suspended: 'Offline', closing: 'Closing', closed: 'Closed', failed: 'Failed' })[realtimeState.value] || realtimeState.value)
async function toggleRealtime(enabled) {
  savingRealtime.value = true
  try {
    const d = await saveTechnicalConfiguration(REALTIME_KEY, enabled)
    toast.success(d.message)
    testResult.value = null
    resetRealtime() // reconnect (or stay off) with the new setting
    await load()
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Unable to save live updates setting.')
  } finally {
    savingRealtime.value = false
  }
}
async function testRealtime() {
  testing.value = true
  testResult.value = null
  try { testResult.value = await runRealtimeTest() } finally { testing.value = false }
}
const windowLabel = computed(() => {
  const s = Number(dedup.value.window_seconds) || 0
  return s % 60 === 0 ? `${s / 60} min` : `${Math.floor(s / 60)} min ${s % 60} sec`
})

async function load() {
  const d = await fetchTechnicalConfiguration()
  items.value = d.items || []
  for (const i of items.value) drafts.value[i.key] = encode(i.value)
  if (d.dedup) dedup.value = { ...d.dedup }
  if (d.dedup_limits) limits.value = d.dedup_limits
  if (d.realtime) realtime.value = d.realtime
}
async function save(key) {
  try { const d = await saveTechnicalConfiguration(key, decode(drafts.value[key])); toast.success(d.message); await load() }
  catch (e) { toast.error(e?.response?.data?.message || 'Unable to save configuration.') }
}
async function saveDedup() {
  savingDedup.value = true
  try {
    const d = await saveTechnicalConfigurationBatch(Object.entries(DEDUP_KEYS).map(([field, key]) => ({ key, value: dedup.value[field] })))
    toast.success(d.message)
    await load()
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Unable to save duplicate detection settings.')
  } finally {
    savingDedup.value = false
  }
}
onMounted(load)
</script>
