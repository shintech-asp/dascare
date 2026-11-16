<template>
  <!-- Support DASCARE (app): voluntary donations + sponsors. Donating happens on the website
       (PayMongo checkout); nothing here appears in any emergency screen. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader title="Support DASCARE" fallback="/profile" />
    <main class="space-y-3 px-4 pt-3">
      <div v-if="loading" class="h-40 animate-pulse rounded-2xl bg-base-100 dark:bg-white/5"></div>
      <p v-else-if="error" class="py-10 text-center text-sm text-red-600">{{ error }}</p>
      <template v-else-if="data">
        <section :class="[ui.card, 'p-5']">
          <p class="text-sm font-black text-slate-900 dark:text-white">Keep emergency response free</p>
          <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-white/50">{{ data.intro }}</p>
          <p class="mt-2 text-xs font-semibold text-slate-500 dark:text-white/45">Requesting an ambulance is always free and has no ads. Donating is voluntary.</p>
          <a v-if="donateUrl && data.enabled" :href="donateUrl" target="_blank" rel="noopener" :class="[ui.primaryButton, 'mt-4 flex items-center justify-center gap-2 no-underline']"><Icon icon="lucide:heart" width="17" /> Donate on the website</a>
          <p v-else-if="!data.enabled" class="mt-3 text-xs text-slate-500">Donations are paused for now.</p>
        </section>

        <section v-for="c in data.campaigns" :key="c.id" :class="[ui.card, 'p-5']">
          <p class="text-[0.62rem] font-black uppercase tracking-[0.16em] text-red-600 dark:text-red-300">{{ c.period === 'month' ? 'Monthly goal' : c.period === 'year' ? 'Yearly goal' : 'Fundraising goal' }}</p>
          <p class="mt-1 text-sm font-black text-slate-900 dark:text-white">{{ c.title }}</p>
          <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-white/50">{{ c.purpose }}</p>
          <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-base-200 dark:bg-white/10"><div class="h-full rounded-full bg-red-500" :style="{ width: c.percent + '%' }"></div></div>
          <p class="mt-1.5 flex justify-between text-xs text-slate-500 dark:text-white/50"><span><b class="text-slate-900 dark:text-white">{{ formatPeso(c.raised_centavos) }}</b> raised</span><span>goal {{ formatPeso(c.goal_centavos) }} · {{ c.percent }}%</span></p>
        </section>

        <section v-if="data.breakdown.length" :class="[ui.card, 'p-5']">
          <p class="text-sm font-black text-slate-900 dark:text-white">Where the money goes</p>
          <ul class="mt-3 space-y-2">
            <li v-for="b in data.breakdown" :key="b.label"><div class="flex justify-between text-xs font-semibold text-slate-600 dark:text-white/65"><span>{{ b.label }}</span><span>{{ b.percent }}%</span></div><div class="mt-1 h-1.5 overflow-hidden rounded-full bg-base-200 dark:bg-white/10"><div class="h-full rounded-full bg-slate-400 dark:bg-white/40" :style="{ width: b.percent + '%' }"></div></div></li>
          </ul>
        </section>

        <section v-if="data.sponsors.length" :class="[ui.card, 'p-5']">
          <p class="text-[0.62rem] font-black uppercase tracking-[0.16em] text-slate-400 dark:text-white/35">Our sponsors</p>
          <div class="mt-3 space-y-2.5">
            <component :is="s.website_url ? 'a' : 'div'" v-for="s in data.sponsors" :key="s.id" :href="s.website_url || undefined" target="_blank" rel="noopener sponsored" class="flex items-center gap-3 no-underline">
              <img v-if="s.has_logo" :src="sponsorLogoUrl(s.id)" :alt="s.name" class="h-10 w-10 flex-shrink-0 rounded-lg object-contain" loading="lazy" />
              <span class="min-w-0"><span class="block truncate text-sm font-bold text-slate-800 dark:text-white/80">{{ s.name }}</span><span v-if="s.tagline && s.tier !== 'supporter'" class="block truncate text-xs text-slate-400">{{ s.tagline }}</span></span>
            </component>
          </div>
        </section>

        <section v-if="data.wall.length" :class="[ui.card, 'p-5']">
          <p class="text-sm font-black text-slate-900 dark:text-white">Thank you to our donors</p>
          <div class="mt-3 flex flex-wrap gap-2"><span v-for="(w, i) in data.wall" :key="i" class="rounded-full bg-base-200 px-3 py-1 text-xs font-bold text-slate-600 dark:bg-white/10 dark:text-white/70">{{ w.name }}</span></div>
        </section>

        <a v-if="sponsorUrl" :href="sponsorUrl" target="_blank" rel="noopener" class="tap block py-2 text-center text-sm font-bold text-red-600 no-underline dark:text-red-300">Become a sponsor</a>
      </template>
    </main>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import ScreenHeader from '@/components/ScreenHeader.vue'
import * as ui from '@/components/ui/styles'
import { API_BASE_URL } from '@/services/api'
import { fetchSupportPublic, formatPeso, sponsorLogoUrl } from '@/services/support'

const loading = ref(true)
const error = ref('')
const data = ref(null)
// The donation page is on the website (PayMongo checkout): …/api → https://host/support (hidden for local dev URLs).
const web = /^https:\/\//.test(API_BASE_URL) ? API_BASE_URL.replace(/\/(dascare_)?api\/?$/, '') : ''
const donateUrl = web ? web + '/support' : ''
const sponsorUrl = web ? web + '/support/sponsor' : ''

onMounted(async () => {
  try { data.value = await fetchSupportPublic() } catch (e) { error.value = 'Unable to load this screen. Check your connection and try again.' } finally { loading.value = false }
})
</script>
