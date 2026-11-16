<template>
  <PageShell title="Hospitals" subtitle="The receiving hospitals DASCARE recommends. The pin decides the 3 nearest to an accident and where navigation goes." icon="lucide:hospital" eyebrow="System Administration">
    <div class="flex flex-wrap items-center gap-3">
      <input v-model.trim="search" type="search" placeholder="Search hospitals" class="min-w-[14rem] flex-1 rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5" />
      <select v-model="statusFilter" class="rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5"><option value="">All</option><option value="active">Active</option><option value="inactive">Switched off</option></select>
      <button type="button" class="rounded-xl bg-red-600 px-4 py-2.5 text-xs font-black text-white" @click="openEdit(null)"><Icon icon="lucide:plus" width="14" class="mr-1 inline" />Add hospital</button>
    </div>
    <p class="mt-2 text-xs text-slate-500">{{ activeCount }} active · only active hospitals are recommended. Switching one off keeps its past handoffs.</p>

    <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Loading…</div>
    <div v-else class="mt-4 grid gap-3 lg:grid-cols-2">
      <article v-for="f in shown" :key="f.id" class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#071829]" :class="f.status === 'inactive' ? 'opacity-70' : ''">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="break-words font-black text-slate-900 dark:text-white">{{ f.name }}</p>
            <p class="mt-0.5 break-words text-xs text-slate-500">{{ f.address_text }}</p>
            <p class="mt-1 text-[.7rem] text-slate-400">{{ typeLabel(f.facility_type) }} · {{ f.latitude.toFixed(5) }}, {{ f.longitude.toFixed(5) }}<template v-if="f.phone"> · {{ f.phone }}</template></p>
            <p v-if="f.capabilities" class="mt-1 break-words text-xs text-slate-500">{{ f.capabilities }}</p>
            <p class="mt-1 text-[.7rem] text-slate-400">{{ f.handoff_count }} handoff{{ f.handoff_count === 1 ? '' : 's' }}</p>
          </div>
          <span class="rounded-full px-2.5 py-1 text-[.62rem] font-black uppercase" :class="f.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'">{{ f.status === 'active' ? 'Active' : 'Off' }}</span>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
          <button type="button" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold dark:border-white/10 dark:text-white/70" @click="openEdit(f)">Edit & pin</button>
          <button type="button" class="rounded-xl border px-3 py-2 text-xs font-bold" :class="f.status === 'active' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'" @click="toggle(f)">{{ f.status === 'active' ? 'Switch off' : 'Switch on' }}</button>
        </div>
      </article>
      <p v-if="!shown.length" class="py-12 text-center text-sm text-slate-400 lg:col-span-2">No hospitals match.</p>
    </div>

    <Teleport to="body">
      <div v-if="ed.open" class="fixed inset-0 z-[10040] grid place-items-center bg-slate-950/50 p-4" @click.self="closeEdit">
        <form class="max-h-[94vh] w-full max-w-3xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl dark:bg-[#0d2943]" @submit.prevent="submit">
          <h3 class="text-lg font-black text-slate-900 dark:text-white">{{ ed.id ? 'Edit hospital' : 'Add hospital' }}</h3>
          <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="grid gap-3">
              <label class="block text-xs font-bold text-slate-500">Name<input v-model.trim="ed.name" maxlength="180" class="fld" /></label>
              <label class="block text-xs font-bold text-slate-500">Type<select v-model="ed.facility_type" class="fld"><option v-for="(l, k) in TYPES" :key="k" :value="k">{{ l }}</option></select></label>
              <label class="block text-xs font-bold text-slate-500">Address<input v-model.trim="ed.address_text" maxlength="255" class="fld" placeholder="Street, barangay, city" /></label>
              <label class="block text-xs font-bold text-slate-500">Phone <span class="font-normal">(optional)</span><input v-model.trim="ed.phone" maxlength="20" class="fld" /></label>
              <label class="block text-xs font-bold text-slate-500">What it can do <span class="font-normal">(optional, shown to the crew)</span><input v-model.trim="ed.capabilities" maxlength="500" class="fld" placeholder="e.g. 24/7 ER, trauma, ICU, maternity" /></label>
              <div class="grid grid-cols-2 gap-3">
                <label class="block text-xs font-bold text-slate-500">Latitude<input v-model.number="ed.latitude" type="number" step="0.00001" class="fld" @change="moveMarker" /></label>
                <label class="block text-xs font-bold text-slate-500">Longitude<input v-model.number="ed.longitude" type="number" step="0.00001" class="fld" @change="moveMarker" /></label>
              </div>
            </div>
            <div>
              <p class="text-xs font-bold text-slate-500">Pin <HelpTip title="Why the pin matters" text="Click the map or drag the red pin to the hospital's entrance. The pin decides which hospitals are recommended as nearest to an accident, and navigation goes to this exact spot. Grey dots are the other hospitals." /></p>
              <div ref="mapEl" class="mt-1.5 h-72 w-full overflow-hidden rounded-2xl border border-base-300 dark:border-white/10"></div>
              <button type="button" class="mt-2 text-xs font-bold text-[#1976D2]" @click="findAddress" :disabled="geocoding">{{ geocoding ? 'Searching…' : 'Find the address on the map' }}</button>
            </div>
          </div>
          <p v-if="ed.error" class="mt-3 text-xs font-bold text-red-600">{{ ed.error }}</p>
          <div class="mt-5 flex justify-end gap-2"><button type="button" class="rounded-xl border border-base-300 px-4 py-2.5 text-xs font-bold dark:border-white/10 dark:text-white/70" @click="closeEdit">Cancel</button><button type="submit" :disabled="ed.saving" class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-black text-white disabled:opacity-50">{{ ed.saving ? 'Saving…' : 'Save hospital' }}</button></div>
        </form>
      </div>
    </Teleport>
  </PageShell>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { Icon } from '@iconify/vue'
