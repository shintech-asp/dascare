<template>
  <!-- Identity-verification status card. Wording and colours follow the
       web's Verification.vue states: 0 unverified, 1 pending, 2 approved,
       3 rejected, 4 resubmission requested. -->
  <RouterLink v-if="status !== 2" :to="{ name: 'VerifyIdentity' }" class="tap block rounded-3xl border p-4 no-underline" :class="tone.box">
    <div class="flex items-start gap-3">
      <span class="grid h-11 w-11 flex-shrink-0 place-items-center rounded-2xl" :class="tone.tile">
        <Icon :icon="tone.icon" width="22" />
      </span>
      <div class="min-w-0 flex-1">
        <p class="text-sm font-black" :class="tone.title">{{ tone.heading }}</p>
        <p class="mt-0.5 text-xs leading-5 text-slate-600 dark:text-white/55">{{ tone.body }}</p>
        <p v-if="tone.cta" class="mt-2 inline-flex items-center gap-1 text-xs font-black" :class="tone.title">
          {{ tone.cta }} <Icon icon="lucide:chevron-right" width="14" />
        </p>
      </div>
    </div>
  </RouterLink>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: Number, default: 0 },
  // What the user is trying to reach, for the "unlock" wording.
  feature: { type: String, default: 'your request history and medical records' },
})

const tone = computed(() => {
  switch (props.status) {
    case 1:
      return {
        icon: 'lucide:hourglass', heading: 'Verification under review',
        body: 'This usually takes 24–48 hours. You can still request emergency help anytime.',
        box: 'border-yellow-200 bg-yellow-50 dark:border-yellow-500/20 dark:bg-yellow-500/10',
        tile: 'bg-yellow-100 text-yellow-600 dark:bg-yellow-500/15 dark:text-yellow-400',
        title: 'text-yellow-800 dark:text-yellow-200', cta: 'View status',
      }
    case 3:
      return {
        icon: 'lucide:x-circle', heading: 'Verification was not approved',
        body: 'See the reason and submit again to unlock ' + props.feature + '.',
        box: 'border-red-200 bg-red-50 dark:border-red-500/20 dark:bg-red-500/10',
        tile: 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400',
        title: 'text-red-800 dark:text-red-200', cta: 'Re-submit verification',
      }
    case 4:
      return {
        icon: 'lucide:rotate-ccw', heading: 'Resubmission requested',
        body: 'An administrator asked for a new submission. Fix the issue to unlock ' + props.feature + '.',
        box: 'border-amber-200 bg-amber-50 dark:border-amber-500/20 dark:bg-amber-500/10',
        tile: 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
        title: 'text-amber-800 dark:text-amber-200', cta: 'Resubmit',
      }
    default:
      return {
        icon: 'lucide:shield-alert', heading: 'Verify your identity',
        body: 'Upload a valid ID to unlock ' + props.feature + '. Emergency SOS always works, verified or not.',
        box: 'border-[#1976D2]/25 bg-[#1976D2]/5 dark:border-[#7fb3ec]/20 dark:bg-[#7fb3ec]/5',
        tile: 'bg-[#1976D2]/10 text-[#1976D2] dark:bg-[#7fb3ec]/10 dark:text-[#7fb3ec]',
        title: 'text-[#1976D2] dark:text-[#7fb3ec]', cta: 'Verify now',
      }
  }
})
</script>
