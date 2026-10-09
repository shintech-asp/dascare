<template>
  <section id="decision-support" class="relative isolate overflow-hidden bg-base-200 transition-colors dark:bg-[#050e1a]">
    <div class="relative mx-auto grid max-w-7xl gap-14 px-5 py-20 lg:grid-cols-2 lg:items-center">
      <div>
        <div class="inline-flex items-center gap-2.5 border-l-2 border-red-600 pl-3 dark:border-red-400">
          <span class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">
            Decision support
          </span>
        </div>
        <h2 class="mt-4 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl dark:text-white">
          Ambulance assignment isn't guesswork.
        </h2>
        <p class="mt-4 max-w-xl text-lg leading-8 text-slate-600 dark:text-[#a9c6e8]">
          When a report is verified, the system scores every available ambulance against the
          incident and ranks the results for the dispatcher to confirm.
        </p>

        <dl class="mt-8 space-y-5">
          <div v-for="c in criteria" :key="c.label" class="flex items-start gap-4">
            <dt class="w-28 shrink-0 font-mono text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-[#7fb3ec]">
              {{ c.label }}
            </dt>
            <dd class="text-sm leading-6 text-slate-600 dark:text-white/60">{{ c.description }}</dd>
          </div>
        </dl>

        <p class="mt-8 text-sm text-slate-500 dark:text-white/40">
          Dispatchers see the ranking and the reasoning behind it, and always make the final call.
        </p>
      </div>

      <div
        class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-2xl shadow-slate-300/40 dark:border-white/10 dark:bg-[#0F2A43] dark:shadow-black/40"
      >
        <div class="flex items-center justify-between border-b border-base-300 pb-4 dark:border-white/10">
          <div>
            <p class="font-mono text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-white/40">
              Incident DA-2291
            </p>
            <p class="mt-1 font-bold text-slate-900 dark:text-white">Barangay Zone III &middot; Severity: Critical</p>
          </div>
          <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700 dark:bg-red-500/10 dark:text-red-300">
            Live match
          </span>
        </div>

        <ul class="mt-5 space-y-4">
          <li
            v-for="(unit, i) in units"
            :key="unit.name"
            class="rounded-2xl p-4"
            :class="
              i === 0
                ? 'border-2 border-red-200 bg-red-50/40 dark:border-red-400/30 dark:bg-red-500/[0.06]'
                : 'border border-base-300 bg-base-200/35 dark:border-white/10 dark:bg-white/[.02]'
            "
          >
            <div class="flex items-center justify-between">
              <p class="font-bold text-slate-900 dark:text-white">{{ unit.name }}</p>
              <p
                class="font-mono text-sm font-bold"
                :class="i === 0 ? 'text-red-700 dark:text-red-300' : 'text-slate-500 dark:text-white/50'"
              >
                {{ unit.score }}%
              </p>
            </div>
            <div class="mt-3 space-y-1.5">
              <div v-for="f in unit.factors" :key="f.label" class="flex items-center gap-2">
                <span class="w-20 shrink-0 text-xs text-slate-400 dark:text-white/40">{{ f.label }}</span>
                <div class="h-1.5 flex-1 rounded-full bg-base-300 dark:bg-white/10">
                  <div
                    class="h-1.5 rounded-full"
                    :class="i === 0 ? 'bg-red-500 dark:bg-red-400' : 'bg-slate-400 dark:bg-[#7fb3ec]/60'"
                    :style="{ width: f.value + '%' }"
                  ></div>
                </div>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>

<script setup>
// Public illustrative example. Operational DSS recommendations are generated
// from live organization, fleet-readiness, distance, and workload data in the
// authenticated Platform/Organization workspaces.
const criteria = [
  { label: 'Distance', description: "Estimated travel time from the ambulance's current position to the incident." },
  { label: 'Availability', description: 'Whether the unit is free, already en route, or currently out of service.' },
  { label: 'Capability', description: 'Whether the unit and crew match what the case requires, from basic to advanced care.' },
  { label: 'Workload', description: 'Current organization workload so the ranking does not repeatedly favor a busy provider.' }
]

const units = [
  {
    name: 'Ambulance 04 · Basic',
    score: 92,
    factors: [
      { label: 'Distance', value: 95 },
      { label: 'Availability', value: 100 },
      { label: 'Capability', value: 85 },
      { label: 'Workload', value: 90 }
    ]
  },
  {
    name: 'Ambulance 11 · Basic',
    score: 78,
    factors: [
      { label: 'Distance', value: 70 },
      { label: 'Availability', value: 100 },
      { label: 'Capability', value: 80 },
      { label: 'Workload', value: 62 }
    ]
  },
  {
    name: 'Ambulance 02 · Advanced',
    score: 65,
    factors: [
      { label: 'Distance', value: 40 },
      { label: 'Availability', value: 100 },
      { label: 'Capability', value: 95 },
      { label: 'Workload', value: 90 }
    ]
  }
]
</script>