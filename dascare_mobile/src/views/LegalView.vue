<template>
  <!-- Terms of Service / Privacy Policy — same content and look as the web
       pages (views/pages/TermsOfService.vue, PrivacyPolicy.vue): live text
       from page-content.php, bundled copy when offline. -->
  <div class="min-h-screen bg-base-100 dark:bg-[#050e1a]">
    <ScreenHeader :title="doc.title" fallback="/welcome" />
    <main class="px-5 pb-[calc(var(--safe-bottom)+2rem)]">
      <section class="border-b border-slate-100 py-8 dark:border-white/5">
        <div class="inline-flex items-center gap-2.5 border-l-2 border-[#1976D2] pl-3 dark:border-[#7fb3ec]">
          <span class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">Legal · {{ doc.title }}</span>
        </div>
        <h1 class="mt-4 text-3xl font-black leading-[1.08] tracking-tight text-slate-950 dark:text-white">{{ doc.heading }}</h1>
        <p class="mt-4 text-base leading-7 text-slate-600 dark:text-[#a9c6e8]">{{ doc.intro }} Last updated {{ doc.updated }}.</p>
      </section>

      <div class="space-y-10 py-8">
        <article v-for="s in sections" :key="s.id">
          <div class="flex items-baseline gap-3">
            <span class="font-mono text-sm font-bold text-[#1976D2] dark:text-[#7fb3ec]">{{ s.num }}</span>
            <h2 class="text-lg font-black tracking-tight text-slate-950 dark:text-white">{{ s.title }}</h2>
          </div>
          <div class="selectable mt-3 space-y-3 text-[15px] leading-7 text-slate-600 dark:text-white/60">
            <p v-for="(p, i) in s.paragraphs" :key="i">{{ p }}</p>
            <ul v-if="s.list" class="list-disc space-y-1.5 pl-5">
              <li v-for="(item, i) in s.list" :key="i">{{ item }}</li>
            </ul>
          </div>
        </article>

        <div class="flex items-start gap-2.5 border-l-2 border-amber-400/70 pl-3 dark:border-amber-400/40">
          <p class="text-sm leading-relaxed text-slate-500 dark:text-white/40">
            For life-threatening emergencies, call your local emergency hotline first. DASCARE supports official response — it does not replace it.
          </p>
        </div>

        <RouterLink :to="{ name: 'Legal', params: { slug: otherSlug } }" replace class="tap flex items-center gap-4 rounded-2xl border border-slate-200/80 p-4 no-underline dark:border-white/10">
          <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-white/60">
            <Icon :icon="otherSlug === 'privacy-policy' ? 'lucide:lock' : 'lucide:scroll-text'" width="18" />
          </span>
          <span class="text-sm font-bold text-slate-900 dark:text-white">{{ LEGAL[otherSlug].title }}</span>
          <Icon icon="lucide:chevron-right" width="18" class="ml-auto text-slate-400" />
        </RouterLink>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import ScreenHeader from '@/components/ScreenHeader.vue'
import api from '@/services/api'
import { LEGAL } from '@/content/legal'

const route = useRoute()
const slug = computed(() => (route.params.slug === 'privacy-policy' ? 'privacy-policy' : 'terms-of-service'))
const otherSlug = computed(() => (slug.value === 'privacy-policy' ? 'terms-of-service' : 'privacy-policy'))
const doc = computed(() => LEGAL[slug.value])
const sections = ref(doc.value.sections)

// Same source as the web: site_page_sections via page-content.php.
watch(slug, async (value) => {
  sections.value = LEGAL[value].sections
  try {
    const { data } = await api.get('/page-content.php', { params: { slug: value } })
    if (data.sections?.length) sections.value = data.sections
  } catch { /* offline — keep the bundled copy */ }
}, { immediate: true })
</script>
