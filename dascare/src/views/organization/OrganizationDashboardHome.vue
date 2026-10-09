<template>
  <section class="min-h-screen bg-base-200 pb-12 dark:bg-[#081b2e]">
    <div class="mx-auto max-w-[1500px] px-4 py-7 sm:px-6 lg:px-8">
      <section class="relative overflow-hidden rounded-[30px] border border-base-300 bg-base-100 p-6 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-8">
        <div class="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full bg-red-500/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-28 right-40 h-56 w-56 rounded-full bg-blue-500/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
          <div class="max-w-3xl"><span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-[0.68rem] font-black uppercase tracking-[0.12em] text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> Approved organization</span><h1 class="mt-4 text-3xl font-black tracking-tight text-slate-950 dark:text-white sm:text-4xl">{{ organization?.name || 'Organization Operations' }}</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500 dark:text-white/50">Manage personnel, ambulance readiness, incoming incident offers, and active rescue missions from one operational workspace.</p></div>
          <div class="flex flex-wrap gap-3"><RouterLink to="/organization/fleet" class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white no-underline hover:bg-red-700"><Icon icon="lucide:ambulance" width="16" /> Manage Fleet</RouterLink><RouterLink to="/organization/profile" class="inline-flex items-center gap-2 rounded-xl border border-base-300 bg-base-100 px-4 py-3 text-sm font-bold text-slate-600 no-underline hover:border-[#1976D2]/30 hover:text-[#1976D2] dark:border-white/10 dark:bg-white/[0.035] dark:text-white/55"><Icon icon="lucide:building" width="16" /> Organization Profile</RouterLink></div>
        </div>
      </section>

      <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Stat label="Organization Members" :value="summary.members" icon="lucide:users-round" hint="Admins and joined operational staff" />
        <Stat label="Fleet Units" :value="summary.fleetTotal" icon="lucide:ambulance" hint="Registered active fleet records" />
        <Stat label="Available Ambulances" :value="summary.fleetAvailable" icon="lucide:circle-check-big" hint="Ready and currently available" />
        <Stat label="Active Missions" :value="summary.activeMissions" icon="lucide:route" hint="Assignments currently in progress" />
      </div>

      <div class="mt-6 grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <section class="rounded-[28px] border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-6">
          <div class="flex items-start justify-between gap-4"><div><p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">Operational setup</p><h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">Organization Readiness</h2><p class="mt-1 text-xs leading-5 text-slate-400 dark:text-white/30">Keep the organization ready for incident offers, assignment, and active missions.</p></div><Icon icon="lucide:list-checks" width="22" class="text-slate-300 dark:text-white/20" /></div>
          <div class="mt-5 space-y-3">
            <SetupItem :done="true" title="Platform approval" text="The organization is verified and operational access is active." to="/organization/application-status" />
            <SetupItem :done="profileComplete" title="Organization profile" text="Confirm contact details, station/base location, and service coverage." to="/organization/profile" />
            <SetupItem :done="summary.members > 1" title="Personnel onboarding" text="Invite dispatchers, drivers, medics, rescuers, supervisors, or fleet staff." to="/organization/personnel" />
            <SetupItem :done="summary.approvedDocuments > 0" title="Compliance documents" text="Approved application documents are connected to the organization record." to="/organization/profile" />
            <SetupItem :done="summary.fleetTotal > 0 && summary.fleetReady > 0" title="Fleet readiness" text="Register ambulance units and complete at least one passed readiness check." to="/organization/fleet" />
          </div>
        </section>

        <section class="rounded-[28px] border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-6">
          <div class="flex items-center justify-between"><div><p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-[#1976D2] dark:text-[#7fb3ec]">Team access</p><h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">Starter Roles</h2></div><Icon icon="lucide:key-round" width="22" class="text-slate-300 dark:text-white/20" /></div>
          <div class="mt-5 grid gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2"><div v-for="role in roles" :key="role.id" class="rounded-2xl bg-base-200 p-3.5 dark:bg-white/[0.035]"><div class="flex items-center justify-between gap-2"><p class="text-xs font-black text-slate-800 dark:text-white/65">{{ role.role_name }}</p><span class="text-[0.62rem] font-bold text-slate-400">{{ role.member_count }}</span></div><p class="mt-1 line-clamp-2 text-[0.67rem] leading-5 text-slate-400 dark:text-white/30">{{ role.description }}</p></div></div>
          <RouterLink to="/organization/personnel" class="mt-5 flex items-center justify-center gap-2 rounded-xl border border-base-300 py-2.5 text-xs font-bold text-slate-600 no-underline hover:border-[#1976D2]/30 hover:text-[#1976D2] dark:border-white/10 dark:text-white/45"><Icon icon="lucide:users" width="14" /> Open Personnel Directory</RouterLink>
        </section>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Icon } from '@iconify/vue'
import { useSession } from '@/composables/useSession'
import { fetchOrganizationMembers, fetchOrganizationProfile, fetchOrganizationRoles } from '@/services/organizationManagement'
import { fetchFleetUnits } from '@/services/organizationFleet'
import { fetchOrganizationOffers, fetchOrganizationMissions } from '@/services/dispatchOperations'

const { organization } = useSession()
const profile=ref(null), roles=ref([]), summary=ref({members:0,pendingInvites:0,serviceAreas:0,approvedDocuments:0,fleetTotal:0,fleetAvailable:0,fleetReady:0,activeMissions:0,pendingOffers:0})
const profileComplete=computed(()=>Boolean(profile.value?.email && profile.value?.phone && profile.value?.address_line && profile.value?.latitude && profile.value?.longitude && summary.value.serviceAreas>0))
const Stat=defineComponent({props:{label:String,value:[String,Number],icon:String,hint:String},setup(p){return()=>h('article',{class:'rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943]'},[h('div',{class:'flex items-start justify-between'},[h('span',{class:'grid h-10 w-10 place-items-center rounded-2xl bg-base-200 text-slate-500 dark:bg-white/5 dark:text-white/40'},[h(Icon,{icon:p.icon,width:19})]),h('span',{class:'text-2xl font-black text-slate-950 dark:text-white'},String(p.value??0))]),h('p',{class:'mt-4 text-sm font-black text-slate-800 dark:text-white/70'},p.label),h('p',{class:'mt-1 text-xs leading-5 text-slate-400 dark:text-white/30'},p.hint)])}})
const SetupItem=defineComponent({props:{done:Boolean,title:String,text:String,to:String},setup(p){return()=>h(RouterLink,{to:p.to,class:'group flex gap-3 rounded-2xl border border-base-300 p-4 no-underline transition hover:border-[#1976D2]/20 hover:bg-blue-50/30 dark:border-white/5 dark:hover:bg-blue-500/5'},()=>[h('span',{class:['mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl',p.done?'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300':'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300']},[h(Icon,{icon:p.done?'lucide:check':'lucide:clock-3',width:15})]),h('span',{class:'min-w-0 flex-1'},[h('span',{class:'block text-sm font-black text-slate-800 dark:text-white/70'},p.title),h('span',{class:'mt-0.5 block text-xs leading-5 text-slate-400 dark:text-white/30'},p.text)]),h(Icon,{icon:'lucide:chevron-right',width:15,class:'mt-2 text-slate-300 transition-transform group-hover:translate-x-0.5 dark:text-white/20'})])}})

async function load(){
  const results=await Promise.allSettled([fetchOrganizationProfile(),fetchOrganizationMembers(),fetchOrganizationRoles(),fetchFleetUnits(),fetchOrganizationOffers(),fetchOrganizationMissions()])
  if(results[0].status==='fulfilled'){const d=results[0].value;profile.value=d.organization;summary.value.serviceAreas=(d.service_areas||[]).length;summary.value.approvedDocuments=(d.documents||[]).filter(x=>x.status==='approved').length}
  if(results[1].status==='fulfilled'){const d=results[1].value;summary.value.members=d.stats?.members||0;summary.value.pendingInvites=d.stats?.pending_invites||0}
  if(results[2].status==='fulfilled') roles.value=results[2].value.roles||[]
  if(results[3].status==='fulfilled'){const d=results[3].value;summary.value.fleetTotal=d.stats?.total||0;summary.value.fleetAvailable=d.stats?.available||0;summary.value.fleetReady=d.stats?.ready||0}
  if(results[4].status==='fulfilled'){const d=results[4].value;summary.value.pendingOffers=(d.offers||[]).filter(x=>x.offer_status==='sent').length}
  if(results[5].status==='fulfilled'){const d=results[5].value;summary.value.activeMissions=(d.missions||[]).filter(x=>!['completed','cancelled','declined'].includes(x.assignment_status)).length}
}
onMounted(load)
</script>
