<template>
  <section class="min-h-screen bg-base-200 px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-5">
      <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-[0.68rem] font-black uppercase tracking-[0.16em] text-red-600 dark:text-red-300">City Operations</p>
          <h1 class="mt-1 text-3xl font-black tracking-tight text-slate-950 dark:text-white">City-wide Incidents</h1>
          <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-white/45">Monitor immediate emergency requests, inspect DSS rankings, and restart resource screening when conditions change.</p>
        </div>
        <button class="inline-flex items-center justify-center gap-2 rounded-xl border border-base-300 bg-base-100 px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-base-200 dark:border-white/10 dark:bg-[#0d2943] dark:text-white/70" @click="load">
          <Icon icon="lucide:refresh-cw" width="16" :class="loading ? 'animate-spin' : ''" /> Refresh
        </button>
      </header>

      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <article v-for="card in statCards" :key="card.label" class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
          <div class="flex items-center justify-between gap-3">
            <div><p class="text-xs font-bold text-slate-400 dark:text-white/35">{{ card.label }}</p><p class="mt-1 text-2xl font-black text-slate-950 dark:text-white">{{ card.value }}</p></div>
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon :icon="card.icon" width="18" /></span>
          </div>
        </article>
      </div>

      <section class="rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
        <div class="flex flex-col gap-3 border-b border-base-300 p-4 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between">
          <div class="relative w-full sm:max-w-md">
            <Icon icon="lucide:search" width="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input v-model.trim="search" class="w-full rounded-xl border border-base-300 bg-base-200 py-2.5 pl-9 pr-3 text-sm outline-none focus:border-red-400 dark:border-white/10 dark:bg-white/[0.04] dark:text-white" placeholder="Reference, requester, barangay…" @keyup.enter="page=1; load()" />
          </div>
          <div class="flex gap-2">
            <select v-model="status" class="rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 text-sm font-semibold text-slate-600 dark:border-white/10 dark:bg-[#071829] dark:text-white/65" @change="page=1; load()">
              <option value="active">Active incidents</option><option value="submitted">Submitted</option><option value="validating">DSS screening</option><option value="verified">Organization accepted</option><option value="assigned">Assigned</option><option value="duplicate">Linked reports</option><option value="all">All</option>
            </select>
            <button class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-700" @click="page=1; load()">Search</button>
          </div>
        </div>

        <div v-if="loading" class="grid place-items-center py-16"><span class="h-8 w-8 animate-spin rounded-full border-2 border-red-600 border-t-transparent"></span></div>
        <div v-else-if="error" class="p-10 text-center"><Icon icon="lucide:triangle-alert" width="24" class="mx-auto text-red-500" /><p class="mt-2 text-sm font-semibold text-slate-500">{{ error }}</p></div>
        <div v-else-if="!items.length" class="p-12 text-center"><Icon icon="lucide:siren" width="28" class="mx-auto text-slate-300" /><p class="mt-3 text-sm font-bold text-slate-500 dark:text-white/40">No incidents match this view.</p></div>
        <div v-else class="overflow-x-auto">
          <table class="w-full min-w-[980px] text-left text-sm">
            <thead class="bg-base-200/70 text-[0.68rem] uppercase tracking-wider text-slate-400 dark:bg-white/[0.03] dark:text-white/30"><tr><th class="px-4 py-3">Incident</th><th class="px-4 py-3">Location</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">DSS / Offer</th><th class="px-4 py-3">Submitted</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-base-300 dark:divide-white/5">
              <tr v-for="item in items" :key="item.id" class="hover:bg-base-200/50 dark:hover:bg-white/[0.025]">
                <td class="px-4 py-4"><div class="flex items-start gap-3"><span class="mt-0.5 h-2.5 w-2.5 rounded-full" :class="severityDot(item.severity)"></span><div><p class="font-black text-slate-900 dark:text-white">{{ item.reference_number }}</p><p class="mt-1 text-xs text-slate-500 dark:text-white/40">{{ item.category_name }} · {{ titleCase(item.severity) }}</p></div></div></td>
                <td class="px-4 py-4"><p class="max-w-xs truncate font-semibold text-slate-700 dark:text-white/65">{{ item.barangay || 'Dasmariñas' }}</p><p class="mt-1 max-w-xs truncate text-xs text-slate-400">{{ item.address_text }}</p></td>
                <td class="px-4 py-4">
                  <div class="flex flex-wrap gap-1.5">
                    <span v-if="item.merged_into_reference" class="rounded-full bg-base-200 px-2.5 py-1 text-[0.68rem] font-black uppercase text-slate-500 dark:bg-white/5 dark:text-white/45" title="Duplicate report linked by dedup">Linked to {{ item.merged_into_reference }}</span>
                    <span v-else class="rounded-full px-2.5 py-1 text-[0.68rem] font-black uppercase" :class="statusClass(item.status)">{{ titleCase(item.status) }}</span>
                    <span v-if="item.linked_count" class="rounded-full bg-red-50 px-2.5 py-1 text-[0.68rem] font-black uppercase text-red-600 dark:bg-red-500/10 dark:text-red-300" title="Duplicate reports linked to this incident">+{{ item.linked_count }} linked</span>
                    <span v-if="item.attention_level && item.attention_level !== 'normal'" class="rounded-full px-2.5 py-1 text-[0.68rem] font-black uppercase" :class="attentionClass(item.attention_level)">
                      {{ attentionLabel(item.attention_level) }}
                    </span>
                  </div>
                </td>
                <td class="px-4 py-4">
                  <p v-if="item.accepted_organization_name" class="text-xs font-bold text-emerald-600 dark:text-emerald-300">Accepted by {{ item.accepted_organization_name }}</p>
                  <template v-else-if="item.current_offer_organization_name"><p class="text-xs font-bold text-amber-600 dark:text-amber-300">Offered to {{ item.current_offer_organization_name }}</p><p class="mt-1 text-[0.68rem] text-slate-400">{{ item.candidate_count || 0 }} DSS candidate{{ item.candidate_count === 1 ? '' : 's' }}</p></template>
                  <p v-else-if="item.dss_run_id" class="text-xs font-semibold text-slate-500 dark:text-white/45">{{ item.candidate_count || 0 }} candidates · {{ titleCase(item.dss_status || '') }}</p>
                  <p v-else class="text-xs text-slate-400">Not screened yet</p>
                </td>
                <td class="px-4 py-4 text-xs text-slate-500 dark:text-white/40">{{ formatDate(item.submitted_at) }}</td>
                <td class="px-4 py-4 text-right"><button class="rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-base-200 dark:border-white/10 dark:bg-white/[0.03] dark:text-white/65" @click="openIncident(item)">Review</button></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="pagination.pages > 1" class="flex items-center justify-between border-t border-base-300 p-4 text-xs text-slate-500 dark:border-white/10 dark:text-white/40"><span>Page {{ pagination.page }} of {{ pagination.pages }}</span><div class="flex gap-2"><button class="rounded-lg border border-base-300 px-3 py-1.5 disabled:opacity-40 dark:border-white/10" :disabled="page<=1" @click="page--; load()">Previous</button><button class="rounded-lg border border-base-300 px-3 py-1.5 disabled:opacity-40 dark:border-white/10" :disabled="page>=pagination.pages" @click="page++; load()">Next</button></div></div>
      </section>
    </div>

    <div v-if="selectedOpen" class="fixed inset-0 z-50 bg-slate-950/55 px-4 py-6" @click.self="closeIncident">
      <div class="mx-auto max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-3xl border border-base-300 bg-base-100 shadow-2xl dark:border-white/10 dark:bg-[#071829]">
        <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-base-300 bg-base-100/95 p-5 backdrop-blur dark:border-white/10 dark:bg-[#071829]/95"><div><p class="text-xs font-black uppercase tracking-wider text-red-600">Incident Review</p><h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">{{ detail?.incident?.reference_number || 'Loading…' }}</h2></div><button class="grid h-9 w-9 place-items-center rounded-xl hover:bg-base-200 dark:hover:bg-white/5" @click="closeIncident"><Icon icon="lucide:x" width="18" /></button></div>
        <div v-if="detailLoading" class="grid place-items-center py-20"><span class="h-8 w-8 animate-spin rounded-full border-2 border-red-600 border-t-transparent"></span></div>
        <div v-else-if="detail" class="space-y-6 p-5 sm:p-6">
          <div v-if="detail.incident.attention_level && detail.incident.attention_level !== 'normal'" class="rounded-2xl border p-4" :class="detail.incident.attention_level === 'critical_overdue' ? 'border-red-300 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10' : 'border-amber-300 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10'">
            <div class="flex items-start gap-3">
              <Icon icon="lucide:triangle-alert" width="18" class="mt-0.5" :class="detail.incident.attention_level === 'critical_overdue' ? 'text-red-600' : 'text-amber-600'" />
              <div>
                <p class="font-black" :class="detail.incident.attention_level === 'critical_overdue' ? 'text-red-800 dark:text-red-200' : 'text-amber-800 dark:text-amber-200'">{{ attentionLabel(detail.incident.attention_level) }}</p>
                <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-white/55">{{ detail.incident.attention_reason || 'This emergency has remained unresolved beyond the configured operational threshold.' }}</p>
              </div>
            </div>
          </div>
          <div v-if="detail.merged_into" class="rounded-2xl border border-base-300 bg-base-200/50 p-4 dark:border-white/10 dark:bg-white/[0.03]">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div class="flex items-start gap-3">
                <Icon icon="lucide:git-merge" width="18" class="mt-0.5 text-red-600 dark:text-red-300" />
                <div>
                  <p class="font-black text-slate-900 dark:text-white">Linked to {{ detail.merged_into.reference_number }} as a duplicate report</p>
                  <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-white/55">{{ detail.merged_into.distance_m }} m from that incident · {{ titleCase(detail.merged_into.status) }} · {{ detail.merged_into.address_text }}. No separate unit is dispatched for this report.</p>
                </div>
              </div>
              <button :disabled="unmergingId === detail.incident.id" class="inline-flex w-fit flex-shrink-0 items-center gap-2 rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-base-200 disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.03] dark:text-white/65" @click="unmergeSelf"><Icon icon="lucide:split" width="14" />Unlink and dispatch separately</button>
            </div>
          </div>
          <div class="grid gap-4 lg:grid-cols-[1.2fr_.8fr]"><div class="rounded-2xl border border-base-300 bg-base-200/50 p-4 dark:border-white/10 dark:bg-white/[0.03]"><h3 class="text-sm font-black text-slate-900 dark:text-white">Incident</h3><dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-xs text-slate-400">Emergency</dt><dd class="font-bold text-slate-700 dark:text-white/65">{{ detail.incident.category_name }} · {{ titleCase(detail.incident.severity) }}</dd></div><div><dt class="text-xs text-slate-400">Requester</dt><dd class="font-bold text-slate-700 dark:text-white/65">{{ detail.incident.requester_name || 'Guest requester' }}</dd></div><div class="sm:col-span-2"><dt class="text-xs text-slate-400">Location</dt><dd class="font-bold text-slate-700 dark:text-white/65">{{ detail.incident.address_text }}</dd></div><div class="sm:col-span-2"><dt class="text-xs text-slate-400">Description</dt><dd class="text-slate-600 dark:text-white/55">{{ detail.incident.description }}</dd></div></dl></div><div class="rounded-2xl border border-base-300 p-4 dark:border-white/10"><h3 class="text-sm font-black text-slate-900 dark:text-white">DSS Cycle</h3><p class="mt-3 text-3xl font-black text-slate-950 dark:text-white">{{ detail.run?.candidate_count ?? 0 }}</p><p class="text-xs text-slate-400">eligible organization candidates</p><button v-if="canRunDss(detail.incident)" :disabled="runningDss" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50" @click="rerunDss"><Icon :icon="runningDss ? 'lucide:loader-circle' : 'lucide:brain-circuit'" width="16" :class="runningDss ? 'animate-spin' : ''" />{{ detail.run ? 'Refresh DSS ranking' : 'Run DSS ranking' }}</button></div></div>

          <LinkedReportsPanel v-if="detail.linked_reports?.length" :reports="detail.linked_reports" can-unmerge :busy-id="unmergingId" @unmerge="unmergeLinked" />

          <section><div class="flex items-center justify-between"><h3 class="text-sm font-black text-slate-900 dark:text-white">DSS Recommendations</h3><span class="text-xs text-slate-400">Advisory ranking — not autonomous dispatch</span></div><div v-if="!detail.recommendations?.length" class="mt-3 rounded-2xl border border-dashed border-base-300 p-8 text-center text-sm text-slate-400 dark:border-white/10">No dispatch-ready candidate is currently eligible.</div><div v-else class="mt-3 space-y-3"><article v-for="rec in detail.recommendations" :key="rec.id" class="rounded-2xl border border-base-300 p-4 dark:border-white/10"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><div class="flex items-center gap-2"><span class="grid h-7 w-7 place-items-center rounded-lg bg-red-50 text-xs font-black text-red-600 dark:bg-red-500/10 dark:text-red-300">#{{ rec.rank_position }}</span><p class="font-black text-slate-900 dark:text-white">{{ rec.organization_name }}</p></div><p class="mt-2 text-xs leading-5 text-slate-500 dark:text-white/45">{{ rec.explanation }}</p></div><div class="text-left sm:text-right"><p class="text-2xl font-black text-slate-950 dark:text-white">{{ Number(rec.total_score).toFixed(1) }}</p><p class="text-[0.65rem] font-bold uppercase tracking-wider text-slate-400">DSS score</p></div></div><div class="mt-3 grid grid-cols-2 gap-2 text-[0.68rem] sm:grid-cols-4"><span class="rounded-lg bg-base-200 px-2 py-1.5">Distance {{ Number(rec.distance_score).toFixed(0) }}</span><span class="rounded-lg bg-base-200 px-2 py-1.5">Availability {{ Number(rec.availability_score).toFixed(0) }}</span><span class="rounded-lg bg-base-200 px-2 py-1.5">Capability {{ Number(rec.capability_score).toFixed(0) }}</span><span class="rounded-lg bg-base-200 px-2 py-1.5">Workload {{ Number(rec.workload_score).toFixed(0) }}</span></div></article></div></section>

          <section v-if="detail.offers?.length"><h3 class="text-sm font-black text-slate-900 dark:text-white">Offer History</h3><div class="mt-3 space-y-2"><div v-for="offer in detail.offers" :key="offer.id" class="flex flex-col gap-2 rounded-xl border border-base-300 px-3 py-3 text-sm dark:border-white/10 sm:flex-row sm:items-center sm:justify-between"><div><p class="font-bold text-slate-800 dark:text-white/70">{{ offer.organization_name }}</p><p class="text-xs text-slate-400">Offered {{ formatDate(offer.offered_at) }}</p></div><span class="w-fit rounded-full px-2.5 py-1 text-[0.68rem] font-black uppercase" :class="offerClass(offer.offer_status)">{{ titleCase(offer.offer_status) }}</span></div></div></section>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import { fetchPlatformIncident, fetchPlatformIncidents, runIncidentDss, unmergePlatformReport } from '@/services/dispatchOperations'
import LinkedReportsPanel from '@/components/incidents/LinkedReportsPanel.vue'
import { useAlert } from '@/composables/useAlert'

const alert = useAlert()
const items = ref([]); const stats = ref({}); const pagination = ref({page:1,pages:1,total:0}); const loading = ref(false); const error = ref('')
const search = ref(''); const status = ref('active'); const page = ref(1)
const selectedOpen = ref(false); const detail = ref(null); const detailLoading = ref(false); const runningDss = ref(false); const unmergingId = ref(null)
const statCards = computed(() => [
  {label:'Active Incidents',value:stats.value.total_active||0,icon:'lucide:siren'},
  {label:'Awaiting DSS',value:stats.value.submitted||0,icon:'lucide:scan-search'},
  {label:'Screening / Offered',value:stats.value.validating||0,icon:'lucide:brain-circuit'},
  {label:'Organization Accepted',value:stats.value.accepted||0,icon:'lucide:badge-check'},
  {label:'Overdue / Escalated',value:(stats.value.overdue||0)+(stats.value.critical_overdue||0),icon:'lucide:triangle-alert'},
])
async function load(){loading.value=true;error.value='';try{const data=await fetchPlatformIncidents({status:status.value,search:search.value,page:page.value,per_page:20});items.value=data.items;stats.value=data.stats;pagination.value=data.pagination}catch(e){error.value=e?.response?.data?.message||e.message||'Unable to load incidents.'}finally{loading.value=false}}
async function openIncident(item){selectedOpen.value=true;detail.value=null;detailLoading.value=true;try{detail.value=await fetchPlatformIncident(item.id)}catch(e){alert.error(e?.response?.data?.message||e.message||'Unable to load incident.')}finally{detailLoading.value=false}}
function closeIncident(){selectedOpen.value=false;detail.value=null}
async function rerunDss(){if(!detail.value?.incident?.id)return;const ok=await alert.confirm('Re-run the DSS resource ranking using current fleet readiness and workload?','Refresh DSS ranking');if(!ok)return;runningDss.value=true;try{const data=await runIncidentDss(detail.value.incident.id);alert.success(data.message);detail.value=await fetchPlatformIncident(detail.value.incident.id);await load()}catch(e){alert.error(e?.response?.data?.message||e.message||'Unable to run DSS.')}finally{runningDss.value=false}}
async function unmerge(requestId,reason){unmergingId.value=requestId;try{const data=await unmergePlatformReport(requestId,reason);alert.success(data.message,'Report unlinked');detail.value=await fetchPlatformIncident(detail.value.incident.id);await load()}catch(e){alert.error(e?.response?.data?.message||e.message||'Unable to unlink this report.')}finally{unmergingId.value=null}}
function unmergeLinked({report,reason}){return unmerge(report.id,reason)}
async function unmergeSelf(){const ok=await alert.confirm(`Unlink ${detail.value.incident.reference_number} from ${detail.value.merged_into.reference_number}? It becomes its own incident and DSS screens a separate unit for it.`,'Unlink report');if(ok)await unmerge(detail.value.incident.id,'Unlinked from platform incident review.')}
function canRunDss(incident){return incident && !['assigned','acknowledged','responding','on_scene','transporting','completed','cancelled','rejected','duplicate','false_alarm'].includes(incident.status) && !detail.value?.offers?.some(o=>o.offer_status==='accepted')}
function titleCase(v=''){return String(v).replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase())}
function formatDate(v){return v?new Date(String(v).replace(' ','T')).toLocaleString():'—'}
function severityDot(v){return {critical:'bg-red-600',high:'bg-orange-500',moderate:'bg-amber-400',low:'bg-blue-400'}[v]||'bg-slate-400'}
function statusClass(v){if(['verified','assigned','acknowledged','responding','on_scene','transporting'].includes(v))return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300';if(['submitted','validating'].includes(v))return 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';return 'bg-base-200 text-slate-500 dark:bg-white/5 dark:text-white/45'}
function attentionLabel(v){return v==='critical_overdue'?'Critical Overdue':v==='overdue'?'Overdue':''}
function attentionClass(v){return v==='critical_overdue'?'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300':'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'}
function offerClass(v){return {sent:'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',accepted:'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',declined:'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',timed_out:'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-white/45',cancelled:'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-white/40'}[v]||'bg-base-200 text-slate-500'}
onMounted(load)
</script>
