<template>
  <PageShell title="Platform Policies" subtitle="Reference the operating boundaries that keep DASCARE focused on emergency coordination rather than hospital administration." icon="lucide:notebook-tabs" eyebrow="Platform Executive">
    <div class="grid gap-4 lg:grid-cols-2">
      <article v-for="policy in policies" :key="policy.title" class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
        <div class="flex items-start gap-4">
          <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl" :class="policy.badge"><Icon :icon="policy.icon" width="20"/></span>
          <div>
            <h2 class="text-sm font-black text-slate-900 dark:text-white">{{ policy.title }}</h2>
            <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-white/45">{{ policy.description }}</p>
            <ul class="mt-3 space-y-2 text-xs text-slate-600 dark:text-white/55">
              <li v-for="item in policy.items" :key="item" class="flex gap-2"><Icon icon="lucide:check" width="14" class="mt-0.5 shrink-0 text-emerald-600"/><span>{{ item }}</span></li>
            </ul>
          </div>
        </div>
      </article>
    </div>
    <section class="mt-5 rounded-3xl border border-amber-200 bg-amber-50/70 p-5 dark:border-amber-500/20 dark:bg-amber-500/10">
      <div class="flex gap-3"><Icon icon="lucide:info" width="18" class="mt-0.5 shrink-0 text-amber-700"/><div><h2 class="text-sm font-black text-amber-900 dark:text-amber-200">Reference page, not a policy-authoring engine</h2><p class="mt-1 text-xs leading-5 text-amber-800/80 dark:text-amber-200/70">For the thesis build, formal policy documents are governed outside this application. This page makes the active system boundaries visible to administrators without pretending DASCARE has a legal-document workflow it does not implement.</p></div></div>
    </section>
  </PageShell>
</template>
<script setup>
import { Icon } from '@iconify/vue'
import PageShell from '@/components/management/PageShell.vue'
const policies=[
 {title:'Emergency access',icon:'lucide:siren',badge:'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300',description:'Emergency assistance must remain reachable even when a citizen is not KYC-approved.',items:['Guest and registered requests enter the emergency pipeline.','Account restrictions do not replace emergency-access safeguards.','Abuse controls flag suspicious use for review instead of permanently blocking legitimate emergencies.']},
 {title:'Human-controlled dispatch',icon:'lucide:brain-circuit',badge:'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300',description:'The DSS supports operational decisions but does not autonomously command ambulances.',items:['DSS ranks eligible organizations/resources.','Organizations accept or decline timed offers.','Dispatchers assign the actual ambulance and crew.','Overrides and sensitive actions are auditable.']},
 {title:'Clinical scope boundary',icon:'lucide:stethoscope',badge:'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300',description:'DASCARE stores only incident-specific emergency coordination information.',items:['Basic assessment, vitals, field notes and handoff are supported.','No automated diagnosis or treatment recommendation.','No full EMR, pharmacy, laboratory, billing or admission system.']},
 {title:'Organization governance',icon:'lucide:building-2',badge:'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',description:'Approved rescue organizations operate as separate tenants inside the city-governed platform.',items:['Platform Executive reviews organization applications/compliance.','Organization Admin manages staff, roles, fleet and readiness.','Employees join through organization invitations rather than citizen KYC.']},
 {title:'Privacy & access control',icon:'lucide:shield-check',badge:'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300',description:'Sensitive operational and citizen information is restricted by role, tenant, and permission.',items:['Frontend visibility is backed by server authorization.','Technical logs should avoid exposing medical/KYC details.','KYC and operational media should be delivered through controlled endpoints.']},
 {title:'Operational transparency',icon:'lucide:history',badge:'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-white/60',description:'Status changes and critical administrative actions should leave an audit trail.',items:['Organization, KYC, account, incident, resource and handoff actions are audited where implemented.','No fake backup, uptime or operational data should be shown when a subsystem does not exist.']},
]
</script>
