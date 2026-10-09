<template>
  <section class="min-h-screen bg-base-200 pb-12 dark:bg-[#081b2e]">
    <div class="mx-auto max-w-[1500px] px-4 py-7 sm:px-6 lg:px-8">
      <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <div class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-[0.68rem] font-black uppercase tracking-[0.12em] text-red-700 dark:bg-red-500/10 dark:text-red-300">
            <Icon icon="lucide:building-2" width="14" /> Organization management
          </div>
          <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white">Organization Profile</h1>
          <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-white/45">Maintain your approved organization contact details, operating base, and service coverage.</p>
        </div>
        <button v-if="canUpdate && !editing" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#1976D2] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#1565c0]" @click="startEditing">
          <Icon icon="lucide:pencil" width="15" /> Edit Profile
        </button>
      </header>

      <div v-if="loading" class="mt-6 grid gap-4 lg:grid-cols-3">
        <div v-for="n in 6" :key="n" class="h-36 animate-pulse rounded-3xl bg-base-100 shadow-sm dark:bg-[#0d2943]"></div>
      </div>

      <div v-else-if="errorMessage" class="mt-6 rounded-3xl border border-red-200 bg-red-50 p-6 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">
        <div class="flex items-center gap-3"><Icon icon="lucide:circle-alert" width="20" /><span class="font-bold">{{ errorMessage }}</span></div>
        <button class="mt-4 rounded-xl border border-red-300 px-4 py-2 text-xs font-bold" @click="load">Try again</button>
      </div>

      <template v-else-if="organization">
        <div class="mt-6 grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
          <div class="space-y-6">
            <section class="overflow-hidden rounded-[28px] border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
              <div class="border-b border-base-300 p-5 dark:border-white/10 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-[#1976D2] dark:text-[#7fb3ec]">Approved identity</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">{{ organization.name }}</h2>
                    <p class="mt-1 text-xs text-slate-400 dark:text-white/30">{{ typeLabel(organization.organization_type) }} · {{ organization.application_reference || 'Legacy organization' }}</p>
                  </div>
                  <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1.5 text-[0.68rem] font-black uppercase tracking-wide text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"><Icon icon="lucide:badge-check" width="13" /> Active</span>
                </div>
              </div>

              <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
                <Info label="Organization Type" :value="typeLabel(organization.organization_type)" icon="lucide:building" />
                <Info label="Registration / Authorization" :value="organization.registration_number || 'Not provided'" icon="lucide:badge" />
                <Info label="Accreditation Body" :value="organization.accreditation_body || 'Not provided'" icon="lucide:landmark" class="sm:col-span-2" />

                <template v-if="editing">
                  <Field label="Organization email"><input v-model.trim="form.email" class="field" type="email" maxlength="190" /></Field>
                  <Field label="Contact number"><input v-model.trim="form.phone" class="field" maxlength="20" /></Field>
                  <Field label="Base / station address" class="sm:col-span-2"><textarea v-model.trim="form.address_line" class="field min-h-[92px] resize-none" maxlength="255"></textarea></Field>
                </template>
                <template v-else>
                  <Info label="Organization Email" :value="organization.email || 'Not provided'" icon="lucide:mail" />
                  <Info label="Contact Number" :value="organization.phone || 'Not provided'" icon="lucide:phone" />
                  <Info label="Base / Station Address" :value="organization.address_line || 'Not provided'" icon="lucide:map-pin" class="sm:col-span-2" />
                </template>
              </div>
            </section>

            <section class="rounded-[28px] border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-6">
              <div class="flex items-center justify-between gap-3">
                <div><p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">Operating base</p><h2 class="mt-1 text-lg font-black text-slate-950 dark:text-white">Station Location</h2></div>
                <span v-if="form.latitude !== null" class="font-mono text-[0.66rem] text-slate-400 dark:text-white/30">{{ Number(form.latitude).toFixed(5) }}, {{ Number(form.longitude).toFixed(5) }}</span>
              </div>
              <div v-if="form.latitude === null || form.longitude === null" class="mt-4 flex items-start gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                <Icon icon="lucide:map-pin-off" width="16" class="mt-0.5 shrink-0" />
                <span><strong>Base location not set.</strong> Edit the profile, click the station location on the map, then save. DASCARE uses these coordinates as the dispatch origin until the ambulance has a live GPS position.</span>
              </div>
              <div ref="mapContainer" class="mt-4 h-[330px] overflow-hidden rounded-2xl border border-base-300 bg-base-200 dark:border-white/10 dark:bg-[#071829]"></div>
              <p v-if="editing" class="mt-2 text-xs text-slate-400 dark:text-white/30">Click the map to move the organization base. Locations are limited to Dasmariñas City.</p>
            </section>
          </div>

          <aside class="space-y-6">
            <section class="rounded-[28px] border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-6">
              <div class="flex items-center justify-between"><div><p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">Coverage</p><h2 class="mt-1 text-lg font-black text-slate-950 dark:text-white">Service Areas</h2></div><Icon icon="lucide:map-pinned" width="20" class="text-slate-300 dark:text-white/20" /></div>
              <div v-if="editing" class="mt-4 flex gap-2"><input v-model.trim="newArea" class="field" placeholder="Add barangay / service area" @keyup.enter.prevent="addArea" /><button class="rounded-xl bg-[#1976D2] px-4 text-xs font-bold text-white" @click="addArea">Add</button></div>
              <div class="mt-4 flex flex-wrap gap-2">
                <button v-for="area in form.service_areas" :key="area" type="button" class="inline-flex items-center gap-1.5 rounded-full bg-base-200 px-3 py-1.5 text-[0.7rem] font-bold text-slate-600 dark:bg-white/5 dark:text-white/45" @click="editing && removeArea(area)">{{ area }}<Icon v-if="editing" icon="lucide:x" width="12" /></button>
                <p v-if="!form.service_areas.length" class="text-xs text-slate-400">No service areas configured.</p>
              </div>
            </section>

            <section class="rounded-[28px] border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-6">
              <div class="flex items-center justify-between"><div><p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">Compliance snapshot</p><h2 class="mt-1 text-lg font-black text-slate-950 dark:text-white">Approved Documents</h2></div><Icon icon="lucide:files" width="20" class="text-slate-300 dark:text-white/20" /></div>
              <div class="mt-4 space-y-3">
                <div v-for="doc in documents" :key="doc.id" class="rounded-2xl bg-base-200 p-3 dark:bg-white/[0.035]"><div class="flex items-start justify-between gap-2"><div class="min-w-0"><p class="truncate text-xs font-bold text-slate-700 dark:text-white/60">{{ doc.original_name || documentLabel(doc.doc_type) }}</p><p class="mt-0.5 text-[0.65rem] text-slate-400 dark:text-white/30">{{ documentLabel(doc.doc_type) }}</p></div><span class="rounded-full px-2 py-0.5 text-[0.62rem] font-bold" :class="doc.status === 'approved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'">{{ doc.status }}</span></div></div>
                <p v-if="!documents.length" class="text-xs text-slate-400">No organization documents found.</p>
              </div>
            </section>

            <div v-if="editing" class="flex gap-3">
              <button class="flex-1 rounded-xl border border-base-300 px-4 py-3 text-sm font-bold text-slate-600 dark:border-white/10 dark:text-white/50" :disabled="saving" @click="cancelEditing">Cancel</button>
              <button class="flex-1 rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50" :disabled="saving" @click="save"><span v-if="saving">Saving…</span><span v-else>Save Changes</span></button>
            </div>
          </aside>
        </div>
      </template>
    </div>
  </section>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { fetchOrganizationProfile, updateOrganizationProfile } from '@/services/organizationManagement'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'