import PageShell from '@/components/management/PageShell.vue'
import HelpTip from '@/components/forms/HelpTip.vue'
import { addBasemap, loadLeaflet } from '@/services/basemap'
import { fetchTechnicalFacilities, saveTechnicalFacility, setTechnicalFacilityStatus } from '@/services/technicalFacilities'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'
import { looksLikeText } from '@/utils/validation'

const toast = useToast()
const alert = useAlert()
const TYPES = { hospital: 'Hospital', clinic: 'Clinic', trauma_center: 'Trauma center', maternity: 'Maternity', other: 'Other' }
const typeLabel = (t) => TYPES[t] || t
const loading = ref(true)
const facilities = ref([])
const bounds = ref({ south: 14.2, north: 14.45, west: 120.8, east: 121.1 })
const search = ref('')
const statusFilter = ref('')
const mapEl = ref(null)
const geocoding = ref(false)
let map = null
let marker = null
let L = null

const activeCount = computed(() => facilities.value.filter((f) => f.status === 'active').length)
const shown = computed(() => facilities.value.filter((f) => (!statusFilter.value || f.status === statusFilter.value) && (!search.value || `${f.name} ${f.address_text}`.toLowerCase().includes(search.value.toLowerCase()))))

async function load() {
  loading.value = true
  try { const d = await fetchTechnicalFacilities(); facilities.value = d.facilities || []; bounds.value = d.bounds || bounds.value } catch (e) { toast.error(e?.response?.data?.message || 'Unable to load hospitals.') } finally { loading.value = false }
}

// ---- editor with a draggable pin
const ed = reactive({ open: false, id: 0, name: '', facility_type: 'hospital', address_text: '', phone: '', capabilities: '', latitude: null, longitude: null, error: '', saving: false })
const CENTER = [14.3294, 120.9367]

