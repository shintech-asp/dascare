<template>
  <!-- "Our supporters": active sponsors, labelled as such. Never shown on emergency screens.
       Same data feeds the app (dascare_mobile/src/components/SupportersStrip.vue). -->
  <section v-if="sponsors.length" class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6">
    <p class="text-center text-[0.68rem] font-black uppercase tracking-[0.18em] text-slate-400 dark:text-white/35">Our supporters · Sponsors</p>
    <div class="mt-5 flex flex-wrap items-center justify-center gap-4">
      <component :is="s.website_url ? 'a' : 'div'" v-for="s in sponsors" :key="s.id" :href="s.website_url || undefined" target="_blank" rel="noopener sponsored"
        class="flex items-center gap-3 rounded-2xl border border-base-300 bg-base-100 px-4 py-3 shadow-sm transition hover:border-red-200 dark:border-white/10 dark:bg-white/[.04]"
        :class="s.tier === 'presenting' ? 'px-6 py-4' : ''">
        <img v-if="s.has_logo" :src="sponsorLogoUrl(s.id)" :alt="s.name" class="object-contain" :class="s.tier === 'presenting' ? 'h-14 w-14' : 'h-10 w-10'" loading="lazy" />
        <span class="min-w-0"><span class="block text-sm font-black text-slate-800 dark:text-white/80">{{ s.name }}</span><span v-if="s.tagline && s.tier !== 'supporter'" class="block max-w-[14rem] truncate text-xs text-slate-400">{{ s.tagline }}</span></span>
      </component>
    </div>
    <p class="mt-4 text-center text-xs text-slate-400 dark:text-white/35">
      Sponsors help keep DASCARE free. <RouterLink to="/support" class="font-bold text-red-600 hover:underline dark:text-red-400">Support DASCARE</RouterLink> ·
      <RouterLink to="/support/sponsor" class="font-bold text-red-600 hover:underline dark:text-red-400">Become a sponsor</RouterLink>
    </p>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { fetchSupportPublic, sponsorLogoUrl } from '@/services/support'

const sponsors = ref([])
onMounted(async () => {
  try { sponsors.value = (await fetchSupportPublic()).sponsors || [] } catch { /* hidden when unavailable */ }
})
</script>
