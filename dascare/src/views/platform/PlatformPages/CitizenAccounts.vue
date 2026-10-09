<template>
  <section class="min-h-screen bg-base-200 pb-10 dark:bg-[#050e1a]">
    <section class="relative overflow-hidden border-b border-base-300 bg-base-100 dark:border-white/10 dark:bg-[#071829]">
      <div class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-30" style="background:radial-gradient(ellipse at top left,rgba(220,38,38,.06),transparent 55%)"></div>
      <div class="relative mx-auto flex max-w-7xl flex-wrap items-end justify-between gap-4 px-4 py-6 sm:px-6 lg:px-8">
        <div>
          <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 font-mono text-[.65rem] font-semibold uppercase tracking-[.15em] text-red-700 dark:bg-red-500/10 dark:text-red-300"><span class="h-1.5 w-1.5 rounded-full bg-red-600"></span>Platform Executive</span>
          <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900 dark:text-white">Citizen Accounts</h1>
          <p class="mt-1 text-sm text-slate-500 dark:text-white/45">Review registered citizens, account status, verification, and request activity.</p>
        </div>
        <button class="inline-flex items-center gap-2 rounded-xl border border-base-300 bg-base-100 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-base-200 disabled:opacity-50 dark:border-white/10 dark:bg-white/5 dark:text-white/60" :disabled="loading" @click="load"><Icon icon="lucide:refresh-cw" width="15" :class="loading?'animate-spin':''"/>Refresh</button>
      </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <StatCard label="Registered citizens" :value="stats.total" icon="lucide:users" />
        <StatCard label="Active" :value="stats.active" icon="lucide:user-check" tone="green" />
        <StatCard label="Verified KYC" :value="stats.verified" icon="lucide:badge-check" tone="blue" />
        <StatCard label="Suspended" :value="stats.suspended" icon="lucide:user-x" tone="amber" />
        <StatCard label="Disabled" :value="stats.disabled" icon="lucide:ban" tone="red" />
      </div>

      <section class="mt-6 overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#071829]">
        <div class="grid gap-3 border-b border-base-300 p-4 dark:border-white/10 md:grid-cols-[1fr_auto_auto] md:items-center sm:p-5">
          <label class="relative block">
            <Icon icon="lucide:search" width="15" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"/>
            <input v-model="searchInput" type="search" placeholder="Search citizen name, email, or phone..." class="w-full rounded-xl border border-base-300 bg-base-100 py-2.5 pl-10 pr-4 text-sm outline-none focus:border-red-300 focus:ring-2 focus:ring-red-100 dark:border-white/10 dark:bg-white/5 dark:text-white" @keyup.enter="applyFilters"/>
          </label>
          <select v-model="status" class="rounded-xl border border-base-300 bg-base-100 px-3.5 py-2.5 text-sm font-semibold text-slate-600 outline-none dark:border-white/10 dark:bg-white/5 dark:text-white/60" @change="applyFilters">
            <option value="all">All account statuses</option><option value="active">Active</option><option value="suspended">Suspended</option><option value="disabled">Disabled</option><option value="pending">Pending</option>
          </select>
          <select v-model="kyc" class="rounded-xl border border-base-300 bg-base-100 px-3.5 py-2.5 text-sm font-semibold text-slate-600 outline-none dark:border-white/10 dark:bg-white/5 dark:text-white/60" @change="applyFilters">
            <option value="all">All KYC states</option><option value="approved">Verified</option><option value="pending">Pending review</option><option value="rejected">Rejected</option><option value="resubmission">Needs resubmission</option><option value="unverified">Unverified</option>
          </select>
        </div>

        <div v-if="loading" class="grid place-items-center py-20"><span class="h-8 w-8 animate-spin rounded-full border-4 border-red-600 border-t-transparent"></span></div>
        <div v-else-if="errorMessage" class="px-5 py-16 text-center"><Icon icon="lucide:triangle-alert" width="28" class="mx-auto text-red-400"/><p class="mt-2 text-sm font-semibold text-slate-500">{{ errorMessage }}</p><button class="mt-3 text-xs font-bold text-red-600 hover:underline" @click="load">Try again</button></div>
        <div v-else-if="items.length" class="overflow-x-auto">
          <table class="w-full min-w-[1080px] text-left">
            <thead class="bg-base-200/70 text-[.66rem] font-black uppercase tracking-[.08em] text-slate-400 dark:bg-white/[.025] dark:text-white/30"><tr><th class="px-5 py-3.5">Citizen</th><th class="px-5 py-3.5">Verification</th><th class="px-5 py-3.5">Requests</th><th class="px-5 py-3.5">Last login</th><th class="px-5 py-3.5">Account</th><th class="px-5 py-3.5 text-right">Manage</th></tr></thead>
            <tbody class="divide-y divide-base-300 dark:divide-white/5">
              <tr v-for="citizen in items" :key="citizen.id" class="hover:bg-base-200/60 dark:hover:bg-base-100/[.025]">
                <td class="px-5 py-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-2xl bg-red-50 text-xs font-black text-red-700 dark:bg-red-500/10 dark:text-red-300">{{ initials(citizen) }}</span><div><p class="text-sm font-black text-slate-800 dark:text-white/80">{{ citizen.name }}</p><p class="mt-0.5 text-xs text-slate-400">{{ citizen.email }}</p><p class="mt-0.5 text-[.68rem] text-slate-400">{{ citizen.phone }}</p></div></div></td>
                <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-[.65rem] font-bold" :class="kycClass(citizen.kyc_status)">{{ kycLabel(citizen.kyc_status) }}</span></td>
                <td class="px-5 py-4"><p class="text-sm font-black text-slate-700 dark:text-white/65">{{ citizen.request_count }}</p><p class="text-[.68rem] text-slate-400">{{ citizen.active_request_count }} active</p></td>
                <td class="px-5 py-4 text-xs text-slate-500 dark:text-white/45">{{ formatDate(citizen.last_login_at) }}</td>
                <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-[.65rem] font-bold" :class="accountClass(citizen.account_status)">{{ accountLabel(citizen.account_status) }}</span></td>
                <td class="px-5 py-4 text-right"><button class="inline-flex items-center gap-1.5 rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-xs font-bold text-slate-600 hover:border-red-200 hover:bg-red-50 hover:text-red-700 dark:border-white/10 dark:bg-white/5 dark:text-white/55" @click="openManage(citizen)"><Icon icon="lucide:settings-2" width="14"/>Manage</button></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="px-5 py-20 text-center"><Icon icon="lucide:users" width="30" class="mx-auto text-slate-300"/><p class="mt-2 text-sm font-semibold text-slate-500">No citizen accounts match the current filters.</p></div>
        <div v-if="!loading && pagination.total" class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 px-5 py-4 dark:border-white/10"><p class="text-xs text-slate-400">Page {{ pagination.page }} of {{ pagination.pages }} · {{ pagination.total }} citizens</p><div class="flex gap-2"><button class="rounded-lg border border-base-300 px-3 py-1.5 text-xs font-bold text-slate-500 disabled:opacity-40" :disabled="pagination.page<=1" @click="goPage(pagination.page-1)">Previous</button><button class="rounded-lg border border-base-300 px-3 py-1.5 text-xs font-bold text-slate-500 disabled:opacity-40" :disabled="pagination.page>=pagination.pages" @click="goPage(pagination.page+1)">Next</button></div></div>
      </section>
    </div>

    <Teleport to="body">
      <div v-if="manageOpen" class="fixed inset-0 z-[90] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm" @click.self="closeManage">
        <section class="w-full max-w-xl overflow-hidden rounded-[28px] border border-base-300 bg-base-100 shadow-2xl dark:border-white/10 dark:bg-[#071829]">
          <header class="flex items-start justify-between gap-4 border-b border-base-300 p-5 dark:border-white/10 sm:p-6"><div><p class="text-[.66rem] font-black uppercase tracking-[.14em] text-red-600">Citizen management</p><h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">{{ selected?.name }}</h2><p class="mt-1 text-xs text-slate-400">{{ selected?.email }} · {{ selected?.phone }}</p></div><button class="rounded-xl p-2 text-slate-400 hover:bg-base-200" @click="closeManage"><Icon icon="lucide:x" width="18"/></button></header>
          <div class="space-y-5 p-5 sm:p-6">
            <div class="grid gap-3 sm:grid-cols-3"><Mini label="Account" :value="accountLabel(selected?.account_status)"/><Mini label="KYC" :value="kycLabel(selected?.kyc_status)"/><Mini label="Requests" :value="String(selected?.request_count ?? 0)"/></div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 text-xs leading-5 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200"><strong>Emergency-access rule:</strong> suspending or disabling a registered account does not block emergency assistance; the person may still use the public guest request flow.</div>
            <label v-if="selected?.account_status==='active'" class="block"><span class="mb-1.5 block text-[.68rem] font-black uppercase tracking-wide text-slate-400">Administrative reason *</span><textarea v-model.trim="reason" rows="4" maxlength="1000" class="w-full resize-none rounded-2xl border border-base-300 bg-base-100 px-3.5 py-3 text-sm outline-none focus:border-red-300 focus:ring-2 focus:ring-red-100 dark:border-white/10 dark:bg-white/5 dark:text-white" placeholder="Explain why this account is being restricted..."></textarea></label>
          </div>
          <footer class="flex flex-wrap justify-end gap-2 border-t border-base-300 p-5 dark:border-white/10 sm:p-6"><button class="rounded-xl border border-base-300 px-4 py-2.5 text-sm font-bold text-slate-600" @click="closeManage">Cancel</button><template v-if="selected?.account_status==='active'"><button class="rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50" :disabled="saving" @click="changeStatus('suspend')"><Icon icon="lucide:pause" class="mr-1 inline" width="15"/>Suspend</button><button class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50" :disabled="saving" @click="changeStatus('disable')"><Icon icon="lucide:ban" class="mr-1 inline" width="15"/>Disable</button></template><button v-else-if="['suspended','disabled'].includes(selected?.account_status)" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50" :disabled="saving" @click="changeStatus('reactivate')"><Icon icon="lucide:rotate-ccw" class="mr-1 inline" width="15"/>Reactivate</button></footer>
        </section>
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { defineComponent, h, onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import { fetchPlatformCitizens, updatePlatformCitizenStatus } from '@/services/platformCitizens'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'

const toast=useToast(), alert=useAlert()
const loading=ref(true), errorMessage=ref(''), items=ref([]), searchInput=ref(''), search=ref(''), status=ref('all'), kyc=ref('all')
const stats=ref({total:0,active:0,verified:0,suspended:0,disabled:0}), pagination=ref({page:1,per_page:20,total:0,pages:1})
const manageOpen=ref(false), selected=ref(null), reason=ref(''), saving=ref(false)
const StatCard=defineComponent({props:{label:String,value:[Number,String],icon:String,tone:String},setup(p){const tones={green:'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300',blue:'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300',amber:'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300',red:'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300'};return()=>h('article',{class:'rounded-2xl border border-base-300 bg-base-100 p-4 dark:border-white/10 dark:bg-[#071829]'},[h('div',{class:'flex items-start justify-between'},[h('div',{},[h('p',{class:'text-2xl font-black text-slate-900 dark:text-white'},String(p.value??0)),h('p',{class:'mt-1 text-[.68rem] font-semibold text-slate-400'},p.label)]),h('span',{class:['grid h-9 w-9 place-items-center rounded-xl',tones[p.tone]||'bg-base-200 text-slate-500']},[h(Icon,{icon:p.icon,width:16})])])])}})
const Mini=defineComponent({props:{label:String,value:String},setup(p){return()=>h('div',{class:'rounded-2xl border border-base-300 bg-base-200/60 p-3'},[h('p',{class:'text-[.62rem] font-black uppercase tracking-wide text-slate-400'},p.label),h('p',{class:'mt-1 text-sm font-black text-slate-800 dark:text-white/70'},p.value||'—')])}})
const initials=c=>`${c.first_name?.[0]||''}${c.last_name?.[0]||''}`.toUpperCase()
const formatDate=v=>v?new Date(String(v).replace(' ','T')).toLocaleString('en-PH',{month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'}):'Never'
const kycLabel=s=>({0:'Unverified',1:'Pending',2:'Verified',3:'Rejected',4:'Needs resubmission'}[Number(s)]||'Unverified')
const kycClass=s=>Number(s)===2?'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300':Number(s)===1?'bg-amber-100 text-amber-700':Number(s)===3?'bg-red-100 text-red-700':'bg-base-200 text-slate-600'
const accountLabel=s=>({active:'Active',suspended:'Suspended',disabled:'Disabled',pending:'Pending'}[s]||s||'Unknown')
const accountClass=s=>s==='active'?'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300':s==='suspended'?'bg-amber-100 text-amber-700':s==='disabled'?'bg-red-100 text-red-700':'bg-base-200 text-slate-600'
async function load(){loading.value=true;errorMessage.value='';try{const d=await fetchPlatformCitizens({status:status.value,kyc:kyc.value,search:search.value,page:pagination.value.page,per_page:pagination.value.per_page});items.value=d.items||[];stats.value=d.stats||stats.value;pagination.value=d.pagination||pagination.value}catch(e){errorMessage.value=e?.response?.data?.message||'Could not load citizen accounts.'}finally{loading.value=false}}
function applyFilters(){search.value=searchInput.value.trim();pagination.value.page=1;load()}
function goPage(p){pagination.value.page=Math.min(Math.max(1,p),pagination.value.pages);load()}
function openManage(c){selected.value={...c};reason.value='';manageOpen.value=true}
function closeManage(){manageOpen.value=false;selected.value=null;reason.value=''}
async function changeStatus(action){if(!selected.value)return;if(['suspend','disable'].includes(action)&&reason.value.trim().length<5){toast.error('Please provide a clear administrative reason.');return}const label=action==='reactivate'?'reactivate':action;const ok=await alert.confirm(`Are you sure you want to ${label} ${selected.value.name}'s account?`,'Confirm account action');if(!ok)return;saving.value=true;try{const d=await updatePlatformCitizenStatus({user_id:selected.value.id,action,reason:reason.value.trim()});toast.success(d.message);closeManage();await load();window.dispatchEvent(new Event('notifications-updated'))}catch(e){toast.error(e?.response?.data?.message||'Could not update citizen account.')}finally{saving.value=false}}
onMounted(load)
</script>
