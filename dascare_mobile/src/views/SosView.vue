<template>
  <div class="min-h-screen bg-base-200 dark:bg-[#050e1a]">
    <ScreenHeader title="Emergency SOS" />
    <main class="space-y-4 px-4 pb-[calc(var(--safe-bottom)+2rem)] pt-4">
      <!-- Phase 2 placeholder: the request form (GPS, photos, guest + citizen)
           is built in Phase 3, reusing the web's EmergencyRequestForm. -->
      <section class="relative overflow-hidden rounded-3xl border border-red-200 bg-red-50 p-6 text-center dark:border-red-500/20 dark:bg-red-500/5">
        <span class="relative mx-auto grid h-20 w-20 place-items-center rounded-full bg-red-600 text-white shadow-lg shadow-red-600/30">
          <span class="absolute inset-0 animate-ping rounded-full bg-red-500/30"></span>
          <Icon icon="lucide:siren" width="34" class="relative" />
        </span>
        <h2 class="mt-5 text-xl font-black text-red-800 dark:text-red-200">The SOS request form is coming next</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-white/55">
          In the next update this screen sends your GPS location to DASCARE dispatch in one tap.
          If you have an emergency right now, call the national emergency hotline.
        </p>
        <a href="tel:911" class="tap mt-5 flex w-full items-center justify-center gap-2 rounded-2xl bg-red-600 py-4 text-base font-black text-white no-underline shadow-lg shadow-red-600/30">
          <Icon icon="lucide:phone-call" width="20" /> Call 911
        </a>
      </section>
    </main>
  </div>
</template>

<script setup>
import ScreenHeader from '@/components/ScreenHeader.vue'
</script>
