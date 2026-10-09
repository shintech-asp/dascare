<template>
  <section id="how-it-works" class="relative isolate overflow-hidden bg-base-100 transition-colors dark:bg-[#050e1a]">
   <div class="relative mx-auto max-w-7xl px-5 py-20">
    <div class="max-w-2xl">
      <div class="inline-flex items-center gap-2.5 border-l-2 border-red-600 pl-3 dark:border-red-400">
        <span class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">
          How it works
        </span>
      </div>

      <h2 class="mt-4 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl dark:text-white">
        From first report to closed case,<br class="hidden sm:block" />
        in five tracked steps.
      </h2>

      <p class="mt-4 max-w-xl text-lg leading-8 text-slate-600 dark:text-[#a9c6e8]">
        Every incident moves through the same accountable sequence, so nothing gets lost between
        a citizen's report and an ambulance's arrival.
      </p>
    </div>

    <ol class="relative mt-16 grid gap-6 lg:grid-cols-5">
      <!-- Continuous track behind the cards, with a travelling pulse peeking
           through the gaps — the route line hero's comment says it deliberately
           left out of the hero itself; here the content is an actual sequence. -->
      <div class="pointer-events-none absolute left-0 right-0 top-[2.35rem] hidden h-px bg-slate-200 lg:block dark:bg-white/10">
        <div class="route-travel absolute top-0 h-px w-16 bg-gradient-to-r from-transparent via-red-500 to-transparent"></div>
      </div>

      <li v-for="(step, i) in steps" :key="step.title" class="relative">
        <div
          class="group h-full rounded-2xl border border-base-300 bg-base-200/55 p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-red-300/70 hover:shadow-xl hover:shadow-red-600/10 dark:border-white/10 dark:bg-[#0F2A43] dark:shadow-black/30 dark:hover:shadow-red-950/30"
        >
          <div class="flex items-center justify-between">
            <span
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600 transition-colors group-hover:bg-red-100 dark:bg-red-500/10 dark:text-red-300 dark:group-hover:bg-red-500/20"
            >
              <Icon :icon="step.icon" width="17" />
            </span>
            <span class="font-mono text-xs font-bold text-slate-300 dark:text-white/20">
              {{ String(i + 1).padStart(2, '0') }}
            </span>
          </div>
          <h3 class="mt-4 font-bold text-slate-900 dark:text-white">{{ step.title }}</h3>
          <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-[#a9c6e8]">{{ step.description }}</p>
        </div>
      </li>
    </ol>
   </div>
  </section>
</template>

<script setup>
import { Icon } from '@iconify/vue'

const steps = [
  {
    title: 'Report',
    icon: 'lucide:clipboard-list',
    description: 'A citizen or guest submits an emergency request with location and the essential incident details.'
  },
  {
    title: 'Screen & match',
    icon: 'lucide:brain-circuit',
    description: 'DASCARE filters ready organizations and ambulances, then ranks suitable candidates using the DSS.'
  },
  {
    title: 'Accept & assign',
    icon: 'lucide:ambulance',
    description: 'An organization accepts the timed offer, then a dispatcher assigns a ready ambulance and available crew.'
  },
  {
    title: 'Respond & track',
    icon: 'lucide:map-pinned',
    description: 'The crew advances the mission and can share browser GPS so dispatch and the citizen can follow the ambulance.'
  },
  {
    title: 'Handoff & close',
    icon: 'lucide:handshake',
    description: 'Responders can record a limited assessment, facility endorsement, physical handoff, field report, and mission completion.'
  }
]
</script>

<style scoped>
.route-travel {
  animation: dascare-route-travel 4s ease-in-out infinite;
}

@keyframes dascare-route-travel {
  0% { left: -4rem; opacity: 0; }
  10% { opacity: 1; }
  90% { opacity: 1; }
  100% { left: 100%; opacity: 0; }
}

@media (prefers-reduced-motion: reduce) {
  .route-travel {
    animation: none;
    opacity: 0;
  }
}
</style>