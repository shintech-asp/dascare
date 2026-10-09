<template>
  <section id="roles" class="relative isolate overflow-hidden bg-base-100 transition-colors dark:bg-[#050e1a]">
   <div class="relative mx-auto max-w-7xl px-5 py-20">
    <div class="max-w-2xl">
      <div class="inline-flex items-center gap-2.5 border-l-2 border-red-600 pl-3 dark:border-red-400">
        <span class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">
          Built for every seat
        </span>
      </div>

      <h2 class="mt-4 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl dark:text-white">
        One platform, four very different jobs.
      </h2>

      <p class="mt-4 max-w-xl text-lg leading-8 text-slate-600 dark:text-[#a9c6e8]">
        Each role sees only what it needs to act quickly, without wading through screens meant
        for someone else.
      </p>
    </div>

    <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
      <div
        v-for="role in roles"
        :key="role.title"
        class="group relative overflow-hidden rounded-2xl border border-base-300 bg-base-200/55 p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-xl"
        :class="role.hoverShadowClass"
      >
        <div
          class="absolute inset-x-0 top-0 h-1 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
          :class="role.barClass"
        ></div>

        <span class="flex h-10 w-10 items-center justify-center rounded-xl" :class="role.badgeClass">
          <Icon :icon="role.icon" width="19" />
        </span>

        <h3 class="mt-4 font-bold text-slate-900 dark:text-white">{{ role.title }}</h3>
        <ul class="mt-2 space-y-2.5 text-sm leading-6 text-slate-600 dark:text-[#a9c6e8]">
          <li v-for="point in role.points" :key="point" class="flex gap-2">
            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" :class="role.dotClass"></span>
            <span>{{ point }}</span>
          </li>
        </ul>
      </div>
    </div>
   </div>
  </section>
</template>

<script setup>
import { Icon } from '@iconify/vue'

// Each accent is a color hero already uses somewhere (siren blue, siren red,
// the disclaimer's amber, the modal's success emerald) — no new colors added.
const roles = [
  {
    title: 'Citizens',
    icon: 'lucide:smartphone',
    barClass: 'bg-[#1976D2]',
    badgeClass: 'bg-[#1976D2]/10 text-[#1976D2]',
    dotClass: 'bg-[#1976D2]',
    hoverShadowClass: 'hover:shadow-[#1976D2]/10 dark:border-white/10 dark:bg-[#0F2A43] dark:shadow-black/30',
    points: [
      'Submit a report with location, photos, and details in under a minute.',
      'Track assignment, travel, and arrival in real time.',
      'Get confirmation the moment a unit is on the way.'
    ]
  },
  {
    title: 'Dispatchers',
    icon: 'lucide:headset',
    barClass: 'bg-red-600 dark:bg-red-400',
    badgeClass: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300',
    dotClass: 'bg-red-600 dark:bg-red-400',
    hoverShadowClass: 'hover:shadow-red-600/10 dark:border-white/10 dark:bg-[#0F2A43] dark:shadow-black/30',
    points: [
      'Review incoming reports and confirm severity before assignment.',
      'See the ranked match for every available ambulance.',
      'Reassign a case the moment circumstances change.'
    ]
  },
  {
    title: 'Rescue personnel',
    icon: 'lucide:ambulance',
    barClass: 'bg-amber-500',
    badgeClass: 'bg-amber-50 text-amber-600 dark:bg-amber-400/10 dark:text-amber-300',
    dotClass: 'bg-amber-500',
    hoverShadowClass: 'hover:shadow-amber-500/10 dark:border-white/10 dark:bg-[#0F2A43] dark:shadow-black/30',
    points: [
      'Receive assignment details and incident location on mobile.',
      'Update status from en route, to arrived, to completed.',
      'Log outcome notes for the case record.'
    ]
  },
  {
    title: 'Ambulance coordinators',
    icon: 'lucide:gauge',
    barClass: 'bg-emerald-500',
    badgeClass: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300',
    dotClass: 'bg-emerald-500',
    hoverShadowClass: 'hover:shadow-emerald-500/10 dark:border-white/10 dark:bg-[#0F2A43] dark:shadow-black/30',
    points: [
      'Manage fleet availability, crew assignment, and service status.',
      'Review response-time trends across the fleet.',
      'Audit closed cases for training and improvement.'
    ]
  }
]
</script>