const { hasPermission, fetchSession } = useSession()
const toast = useToast()
const loading = ref(true), saving = ref(false), editing = ref(false), errorMessage = ref('')
const organization = ref(null), documents = ref([]), mapContainer = ref(null), newArea = ref('')
const form = ref({ email:'', phone:'', address_line:'', latitude:null, longitude:null, service_areas:[] })
const original = ref(null)
let map = null, marker = null
const canUpdate = computed(() => hasPermission('org_settings.org_profile.update'))
const typeMap = { city_rescue:'City Rescue / LGU Unit', barangay_rescue:'Barangay Rescue Unit', hospital:'Hospital Ambulance Service', private_ambulance:'Private Ambulance / Rescue Service', other:'Other Rescue Organization' }
const typeLabel = v => typeMap[v] || v || '—'
const documentLabel = type => ({ registration_document:'Registration / authorization', operating_authority:'Operating / accreditation', supporting_document:'Additional supporting document' }[type] || type)

const Field = defineComponent({ props:{label:String}, setup(props,{slots,attrs}){ return()=>h('label',{class:['block',attrs.class]},[h('span',{class:'mb-1.5 block text-[0.68rem] font-black uppercase tracking-wide text-slate-400 dark:text-white/30'},props.label),slots.default?.()]) } })
const Info = defineComponent({ props:{label:String,value:String,icon:String}, setup(props,{attrs}){ return()=>h('div',{class:['rounded-2xl border border-base-300 p-4 dark:border-white/10',attrs.class]},[h('div',{class:'flex items-center gap-2 text-slate-400 dark:text-white/30'},[h(Icon,{icon:props.icon,width:14}),h('span',{class:'text-[0.65rem] font-black uppercase tracking-wide'},props.label)]),h('p',{class:'mt-2 text-sm font-semibold leading-6 text-slate-700 dark:text-white/60'},props.value)]) } })

