<template>
  <section class="min-h-screen bg-base-200 px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[1500px] space-y-5">
      <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-[.68rem] font-black uppercase tracking-[.16em] text-red-600">Dispatch</p><h1 class="mt-1 flex flex-wrap items-center gap-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white">Active Missions <LiveBadge :live="live" /></h1><p class="mt-2 max-w-3xl text-sm text-slate-500 dark:text-white/45">Follow assigned incidents from acknowledgement through response, arrival, transport, and completion.</p></div><button class="inline-flex items-center gap-2 rounded-xl border border-base-300 bg-base-100 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-base-200 dark:border-white/10 dark:bg-[#0d2943] dark:text-white/65" @click="load"><Icon icon="lucide:refresh-cw" width="16" :class="loading?'animate-spin':''"/>Refresh</button></header>

      <div v-if="loading" class="grid place-items-center rounded-3xl border border-base-300 bg-base-100 py-20 dark:border-white/10 dark:bg-[#0d2943]"><span class="h-8 w-8 animate-spin rounded-full border-2 border-red-600 border-t-transparent"></span></div>
      <div v-else-if="error" class="rounded-3xl border border-red-200 bg-red-50 p-8 text-center text-sm font-semibold text-red-700">{{ error }}</div>
      <div v-else class="grid gap-5 xl:grid-cols-[390px_minmax(0,1fr)]">
        <aside class="overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#0d2943]"><div class="border-b border-base-300 p-4 dark:border-white/10"><h2 class="text-sm font-black text-slate-900 dark:text-white">Mission Queue</h2><p class="mt-1 text-xs text-slate-400">{{ activeCount }} active · {{ completedCount }} completed</p></div><div class="max-h-[680px] overflow-y-auto p-2"><button v-for="m in missions" :key="m.id" class="mb-1 w-full rounded-2xl border p-3 text-left transition" :class="selected?.id===m.id?'border-red-200 bg-red-50 dark:border-red-500/20 dark:bg-red-500/10':'border-transparent hover:border-base-300 hover:bg-base-200 dark:hover:border-white/10 dark:hover:bg-white/[.03]'" @click="selectedId=m.id"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-black text-slate-900 dark:text-white">{{m.reference_number}}</p><p class="mt-1 text-xs text-slate-500 dark:text-white/45">{{m.unit_code}} · {{m.category_name}}</p><p class="mt-1 text-[.65rem] text-slate-400">{{m.barangay||'Dasmariñas'}} · {{m.crew.length}} crew<span v-if="m.linked_count" class="ml-1 font-black text-red-600 dark:text-red-300">· +{{m.linked_count}} linked</span></p></div><span class="rounded-full px-2 py-1 text-[.58rem] font-black uppercase" :class="missionClass(m.assignment_status)">{{ pretty(m.assignment_status) }}</span></div></button><div v-if="!missions.length" class="p-10 text-center text-sm text-slate-400">No missions yet.</div></div></aside>

        <main v-if="selected" class="overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
          <header class="border-b border-base-300 p-5 dark:border-white/10 sm:p-6"><div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><div class="flex flex-wrap items-center gap-2"><h2 class="text-xl font-black text-slate-950 dark:text-white">{{selected.reference_number}}</h2><span class="rounded-full px-2.5 py-1 text-[.62rem] font-black uppercase" :class="missionClass(selected.assignment_status)">{{pretty(selected.assignment_status)}}</span></div><p class="mt-2 text-sm text-slate-500 dark:text-white/45">{{selected.category_name}} · {{selected.address_text}}</p></div><div class="rounded-2xl bg-base-200 px-4 py-3"><p class="text-sm font-black text-slate-800 dark:text-white/70">{{selected.unit_code}}</p><p class="mt-1 text-[.62rem] text-slate-400">{{pretty(selected.ambulance_type)}} · {{selected.plate_number}}</p></div></div></header>

          <div class="space-y-6 p-5 sm:p-6">
            <section><h3 class="text-sm font-black text-slate-900 dark:text-white">Mission progress</h3><div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6"><div v-for="step in steps" :key="step.key" class="rounded-2xl border p-3 text-center" :class="stepReached(step.key)?'border-red-200 bg-red-50 dark:border-red-500/20 dark:bg-red-500/10':'border-base-300 bg-base-200/30 dark:border-white/10 dark:bg-white/[.02]'"><span class="mx-auto grid h-8 w-8 place-items-center rounded-full" :class="stepReached(step.key)?'bg-red-600 text-white':'bg-base-200 text-slate-400'"><Icon :icon="step.icon" width="14"/></span><p class="mt-2 text-[.6rem] font-black uppercase tracking-wide" :class="stepReached(step.key)?'text-red-700 dark:text-red-300':'text-slate-400'">{{step.label}}</p></div></div></section>

            <div class="grid gap-5 lg:grid-cols-2"><section class="rounded-2xl border border-base-300 bg-base-200/35 p-4 dark:border-white/10 dark:bg-white/[.02]"><h3 class="text-sm font-black text-slate-900 dark:text-white">Incident & requester</h3><div class="mt-3 space-y-2 text-xs"><Row label="Severity" :value="pretty(selected.severity)"/><Row label="Requester" :value="selected.requester_name||'Guest requester'"/><Row label="Phone" :value="selected.requester_phone"/><Row label="Address" :value="selected.address_text"/></div></section><section class="rounded-2xl border border-base-300 bg-base-200/35 p-4 dark:border-white/10 dark:bg-white/[.02]"><h3 class="text-sm font-black text-slate-900 dark:text-white">Assigned crew</h3><div class="mt-3 space-y-2"><div v-for="c in selected.crew" :key="c.organization_member_id" class="flex items-center justify-between gap-3 rounded-xl bg-base-100 px-3 py-2 dark:bg-white/5"><div><p class="text-xs font-black text-slate-700 dark:text-white/60">{{c.first_name}} {{c.last_name}}</p><p class="mt-0.5 text-[.61rem] text-slate-400">{{pretty(c.crew_role)}} · {{c.employee_code||'No employee code'}}</p></div><span class="text-[.59rem] font-black uppercase text-slate-400">{{pretty(c.response_status)}}</span></div></div></section></div>

            <section class="rounded-2xl border border-base-300 bg-base-200/35 p-4 dark:border-white/10 dark:bg-white/[.02]"><div class="flex flex-wrap gap-2"><RouterLink to="/organization/tracking" class="rounded-xl bg-base-100 px-3 py-2 text-xs font-black text-slate-700 no-underline hover:bg-base-200 dark:bg-white/5 dark:text-white/60"><Icon icon="lucide:map-pinned" width="14" class="mr-1 inline"/>Live Tracking</RouterLink><a v-if="canEditIncident && selected.assignment_status!=='completed'" href="#" role="button" class="rounded-xl bg-base-100 px-3 py-2 text-xs font-black text-slate-700 no-underline hover:bg-base-200 dark:bg-white/5 dark:text-white/60" @click.prevent="openEdit"><Icon icon="lucide:pencil" width="14" class="mr-1 inline"/>Edit Details</a><RouterLink to="/organization/assessments" class="rounded-xl bg-base-100 px-3 py-2 text-xs font-black text-slate-700 no-underline hover:bg-base-200 dark:bg-white/5 dark:text-white/60"><Icon icon="lucide:stethoscope" width="14" class="mr-1 inline"/>Assessment</RouterLink><RouterLink to="/organization/handoffs" class="rounded-xl bg-base-100 px-3 py-2 text-xs font-black text-slate-700 no-underline hover:bg-base-200 dark:bg-white/5 dark:text-white/60"><Icon icon="lucide:handshake" width="14" class="mr-1 inline"/>Facility Handoff</RouterLink><RouterLink to="/organization/field-reports" class="rounded-xl bg-base-100 px-3 py-2 text-xs font-black text-slate-700 no-underline hover:bg-base-200 dark:bg-white/5 dark:text-white/60"><Icon icon="lucide:notebook-pen" width="14" class="mr-1 inline"/>Field Report</RouterLink></div></section>

            <LinkedReportsPanel v-if="linkedReports.length" :reports="linkedReports" :can-unmerge="canEditIncident && selected.assignment_status!=='completed'" :busy-id="unmergingId" @unmerge="unmergeLinked" />

            <section v-if="nextActions.length" class="rounded-2xl border border-red-200 bg-red-50/70 p-4 dark:border-red-500/20 dark:bg-red-500/10"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="text-sm font-black text-red-800 dark:text-red-200">Next mission action</h3><p class="mt-1 text-xs text-red-700/70 dark:text-red-200/60">Status updates are recorded in the incident, assignment, ambulance, and citizen timeline.</p></div><div class="flex flex-wrap gap-2"><button v-for="a in nextActions" :key="a.status" :disabled="saving" class="rounded-xl bg-red-600 px-4 py-2.5 text-xs font-black text-white hover:bg-red-700 disabled:opacity-50" @click="advance(a)"><Icon :icon="a.icon" width="14" class="mr-1 inline"/>{{a.label}}</button></div></div></section>
            <section v-else-if="selected.assignment_status==='completed'" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">Mission completed. Crew availability has been released; the ambulance is marked Returning and must be returned to service through Fleet.</section>
          </div>
        </main>
        <main v-else class="grid min-h-[500px] place-items-center rounded-3xl border border-dashed border-base-300 bg-base-100 p-10 text-center dark:border-white/10 dark:bg-[#0d2943]"><p class="text-sm font-bold text-slate-400">Choose a mission to view its operational status.</p></main>
      </div>
    </div>

    <!-- Edit incident details — same modal as the Incident Records page -->
    <div v-if="editOpen" class="fixed inset-0 z-[95] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" @click.self="editOpen=false">
      <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-3xl border border-base-300 bg-base-100 shadow-2xl dark:border-white/10 dark:bg-[#071829]">
        <header class="flex items-start justify-between border-b border-base-300 p-5 dark:border-white/10 sm:p-6">
          <div>
            <p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">{{ editForm.reference_number }}</p>
            <h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">Edit Incident Details</h2>
            <p class="mt-1 text-xs text-slate-400">Fill in what the requester couldn't — leave anything you don't know blank.</p>
          </div>
          <button class="rounded-xl p-2 text-slate-400 hover:bg-base-200 dark:hover:bg-white/5" @click="editOpen=false"><Icon icon="lucide:x" width="18"/></button>
        </header>

        <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
          <label class="block"><span :class="labelClass">Requester name</span><input v-model.trim="editForm.requester_name" :class="fieldClass" placeholder="Juan Dela Cruz" /></label>
          <label class="block"><span :class="labelClass">Contact number</span><input v-model.trim="editForm.requester_phone" :class="fieldClass" placeholder="09XXXXXXXXX" /></label>
          <label class="block"><span :class="labelClass">Barangay</span><input v-model.trim="editForm.barangay" :class="fieldClass" placeholder="Barangay" /></label>
          <label class="block"><span :class="labelClass">Landmark</span><input v-model.trim="editForm.landmark" :class="fieldClass" placeholder="Nearest known landmark" /></label>
          <label class="block sm:col-span-2"><span :class="labelClass">Address</span><input v-model.trim="editForm.address_text" :class="fieldClass" placeholder="House/unit number, street, subdivision" /></label>
          <label class="block sm:col-span-2"><span :class="labelClass">Situation description</span><textarea v-model.trim="editForm.description" rows="4" :class="fieldClass" placeholder="What's happening — visible injuries, hazards, anything the crew should know."></textarea></label>
          <p v-if="phoneInvalid" class="sm:col-span-2 -mt-1 text-xs text-red-500">Enter a valid PH mobile number (09XXXXXXXXX or +639XXXXXXXXX), or leave it blank.</p>
        </div>

        <footer class="flex items-center justify-end gap-2 border-t border-base-300 p-5 dark:border-white/10 sm:p-6">
          <button class="rounded-xl border border-base-300 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-base-200 dark:border-white/10 dark:text-white/60" @click="editOpen=false">Cancel</button>
          <button class="rounded-xl bg-[#1976D2] px-5 py-2.5 text-sm font-bold text-white hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed" :disabled="savingEdit||phoneInvalid" @click="saveEdit">
            <Icon v-if="savingEdit" icon="lucide:loader-2" width="14" class="mr-1 inline animate-spin"/>{{ savingEdit ? 'Saving…' : 'Save details' }}
          </button>
        </footer>
      </div>
    </div>
  </section>
</template>
<script setup>
import { computed, defineComponent, h, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { fetchOrganizationLinkedReports, fetchOrganizationMissions, unmergeOrganizationReport, updateMissionStatus } from '@/services/dispatchOperations'
import LinkedReportsPanel from '@/components/incidents/LinkedReportsPanel.vue'
import { updateIncidentDetails } from '@/services/organizationCompletion'
import { useAlert } from '@/composables/useAlert'
import { useToast } from '@/composables/useToast'
import { useSession } from '@/composables/useSession'
import { useLiveUpdates } from '@/composables/useLiveUpdates'
import { realtimeChannels } from '@/services/realtime'
import LiveBadge from '@/components/realtime/LiveBadge.vue'
const alert=useAlert(),toast=useToast(),{ hasPermission }=useSession(),missions=ref([]),selectedId=ref(null),loading=ref(false),error=ref(''),saving=ref(false),canDispatch=ref(false),canMission=ref(false),currentMemberId=ref(null)
const canEditIncident=computed(()=>hasPermission('incidents.emergency_requests.update'))

// --- Edit incident details (same capability as the Incident Records page) ---
const fieldClass='w-full rounded-xl border border-base-300 bg-base-100 px-3.5 py-2.5 text-sm text-slate-800 outline-none transition focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/10 dark:border-white/10 dark:bg-[#071829] dark:text-white'
const labelClass='mb-1.5 block text-[0.68rem] font-black uppercase tracking-wide text-slate-400 dark:text-white/30'
const PLACEHOLDERS=['Unspecified','Pinned location (no address provided)','EMERGENCY REQUEST. No details provided — confirm with requester on contact.']
const unplaceholder=v=>PLACEHOLDERS.includes((v||'').trim())?'':(v||'')
const editOpen=ref(false),savingEdit=ref(false)
const editForm=reactive({request_id:null,reference_number:'',requester_name:'',requester_phone:'',barangay:'',address_text:'',landmark:'',description:''})
const phoneInvalid=computed(()=>{const p=(editForm.requester_phone||'').replace(/[\s-]/g,'');return p!==''&&!/^(\+639\d{9}|09\d{9})$/.test(p)})
function openEdit(){const m=selected.value;if(!m)return;editForm.request_id=m.emergency_request_id;editForm.reference_number=m.reference_number;editForm.requester_name=m.requester_name==='Guest requester'?'':(m.requester_name||'');editForm.requester_phone=m.requester_phone||'';editForm.barangay=unplaceholder(m.barangay);editForm.address_text=unplaceholder(m.address_text);editForm.landmark=m.landmark||'';editForm.description=unplaceholder(m.description);editOpen.value=true}
async function saveEdit(){if(phoneInvalid.value||savingEdit.value)return;savingEdit.value=true;try{await updateIncidentDetails({emergency_request_id:editForm.request_id,requester_name:editForm.requester_name,requester_phone:editForm.requester_phone,barangay:editForm.barangay,address_text:editForm.address_text,landmark:editForm.landmark,description:editForm.description});toast.success('Incident details updated.','Saved');editOpen.value=false;await load()}catch(e){toast.error(e?.response?.data?.message||e.message||'Unable to update incident details.','Error')}finally{savingEdit.value=false}}
const selected=computed(()=>missions.value.find(m=>m.id===selectedId.value)||missions.value[0]||null);const activeCount=computed(()=>missions.value.filter(m=>m.assignment_status!=='completed').length);const completedCount=computed(()=>missions.value.filter(m=>m.assignment_status==='completed').length)
const steps=[{key:'assigned',label:'Assigned',icon:'lucide:user-round-check'},{key:'acknowledged',label:'Acknowledged',icon:'lucide:badge-check'},{key:'responding',label:'En Route',icon:'lucide:navigation'},{key:'on_scene',label:'On Scene',icon:'lucide:map-pin-check'},{key:'transporting',label:'Transporting',icon:'lucide:ambulance'},{key:'completed',label:'Completed',icon:'lucide:circle-check-big'}]
// --- Duplicate reports dedup linked to the selected incident ---
const linkedReports=ref([]),unmergingId=ref(null)
async function loadLinked(){const m=selected.value;if(!m||!m.linked_count){linkedReports.value=[];return}try{linkedReports.value=await fetchOrganizationLinkedReports(m.emergency_request_id)}catch{linkedReports.value=[]}}
watch(()=>[selected.value?.emergency_request_id,selected.value?.linked_count],loadLinked)
async function unmergeLinked({report,reason}){unmergingId.value=report.id;try{const d=await unmergeOrganizationReport(report.id,reason);toast.success(d.message,'Report unlinked');await load();await loadLinked()}catch(e){toast.error(e?.response?.data?.message||e.message||'Unable to unlink this report.','Error')}finally{unmergingId.value=null}}
const order=steps.map(s=>s.key);const isAssignedCrew=computed(()=>selected.value?.crew?.some(c=>c.organization_member_id===currentMemberId.value));const canAct=computed(()=>canDispatch.value||(canMission.value&&isAssignedCrew.value))
const nextActions=computed(()=>{if(!selected.value||!canAct.value)return[];const s=selected.value.assignment_status;return {assigned:[{status:'acknowledged',label:'Acknowledge Mission',icon:'lucide:badge-check'}],acknowledged:[{status:'responding',label:'Start Response',icon:'lucide:navigation'}],responding:[{status:'on_scene',label:'Arrived On Scene',icon:'lucide:map-pin-check'}],on_scene:[{status:'transporting',label:'Start Transport',icon:'lucide:ambulance'},{status:'completed',label:'Complete Without Transport',icon:'lucide:circle-check-big'}],transporting:[{status:'completed',label:'Complete Mission',icon:'lucide:circle-check-big'}]}[s]||[]})
const Row=defineComponent({props:{label:String,value:[String,Number]},setup(p){return()=>h('div',{class:'flex items-start justify-between gap-4 border-b border-base-300/70 pb-2 last:border-0 last:pb-0 dark:border-white/5'},[h('span',{class:'font-bold text-slate-400'},p.label),h('span',{class:'max-w-[65%] text-right font-semibold text-slate-700 dark:text-white/55'},p.value||'—')])}})
async function load(){loading.value=missions.value.length===0;error.value='';try{const d=await fetchOrganizationMissions();missions.value=d.missions||[];canDispatch.value=!!d.can_dispatch;canMission.value=!!d.can_update_mission;currentMemberId.value=d.current_member_id;if(!selectedId.value||!missions.value.some(m=>m.id===selectedId.value))selectedId.value=missions.value[0]?.id||null}catch(e){error.value=e?.response?.data?.message||e.message||'Unable to load missions.'}finally{loading.value=false}}
function stepReached(k){if(!selected.value)return false;return order.indexOf(k)<=order.indexOf(selected.value.assignment_status)}
async function advance(a){const ok=await alert.confirm(`${a.label} for ${selected.value.reference_number}?`,'Update mission status');if(!ok)return;saving.value=true;try{const d=await updateMissionStatus(selected.value.id,a.status);alert.success(d.message);await load()}catch(e){alert.error(e?.response?.data?.message||e.message||'Unable to update mission.')}finally{saving.value=false}}
const pretty=v=>String(v||'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());function missionClass(v){return {assigned:'bg-slate-100 text-slate-600',acknowledged:'bg-blue-50 text-blue-700',responding:'bg-amber-50 text-amber-700',on_scene:'bg-orange-50 text-orange-700',transporting:'bg-violet-50 text-violet-700',completed:'bg-emerald-50 text-emerald-700'}[v]||'bg-base-200 text-slate-500'}
// Live updates: assignments, status changes, handoffs, linked reports and
// detail edits on this org's incidents re-load the list instantly (GPS pings
// on the org channel are ignored). Polls every 15 s without live updates.
const { live }=useLiveUpdates(load,{channels:()=>[realtimeChannels.value?.org],onEvent:msg=>msg.name==='ambulance.location'?false:undefined})
onMounted(()=>{load()})
</script>
