<template>
  <!-- "Start transport": choose the receiving facility first. The parent
       creates the facility handoff with these details, then moves the mission
       to transporting — and navigation / the live route now point to this
       facility. Same file in dascare/src/components/incidents/
       TransportFacilityPicker.vue (web) — keep in sync. -->
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[10040] flex items-end justify-center bg-slate-950/60 sm:items-center sm:p-4" @click.self="$emit('close')">
      <form class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-3xl bg-base-100 p-6 pb-[calc(var(--safe-bottom,0px)+1.5rem)] shadow-2xl sm:rounded-3xl dark:bg-[#0c1a2c]" @submit.prevent="confirm">
        <div class="flex items-start gap-3">
          <span class="grid h-11 w-11 flex-shrink-0 place-items-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300"><Icon icon="lucide:hospital" width="22" /></span>
          <div class="min-w-0">
            <h2 class="text-lg font-black text-slate-900 dark:text-white">Where is the patient going?</h2>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-white/50">{{ reference }} · the facility is notified (handoff) and navigation goes there.</p>
          </div>
        </div>

        <p class="mt-5 text-xs font-bold text-slate-600 dark:text-white/55">Receiving facility · nearest first</p>
        <div class="mt-2 space-y-2">
          <button v-for="f in sorted" :key="f.id" type="button" class="flex w-full items-start gap-3 rounded-2xl border-2 p-3 text-left transition"
            :class="selectedId === f.id ? 'border-emerald-600 bg-emerald-50 dark:bg-emerald-500/10' : 'border-base-300 dark:border-white/10'" @click="pick(f)">
            <span class="mt-0.5 grid h-5 w-5 flex-shrink-0 place-items-center rounded-full border-2" :class="selectedId === f.id ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-300 dark:border-white/20'"><Icon v-if="selectedId === f.id" icon="lucide:check" width="12" /></span>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-black text-slate-900 dark:text-white">{{ f.name }}</span>
              <span class="block truncate text-xs text-slate-500 dark:text-white/45">{{ f.address_text }}</span>
              <span v-if="f.capabilities" class="mt-0.5 block truncate text-[0.7rem] text-slate-400">{{ f.capabilities }}</span>
            </span>
            <span v-if="f.km != null" class="flex-shrink-0 text-right text-xs font-bold text-slate-600 dark:text-white/60">{{ f.km.toFixed(1) }} km<br><span class="font-normal text-slate-400">~{{ f.eta }} min</span></span>
          </button>
          <p v-if="!facilities.length" class="rounded-2xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">No receiving facilities are set up yet. A platform administrator adds them.</p>
        </div>

        <div class="mt-4 grid grid-cols-[7rem_1fr] gap-3">
          <label class="block"><span class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">ETA (min)</span>
            <input v-model.number="eta" type="number" min="1" max="240" class="w-full rounded-xl border border-base-300 bg-base-200/50 px-3 py-2.5 text-sm text-slate-800 focus:border-emerald-600 focus:outline-none dark:border-white/10 dark:bg-white/5 dark:text-white" />
          </label>
          <label class="block"><span class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-white/55">Notes for the facility</span>
            <input v-model="notes" maxlength="500" placeholder="Condition, special needs…" class="w-full rounded-xl border border-base-300 bg-base-200/50 px-3 py-2.5 text-sm text-slate-800 focus:border-emerald-600 focus:outline-none dark:border-white/10 dark:bg-white/5 dark:text-white" />
          </label>
        </div>

        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button type="button" class="rounded-2xl border border-base-300 px-4 py-3 text-sm font-bold text-slate-600 dark:border-white/10 dark:text-white/60" @click="$emit('close')">Back</button>
          <button type="submit" :disabled="!selectedId || busy" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-black text-white disabled:opacity-50">
            <Icon :icon="busy ? 'lucide:loader-circle' : 'lucide:ambulance'" width="17" :class="busy ? 'animate-spin' : ''" /> Start transport
          </button>
        </div>
      </form>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  facilities: { type: Array, default: () => [] }, // organizations/care/facilities.php
  from: { type: Object, default: null }, // incident { latitude, longitude } — sorts by distance
  reference: { type: String, default: '' },
  busy: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'confirm'])

const selectedId = ref(null)
const eta = ref(15)
const notes = ref('')

function km(a, b) {
  const toRad = (d) => (d * Math.PI) / 180
  const dLat = toRad(b.latitude - a.latitude), dLng = toRad(b.longitude - a.longitude)
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a.latitude)) * Math.cos(toRad(b.latitude)) * Math.sin(dLng / 2) ** 2
  return 2 * 6371 * Math.asin(Math.sqrt(h))
}
// Road ≈ 1.4 × straight line at ~30 km/h in the city (same as routing.php's estimate).
const sorted = computed(() => props.facilities.map((f) => {
  const d = props.from && f.latitude != null ? km({ latitude: Number(props.from.latitude), longitude: Number(props.from.longitude) }, { latitude: Number(f.latitude), longitude: Number(f.longitude) }) : null
  return { ...f, km: d, eta: d != null ? Math.max(3, Math.round((d * 1.4) / 30 * 60)) : null }
}).sort((a, b) => (a.km ?? 1e9) - (b.km ?? 1e9)))

function pick(f) { selectedId.value = f.id; if (f.eta) eta.value = f.eta }
function confirm() { if (selectedId.value) emit('confirm', { medical_facility_id: selectedId.value, eta_minutes: Number(eta.value) || 15, notes: notes.value.trim() }) }

watch(() => props.open, (v) => { if (v) { selectedId.value = null; notes.value = ''; eta.value = 15; if (sorted.value[0]) pick(sorted.value[0]) } })
</script>