async function openEdit(f) {
  Object.assign(ed, { open: true, id: f?.id || 0, name: f?.name || '', facility_type: f?.facility_type || 'hospital', address_text: f?.address_text || '', phone: f?.phone || '', capabilities: f?.capabilities || '', latitude: f?.latitude ?? null, longitude: f?.longitude ?? null, error: '', saving: false })
  await nextTick()
  try {
    L = await loadLeaflet()
    map = L.map(mapEl.value, { zoomControl: true }).setView(f ? [f.latitude, f.longitude] : CENTER, f ? 16 : 13)
    addBasemap(L, map)
    for (const o of facilities.value) if (o.id !== ed.id) L.circleMarker([o.latitude, o.longitude], { radius: 5, color: '#64748b', weight: 1, fillOpacity: 0.7 }).bindTooltip(o.name).addTo(map)
    if (f) placeMarker(f.latitude, f.longitude)
    map.on('click', (e) => placeMarker(e.latlng.lat, e.latlng.lng))
    setTimeout(() => map?.invalidateSize(), 120)
  } catch { toast.error('The map could not load. Check your internet connection.') }
}
function placeMarker(lat, lng) {
  ed.latitude = Number(lat.toFixed(6)); ed.longitude = Number(lng.toFixed(6))
  if (marker) marker.setLatLng([lat, lng])
  else {
    marker = L.marker([lat, lng], { draggable: true }).addTo(map)
    marker.on('dragend', () => { const p = marker.getLatLng(); ed.latitude = Number(p.lat.toFixed(6)); ed.longitude = Number(p.lng.toFixed(6)) })
  }
}
function moveMarker() { if (map && Number.isFinite(ed.latitude) && Number.isFinite(ed.longitude)) { placeMarker(ed.latitude, ed.longitude); map.setView([ed.latitude, ed.longitude], Math.max(map.getZoom(), 15)) } }
async function findAddress() {
  if (ed.address_text.length < 5 && ed.name.length < 3) { ed.error = 'Type the hospital name or address first.'; return }
  geocoding.value = true
  ed.error = ''
  try {
    const q = `${ed.name} ${ed.address_text}`.trim()
    const r = await fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=ph&viewbox=${bounds.value.west},${bounds.value.north},${bounds.value.east},${bounds.value.south}&bounded=1&q=${encodeURIComponent(q)}`)
    const hit = (await r.json())[0]
    if (!hit) { ed.error = 'Nothing found — click the map to place the pin yourself.'; return }
    placeMarker(Number(hit.lat), Number(hit.lon)); map.setView([Number(hit.lat), Number(hit.lon)], 17)
  } catch { ed.error = 'The address search is unavailable — click the map to place the pin yourself.' } finally { geocoding.value = false }
}
function closeEdit() { ed.open = false; if (map) { map.remove(); map = null; marker = null } }
onBeforeUnmount(() => { if (map) map.remove() })

async function submit() {
  ed.error = ''
  if (ed.name.length < 3 || !looksLikeText(ed.name, 3)) { ed.error = 'Enter the hospital name.'; return }
  if (ed.address_text.length < 5 || !looksLikeText(ed.address_text, 4)) { ed.error = 'Enter the address (street, barangay, city).'; return }
  if (ed.phone && !/^[0-9+()\-\s]{7,20}$/.test(ed.phone)) { ed.error = 'Enter a valid phone number, or leave it blank.'; return }
  const b = bounds.value
  if (!Number.isFinite(ed.latitude) || !Number.isFinite(ed.longitude) || ed.latitude < b.south || ed.latitude > b.north || ed.longitude < b.west || ed.longitude > b.east) { ed.error = 'Place the pin on the map inside Dasmariñas and its surroundings.'; return }
  if (!(await alert.confirm(`${ed.id ? 'Save changes to' : 'Add'} ${ed.name}? The pin at ${ed.latitude.toFixed(5)}, ${ed.longitude.toFixed(5)} decides when it is recommended.`, ed.id ? 'Save hospital' : 'Add hospital'))) return
  ed.saving = true
  try {
    const d = await saveTechnicalFacility({ id: ed.id, name: ed.name, facility_type: ed.facility_type, address_text: ed.address_text, phone: ed.phone, capabilities: ed.capabilities, latitude: ed.latitude, longitude: ed.longitude })
    toast.success(d.message)
    closeEdit()
    await load()
  } catch (e) { ed.error = e?.response?.data?.message || 'Unable to save the hospital.' } finally { ed.saving = false }
}

async function toggle(f) {
  const next = f.status === 'active' ? 'inactive' : 'active'
  if (!(await alert.confirm(next === 'inactive' ? `Switch off ${f.name}? It will no longer be recommended to crews. Past handoffs keep it.` : `Switch on ${f.name}? It can be recommended again.`, next === 'inactive' ? 'Switch off hospital' : 'Switch on hospital'))) return
  try { const d = await setTechnicalFacilityStatus(f.id, next); toast.success(d.message); await load() } catch (e) { toast.error(e?.response?.data?.message || 'Unable to change that.') }
}

onMounted(load)
</script>

<style scoped>
.fld { margin-top: .375rem; width: 100%; border-radius: .75rem; border: 1px solid hsl(var(--bc) / .12); background: transparent; padding: .625rem .75rem; font-size: .875rem; color: inherit; }
.fld:focus { outline: none; border-color: #dc2626; }
</style>
