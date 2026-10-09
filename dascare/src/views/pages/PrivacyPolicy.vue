<template>
  <div class="bg-white dark:bg-[#050e1a]">
    <!-- Header band -->
    <section class="border-b border-slate-100 dark:border-white/5">
      <div class="mx-auto max-w-7xl px-5 py-16 sm:py-20">
        <div class="inline-flex items-center gap-2.5 border-l-2 border-[#1976D2] pl-3 dark:border-[#7fb3ec]">
          <span class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">
            Legal · Privacy Policy
          </span>
        </div>
        <h1 class="mt-6 max-w-2xl text-4xl font-black leading-[1.05] tracking-tight text-slate-950 sm:text-5xl dark:text-white">
          What we collect, and why.
        </h1>
        <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600 dark:text-[#a9c6e8]">
          DASCARE handles time-sensitive personal and location data to coordinate emergency response.
          This policy explains what's collected, who can see it, and the choices you have. Last updated
          {{ lastUpdated }}.
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
            DASCARE does not provide medical diagnosis and does not maintain full hospital medical
            records — data collected here supports dispatch, tracking, and limited prehospital
            handoff only.
          </p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-6 text-sm leading-6 text-slate-600 dark:bg-white/5 dark:text-white/55">
          To exercise your rights under the Data Privacy Act of 2012, or to ask a question about this
          policy, reach the DASCARE data protection contact through your account settings or your
          organization administrator.
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
    to: '/terms-of-service',
    icon: 'lucide:file-text',
    title: 'Terms of Service',
    description: 'The rules for citizens, guests, and organizations using DASCARE.',
  },
]

/* ===== CONTENT =====
   Seeded with the same copy that used to be hardcoded here, so the page
   renders complete immediately — fetchSections() below then swaps in the
   DB version (site_page_sections, page_slug='privacy-policy') once it
   resolves, or silently keeps this fallback if the endpoint errors. */
const sections = ref([
  {
    id: 'scope',
    num: '01',
    title: 'Scope of this policy',
    paragraphs: [
      'This policy applies to citizens, guests, and organization staff using DASCARE\'s web and mobile system for Dasmariñas City, and describes how we collect, use, share, and protect personal data in connection with emergency and scheduled ambulance coordination.',
      'We process data in line with the Philippine Data Privacy Act of 2012 (RA 10173) and its implementing rules.',
    ],
  },
  {
    id: 'information-we-collect',
    num: '02',
    title: 'Information we collect',
    paragraphs: ['Depending on how you use DASCARE, we collect:'],
    list: [
      'Account details — name, contact number, email, and identity verification information for citizen accounts.',
      'Request details — the reported location, situation description, and, for guests, the name and phone number provided at submission.',
      'Location and tracking data — the coordinates attached to a request, and the live position of an assigned ambulance during an active mission.',
      'Limited prehospital information — vital signs, observations, and interventions recorded by responders for a specific incident, not a full medical history.',
      'Operational data from organizations — staff duty status, vehicle and crew assignments, and mission records.',
      'System data — device information, login activity, and audit logs of actions taken within the platform.',
    ],
  },
  {
    id: 'how-we-use-it',
    num: '03',
    title: 'How we use this information',
    list: [
      'To route a request to the decision support system, which ranks suitable organizations and ambulances by distance, readiness, capability, and workload.',
      'To let a dispatcher accept an incident and assign an ambulance and crew.',
      'To provide live mission tracking and status updates to the requester and assigned organization.',
      'To support limited hospital endorsement and handoff at the point of transfer.',
      'To generate analytics, incident heatmaps, and performance reporting at an aggregate level.',
      'To maintain audit logs for accountability, dispute review, and security.',
      'To operate guest request safeguards, which apply an additional dispatcher check after repeated submissions from the same device or number — without ever blocking a genuine emergency.',
    ],
  },
  {
    id: 'who-sees-it',
    num: '04',
    title: 'Who your information is shared with',
    paragraphs: [
      'Request and location details are shared with the organization and dispatcher assigned to your incident, and with the responding crew, for the duration needed to deliver assistance. Receiving facilities see only the limited handoff information relevant to a transfer, not your full request history.',
      'Platform and organization administrators can access data within their role-based permissions for oversight, compliance, and audit purposes — access is scoped by DASCARE\'s role-based access control (RBAC), not open to every staff account by default.',
      'We do not sell personal data, and we do not share it with advertisers.',
    ],
  },
  {
    id: 'guest-data',
    num: '05',
    title: 'Guest requests',
    paragraphs: [
      'Guest requests are not attached to a registered account. The name and phone number you provide are used to process the request, apply guest safeguards, and allow a dispatcher to reach you — they are not retained as part of an ongoing profile.',
    ],
  },
  {
    id: 'retention',
    num: '06',
    title: 'Data retention',
    paragraphs: [
      'We retain incident, mission, and audit records for as long as needed to support accountability, dispute resolution, analytics, and legal or regulatory requirements, then delete or anonymize them. Account information is retained while your account remains active, and for a limited period after closure where required for legitimate operational or legal purposes.',
    ],
  },
  {
    id: 'security',
    num: '07',
    title: 'How we protect it',
    paragraphs: [
      'DASCARE restricts access through role-based permissions, keeps a logged audit trail of sensitive actions, and applies technical and organizational safeguards appropriate to the sensitivity of emergency and location data. No system is completely immune to risk, and we work to identify and address vulnerabilities as they are found.',
    ],
  },
  {
    id: 'your-rights',
    num: '08',
    title: 'Your rights',
    paragraphs: ['Subject to applicable law, you may:'],
    list: [
      'Request access to the personal data we hold about you.',
      'Request correction of inaccurate or outdated information.',
      'Request deletion of your account data, subject to records we must retain for legal or safety reasons.',
      'Object to or request restriction of certain processing.',
      'Lodge a complaint with the National Privacy Commission if you believe your rights have been violated.',
    ],
  },
  {
    id: 'children',
    num: '09',
    title: "Minors' data",
    paragraphs: [
      'DASCARE may process data about a minor when they are the patient in an emergency or scheduled transport request submitted by a parent, guardian, or bystander. Such data is used solely to coordinate the response and is subject to the same safeguards as any other request.',
    ],
  },
  {
    id: 'changes',
    num: '10',
    title: 'Changes to this policy',
    paragraphs: [
      'We may update this policy as DASCARE evolves. Material changes will be reflected here with an updated date.',
    ],
  },
])

/* ===== FETCH ===== */
const fetchSections = async () => {
  try {
    const res = await axios.get(`${API_BASE}/page-content.php`, {
      params: { slug: 'privacy-policy' },
      withCredentials: true,
    })
    if (res.data.sections?.length) sections.value = res.data.sections
  } catch (err) {
    console.warn('page-content.php not available yet — showing fallback copy', err)
  }
}

onMounted(fetchSections)
</script>
