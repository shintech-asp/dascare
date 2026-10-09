<template>
  <div class="min-h-screen bg-base-200 dark:bg-[#050e1a]">
  <ScreenHeader title="Server connection" />
  <main class="flex flex-col px-5 pb-[calc(var(--safe-bottom)+1.5rem)]">

    <!-- Phase 0 check: can the app reach the PHP API? -->
    <section class="relative mt-4 overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-6 shadow-sm dark:border-white/10 dark:bg-[#071829]">
      <div class="pointer-events-none absolute -right-24 -top-24 h-64 w-64 rounded-full bg-red-500/10 blur-3xl"></div>

      <p class="relative text-[0.68rem] font-black uppercase tracking-[0.16em] text-red-600 dark:text-red-300">Setup check</p>
      <h1 class="relative mt-1 text-2xl font-black tracking-tight text-slate-950 dark:text-white">DASCARE API connection</h1>

      <div class="relative mt-6 flex items-center gap-4">
        <span class="grid h-14 w-14 flex-shrink-0 place-items-center rounded-2xl" :class="statusTile">
          <Icon :icon="statusIcon" width="26" :class="state === 'checking' ? 'animate-spin' : ''" />
        </span>
        <div class="min-w-0">
          <p class="text-base font-black" :class="statusText">{{ headline }}</p>
          <p class="mt-0.5 text-xs text-slate-500 dark:text-white/45">{{ detail }}</p>
        </div>
      </div>

      <dl class="relative mt-6 space-y-2 border-t border-base-300 pt-4 text-xs dark:border-white/10">
        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-400">API address</dt><dd class="selectable break-all text-right font-mono text-slate-600 dark:text-white/60">{{ API_BASE_URL }}</dd></div>
        <div v-if="latencyMs != null" class="flex justify-between gap-4"><dt class="font-bold text-slate-400">Response time</dt><dd class="font-semibold text-slate-600 dark:text-white/60">{{ latencyMs }} ms</dd></div>
        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-400">Running on</dt><dd class="font-semibold text-slate-600 dark:text-white/60">{{ platformLabel }}</dd></div>
      </dl>

      <button type="button" :disabled="state === 'checking'" class="tap relative mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60" @click="check">
        <Icon icon="lucide:refresh-cw" width="16" :class="state === 'checking' ? 'animate-spin' : ''" /> Check again
      </button>
    </section>

    <section v-if="state === 'failed'" class="mt-4 rounded-3xl border border-amber-200 bg-amber-50 p-5 text-xs leading-5 text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
      <p class="font-black">Can't reach the API? Check:</p>
      <ul class="mt-2 list-disc space-y-1 pl-4">
        <li>XAMPP Apache and MySQL are running on your Mac.</li>
        <li>Emulator: <span class="font-mono">.env</span> uses <span class="font-mono">http://10.0.2.2/…</span></li>
        <li>Real phone: same Wi-Fi as the Mac, and <span class="font-mono">.env</span> uses the Mac's Wi-Fi IP.</li>
        <li>After editing <span class="font-mono">.env</span>, run <span class="font-mono">npm run sync</span> and press Run again.</li>
      </ul>
    </section>

  </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Capacitor } from '@capacitor/core'
import api, { API_BASE_URL } from '@/services/api'
import ScreenHeader from '@/components/ScreenHeader.vue'

const state = ref('checking') // checking | ok | failed
const latencyMs = ref(null)
const errorText = ref('')
const platformLabel = Capacitor.isNativePlatform() ? `Android app (${Capacitor.getPlatform()})` : 'Browser preview'

// Read-only public endpoint that already exists; proves the app can reach
// XAMPP and that CORS lets it through. No backend changes needed for this.
async function check() {
  state.value = 'checking'
  latencyMs.value = null
  errorText.value = ''
  const started = performance.now()
  try {
    const { data } = await api.get('/citizen/guest_status.php')
    if (!data?.success) throw new Error('Unexpected response from the API.')
    latencyMs.value = Math.round(performance.now() - started)
    state.value = 'ok'
  } catch (err) {
    errorText.value = err.response ? `HTTP ${err.response.status}` : (err.message || 'Network error')
    state.value = 'failed'
  }
}

const headline = computed(() => ({ checking: 'Checking…', ok: 'Connected to DASCARE API', failed: 'Not connected' })[state.value])
const detail = computed(() => ({
  checking: 'Contacting the server.',
  ok: 'The app can reach your XAMPP server.',
  failed: errorText.value,
})[state.value])
const statusIcon = computed(() => ({ checking: 'lucide:loader-circle', ok: 'lucide:circle-check-big', failed: 'lucide:wifi-off' })[state.value])
const statusTile = computed(() => ({
  checking: 'bg-base-200 text-slate-400 dark:bg-white/5 dark:text-white/40',
  ok: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300',
  failed: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300',
})[state.value])
const statusText = computed(() => ({
  checking: 'text-slate-700 dark:text-white/70',
  ok: 'text-emerald-700 dark:text-emerald-300',
  failed: 'text-red-700 dark:text-red-300',
})[state.value])

onMounted(check)
</script>
