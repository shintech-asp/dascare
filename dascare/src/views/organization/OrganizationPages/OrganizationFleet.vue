<template>
  <section class="min-h-screen bg-base-200 pb-12 dark:bg-[#081b2e]">
    <div class="mx-auto max-w-[1500px] px-4 py-7 sm:px-6 lg:px-8">
      <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <div class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-[0.68rem] font-black uppercase tracking-[0.12em] text-red-700 dark:bg-red-500/10 dark:text-red-300"><Icon icon="lucide:ambulance" width="14" /> Organization resources</div>
          <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white">Fleet</h1>
          <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-white/45">Register ambulance units, maintain their identity and credentials, and control whether they are eligible for future dispatch.</p>
        </div>
        <button v-if="canCreate" class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-red-600/15 hover:bg-red-700" @click="openCreate"><Icon icon="lucide:plus" width="16" /> Add Ambulance</button>
      </header>

      <div v-if="loading" class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><div v-for="n in 4" :key="n" class="h-32 animate-pulse rounded-3xl bg-base-100 dark:bg-[#0d2943]"></div></div>
      <template v-else>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Stat label="Registered Units" :value="stats.total" icon="lucide:ambulance" hint="Active fleet records" />
          <Stat label="Available" :value="stats.available" icon="lucide:circle-check-big" hint="Eligible for future assignment" />
          <Stat label="Readiness Passed" :value="stats.ready" icon="lucide:clipboard-check" hint="Latest check is ready" />
          <Stat label="Needs Attention" :value="stats.attention + stats.credential_alerts" icon="lucide:triangle-alert" hint="Readiness or credential alerts" />
        </div>

        <div v-if="errorMessage" class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">{{ errorMessage }}</div>

        <section class="mt-6 overflow-hidden rounded-[28px] border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
          <div class="flex flex-col gap-3 border-b border-base-300 p-5 dark:border-white/10 lg:flex-row lg:items-center lg:justify-between sm:p-6">
            <div><p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">Fleet directory</p><h2 class="mt-1 text-lg font-black text-slate-950 dark:text-white">Ambulance Units</h2></div>
            <div class="flex flex-col gap-2 sm:flex-row">
              <div class="relative"><Icon icon="lucide:search" width="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"/><input v-model="search" class="w-full rounded-xl border border-base-300 bg-base-200 py-2.5 pl-9 pr-3 text-sm outline-none focus:border-[#1976D2] dark:border-white/10 dark:bg-white/[0.035] dark:text-white sm:w-64" placeholder="Search unit or plate" /></div>
              <select v-model="statusFilter" class="rounded-xl border border-base-300 bg-base-200 px-3 py-2.5 text-sm text-slate-600 outline-none dark:border-white/10 dark:bg-white/[0.035] dark:text-white/60"><option value="">All statuses</option><option value="available">Available</option><option value="maintenance">Maintenance</option><option value="offline">Offline</option><option value="reserved">Reserved</option><option value="dispatched">Dispatched</option><option value="on_scene">On scene</option><option value="transporting">Transporting</option><option value="returning">Returning</option></select>
            </div>
          </div>

          <div v-if="!filteredUnits.length" class="p-12 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-base-200 text-slate-400 dark:bg-white/5 dark:text-white/25"><Icon icon="lucide:ambulance" width="24" /></span><h3 class="mt-4 text-sm font-black text-slate-800 dark:text-white/70">No ambulance units found</h3><p class="mt-1 text-xs text-slate-400">Add the organization's first ambulance to begin fleet readiness setup.</p></div>

          <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left">
              <thead class="bg-base-200/70 text-[0.65rem] font-black uppercase tracking-[0.1em] text-slate-400 dark:bg-white/[0.025] dark:text-white/25"><tr><th class="px-6 py-3.5">Unit</th><th class="px-4 py-3.5">Classification</th><th class="px-4 py-3.5">Operational Status</th><th class="px-4 py-3.5">Readiness</th><th class="px-4 py-3.5">Credentials</th><th class="px-4 py-3.5">Maintenance</th><th class="px-6 py-3.5 text-right">Action</th></tr></thead>
              <tbody class="divide-y divide-base-300 dark:divide-white/5">
                <tr v-for="unit in filteredUnits" :key="unit.id" class="hover:bg-base-200/35 dark:hover:bg-white/[0.02]">
                  <td class="px-6 py-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon icon="lucide:ambulance" width="19"/></span><div><p class="text-sm font-black text-slate-900 dark:text-white">{{ unit.unit_code }}</p><p class="mt-0.5 text-xs text-slate-400">{{ unit.plate_number }}<span v-if="unit.vehicle_make_model"> · {{ unit.vehicle_make_model }}</span></p></div></div></td>
                  <td class="px-4 py-4"><p class="text-xs font-bold text-slate-700 dark:text-white/60">{{ typeLabel(unit.ambulance_type) }}</p><p class="mt-1 text-[0.68rem] text-slate-400">Capacity {{ unit.capacity }}</p></td>
                  <td class="px-4 py-4"><StatusPill :value="unit.status" /></td>
                  <td class="px-4 py-4"><ReadinessPill :value="unit.readiness_status" /><p v-if="unit.readiness_checked_at" class="mt-1 text-[0.64rem] text-slate-400">{{ relativeDate(unit.readiness_checked_at) }}</p></td>
                  <td class="px-4 py-4"><span v-if="unit.credential_alert" class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-1 text-[0.64rem] font-bold text-red-700 dark:bg-red-500/15 dark:text-red-300"><Icon icon="lucide:triangle-alert" width="11"/> Expired</span><span v-else class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-[0.64rem] font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"><Icon icon="lucide:badge-check" width="11"/> Clear</span></td>
                  <td class="px-4 py-4"><span class="text-xs font-bold" :class="unit.open_maintenance ? 'text-amber-600 dark:text-amber-300' : 'text-slate-400'">{{ unit.open_maintenance ? `${unit.open_maintenance} open` : 'No open work' }}</span><p v-if="unit.next_maintenance_date" class="mt-1 text-[0.64rem] text-slate-400">Next {{ formatDate(unit.next_maintenance_date) }}</p></td>
                  <td class="px-6 py-4 text-right"><button class="rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-base-200 dark:border-white/10 dark:bg-white/5 dark:text-white/50" @click="openDetail(unit.id)">View Unit</button></td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>

      <!-- Add / edit unit -->
      <div v-if="formOpen" class="fixed inset-0 z-[95] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" @click.self="formOpen=false">
        <section class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-[28px] border border-base-300 bg-base-100 shadow-2xl dark:border-white/10 dark:bg-[#0d2943]">
          <header class="flex items-start justify-between border-b border-base-300 p-5 dark:border-white/10 sm:p-6"><div><p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">{{ form.id ? 'Fleet record' : 'New resource' }}</p><h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">{{ form.id ? 'Edit Ambulance' : 'Add Ambulance' }}</h2><p class="mt-1 text-xs text-slate-400">New units start Offline until a readiness check is completed.</p></div><button class="rounded-xl p-2 text-slate-400 hover:bg-base-200 dark:hover:bg-white/5" @click="formOpen=false"><Icon icon="lucide:x" width="18"/></button></header>
          <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
            <label class="block"><span :class="labelClass">Unit code *</span><input v-model.trim="form.unit_code" :class="fieldClass" placeholder="AMB-01" /></label>
            <label class="block"><span :class="labelClass">Plate number *</span><input v-model.trim="form.plate_number" :class="fieldClass" placeholder="ABC 1234" /></label>
            <label class="block"><span :class="labelClass">Ambulance type *</span><select v-model="form.ambulance_type" :class="fieldClass"><option value="basic_life_support">Basic Life Support</option><option value="advanced_life_support">Advanced Life Support</option><option value="patient_transport">Patient Transport</option><option value="rescue_unit">Rescue Unit</option></select></label>
            <label class="block"><span :class="labelClass">Capacity *</span><input v-model.number="form.capacity" type="number" min="1" max="20" :class="fieldClass" /></label>
            <label class="block"><span :class="labelClass">Vehicle make / model</span><input v-model.trim="form.vehicle_make_model" :class="fieldClass" placeholder="Toyota HiAce" /></label>
            <label class="block"><span :class="labelClass">Model year</span><input v-model.number="form.model_year" type="number" min="1980" :class="fieldClass" placeholder="2025" /></label>
            <label class="block"><span :class="labelClass">Registration expiry</span><input v-model="form.registration_expiry" type="date" :class="fieldClass" /></label>
            <label class="block"><span :class="labelClass">Inspection expiry</span><input v-model="form.inspection_expiry" type="date" :class="fieldClass" /></label>
            <label class="block sm:col-span-2"><span :class="labelClass">Capabilities / equipment notes</span><textarea v-model.trim="form.capability_notes" rows="4" :class="`${fieldClass} resize-none`" placeholder="Oxygen, AED, trauma kit, wheelchair access, or other relevant capability notes"></textarea></label>
          </div>
          <footer class="flex justify-end gap-2 border-t border-base-300 p-5 dark:border-white/10 sm:p-6"><button class="rounded-xl border border-base-300 px-4 py-2.5 text-sm font-bold text-slate-600 dark:border-white/10 dark:text-white/50" @click="formOpen=false">Cancel</button><button class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50" :disabled="saving" @click="saveUnit">{{ saving ? 'Saving…' : 'Save Ambulance' }}</button></footer>
        </section>
      </div>

      <!-- Detail -->
      <div v-if="detailOpen" class="fixed inset-0 z-[94] flex justify-end bg-slate-950/55 backdrop-blur-sm" @click.self="detailOpen=false">
        <aside class="h-full w-full max-w-2xl overflow-y-auto border-l border-base-300 bg-base-100 shadow-2xl dark:border-white/10 dark:bg-[#0d2943]">
          <div v-if="detailLoading" class="p-10"><div class="h-32 animate-pulse rounded-3xl bg-base-200 dark:bg-white/5"></div></div>
          <template v-else-if="detail?.unit">
            <header class="sticky top-0 z-10 border-b border-base-300 bg-base-100/95 p-5 backdrop-blur dark:border-white/10 dark:bg-[#0d2943]/95 sm:p-6"><div class="flex items-start justify-between gap-3"><div><div class="flex flex-wrap items-center gap-2"><h2 class="text-2xl font-black text-slate-950 dark:text-white">{{ detail.unit.unit_code }}</h2><StatusPill :value="detail.unit.status"/></div><p class="mt-1 text-sm text-slate-400">{{ detail.unit.plate_number }} · {{ typeLabel(detail.unit.ambulance_type) }}</p></div><button class="rounded-xl p-2 text-slate-400 hover:bg-base-200 dark:hover:bg-white/5" @click="detailOpen=false"><Icon icon="lucide:x" width="19"/></button></div></header>
            <div class="space-y-5 p-5 sm:p-6">
              <section class="grid gap-3 sm:grid-cols-3"><Info label="Readiness" :value="readinessLabel(detail.readiness_checks?.[0]?.overall_status)"/><Info label="Capacity" :value="String(detail.unit.capacity)"/><Info label="Last location" :value="detail.unit.last_location_at ? relativeDate(detail.unit.last_location_at) : 'No tracking yet'"/></section>
              <section class="rounded-3xl border border-base-300 bg-base-200/55 p-5 dark:border-white/10 dark:bg-white/[0.025]"><div class="flex items-start justify-between gap-4"><div><p class="text-xs font-black uppercase tracking-wide text-slate-400">Vehicle & credentials</p><p class="mt-2 text-sm font-bold text-slate-800 dark:text-white/70">{{ detail.unit.vehicle_make_model || 'Make/model not recorded' }}<span v-if="detail.unit.model_year"> · {{ detail.unit.model_year }}</span></p></div><span :class="detail.unit.credential_state?.valid ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'" class="rounded-full px-2.5 py-1 text-[0.64rem] font-bold">{{ detail.unit.credential_state?.valid ? 'Credentials clear' : 'Credential alert' }}</span></div><div class="mt-4 grid gap-3 sm:grid-cols-2"><Info label="Registration expiry" :value="formatDate(detail.unit.registration_expiry)"/><Info label="Inspection expiry" :value="formatDate(detail.unit.inspection_expiry)"/></div><p v-if="detail.unit.capability_notes" class="mt-4 text-xs leading-5 text-slate-500 dark:text-white/40">{{ detail.unit.capability_notes }}</p></section>

              <div class="flex flex-wrap gap-2"><button v-if="canUpdate" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-base-200 dark:border-white/10 dark:text-white/50" @click="openEditFromDetail"><Icon icon="lucide:pencil" width="13" class="mr-1 inline"/> Edit</button><button v-if="canStatus" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-base-200 dark:border-white/10 dark:text-white/50" @click="openStatus"><Icon icon="lucide:power" width="13" class="mr-1 inline"/> Change Status</button><RouterLink to="/organization/readiness" class="rounded-xl bg-[#1976D2] px-3 py-2 text-xs font-bold text-white no-underline"><Icon icon="lucide:clipboard-check" width="13" class="mr-1 inline"/> Readiness & Maintenance</RouterLink><button v-if="canDelete" class="ml-auto rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50 dark:border-red-500/20 dark:hover:bg-red-500/10" @click="archiveCurrent"><Icon icon="lucide:archive" width="13" class="mr-1 inline"/> Archive</button></div>

              <section><div class="flex items-center justify-between"><div><p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">Timeline</p><h3 class="mt-1 text-lg font-black text-slate-950 dark:text-white">Status History</h3></div></div><div class="mt-4 space-y-3"><div v-for="log in detail.status_history" :key="log.id" class="flex gap-3 rounded-2xl border border-base-300 p-3.5 dark:border-white/5"><span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-red-500"></span><div class="min-w-0"><p class="text-xs font-black text-slate-700 dark:text-white/65">{{ statusLabel(log.old_status) }} → {{ statusLabel(log.new_status) }}</p><p v-if="log.reason" class="mt-1 text-[0.68rem] leading-5 text-slate-400">{{ log.reason }}</p><p class="mt-1 text-[0.62rem] text-slate-400">{{ formatDateTime(log.created_at) }}<span v-if="log.changed_by_name?.trim()"> · {{ log.changed_by_name }}</span></p></div></div><p v-if="!detail.status_history?.length" class="rounded-2xl bg-base-200 p-4 text-xs text-slate-400 dark:bg-white/[0.03]">No status history yet.</p></div></section>
            </div>
          </template>
        </aside>
      </div>

      <!-- Status change -->
      <div v-if="statusOpen" class="fixed inset-0 z-[99] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" @click.self="statusOpen=false">
        <section class="w-full max-w-lg rounded-[28px] border border-base-300 bg-base-100 shadow-2xl dark:border-white/10 dark:bg-[#0d2943]"><header class="border-b border-base-300 p-5 dark:border-white/10"><p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600">Fleet availability</p><h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">Change Operational Status</h2></header><div class="space-y-4 p-5"><label class="block"><span :class="labelClass">New status</span><select v-model="statusForm.status" :class="fieldClass"><option value="available">Available</option><option value="maintenance">Maintenance</option><option value="offline">Offline</option></select></label><label class="block"><span :class="labelClass">Reason</span><textarea v-model.trim="statusForm.reason" rows="3" :class="`${fieldClass} resize-none`" :placeholder="statusForm.status === 'available' ? 'Optional when returning to service' : 'Why is this unit being taken out of service?'" /></label><p class="rounded-2xl bg-base-200 p-3 text-[0.68rem] leading-5 text-slate-500 dark:bg-white/[0.035] dark:text-white/35">Available requires a passed readiness check, valid credentials, and no in-progress maintenance. Mission states are controlled by dispatch.</p></div><footer class="flex justify-end gap-2 border-t border-base-300 p-5 dark:border-white/10"><button class="rounded-xl border border-base-300 px-4 py-2.5 text-sm font-bold text-slate-600" @click="statusOpen=false">Cancel</button><button class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50" :disabled="statusSaving" @click="saveStatus">{{ statusSaving ? 'Saving…' : 'Update Status' }}</button></footer></section>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Icon } from '@iconify/vue'
import { archiveFleetUnit, createFleetUnit, fetchFleetUnit, fetchFleetUnits, updateFleetUnit, updateFleetUnitStatus } from '@/services/organizationFleet'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'

const { hasPermission } = useSession(); const toast=useToast(); const alert=useAlert()
const loading=ref(true), errorMessage=ref(''), units=ref([]), stats=ref({total:0,available:0,maintenance:0,offline:0,ready:0,attention:0,credential_alerts:0}), search=ref(''), statusFilter=ref('')
const formOpen=ref(false), saving=ref(false), detailOpen=ref(false), detailLoading=ref(false), detail=ref(null), statusOpen=ref(false), statusSaving=ref(false)
const emptyForm=()=>({id:null,unit_code:'',plate_number:'',vehicle_make_model:'',model_year:'',ambulance_type:'basic_life_support',capacity:1,registration_expiry:'',inspection_expiry:'',capability_notes:''})
const form=ref(emptyForm()), statusForm=ref({status:'available',reason:''})
const fieldClass='w-full rounded-xl border border-base-300 bg-base-100 px-3.5 py-2.5 text-sm text-slate-800 outline-none transition focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/10 dark:border-white/10 dark:bg-[#071829] dark:text-white'
const labelClass='mb-1.5 block text-[0.68rem] font-black uppercase tracking-wide text-slate-400 dark:text-white/30'
const canCreate=computed(()=>hasPermission('fleet.ambulances.create')), canUpdate=computed(()=>hasPermission('fleet.ambulances.update')), canDelete=computed(()=>hasPermission('fleet.ambulances.delete')), canStatus=computed(()=>hasPermission('fleet.ambulance_status.update'))
const filteredUnits=computed(()=>{const q=search.value.trim().toLowerCase();return units.value.filter(u=>(!statusFilter.value||u.status===statusFilter.value)&&(!q||`${u.unit_code} ${u.plate_number} ${u.vehicle_make_model||''} ${typeLabel(u.ambulance_type)}`.toLowerCase().includes(q)))})
const typeLabel=v=>({basic_life_support:'Basic Life Support',advanced_life_support:'Advanced Life Support',patient_transport:'Patient Transport',rescue_unit:'Rescue Unit'}[v]||v)
const statusLabel=v=>({available:'Available',reserved:'Reserved',dispatched:'Dispatched',on_scene:'On scene',transporting:'Transporting',returning:'Returning',maintenance:'Maintenance',offline:'Offline'}[v]||v||'Created')
const readinessLabel=v=>({ready:'Ready',needs_attention:'Needs attention',out_of_service:'Out of service'}[v]||'Not checked')
const formatDate=v=>v?new Date(`${String(v).slice(0,10)}T00:00:00`).toLocaleDateString([], {month:'short',day:'numeric',year:'numeric'}):'Not recorded'
const formatDateTime=v=>v?new Date(String(v).replace(' ','T')).toLocaleString():'—'
const relativeDate=v=>{if(!v)return'—';const d=new Date(String(v).replace(' ','T')),ms=Date.now()-d.getTime();if(ms<60000)return'Just now';if(ms<3600000)return`${Math.floor(ms/60000)}m ago`;if(ms<86400000)return`${Math.floor(ms/3600000)}h ago`;return formatDateTime(v)}
const Stat=defineComponent({props:{label:String,value:[String,Number],icon:String,hint:String},setup(p){return()=>h('article',{class:'rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943]'},[h('div',{class:'flex items-start justify-between'},[h('span',{class:'grid h-10 w-10 place-items-center rounded-2xl bg-base-200 text-slate-500 dark:bg-white/5 dark:text-white/40'},[h(Icon,{icon:p.icon,width:19})]),h('span',{class:'text-2xl font-black text-slate-950 dark:text-white'},String(p.value??0))]),h('p',{class:'mt-4 text-sm font-black text-slate-800 dark:text-white/70'},p.label),h('p',{class:'mt-1 text-xs leading-5 text-slate-400 dark:text-white/30'},p.hint)])}})
const StatusPill=defineComponent({props:{value:String},setup(p){return()=>h('span',{class:['inline-flex rounded-full px-2.5 py-1 text-[0.64rem] font-bold',p.value==='available'?'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300':p.value==='maintenance'?'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300':p.value==='offline'?'bg-slate-200 text-slate-600 dark:bg-white/10 dark:text-white/45':'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300']},statusLabel(p.value))}})
const ReadinessPill=defineComponent({props:{value:String},setup(p){return()=>h('span',{class:['inline-flex rounded-full px-2.5 py-1 text-[0.64rem] font-bold',p.value==='ready'?'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300':p.value==='out_of_service'?'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300':p.value==='needs_attention'?'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300':'bg-base-200 text-slate-500 dark:bg-white/5 dark:text-white/40']},readinessLabel(p.value))}})
const Info=defineComponent({props:{label:String,value:String},setup(p){return()=>h('div',{class:'rounded-2xl bg-base-200 p-3.5 dark:bg-white/[0.035]'},[h('p',{class:'text-[0.62rem] font-black uppercase tracking-wide text-slate-400'},p.label),h('p',{class:'mt-1 text-xs font-bold text-slate-700 dark:text-white/60'},p.value||'—')])}})

async function load(){loading.value=true;errorMessage.value='';try{const r=await fetchFleetUnits();units.value=r.units||[];stats.value=r.stats||stats.value}catch(e){errorMessage.value=e?.response?.data?.message||'Could not load the organization fleet.'}finally{loading.value=false}}
function openCreate(){form.value=emptyForm();formOpen.value=true}
function normalizeForm(u){return{id:u.id,unit_code:u.unit_code||'',plate_number:u.plate_number||'',vehicle_make_model:u.vehicle_make_model||'',model_year:u.model_year||'',ambulance_type:u.ambulance_type||'basic_life_support',capacity:u.capacity||1,registration_expiry:u.registration_expiry||'',inspection_expiry:u.inspection_expiry||'',capability_notes:u.capability_notes||''}}
async function saveUnit(){saving.value=true;try{const r=form.value.id?await updateFleetUnit(form.value):await createFleetUnit(form.value);toast.success(r.message);formOpen.value=false;await load();if(form.value.id&&detailOpen.value)await openDetail(form.value.id)}catch(e){toast.error(e?.response?.data?.message||'Could not save the ambulance.')}finally{saving.value=false}}
async function openDetail(id){detailOpen.value=true;detailLoading.value=true;try{detail.value=await fetchFleetUnit(id)}catch(e){toast.error(e?.response?.data?.message||'Could not load ambulance details.');detailOpen.value=false}finally{detailLoading.value=false}}
function openEditFromDetail(){form.value=normalizeForm(detail.value.unit);formOpen.value=true}
function openStatus(){statusForm.value={status:detail.value.unit.status==='available'?'available':'offline',reason:''};statusOpen.value=true}
async function saveStatus(){statusSaving.value=true;try{const r=await updateFleetUnitStatus(detail.value.unit.id,statusForm.value.status,statusForm.value.reason);toast.success(r.message);statusOpen.value=false;await load();await openDetail(detail.value.unit.id)}catch(e){toast.error(e?.response?.data?.message||'Could not update status.')}finally{statusSaving.value=false}}
async function archiveCurrent(){const u=detail.value?.unit;if(!u)return;const ok=await alert.confirm(`Archive ${u.unit_code} from the active fleet? This keeps its history but removes it from operational lists.`, 'Archive ambulance');if(!ok)return;try{const r=await archiveFleetUnit(u.id,'Archived by organization fleet administrator.');toast.success(r.message);detailOpen.value=false;await load()}catch(e){toast.error(e?.response?.data?.message||'Could not archive this ambulance.')}}
onMounted(load)
</script>
