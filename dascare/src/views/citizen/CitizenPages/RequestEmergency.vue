<!--
  citizen/CitizenPages/RequestEmergency.vue

  Thin shell over the single merged request form. The old Instant/Standard
  split is gone — there's one form now (EmergencyRequestForm.vue), minimal by
  default with an optional details section — so this file just owns the page
  chrome (header, disclaimer) and where to navigate after a successful submit.
  A future guest route can reuse EmergencyRequestForm.vue directly the same way.
-->
<template>
  <section class="min-h-screen bg-base-200 dark:bg-[#050e1a] pb-10">

    <!-- ================= Header ================= -->
    <section class="bg-base-100 dark:bg-[#071829] border-b border-base-300 dark:border-white/10 relative overflow-hidden">
      <div
        class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-30"
        style="background: radial-gradient(ellipse at top left, rgba(220,38,38,0.06), transparent 55%);"
      ></div>
      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 relative">
        <span class="inline-flex items-center gap-2 rounded-full bg-red-50 dark:bg-red-500/10 px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.15em] text-red-700 dark:text-red-300">
          <span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>
          New Request
        </span>
        <h1 class="mt-2 text-xl sm:text-2xl font-bold text-slate-900 dark:text-white">Request Emergency Assistance</h1>
        <p class="text-sm text-slate-500 dark:text-white/45 mt-1 max-w-2xl">
          A dispatcher reviews every request and assigns the ambulance and crew — this doesn't page an
          ambulance automatically.
        </p>
      </div>
    </section>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">

      <div class="rounded-2xl border border-amber-200 dark:border-amber-500/20 bg-amber-50 dark:bg-amber-500/10 px-4 py-3 mb-6 flex items-start gap-3">
        <Icon icon="lucide:alert-triangle" width="18" class="text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
        <p class="text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
          For an immediately life-threatening emergency, also call your official local emergency hotline directly.
          DASCARE coordinates ambulance response — it does not replace public emergency services and cannot provide
          medical diagnosis.
        </p>
      </div>

      <div class="max-w-xl mx-auto">
        <EmergencyRequestForm @submitted="onSubmitted" />
      </div>
    </div>
  </section>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { Icon } from '@iconify/vue'
import EmergencyRequestForm from '@/components/emergency-request/EmergencyRequestForm.vue'

const router = useRouter()

// The form component shows its own success/error alerts; this just owns the
// one thing specific to the citizen dashboard route — where to go afterward.
function onSubmitted(result) {
  if (result?.id) {
    router.push(`/citizen/requests/${result.id}`)
  }
}
</script>
