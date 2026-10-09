<template>
  <div class="bg-white dark:bg-[#050e1a]">
    <!-- Header band -->
    <section class="border-b border-slate-100 dark:border-white/5">
      <div class="mx-auto max-w-7xl px-5 py-16 sm:py-20">
        <div class="inline-flex items-center gap-2.5 border-l-2 border-[#1976D2] pl-3 dark:border-[#7fb3ec]">
          <span class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">
            Legal · Terms of Service
          </span>
        </div>
        <h1 class="mt-6 max-w-2xl text-4xl font-black leading-[1.05] tracking-tight text-slate-950 sm:text-5xl dark:text-white">
          The rules for using DASCARE.
        </h1>
        <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600 dark:text-[#a9c6e8]">
          These terms cover citizens, guests, and staff of verified ambulance organizations using
          DASCARE for Dasmariñas City. Last updated {{ lastUpdated }}.
        </p>
      </div>
    </section>

    <div class="mx-auto grid max-w-7xl gap-10 px-5 py-14 lg:grid-cols-[220px_1fr]">
      <!-- Section nav -->
      <nav class="hidden lg:block">
        <div class="sticky top-24 space-y-1">
          <p class="mb-3 font-mono text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-white/30">
            On this page
          </p>
          <a v-for="s in sections" :key="s.id" :href="`#${s.id}`"
            class="block rounded-lg px-3 py-1.5 text-sm text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-900 dark:text-white/50 dark:hover:bg-white/5 dark:hover:text-white">
            {{ s.num }}. {{ s.title }}
          </a>
        </div>
      </nav>

      <!-- Content -->
      <div class="min-w-0 space-y-14">
        <article v-for="s in sections" :id="s.id" :key="s.id" class="scroll-mt-24">
          <div class="flex items-baseline gap-3">
            <span class="font-mono text-sm font-bold text-[#1976D2] dark:text-[#7fb3ec]">{{ s.num }}</span>
            <h2 class="text-xl font-black tracking-tight text-slate-950 dark:text-white">{{ s.title }}</h2>
          </div>
          <div class="mt-4 space-y-3 text-[15px] leading-7 text-slate-600 dark:text-white/60">
            <p v-for="(p, i) in s.paragraphs" :key="i">{{ p }}</p>
            <ul v-if="s.list" class="list-disc space-y-1.5 pl-5">
              <li v-for="(item, i) in s.list" :key="i">{{ item }}</li>
            </ul>
          </div>
        </article>

        <div class="flex items-start gap-2.5 border-l-2 border-amber-400/70 pl-3 dark:border-amber-400/40">
          <p class="text-sm leading-relaxed text-slate-500 dark:text-white/40">
            For life-threatening emergencies, call your local emergency hotline first. DASCARE supports
            official response — it does not replace it.
          </p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-6 text-sm leading-6 text-slate-600 dark:bg-white/5 dark:text-white/55">
          Questions about these terms? Reach the DASCARE team through your organization administrator,
          or via the contact details in your account settings.
        </div>

        <!-- Related documents -->
        <div class="border-t border-slate-100 pt-10 dark:border-white/5">
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
      </div>
    </div>
  </div>
</template>

<script setup>
import axios from 'axios'
import { ref, onMounted } from 'vue'
import { Icon } from '@iconify/vue'

const API_BASE = import.meta.env.VITE_API_BASE_URL
const lastUpdated = 'August 6, 2026'

const relatedDocs = [
  {
    to: '/about',
    icon: 'lucide:info',
    title: 'About DASCARE',
    description: 'What DASCARE is, its mission and vision, and what it does and does not cover.',
  },
  {
    to: '/privacy-policy',
    icon: 'lucide:lock',
    title: 'Privacy Policy',
    description: 'What DASCARE collects during a request, and who can see it.',
  },
]

/* ===== CONTENT =====
   Seeded with the same copy that used to be hardcoded here, so the page
   renders complete immediately — fetchSections() below then swaps in the
   DB version (site_page_sections, page_slug='terms-of-service') once it
   resolves, or silently keeps this fallback if the endpoint errors. */
const sections = ref([
  {
    id: 'acceptance',
    num: '01',
    title: 'Acceptance of terms',
    paragraphs: [
      'By creating an account, submitting a guest request, or otherwise using DASCARE, you agree to these Terms of Service and to the Privacy Policy. If you are using DASCARE on behalf of an ambulance organization, you also agree on behalf of that organization, and confirm you are authorized to do so.',
      'If you do not agree with these terms, do not use DASCARE — you can still contact your local emergency hotline directly.',
    ],
  },
  {
    id: 'who-can-use',
    num: '02',
    title: 'Who can use DASCARE',
    paragraphs: [
      'DASCARE is built for residents, guests, and verified rescue personnel operating within Dasmariñas City. Citizen accounts require identity verification before certain features unlock. Guests may submit emergency and standard requests without an account, subject to the guest safeguards described below.',
      'Ambulance organizations must apply and be approved by platform administrators before their staff, vehicles, and schedules can appear in DASCARE. Approval can be suspended or withdrawn for non-compliance with applicable regulations or these terms.',
    ],
  },
  {
    id: 'emergency-scope',
    num: '03',
    title: 'Emergency use and limitations',
    paragraphs: [
      'DASCARE is a coordination tool, not an emergency hotline and not a substitute for one. In a life-threatening situation, contact your local emergency hotline first; use DASCARE to coordinate ambulance response alongside that call, not instead of it.',
      'DASCARE\'s decision support system ranks the most suitable available organization and ambulance based on distance, readiness, capability, and workload. This ranking is a recommendation. A human dispatcher at the receiving organization must still accept the incident and assign the ambulance and crew — DASCARE does not dispatch automatically, and cannot guarantee response time, ambulance availability, or outcome.',
    ],
  },
  {
    id: 'guest-requests',
    num: '04',
    title: 'Guest requests',
    paragraphs: [
      'Guests may submit requests without registering an account. A genuine emergency is never blocked by guest limits — after a device or phone number sends a number of guest requests in a day, further requests still go through, but receive an additional dispatcher check to guard against misuse.',
      'Guest requests are not saved to any account. The reference number issued at submission is the only way to follow up on a guest request; DASCARE cannot recover it if lost.',
    ],
  },
  {
    id: 'responsibilities',
    num: '05',
    title: 'Your responsibilities',
    list: [
      'Provide accurate location, contact, and situation information when submitting a request.',
      'Use DASCARE only for genuine emergencies, legitimate scheduled transport, or authorized operational purposes.',
      'Keep your account credentials confidential and notify us of any unauthorized use.',
      'Do not submit false, exaggerated, or duplicate reports — repeated suspected abuse may be reviewed and can result in account restrictions.',
    ],
  },
  {
    id: 'organization-responsibilities',
    num: '06',
    title: 'Organization responsibilities',
    paragraphs: [
      'Verified organizations are responsible for keeping their fleet, staff, and schedule information current, for responding to offered incidents in good faith, and for maintaining the credentials and compliance documents required for continued approval.',
      'Organizations manage access within their own account through role-based permissions, and are responsible for the actions of staff accounts they create and authorize.',
    ],
  },
  {
    id: 'location-tracking',
    num: '07',
    title: 'Location and tracking data',
    paragraphs: [
      'To coordinate a response, DASCARE collects the location submitted with a request and, during an active mission, the live location of the assigned ambulance. This data is used for dispatch, tracking, handoff coordination, and safety analytics, and is handled as described in the Privacy Policy.',
    ],
  },
  {
    id: 'availability',
    num: '08',
    title: 'Service availability',
    paragraphs: [
      'DASCARE is provided on an "as available" basis. As an academic capstone system, it does not carry the uptime guarantees of a commercial emergency dispatch platform. Connectivity issues, device limitations, or maintenance may affect availability — this is one more reason to treat official emergency hotlines as the primary channel for life-threatening situations.',
    ],
  },
  {
    id: 'liability',
    num: '09',
    title: 'Limitation of liability',
    paragraphs: [
      'DASCARE, its developers, and participating organizations are not liable for delays, unavailability, or outcomes arising from use of the platform, to the fullest extent permitted by law. Nothing in these terms limits liability that cannot be limited under applicable Philippine law.',
    ],
  },
  {
    id: 'termination',
    num: '10',
    title: 'Suspension and termination',
    paragraphs: [
      'We may suspend or terminate access for accounts or organizations that violate these terms, misuse the platform, or pose a risk to other users, with notice where practicable. You may stop using DASCARE and request account closure at any time.',
    ],
  },
  {
    id: 'changes',
    num: '11',
    title: 'Changes to these terms',
    paragraphs: [
      'We may update these terms as DASCARE evolves. Material changes will be reflected here with an updated date; continued use after changes take effect means you accept the revised terms.',
    ],
  },
  {
    id: 'governing-law',
    num: '12',
    title: 'Governing law',
    paragraphs: [
      'These terms are governed by the laws of the Republic of the Philippines, without regard to conflict-of-law principles.',
    ],
  },
])

/* ===== FETCH ===== */
const fetchSections = async () => {
  try {
    const res = await axios.get(`${API_BASE}/page-content.php`, {
      params: { slug: 'terms-of-service' },
      withCredentials: true,
    })
    if (res.data.sections?.length) sections.value = res.data.sections
  } catch (err) {
    console.warn('page-content.php not available yet — showing fallback copy', err)
  }
}

onMounted(fetchSections)
</script>
