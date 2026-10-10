<template>
  <PageShell title="Incident Heatmap" subtitle="Visualize recent emergency demand across Dasmariñas using actual incident coordinates." icon="lucide:map" eyebrow="Platform Executive">
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
      <section class="overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
        <div class="flex flex-col gap-3 border-b border-base-300 p-4 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-sm font-black text-slate-900 dark:text-white">Emergency demand map</h2>
            <p class="mt-1 text-xs text-slate-400">{{ total }} mapped incident{{ total === 1 ? '' : 's' }} in the selected period.</p>
          </div>
          <div class="flex items-center gap-2">
            <select v-model.number="days" class="rounded-xl border border-base-300 bg-base-200 px-3 py-2 text-xs font-bold text-slate-700 outline-none dark:border-white/10 dark:bg-white/5 dark:text-white/65" @change="load">
              <option :value="7">Last 7 days</option>
              <option :value="30">Last 30 days</option>
              <option :value="90">Last 90 days</option>
              <option :value="365">Last year</option>
            </select>
            <button class="rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-xs font-black text-slate-600 hover:bg-base-200 dark:border-white/10 dark:bg-white/5 dark:text-white/55" @click="load">
              <Icon icon="lucide:refresh-cw" width="14" :class="loading ? 'animate-spin' : ''" />
            </button>
          </div>
        </div>
        <div v-if="error" class="m-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">{{ error }}</div>
        <div ref="mapContainer" class="h-[620px] w-full"></div>
      </section>

      <aside class="space-y-4">
        <section class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
          <h2 class="text-sm font-black text-slate-900 dark:text-white">Severity legend</h2>
          <div class="mt-4 grid grid-cols-2 gap-2 text-xs font-bold">
            <span v-for="item in legend" :key="item.label" class="flex items-center gap-2 rounded-xl bg-base-200 px-3 py-2 dark:bg-white/[.04]">
              <span class="h-2.5 w-2.5 rounded-full" :style="{ background:item.color }"></span>{{ item.label }}
            </span>
          </div>
          <p class="mt-4 text-xs leading-5 text-slate-400">Markers are incident locations, not patient identities. This view is for city-wide operational awareness and thesis demonstration.</p>
        </section>

        <section class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-black text-slate-900 dark:text-white">Top barangays</h2>
            <span class="text-[.62rem] font-black uppercase tracking-[.12em] text-slate-400">{{ days }} days</span>
          </div>
          <div class="mt-4 space-y-2.5">
            <article v-for="(h,i) in hotspots" :key="h.barangay" class="rounded-2xl border border-base-300 bg-base-200/45 p-3 dark:border-white/10 dark:bg-white/[.03]">
              <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate text-xs font-black text-slate-800 dark:text-white/75">{{ i+1 }}. {{ h.barangay }}</p>
                  <p class="mt-1 text-[.62rem] text-slate-400">{{ h.critical_count }} critical · {{ h.high_count }} high</p>
                </div>
                <span class="rounded-full bg-red-50 px-2.5 py-1 text-[.62rem] font-black text-red-700 dark:bg-red-500/10 dark:text-red-300">{{ h.incident_count }}</span>
              </div>
            </article>
            <p v-if="!hotspots.length && !loading" class="py-8 text-center text-xs font-semibold text-slate-400">No mapped incident data for this period.</p>
          </div>
        </section>
      </aside>
    </div>
  </PageShell>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import PageShell from '@/components/management/PageShell.vue'
import api from '@/services/api'

const days = ref(30), loading = ref(false), error = ref(''), total = ref(0), hotspots = ref([]), points = ref([])
const mapContainer = ref(null)
let map = null, layer = null
const legend = [
  { label:'Critical', color:'#dc2626' },
  { label:'High', color:'#f97316' },
  { label:'Moderate', color:'#f59e0b' },
  { label:'Low', color:'#2563eb' },
]
const severityColor = s => ({critical:'#dc2626',high:'#f97316',moderate:'#f59e0b',low:'#2563eb'}[String(s||'').toLowerCase()] || '#64748b')
const loadLeaflet = () => new Promise((resolve,reject)=>{if(window.L)return resolve(window.L);if(!document.querySelector('link[data-dascare-leaflet]')){const l=document.createElement('link');l.rel='stylesheet';l.href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';l.dataset.dascareLeaflet='1';document.head.appendChild(l)}const existing=document.querySelector('script[data-dascare-leaflet]');if(existing){existing.addEventListener('load',()=>resolve(window.L),{once:true});existing.addEventListener('error',reject,{once:true});return}const s=document.createElement('script');s.src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';s.dataset.dascareLeaflet='1';s.onload=()=>resolve(window.L);s.onerror=reject;document.head.appendChild(s)})
async function ensureMap(){if(map||!mapContainer.value)return;const L=await loadLeaflet();map=L.map(mapContainer.value,{zoomControl:true}).setView([14.3294,120.9367],13);L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',{attribution:'&copy; OpenStreetMap contributors &copy; CARTO',maxZoom:19}).addTo(map);layer=L.layerGroup().addTo(map)}
function renderPoints(){if(!map||!layer||!window.L)return;const L=window.L;layer.clearLayers();const bounds=[];for(const p of points.value){const lat=Number(p.latitude),lng=Number(p.longitude);if(!Number.isFinite(lat)||!Number.isFinite(lng))continue;const color=severityColor(p.severity);const marker=L.circleMarker([lat,lng],{radius:p.severity==='critical'?9:7,color:'#fff',weight:2,fillColor:color,fillOpacity:.78,opacity:.9});marker.bindPopup(`<strong>${escapeHtml(p.reference_number||'Incident')}</strong><br>${escapeHtml(p.category_name||'Emergency')} · ${escapeHtml(p.severity||'')}<br>${escapeHtml(p.barangay||p.address_text||'Dasmariñas')}<br><small>${escapeHtml(p.status||'')}</small>`);marker.addTo(layer);bounds.push([lat,lng])}if(bounds.length)map.fitBounds(bounds,{padding:[30,30],maxZoom:15});else map.setView([14.3294,120.9367],13)}
function escapeHtml(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]))}
async function load(){loading.value=true;error.value='';try{const {data}=await api.get('/platform/analytics/heatmap.php',{params:{days:days.value}});points.value=data.points||[];hotspots.value=data.hotspots||[];total.value=Number(data.total||0);await nextTick();await ensureMap();renderPoints()}catch(e){error.value=e?.response?.data?.message||e.message||'Unable to load incident heatmap.'}finally{loading.value=false}}
onMounted(load)
onUnmounted(()=>{if(map){map.remove();map=null}})
</script>