function snapshot() { return JSON.parse(JSON.stringify(form.value)) }
function startEditing(){ original.value=snapshot(); editing.value=true }
function cancelEditing(){ if(original.value) form.value=JSON.parse(JSON.stringify(original.value)); editing.value=false; nextTick(renderMap) }
function addArea(){ const v=newArea.value.trim(); if(v && !form.value.service_areas.some(a=>a.toLowerCase()===v.toLowerCase())) form.value.service_areas.push(v); newArea.value='' }
function removeArea(area){ form.value.service_areas=form.value.service_areas.filter(a=>a!==area) }

async function loadLeaflet(){
  if(window.L) return window.L
  if(!document.querySelector('link[data-dascare-leaflet]')){ const link=document.createElement('link'); link.rel='stylesheet'; link.href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'; link.dataset.dascareLeaflet='1'; document.head.appendChild(link) }
  await new Promise((resolve,reject)=>{ const existing=document.querySelector('script[data-dascare-leaflet]'); if(existing){ if(window.L) return resolve(); existing.addEventListener('load',resolve,{once:true}); existing.addEventListener('error',reject,{once:true}); return } const script=document.createElement('script'); script.src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'; script.dataset.dascareLeaflet='1'; script.onload=resolve; script.onerror=reject; document.head.appendChild(script) })
  return window.L
}
async function renderMap(){
  if(!mapContainer.value) return
  try{
    const L=await loadLeaflet()
    if(map){ map.remove(); map=null; marker=null }
    const startLat=form.value.latitude ?? 14.3294; const startLng=form.value.longitude ?? 120.9367
    map=L.map(mapContainer.value,{zoomControl:true,maxBounds:[[14.24,120.85],[14.42,121.02]]}).setView([startLat,startLng],form.value.latitude===null?13:15)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map)
    if(form.value.latitude!==null) marker=L.marker([form.value.latitude,form.value.longitude]).addTo(map)
    map.on('click',(e)=>{ if(!editing.value) return; const {lat,lng}=e.latlng; if(lat<14.26||lat>14.40||lng<120.87||lng>121.00){ toast.warning?.('Choose a location inside Dasmariñas City.'); return } form.value.latitude=lat; form.value.longitude=lng; if(marker) marker.setLatLng([lat,lng]); else marker=L.marker([lat,lng]).addTo(map) })
    setTimeout(()=>map?.invalidateSize(),60)
  }catch(e){ console.error(e) }
}
async function load(){
  loading.value=true; errorMessage.value=''
  try{
    const data=await fetchOrganizationProfile()
    organization.value=data.organization
    documents.value=data.documents||[]
    form.value={
      email:data.organization.email||'',
      phone:data.organization.phone||'',
      address_line:data.organization.address_line||'',
      latitude:data.organization.latitude,
      longitude:data.organization.longitude,
      service_areas:data.service_areas||[]
    }
    // The map container is inside the non-loading branch of the template.
    // Render the real profile first, then initialize Leaflet once the DOM exists.
    loading.value=false
    await nextTick()
    await renderMap()
  }
  catch(e){
    errorMessage.value=e?.response?.data?.message||'Could not load the organization profile.'
    loading.value=false
  }
}
async function save(){
  saving.value=true
  try{ const result=await updateOrganizationProfile(form.value); toast.success?.(result.message||'Organization profile updated.'); editing.value=false; await fetchSession(true); await load() }
  catch(e){ toast.error?.(e?.response?.data?.message||'Could not save the organization profile.') }
  finally{ saving.value=false }
}
watch(editing,()=>nextTick(()=>map?.invalidateSize()))
onMounted(load)
onBeforeUnmount(()=>map?.remove())
</script>

<style scoped>
.field {
  width: 100%;
  border-radius: 0.75rem;
  border: 1px solid var(--color-base-300);
  background: var(--color-base-100);
  padding: 0.625rem 0.875rem;
  font-size: 0.875rem;
  line-height: 1.25rem;
  color: #1e293b;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
}
.field:focus {
  border-color: #1976d2;
  box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.10);
}
:global([data-theme="dark"]) .field {
  border-color: rgba(255, 255, 255, 0.10);
  background: #071829;
  color: #ffffff;
}
</style>
