<template>
  <section class="min-h-screen bg-base-200 px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[1500px] space-y-5">
      <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-[.68rem] font-black uppercase tracking-[.16em] text-red-600">Dispatch</p>
          <h1 class="mt-1 flex flex-wrap items-center gap-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white">Live Mission Tracking <LiveBadge :live="live" /></h1>
          <p class="mt-2 max-w-3xl text-sm text-slate-500 dark:text-white/45">Monitor active ambulance positions and share GPS from an assigned responder device.</p>
        </div>
        <button class="inline-flex items-center gap-2 rounded-xl border border-base-300 bg-base-100 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-base-200 dark:border-white/10 dark:bg-[#0d2943] dark:text-white/65" @click="load">
          <Icon icon="lucide:refresh-cw" width="16" :class="loading ? 'animate-spin' : ''" /> Refresh
        </button>
      </header>

      <div v-if="error" class="rounded-3xl border border-red-200 bg-red-50 p-5 text-sm font-semibold text-red-700">{{ error }}</div>
      <div class="grid gap-5 xl:grid-cols-[370px_minmax(0,1fr)]">
        <aside class="overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
          <div class="border-b border-base-300 p-4 dark:border-white/10">
            <h2 class="text-sm font-black text-slate-900 dark:text-white">Active units</h2>
            <p class="mt-1 text-xs text-slate-400">{{ missions.length }} tracked mission{{ missions.length === 1 ? '' : 's' }}</p>
          </div>
          <div class="max-h-[720px] overflow-y-auto p-2">
            <button v-for="m in missions" :key="m.id" class="mb-1 w-full rounded-2xl border p-3 text-left transition" :class="selected?.id===m.id?'border-red-200 bg-red-50 dark:border-red-500/20 dark:bg-red-500/10':'border-transparent hover:border-base-300 hover:bg-base-200 dark:hover:border-white/10 dark:hover:bg-white/[.03]'" @click="selectedId=m.id">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate text-sm font-black text-slate-900 dark:text-white">{{ m.reference_number }}</p>
                  <p class="mt-1 text-xs text-slate-500 dark:text-white/45">{{ m.unit_code }} · {{ pretty(m.assignment_status) }}</p>
                </div>
                <span class="rounded-full px-2 py-1 text-[.58rem] font-black uppercase" :class="m.location_stale?'bg-amber-50 text-amber-700':'bg-emerald-50 text-emerald-700'">{{ m.location_stale ? 'Stale' : 'Live' }}</span>
              </div>
              <p class="mt-2 text-[.65rem] text-slate-400">{{ m.last_location_at ? `Updated ${relative(m.last_location_at)}` : 'No GPS fix yet' }}</p>
            </button>
            <div v-if="!missions.length && !loading" class="p-10 text-center text-sm text-slate-400">No active missions to track.</div>
          </div>
        </aside>

        <main class="overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
          <div v-if="loading && !selected" class="grid min-h-[560px] place-items-center"><span class="h-8 w-8 animate-spin rounded-full border-2 border-red-600 border-t-transparent"></span></div>
          <template v-else-if="selected">
            <header class="border-b border-base-300 p-5 dark:border-white/10 sm:p-6">
              <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-black text-slate-950 dark:text-white">{{ selected.reference_number }}</h2>
                    <span class="rounded-full bg-red-50 px-2.5 py-1 text-[.62rem] font-black uppercase text-red-700 dark:bg-red-500/10 dark:text-red-300">{{ pretty(selected.assignment_status) }}</span>
                  </div>
                  <p class="mt-2 text-sm text-slate-500 dark:text-white/45">{{ selected.address_text }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                  <button v-if="canShareSelected && !sharing" class="rounded-xl bg-red-600 px-4 py-2.5 text-xs font-black text-white hover:bg-red-700" @click="startSharing"><Icon icon="lucide:locate-fixed" width="14" class="mr-1 inline"/>Share This Device GPS</button>
                  <button v-if="sharing" class="rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-black text-red-700" @click="stopSharing"><Icon icon="lucide:square" width="13" class="mr-1 inline"/>Stop Sharing</button>
                  <!-- Turn-by-turn for the driver: hand the destination to Google Maps / Waze -->
                  <a :href="navigateUrl('google')" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-xl border border-base-300 bg-base-100 px-4 py-2.5 text-xs font-black text-slate-700 no-underline hover:bg-base-200 dark:border-white/10 dark:bg-[#0d2943] dark:text-white/70"><Icon icon="lucide:navigation" width="14"/>Google Maps</a>
                  <a :href="navigateUrl('waze')" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-xl border border-base-300 bg-base-100 px-4 py-2.5 text-xs font-black text-slate-700 no-underline hover:bg-base-200 dark:border-white/10 dark:bg-[#0d2943] dark:text-white/70"><Icon icon="lucide:navigation-2" width="14"/>Waze</a>
                </div>
              </div>
            </header>

            <div class="space-y-5 p-5 sm:p-6">
              <div class="grid gap-3 sm:grid-cols-4">
                <Stat label="Ambulance" :value="selected.unit_code" icon="lucide:ambulance" />
                <Stat label="GPS state" :value="selected.location_stale ? 'Stale / offline' : 'Live'" icon="lucide:satellite" />
                <Stat label="Accuracy" :value="selected.last_accuracy_m != null ? `${Math.round(selected.last_accuracy_m)} m` : '—'" icon="lucide:crosshair" />
                <Stat label="Last update" :value="selected.last_location_at ? relative(selected.last_location_at) : 'No fix'" icon="lucide:clock-3" />
              </div>

              <div v-if="sharing" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
                <Icon icon="lucide:radio" width="16" class="mr-2 inline animate-pulse"/>Location sharing is active. DASCARE sends the latest browser GPS fix approximately every {{ intervalSeconds }} seconds.
              </div>
              <div v-else-if="selected.assigned_to_current_user && canShare" class="rounded-2xl border border-base-300 bg-base-200/40 p-4 text-xs text-slate-500 dark:border-white/10 dark:bg-white/[.03] dark:text-white/45">If this device is inside the assigned ambulance, use <strong>Share This Device GPS</strong> during the mission. Keep the page open while responding.</div>

              <div class="h-[480px] w-full overflow-hidden rounded-2xl border border-base-300 dark:border-white/10">
                <LiveMissionMap :key="selected.id" :incident="{ latitude: selected.incident_latitude, longitude: selected.incident_longitude }" :ambulance="selected.last_latitude != null ? { latitude: selected.last_latitude, longitude: selected.last_longitude } : null" :route="selected.route" />
              </div>
            </div>
          </template>
          <div v-else class="grid min-h-[560px] place-items-center p-10 text-center text-sm font-bold text-slate-400">Choose an active mission to open its live map.</div>
        </main>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { fetchLiveTracking, pushAmbulanceLocation } from '@/services/rescueOperations'
import { useAlert } from '@/composables/useAlert'
import { useLiveUpdates } from '@/composables/useLiveUpdates'
import LiveBadge from '@/components/realtime/LiveBadge.vue'
import LiveMissionMap from '@/components/maps/LiveMissionMap.vue'
import { realtimeChannels } from '@/services/realtime'

const alert = useAlert()
const missions = ref([]), selectedId = ref(null), loading = ref(false), error = ref('')
const canShare = ref(false), intervalSeconds = ref(15), sharing = ref(false)
let watchId = null, lastSentAt = 0
const selected = computed(() => missions.value.find(m => m.id === selectedId.value) || missions.value[0] || null)
const canShareSelected = computed(() => !!selected.value?.assigned_to_current_user && canShare.value)

const Stat = defineComponent({ props:{label:String,value:[String,Number],icon:String}, setup(p){ return()=>h('div',{class:'rounded-2xl border border-base-300 bg-base-200/35 p-4 dark:border-white/10 dark:bg-white/[.03]'},[h(Icon,{icon:p.icon,width:16,class:'text-red-600'}),h('p',{class:'mt-3 text-[.62rem] font-black uppercase tracking-[.12em] text-slate-400'},p.label),h('p',{class:'mt-1 text-sm font-black text-slate-900 dark:text-white'},p.value||'—')]) } })
const pretty=v=>String(v||'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase())
function relative(v){ if(!v)return'—'; const ms=Date.now()-new Date(String(v).replace(' ','T')).getTime(); const sec=Math.max(0,Math.floor(ms/1000)); if(sec<60)return`${sec}s ago`; const min=Math.floor(sec/60); if(min<60)return`${min}m ago`; return`${Math.floor(min/60)}h ago` }

async function load(silent=false){ if(!silent)loading.value=true; error.value=''; try{ const d=await fetchLiveTracking(); missions.value=d.missions||[]; canShare.value=!!d.can_share_location; intervalSeconds.value=d.tracking_interval_seconds||15; if(!selectedId.value||!missions.value.some(m=>m.id===selectedId.value))selectedId.value=missions.value[0]?.id||null; }catch(e){error.value=e?.response?.data?.message||e.message||'Unable to load tracking.'}finally{loading.value=false} }

// Map: components/maps/LiveMissionMap.vue (MapLibre) — road route + ETA from
// reusables/routing.php, ambulance gliding between fixes.
watch(selectedId,()=>stopSharing())
// Turn-by-turn for the driver: the current destination (hospital while
// transporting, otherwise the incident) opened in Google Maps or Waze.
function navigateUrl(app){const d=selected.value?.route?.destination||{latitude:selected.value?.incident_latitude,longitude:selected.value?.incident_longitude};const ll=`${d.latitude},${d.longitude}`;return app==='waze'?`https://waze.com/ul?ll=${encodeURIComponent(ll)}&navigate=yes`:`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(ll)}&travelmode=driving`}

function startSharing(){if(!navigator.geolocation){alert.error('Geolocation is not supported by this browser.');return}if(!canShareSelected.value){alert.error('Only assigned field responders can share this ambulance location.');return}sharing.value=true;watchId=navigator.geolocation.watchPosition(async pos=>{const now=Date.now();if(now-lastSentAt<intervalSeconds.value*1000-1000)return;lastSentAt=now;try{await pushAmbulanceLocation({assignment_id:selected.value.id,latitude:pos.coords.latitude,longitude:pos.coords.longitude,accuracy_m:pos.coords.accuracy,speed_kph:pos.coords.speed!=null?pos.coords.speed*3.6:null,heading_degrees:pos.coords.heading});if(!live.value)await load(true)}catch(e){alert.error(e?.response?.data?.message||e.message||'Location update failed.');stopSharing()}},err=>{alert.error(err.message||'Unable to read GPS.');stopSharing()},{enableHighAccuracy:true,maximumAge:5000,timeout:15000})}
function stopSharing(){if(watchId!=null&&navigator.geolocation)navigator.geolocation.clearWatch(watchId);watchId=null;sharing.value=false;lastSentAt=0}

// Live updates: crew GPS pings move the unit on the map as they arrive;
// other org events re-fetch the list. Polls every 15 s without live updates.
function applyLocation(d){const m=missions.value.find(x=>x.ambulance_id===Number(d.ambulance_id)&&x.id===Number(d.assignment_id));if(!m)return false;Object.assign(m,{last_latitude:d.latitude,last_longitude:d.longitude,last_accuracy_m:d.accuracy_m,last_location_at:new Date(d.sent_at||Date.now()).toISOString(),location_age_seconds:0,location_stale:false});return true}
const { live } = useLiveUpdates(load, { channels: () => [realtimeChannels.value?.org], onEvent: msg => { if (msg.name==='ambulance.location' && applyLocation(msg.data)) return false; if (msg.name==='route.updated') { const m=missions.value.find(x=>x.id===Number(msg.data?.assignment_id)); if (m) { m.route=msg.data.route; return false } } } })
onMounted(()=>{load()})
onUnmounted(()=>{stopSharing()})
</script>
