<template>
  <section id="mobile-app" class="relative isolate overflow-hidden bg-base-200 transition-colors dark:bg-[#050e1a]">
    <div class="relative mx-auto grid max-w-7xl gap-14 px-5 py-20 lg:grid-cols-2 lg:items-center">
      <div class="order-2 lg:order-1">
        <div class="inline-flex items-center gap-2.5 border-l-2 border-red-600 pl-3 dark:border-red-400">
          <span class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">
            Mobile-ready rescue experience
          </span>
        </div>

        <h2 class="mt-4 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl dark:text-white">
          The rescue workflow is ready for mobile delivery.
        </h2>

        <p class="mt-4 max-w-xl text-lg leading-8 text-slate-600 dark:text-[#a9c6e8]">
          The current responsive web experience already supports the citizen and responder workflow. A packaged mobile client can reuse these stable APIs and flows for deployment.
        </p>

        <ul class="mt-8 space-y-4">
          <li v-for="f in features" :key="f.title" class="flex gap-3">
            <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-red-600 dark:bg-red-400"></span>
            <div>
              <p class="font-bold text-slate-900 dark:text-white">{{ f.title }}</p>
              <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-[#a9c6e8]">{{ f.description }}</p>
            </div>
          </li>
        </ul>
      </div>

      <!-- daisyUI mockup-phone, wrapped the same way hero wraps its right-hand
           media: rounded-3xl / border-slate-200/80 / shadow-2xl shadow-slate-300/40 -->
      <div class="order-1 flex justify-center lg:order-2">
        <div
          class="mockup-phone rounded-[3rem] border-[#1976D2]/60 shadow-2xl shadow-slate-300/40 dark:shadow-black/40"
        >
          <div class="mockup-phone-camera"></div>
          <div class="mockup-phone-display bg-base-100 transition-colors dark:bg-[#0F2A43]">
            <div class="flex h-full flex-col p-5">
              <div class="flex items-center justify-between text-[11px] font-semibold text-slate-400 dark:text-white/40">
                <span>9:41</span>
                <span>DASCARE</span>
              </div>

              <div
                class="mt-4 flex flex-col items-center gap-0.5 rounded-2xl border border-red-200 bg-red-50 py-4 text-center dark:border-red-400/20 dark:bg-red-500/10"
              >
                <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-red-700 dark:text-red-300">
                  <span class="relative flex h-1.5 w-1.5">
                    <span class="motion-safe:animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>
                  </span>
                  Active request
                </p>
                <p class="font-bold text-slate-900 dark:text-white">Ambulance en route</p>
                <p class="text-xs text-slate-500 dark:text-white/50">Estimated arrival &middot; 6 min</p>
              </div>

              <ul class="steps steps-vertical mt-4 items-start text-left">
                <li
                  v-for="s in statuses"
                  :key="s.label"
                  class="step text-xs"
                  :class="s.done ? 'step-success' : ''"
                >
                  {{ s.label }}
                </li>
              </ul>

              <button
                class="mt-auto w-full rounded-xl bg-red-600 py-3 font-semibold text-white shadow-lg shadow-red-600/25 transition-colors hover:bg-red-700 dark:shadow-red-950/40"
              >
                Share live location
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const features = [
  {
    title: 'One-tap requests',
    description: 'Send location and incident details without filling out long forms.'
  },
  {
    title: 'Live location sharing',
    description: 'Responders see an updating position instead of a fixed pin.'
  },
  {
    title: 'Status notifications',
    description: 'Get notified the moment a unit is assigned, en route, or arrived.'
  }
]

const statuses = [
  { label: 'Report received', done: true },
  { label: 'Ambulance assigned', done: true },
  { label: 'En route', done: true },
  { label: 'Arrived', done: false }
]
</script>