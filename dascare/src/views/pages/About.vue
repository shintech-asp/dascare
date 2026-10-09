<template>
  <div class="bg-white dark:bg-[#050e1a]">
    <!-- Header band, echoing Hero.vue's eyebrow + headline treatment -->
    <section class="relative isolate overflow-hidden">
      <div class="mx-auto max-w-7xl px-5 py-20 sm:py-24">
        <div class="inline-flex items-center gap-2.5 border-l-2 border-[#1976D2] pl-3 dark:border-[#7fb3ec]">
          <span class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">
            About DASCARE
          </span>
        </div>
        <h1 class="mt-6 max-w-3xl text-4xl font-black leading-[1.05] tracking-tight text-slate-950 sm:text-5xl dark:text-white">
          One name, seven words,<br class="hidden sm:block" />
          one job for the city.
        </h1>
        <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600 dark:text-[#a9c6e8]">
          <strong class="font-bold text-slate-900 dark:text-white">D</strong>asmariñas
          <strong class="font-bold text-slate-900 dark:text-white">C</strong>oordinated
          <strong class="font-bold text-slate-900 dark:text-white">A</strong>mbulance
          <strong class="font-bold text-slate-900 dark:text-white">R</strong>escue for
          <strong class="font-bold text-slate-900 dark:text-white">E</strong>mergencies — a web and mobile
          system that gives Dasmariñas citizens one way to ask for help, and gives the city's ambulance
          organizations one shared system to answer that ask.
        </p>
      </div>
    </section>

    <!-- Mission & Vision -->
    <section class="mx-auto max-w-7xl px-5 pb-6">
      <div class="grid gap-6 sm:grid-cols-2">
        <div v-for="card in missionVisionCards" :key="card.key"
          class="rounded-3xl border border-slate-200/80 bg-white p-8 shadow-xl shadow-slate-300/30 dark:border-white/10 dark:bg-white/5 dark:shadow-black/40">
          <span class="grid h-11 w-11 place-items-center rounded-2xl ring-1 ring-white/15" :class="card.iconBg">
            <Icon :icon="card.icon" width="22" class="text-white" />
          </span>
          <h2 class="mt-5 text-xl font-black tracking-tight text-slate-950 dark:text-white">
            {{ contentLoading ? card.fallbackTitle : (section(card.key)?.title ?? card.fallbackTitle) }}
          </h2>
          <p class="mt-3 text-[15px] leading-7 text-slate-600 dark:text-white/60">
            {{ contentLoading ? card.fallbackBody : (paragraph(card.key) ?? card.fallbackBody) }}
          </p>
        </div>
      </div>
    </section>

    <!-- Team credit -->
    <section class="mx-auto max-w-7xl px-5 pb-4">
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p class="font-mono text-[11px] font-bold uppercase tracking-[0.25em] text-[#1976D2] dark:text-[#7fb3ec]">
            Built by
          </p>
          <h2 class="mt-3 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white">
            Developed by TNBMA Group
          </h2>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 px-3 py-1.5 font-mono text-xs font-semibold text-slate-500 dark:border-white/15 dark:text-white/50">
          <span class="h-1.5 w-1.5 rounded-full bg-[#1976D2] dark:bg-[#7fb3ec]"></span>
          {{ version ? `v${version}` : '\u00A0' }}
        </span>
      </div>

      <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
        <!-- Loading skeleton -->
        <template v-if="loading">
          <div v-for="n in 5" :key="n" class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white dark:border-white/10 dark:bg-white/5">
            <div class="aspect-square w-full animate-pulse bg-slate-100 dark:bg-white/5"></div>
            <div class="space-y-2 p-4">
              <div class="h-3.5 w-3/4 animate-pulse rounded bg-slate-100 dark:bg-white/10"></div>
              <div class="h-2.5 w-1/2 animate-pulse rounded bg-slate-100 dark:bg-white/10"></div>
            </div>
          </div>
        </template>

        <!-- Fetched members -->
        <div v-else v-for="member in team" :key="member.id"
          class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-lg shadow-slate-300/20 dark:border-white/10 dark:bg-white/5 dark:shadow-black/30">
          <div class="flex aspect-square w-full items-center justify-center bg-slate-100 dark:bg-white/5">
            <img v-if="member.photo" :src="member.photo" :alt="member.name" class="h-full w-full object-cover" />
            <Icon v-else icon="lucide:user-round" width="36" class="text-slate-300 dark:text-white/20" />
          </div>
          <div class="p-4">
            <p class="text-sm font-bold text-slate-900 dark:text-white">{{ member.name }}</p>
            <p class="mt-0.5 font-mono text-[11px] font-semibold uppercase tracking-wide text-[#1976D2] dark:text-[#7fb3ec]">
              {{ member.role }}
            </p>
          </div>
        </div>
      </div>
    </section>


    <section class="mx-auto max-w-7xl px-5 py-16">
      <div class="max-w-2xl">
        <p class="font-mono text-[11px] font-bold uppercase tracking-[0.25em] text-[#1976D2] dark:text-[#7fb3ec]">
          How it fits together
        </p>
        <h2 class="mt-3 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white">
          A decision support system suggests. A dispatcher decides.
        </h2>
      </div>

      <div class="mt-10 grid gap-5 sm:grid-cols-3">
        <div v-for="card in howItWorksCards" :key="card.key" class="rounded-2xl border border-slate-200/80 p-6 dark:border-white/10">
          <Icon :icon="card.icon" width="22" :class="card.iconClass" />
          <h3 class="mt-4 text-sm font-bold text-slate-900 dark:text-white">
            {{ contentLoading ? card.fallbackTitle : (section(card.key)?.title ?? card.fallbackTitle) }}
          </h3>
          <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-white/55">
            {{ contentLoading ? card.fallbackBody : (paragraph(card.key) ?? card.fallbackBody) }}
          </p>
        </div>
      </div>
    </section>

    <!-- Scope: what DASCARE is / is not -->
    <section class="mx-auto max-w-7xl px-5 pb-20">
      <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-3xl bg-slate-50 p-8 dark:bg-white/5">
          <h3 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-slate-900 dark:text-white">
            <Icon icon="lucide:circle-check-big" width="18" class="text-emerald-500" />
            {{ contentLoading ? 'What DASCARE covers' : (section('scope-covers')?.title ?? 'What DASCARE covers') }}
          </h3>
          <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600 dark:text-white/60">
            <li v-for="(item, i) in (contentLoading ? fallbackScopeCovers : (list('scope-covers') ?? fallbackScopeCovers))" :key="i" class="flex gap-2">
              <Icon icon="lucide:check" width="16" class="mt-1 shrink-0 text-emerald-500" /> {{ item }}
            </li>
          </ul>
        </div>
        <div class="rounded-3xl bg-slate-50 p-8 dark:bg-white/5">
          <h3 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-slate-900 dark:text-white">
            <Icon icon="lucide:circle-x" width="18" class="text-slate-400 dark:text-white/40" />
            {{ contentLoading ? 'What DASCARE does not do' : (section('scope-not')?.title ?? 'What DASCARE does not do') }}
          </h3>
          <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600 dark:text-white/60">
            <li v-for="(item, i) in (contentLoading ? fallbackScopeNot : (list('scope-not') ?? fallbackScopeNot))" :key="i" class="flex gap-2">
              <Icon icon="lucide:x" width="16" class="mt-1 shrink-0 text-slate-400 dark:text-white/40" /> {{ item }}
            </li>
          </ul>
        </div>
      </div>

      <div class="mt-8 flex items-start gap-2.5 border-l-2 border-amber-400/70 pl-3 dark:border-amber-400/40">
        <p class="text-sm leading-relaxed text-slate-500 dark:text-white/40">
          {{ contentLoading ? fallbackDisclaimer : (paragraph('disclaimer') ?? fallbackDisclaimer) }}
        </p>
      </div>
    </section>

    <!-- Related documents -->
    <section class="border-t border-slate-100 dark:border-white/5">
      <div class="mx-auto max-w-7xl px-5 py-14">
        <p class="font-mono text-[11px] font-bold uppercase tracking-[0.25em] text-slate-400 dark:text-white/30">
          Related documents
        </p>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          <RouterLink v-for="doc in relatedDocs" :key="doc.to" :to="doc.to"
            class="group flex items-start gap-4 rounded-2xl border border-slate-200/80 p-5 transition-colors hover:border-[#1976D2]/40 hover:bg-slate-50 dark:border-white/10 dark:hover:border-[#7fb3ec]/30 dark:hover:bg-white/5">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-white/60">
              <Icon :icon="doc.icon" width="18" />
            </span>
            <span>
              <span class="flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-white">
                {{ doc.title }}
                <Icon icon="lucide:arrow-up-right" width="14" class="text-slate-400 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 dark:text-white/40" />
              </span>
              <span class="mt-1 block text-sm leading-6 text-slate-500 dark:text-white/50">{{ doc.description }}</span>
            </span>
          </RouterLink>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import axios from 'axios'
import { ref, onMounted } from 'vue'
import { Icon } from '@iconify/vue'

const API_BASE = import.meta.env.VITE_API_BASE_URL

/* ===== STATE =====
   One fetch (page-content.php?slug=about) covers the page text sections,
   the team credit cards, and the version badge — all served from the DB
   now instead of being hardcoded here. `loading` covers all three; the
   template falls back to the copy below (matching what shipped before
   this was DB-driven) if the endpoint isn't reachable yet. */
const loading = ref(true)
const contentLoading = ref(true)
const sections = ref([])
const team = ref([])
const version = ref(null)

const section = (key) => sections.value.find((s) => s.id === key)
const paragraph = (key) => section(key)?.paragraphs?.[0] ?? null
const list = (key) => section(key)?.list ?? null

/* ===== CARD LAYOUT (icons stay static; text is DB-driven) ===== */
const missionVisionCards = [
  {
    key: 'mission',
    icon: 'lucide:target',
    iconBg: 'bg-gradient-to-br from-[#1976D2] to-[#0F2A43]',
    fallbackTitle: 'Mission',
    fallbackBody:
      'To close the gap between the moment a Dasmariñas resident calls for help and the moment an ambulance reaches them — by giving citizens one trusted way to report an emergency, and giving rescue organizations a shared, real-time picture instead of separate radios, logs, and guesswork.',
  },
  {
    key: 'vision',
    icon: 'lucide:eye',
    iconBg: 'bg-gradient-to-br from-red-600 to-red-900',
    fallbackTitle: 'Vision',
    fallbackBody:
      'A Dasmariñas City where every request for ambulance assistance is seen, ranked, and routed to the organization best placed to respond — with a human dispatcher always in the loop — so no call is lost to a busy line, an unanswered radio, or a system nobody shares.',
  },
]

const howItWorksCards = [
  {
    key: 'how-it-works-1',
    icon: 'lucide:siren',
    iconClass: 'text-red-600 dark:text-red-400',
    fallbackTitle: 'A request comes in',
    fallbackBody:
      'A citizen or guest reports an emergency, or a scheduled patient transport, through the app — no account required for urgent cases.',
  },
  {
    key: 'how-it-works-2',
    icon: 'lucide:brain-circuit',
    iconClass: 'text-[#1976D2] dark:text-[#7fb3ec]',
    fallbackTitle: 'DASCARE ranks the response',
    fallbackBody:
      'Distance, readiness, capability, and current workload rank the most suitable available organization and ambulance — a recommendation, not an automatic dispatch.',
  },
  {
    key: 'how-it-works-3',
    icon: 'lucide:user-check',
    iconClass: 'text-emerald-500',
    fallbackTitle: 'A human accepts and assigns',
    fallbackBody:
      "The incident is sent to an organization's dispatcher, who must still accept it and assign the ambulance and crew before anyone rolls out.",
  },
]

const fallbackScopeCovers = [
  'Emergency and scheduled transport requests, from citizens and guests',
  'Live ambulance tracking and mission status updates',
  'Organization-managed staff, vehicles, schedules, and role-based access',
  'Limited hospital endorsement and handoff coordination',
  'Analytics, incident heatmaps, and audit logs for accountability',
]
const fallbackScopeNot = [
  'Provide medical diagnosis or treatment guidance',
  'Maintain full hospital records or detailed bed management',
  'Dispatch police or fire services',
  "Replace a city's official emergency hotline",
]
const fallbackDisclaimer =
  'DASCARE is developed as part of an academic capstone project. For life-threatening emergencies, always contact your local emergency hotline first — DASCARE supports official response, it does not replace it.'

const fallbackTeam = [
  { id: 1, name: 'Full Name', role: 'Role' },
  { id: 2, name: 'Full Name', role: 'Role' },
  { id: 3, name: 'Full Name', role: 'Role' },
  { id: 4, name: 'Full Name', role: 'Role' },
  { id: 5, name: 'Full Name', role: 'Role' },
]

const relatedDocs = [
  {
    to: '/terms-of-service',
    icon: 'lucide:file-text',
    title: 'Terms of Service',
    description: 'The rules for citizens, guests, and organizations using DASCARE.',
  },
  {
    to: '/privacy-policy',
    icon: 'lucide:lock',
    title: 'Privacy Policy',
    description: 'What DASCARE collects during a request, and who can see it.',
  },
]

/* ===== FETCH =====
   Photos are intentionally left out for now — page-content.php only
   returns name + role per team member; every card falls back to the
   placeholder user icon in the template above. */
const fetchAboutContent = async () => {
  try {
    const res = await axios.get(`${API_BASE}/page-content.php`, {
      params: { slug: 'about' },
      withCredentials: true,
    })
    sections.value = res.data.sections ?? []
    team.value = res.data.team?.length ? res.data.team : fallbackTeam
    version.value = res.data.version ?? null
  } catch (err) {
    console.warn('page-content.php not available yet — showing fallback copy', err)
    sections.value = []
    team.value = fallbackTeam
    version.value = '0.1.1'
  } finally {
    contentLoading.value = false
    loading.value = false
  }
}

onMounted(fetchAboutContent)
</script>
