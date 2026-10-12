<template>
  <!-- Close an emergency request before it completes.
       requester: "Cancel request" with optional quick reasons.
       organization / platform: choose the outcome (cancelled, false alarm,
       rejected) and give a reason (required). -->
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[10040] flex items-end justify-center bg-slate-950/60 p-0 backdrop-blur-sm sm:items-center sm:p-4" @click.self="$emit('close')">
      <div class="w-full max-w-md rounded-t-3xl bg-base-100 p-6 pb-[calc(var(--safe-bottom)+1.5rem)] shadow-2xl sm:rounded-3xl dark:bg-[#0c1a2c]">
        <div class="flex items-start gap-3">
          <span class="grid h-11 w-11 flex-shrink-0 place-items-center rounded-full bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300">
            <Icon icon="lucide:octagon-x" width="22" />
          </span>
          <div class="min-w-0">
            <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ isRequester ? 'Cancel this request?' : 'Close this incident?' }}</h2>
            <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-white/50">
              <span class="font-mono font-bold">{{ reference }}</span>
              <template v-if="isRequester"> — only if you no longer need an ambulance. Any unit on the way is called back.</template>
              <template v-else> — pending offers are withdrawn and any assigned unit and crew are released.</template>
            </p>
          </div>
        </div>

        <!-- Staff: outcome -->
        <div v-if="!isRequester" class="mt-5 grid gap-2" :class="outcomes.length === 3 ? 'grid-cols-3' : 'grid-cols-2'">
          <button v-for="o in outcomes" :key="o.value" type="button" @click="status = o.value"
            class="rounded-xl border-2 px-2 py-2.5 text-xs font-black transition-colors"
            :class="status === o.value ? 'border-red-600 bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'border-base-300 text-slate-600 hover:border-red-200 dark:border-white/10 dark:text-white/60'">
            <Icon :icon="o.icon" width="16" class="mx-auto mb-1" />{{ o.label }}
          </button>
        </div>

        <p class="mt-5 text-xs font-bold text-slate-600 dark:text-white/55">{{ isRequester ? 'Reason (optional)' : 'Reason' }}</p>
        <div class="mt-2 flex flex-wrap gap-1.5">
          <button v-for="r in presets" :key="r" type="button" @click="reason = r"
            class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
            :class="reason === r ? 'border-red-600 bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'border-base-300 text-slate-600 hover:border-red-200 dark:border-white/10 dark:text-white/60'">
            {{ r }}
          </button>
        </div>
        <textarea v-model="reason" rows="2" maxlength="255" :placeholder="isRequester ? 'Anything dispatch should know (optional)' : 'Why is this incident being closed?'"
          class="mt-2 w-full rounded-xl border border-base-300 bg-base-200/50 px-3 py-2.5 text-sm text-slate-800 focus:border-red-600 focus:outline-none dark:border-white/10 dark:bg-white/5 dark:text-white"></textarea>

        <p v-if="linkedCount && !isRequester" class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
          {{ linkedCount }} linked {{ linkedCount === 1 ? 'report is' : 'reports are' }} closed with it.
        </p>
        <p v-if="linkedCount && isRequester" class="mt-2 rounded-xl bg-slate-100 px-3 py-2 text-xs text-slate-600 dark:bg-white/5 dark:text-white/55">
          Others reported this emergency too — their reports will be dispatched on their own.
        </p>

        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button type="button" class="rounded-xl border border-base-300 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-base-200 dark:border-white/10 dark:text-white/60" @click="$emit('close')">
            {{ isRequester ? 'Keep request' : 'Back' }}
          </button>
          <button type="button" :disabled="busy || (!isRequester && !reason.trim())"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
            @click="$emit('confirm', { status, reason: reason.trim() })">
            <Icon v-if="busy" icon="lucide:loader-circle" width="16" class="animate-spin" />
            {{ isRequester ? 'Cancel request' : confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  mode: { type: String, default: 'requester' }, // requester | organization | platform
  reference: { type: String, default: '' },
  linkedCount: { type: Number, default: 0 },
  busy: { type: Boolean, default: false },
})
defineEmits(['close', 'confirm'])

const isRequester = computed(() => props.mode === 'requester')
const outcomes = computed(() => [
  { value: 'cancelled', label: 'Cancelled', icon: 'lucide:circle-x' },
  { value: 'false_alarm', label: 'False alarm', icon: 'lucide:shield-alert' },
  ...(props.mode === 'platform' ? [{ value: 'rejected', label: 'Rejected', icon: 'lucide:ban' }] : []),
])
const PRESETS = {
  requester: ['No longer needed', 'Sent by mistake', 'Got help another way', 'Patient is okay now'],
  cancelled: ['Requester cancelled by phone', 'Patient taken by private vehicle', 'Handled by another unit'],
  false_alarm: ['No emergency found on scene', 'Caller confirmed no emergency', 'Prank or test report'],
  rejected: ['Outside Dasmariñas coverage', 'Not an emergency request', 'Abusive or spam report'],
}
const status = ref('cancelled')
const reason = ref('')
const presets = computed(() => (isRequester.value ? PRESETS.requester : PRESETS[status.value]))
const confirmLabel = computed(() => ({ cancelled: 'Cancel incident', false_alarm: 'Mark false alarm', rejected: 'Reject request' })[status.value])

watch(() => props.open, (v) => { if (v) { status.value = 'cancelled'; reason.value = '' } })
</script>
