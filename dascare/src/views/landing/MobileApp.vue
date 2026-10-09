<template>
  <section id="mobile-app" class="relative isolate overflow-hidden bg-base-200 transition-colors dark:bg-[#050e1a]">
    <div class="relative mx-auto grid max-w-7xl gap-14 px-5 py-20 lg:grid-cols-2 lg:items-center">
      <div class="order-2 lg:order-1">
        <div class="inline-flex items-center gap-2.5 border-l-2 border-red-600 pl-3 dark:border-red-400">
          <span class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">
            DASCARE for Android
          </span>
        </div>

        <h2 class="mt-4 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl dark:text-white">
          Request help right from your phone.
        </h2>

        <p class="mt-4 max-w-xl text-lg leading-8 text-slate-600 dark:text-[#a9c6e8]">
          The DASCARE app puts the SOS button, live ambulance tracking, and your emergency information in your pocket —
          with or without an account.
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

        <!-- APK download. downloads/app.json + DASCARE.apk are published by
             `npm run apk:web` in dascare_mobile/ (not stored in git). -->
        <div class="mt-9 flex flex-wrap items-center gap-4">
          <a
            v-if="app"
            :href="apkUrl"
            download="DASCARE.apk"
            class="inline-flex items-center gap-3 rounded-2xl bg-slate-900 px-5 py-3.5 text-white no-underline shadow-lg shadow-slate-900/20 transition-transform hover:-translate-y-0.5 dark:bg-white dark:text-slate-900"
          >
            <Icon icon="mdi:android" width="30" class="text-[#3ddc84]" />
            <span class="flex flex-col leading-tight">
              <span class="text-[11px] font-semibold uppercase tracking-wider opacity-70">Download for</span>
              <span class="text-lg font-black">Android</span>
            </span>
            <Icon icon="lucide:download" width="20" class="ml-1 opacity-80" />
          </a>
          <span
            v-else
            class="inline-flex cursor-not-allowed items-center gap-3 rounded-2xl border border-dashed border-slate-300 px-5 py-3.5 text-slate-400 dark:border-white/15 dark:text-white/35"
            title="Run `npm run apk:web` in dascare_mobile/ to publish the app"
          >
            <Icon icon="mdi:android" width="30" />
            <span class="flex flex-col leading-tight">
              <span class="text-[11px] font-semibold uppercase tracking-wider">Android app</span>
              <span class="text-sm font-bold">{{ loading ? 'Checking…' : 'Not published yet' }}</span>
            </span>
          </span>

          <p v-if="app" class="text-xs leading-5 text-slate-500 dark:text-white/45">
            <span class="font-semibold text-slate-700 dark:text-white/70">Version {{ app.version }}</span>
            · {{ sizeLabel }} · APK<br />
            Android 7.0 or later. Chrome warns about any app from outside the Play Store —<br class="hidden sm:inline" />
            tap <span class="font-semibold">Download anyway</span>, then allow installs from your browser.
          </p>
        </div>
      </div>

      <!-- Phone mockup of the app's Home screen (same design as dascare_mobile) -->
      <div class="order-1 flex justify-center lg:order-2">
        <div class="mockup-phone rounded-[3rem] border-[#1976D2]/60 shadow-2xl shadow-slate-300/40 dark:shadow-black/40">
          <div class="mockup-phone-camera"></div>
          <div class="mockup-phone-display bg-[#f5efe1] transition-colors dark:bg-[#050e1a]">
            <div class="relative flex h-full flex-col">
              <!-- top bar -->
              <div class="flex items-center gap-2.5 px-5 pb-3 pt-9">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-white shadow-sm ring-1 ring-[#1976D2]/20">
                  <img :src="logoUrl" alt="" class="h-7 w-7 object-contain" />
                </span>
                <span class="flex flex-col leading-tight">
                  <span class="text-sm font-bold text-slate-900 dark:text-white">Dascare</span>
                  <span class="text-[7px] font-semibold uppercase tracking-[0.25em] text-[#1976D2] dark:text-[#7fb3ec]">Ambulance Dispatch</span>
                </span>
              </div>

              <div class="flex-1 space-y-3 px-4">
                <div>
                  <p class="text-[11px] text-slate-500 dark:text-white/45">Good afternoon,</p>
                  <p class="text-lg font-black leading-tight text-slate-950 dark:text-white">Maria</p>
                </div>

                <!-- active request card -->
                <div class="rounded-2xl border border-red-200 bg-[#faf7ef] p-3.5 shadow-sm dark:border-red-500/20 dark:bg-[#071829]">
                  <div class="flex items-center justify-between">
                    <span class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-wider text-red-600 dark:text-red-300">
                      <span class="relative flex h-1.5 w-1.5">
                        <span class="motion-safe:animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-red-600"></span>
                      </span>
                      Active request
                    </span>
                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-[9px] font-bold text-red-700 dark:bg-red-500/15 dark:text-red-300">Responding</span>
                  </div>
                  <p class="mt-2 font-mono text-[9px] font-semibold text-slate-500 dark:text-white/45">DAS-2026-000128</p>
                  <p class="text-sm font-bold text-slate-900 dark:text-white">Ambulance en route</p>
                  <div class="mt-2.5 flex gap-0.5">
                    <span v-for="i in 7" :key="i" class="h-1 flex-1 rounded-full" :class="i <= 4 ? 'bg-red-500' : 'bg-slate-200 dark:bg-white/10'"></span>
                  </div>
                  <div class="mt-2 flex items-center justify-between text-[9px]">
                    <span class="text-slate-500 dark:text-white/45">BRGY-01 · 1.2 km away</span>
                    <span class="flex items-center gap-0.5 font-bold text-red-600 dark:text-red-300">Track live <Icon icon="lucide:chevron-right" width="10" /></span>
                  </div>
                </div>

                <!-- SOS card -->
                <div class="flex items-center gap-3 rounded-2xl bg-gradient-to-br from-red-600 to-red-700 p-3.5 text-white shadow-lg shadow-red-600/30">
                  <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-white/15"><Icon icon="lucide:siren" width="20" /></span>
                  <span class="leading-tight">
                    <span class="block text-sm font-black">Another emergency?</span>
                    <span class="block text-[9px] text-white/85">Tap to send your location to dispatch.</span>
                  </span>
                </div>
              </div>

              <!-- bottom bar with the raised SOS button -->
              <div class="relative mt-3 grid grid-cols-5 items-end border-t border-slate-200 bg-white px-1 pb-4 pt-2 dark:border-white/10 dark:bg-[#050e1a]">
                <span v-for="t in leftTabs" :key="t.label" class="flex flex-col items-center gap-0.5" :class="t.active ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-white/40'">
                  <Icon :icon="t.icon" width="16" /><span class="text-[8px] font-semibold">{{ t.label }}</span>
                </span>
                <span class="relative flex justify-center">
                  <span class="absolute -top-7 grid h-12 w-12 place-items-center rounded-full border-4 border-white bg-red-600 text-white shadow-lg shadow-red-600/40 dark:border-[#050e1a]">
                    <span class="flex flex-col items-center"><Icon icon="lucide:triangle-alert" width="15" /><span class="text-[6px] font-black">SOS</span></span>
                  </span>
                  <span class="mt-5 text-[8px] font-semibold text-red-600 dark:text-red-400">Emergency</span>
                </span>
                <span v-for="t in rightTabs" :key="t.label" class="relative flex flex-col items-center gap-0.5 text-slate-400 dark:text-white/40">
                  <Icon :icon="t.icon" width="16" /><span class="text-[8px] font-semibold">{{ t.label }}</span>
                  <span v-if="t.badge" class="absolute -top-1 right-2.5 grid h-3 min-w-3 place-items-center rounded-full bg-red-600 px-0.5 text-[7px] font-black text-white">{{ t.badge }}</span>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import logoUrl from '../../../img/logoo.png'

const features = [
  {
    title: 'One-tap SOS — no account needed',
    description: 'Your GPS location goes straight to dispatch. Add details or photos only if you can.',
  },
  {
    title: 'Live ambulance tracking',
    description: 'Follow every step from "ambulance assigned" to "arrived", with the unit on the map.',
  },
  {
    title: 'Push notifications',
    description: 'Get alerted the moment a unit is assigned, en route, or on scene — even with the app closed.',
  },
]

const leftTabs = [
  { label: 'Home', icon: 'lucide:house', active: true },
  { label: 'Requests', icon: 'lucide:clipboard-list' },
]
const rightTabs = [
  { label: 'Alerts', icon: 'lucide:bell', badge: 1 },
  { label: 'Profile', icon: 'lucide:circle-user-round' },
]

// Published app info (version/size), written by `npm run apk:web`.
const APK_PATH = 'downloads/DASCARE.apk'
const app = ref(null)
const loading = ref(true)
const apkUrl = `${import.meta.env.BASE_URL}${APK_PATH}`
const sizeLabel = computed(() => (app.value?.size_bytes ? `${(app.value.size_bytes / 1e6).toFixed(1)} MB` : ''))

onMounted(async () => {
  try {
    const res = await fetch(`${import.meta.env.BASE_URL}downloads/app.json`, { cache: 'no-store' })
    if (res.ok) app.value = await res.json()
  } catch { /* not published — show the placeholder */ } finally {
    loading.value = false
  }
})
</script>
