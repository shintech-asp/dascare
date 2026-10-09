<template>
  <section>
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="flex items-center gap-2 text-sm font-black text-slate-900 dark:text-white">
        <Icon icon="lucide:git-merge" width="16" class="text-red-600 dark:text-red-300" />
        Linked reports
        <span class="rounded-full bg-red-50 px-2 py-0.5 text-[0.65rem] font-black text-red-600 dark:bg-red-500/10 dark:text-red-300">{{ reports.length }}</span>
      </h3>
      <span class="text-xs text-slate-400">Same emergency reported by other people — one unit covers all</span>
    </div>
    <p v-if="flaggedCount" class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
      <Icon icon="lucide:triangle-alert" width="13" class="mr-1 inline" />
      {{ flaggedCount }} linked report{{ flaggedCount === 1 ? '' : 's' }} {{ flaggedCount === 1 ? 'has' : 'have' }} signs it may be a different emergency. Check before treating it as the same patient.
    </p>

    <div class="mt-3 space-y-3">
      <article v-for="r in reports" :key="r.id" class="rounded-2xl border p-4" :class="r.warnings.length ? 'border-amber-200 dark:border-amber-500/25' : 'border-base-300 dark:border-white/10'">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-black text-slate-900 dark:text-white">{{ r.reference_number }}</p>
              <span class="rounded-full bg-base-200 px-2 py-0.5 text-[0.62rem] font-black uppercase text-slate-500 dark:bg-white/5 dark:text-white/45">{{ r.source === 'guest' ? 'Guest' : 'Citizen' }}</span>
            </div>
            <p class="mt-1 text-xs text-slate-500 dark:text-white/45">
              <Icon icon="lucide:map-pin" width="12" class="mr-0.5 inline" />{{ r.distance_m }} m from the first report
              <span class="mx-1 text-slate-300 dark:text-white/20">·</span>
              <Icon icon="lucide:clock" width="12" class="mr-0.5 inline" />{{ humanGap(r.gap_seconds) }} later
            </p>
          </div>
          <button v-if="canUnmerge && confirmingId !== r.id" type="button" :disabled="busyId === r.id"
            class="inline-flex w-fit items-center gap-1.5 rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-base-200 disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.03] dark:text-white/65"
            @click="startConfirm(r)">
            <Icon icon="lucide:split" width="14" /> Unlink
          </button>
        </div>

        <div class="mt-3 flex flex-wrap gap-1.5">
          <span v-for="w in r.warnings" :key="w.key" :title="w.detail"
            class="rounded-full bg-amber-100 px-2.5 py-1 text-[0.65rem] font-black uppercase text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
            {{ w.label }}
          </span>
          <span v-if="!r.warnings.length" class="rounded-full bg-emerald-50 px-2.5 py-1 text-[0.65rem] font-black uppercase text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">Consistent with first report</span>
          <span v-if="r.photo_count" class="rounded-full bg-base-200 px-2.5 py-1 text-[0.65rem] font-black uppercase text-slate-500 dark:bg-white/5 dark:text-white/45">
            <Icon icon="lucide:image" width="11" class="mr-0.5 inline" />{{ r.photo_count }} photo{{ r.photo_count === 1 ? '' : 's' }}
          </span>
        </div>
        <ul v-if="r.warnings.length" class="mt-2 space-y-0.5 text-[0.7rem] text-amber-700/90 dark:text-amber-200/70">
          <li v-for="w in r.warnings" :key="w.key">• {{ w.detail }}</li>
        </ul>

        <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
          <div><dt class="text-slate-400">Requester</dt><dd class="font-bold text-slate-700 dark:text-white/65">{{ r.requester_name || 'Guest requester' }}<span v-if="r.requester_phone" class="font-semibold text-slate-500 dark:text-white/45"> · {{ r.requester_phone }}</span></dd></div>
          <div><dt class="text-slate-400">Location</dt><dd class="font-bold text-slate-700 dark:text-white/65">{{ r.address_text }}<span v-if="r.landmark" class="block font-semibold text-slate-400">Near {{ r.landmark }}</span></dd></div>
          <div class="sm:col-span-2"><dt class="text-slate-400">Description</dt><dd class="whitespace-pre-line text-slate-600 dark:text-white/55">{{ r.description }}</dd></div>
        </dl>

        <div v-if="confirmingId === r.id" class="mt-3 rounded-xl border border-red-200 bg-red-50/70 p-3 dark:border-red-500/20 dark:bg-red-500/10">
          <p class="text-xs font-black text-red-800 dark:text-red-200">Unlink {{ r.reference_number }}?</p>
          <p class="mt-1 text-[0.7rem] text-red-700/80 dark:text-red-200/60">It becomes its own incident and DSS screens a separate unit for it. Use this when the report turns out to be a different emergency.</p>
          <textarea v-model.trim="reason" rows="2" maxlength="255" placeholder="Reason (optional) — e.g. crew found only one patient; this report mentions a second car."
            class="mt-2 w-full rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-xs text-slate-800 outline-none focus:border-red-400 dark:border-white/10 dark:bg-[#071829] dark:text-white"></textarea>
          <div class="mt-2 flex justify-end gap-2">
            <button type="button" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-base-200 dark:border-white/10 dark:text-white/60" @click="confirmingId = null">Cancel</button>
            <button type="button" :disabled="busyId === r.id" class="inline-flex items-center gap-1.5 rounded-xl bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-700 disabled:opacity-50" @click="confirm(r)">
              <Icon :icon="busyId === r.id ? 'lucide:loader-circle' : 'lucide:split'" width="14" :class="busyId === r.id ? 'animate-spin' : ''" />
              {{ busyId === r.id ? 'Unlinking…' : 'Unlink and dispatch separately' }}
            </button>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup>
// Reports dedup linked to one incident (reusables/dispatch_dedup.php →
// dedupLinkedReports), with the warning badges reviewers use to spot a wrong
// merge. The parent owns the API call: it receives `unmerge` with
// { report, reason } and passes `busyId` back while it runs.
import { computed, ref } from 'vue'
import { Icon } from '@iconify/vue'

const props = defineProps({
  reports: { type: Array, default: () => [] },
  canUnmerge: { type: Boolean, default: false },
  busyId: { type: Number, default: null },
})
const emit = defineEmits(['unmerge'])

const confirmingId = ref(null)
const reason = ref('')
const flaggedCount = computed(() => props.reports.filter(r => r.warnings?.length).length)

function startConfirm(r) {
  confirmingId.value = r.id
  reason.value = ''
}
function confirm(r) {
  emit('unmerge', { report: r, reason: reason.value })
}
function humanGap(s) {
  s = Number(s) || 0
  if (s < 60) return `${s} sec`
  const m = Math.floor(s / 60), rest = s % 60
  return rest ? `${m} min ${rest} sec` : `${m} min`
}
</script>
