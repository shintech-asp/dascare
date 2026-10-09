<template>
  <div>
    <div class="mb-2 flex items-center justify-between">
      <span class="text-xs font-semibold text-slate-500 dark:text-white/50">{{ label }}</span>
      <button type="button" class="tap inline-flex items-center gap-1 py-1 text-xs font-semibold text-[#1976D2] disabled:opacity-50 dark:text-[#7fb3ec]" :disabled="locating" @click="useCurrentLocation">
        <Icon :icon="locating ? 'lucide:loader-circle' : 'lucide:locate-fixed'" width="14" :class="locating ? 'animate-spin' : ''" />
        {{ locating ? 'Locating…' : 'Use my current location' }}
      </button>
    </div>
    <div ref="mapEl" class="h-64 w-full overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10"></div>
    <p class="mt-1.5 text-xs text-slate-400 dark:text-white/30">{{ hint }}</p>
    <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
  </div>
</template>

<script setup>
// Leaflet map with a single draggable-by-tap pin, limited to Dasmariñas
// City (same bounds as the web forms and kyc_submit.php / create.php).
// Leaflet is bundled (the web loads it from unpkg) so the map UI still
// works on a weak connection; OSM tiles still need internet.
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import { Geolocation } from '@capacitor/geolocation'
import { isPermissionDenied, locationDeniedText } from '@/utils/permissions'

const props = defineProps({
  lat: { type: Number, default: null },
  lng: { type: Number, default: null },
  label: { type: String, default: 'Pin your location' },
  hint: { type: String, default: 'Tap the map to drop or move the pin.' },
})
const emit = defineEmits(['update', 'picked'])

const DASMARINAS_CENTER = [14.3294, 120.9367]
const DASMARINAS_BOUNDS = [[14.26, 120.87], [14.40, 121.00]]

const mapEl = ref(null)
const locating = ref(false)
const error = ref('')
let map = null
let marker = null

function inDasmarinas(lat, lng) {
  const [[s, w], [n, e]] = DASMARINAS_BOUNDS
  return lat >= s && lat <= n && lng >= w && lng <= e
}

const pinIcon = L.divIcon({
  className: '',
  html: '<span style="display:block;width:22px;height:22px;border-radius:9999px;background:#dc2626;border:3px solid white;box-shadow:0 0 0 3px #dc262655,0 2px 6px rgba(0,0,0,.3)"></span>',
  iconSize: [22, 22],
  iconAnchor: [11, 11],
})

function placePin(lat, lng, source) {
  if (!inDasmarinas(lat, lng)) {
    error.value = 'Please pick a location within Dasmariñas City.'
    return false
  }
  error.value = ''
  if (marker) marker.setLatLng([lat, lng])
  else marker = L.marker([lat, lng], { icon: pinIcon }).addTo(map)
  emit('update', { lat, lng, source })
  return true
}

async function useCurrentLocation() {
  locating.value = true
  error.value = ''
  try {
    // Ask once. A first "Don't allow" comes back as prompt-with-rationale,
    // not "denied" — anything short of granted means stop here, otherwise
    // getCurrentPosition() would pop the system dialog a second time.
    const perm = await Geolocation.requestPermissions().catch(() => null)
    if (perm && perm.location !== 'granted' && perm.coarseLocation !== 'granted') throw Object.assign(new Error('denied'), { denied: true })
    const pos = await Geolocation.getCurrentPosition({ enableHighAccuracy: true, timeout: 15000, maximumAge: 10000 })
    const { latitude, longitude } = pos.coords
    if (placePin(latitude, longitude, 'gps')) {
      map.setView([latitude, longitude], 17)
      emit('picked', { lat: latitude, lng: longitude, source: 'gps' })
    }
  } catch (err) {
    error.value = isPermissionDenied(err)
      ? locationDeniedText
      : 'Couldn’t get your location. Make sure Location is on, or tap the map instead.'
  } finally {
    locating.value = false
  }
}

onMounted(() => {
  const bounds = L.latLngBounds(DASMARINAS_BOUNDS)
  map = L.map(mapEl.value, { maxBounds: bounds.pad(0.05), maxBoundsViscosity: 1, minZoom: 12, attributionControl: true })
    .setView(props.lat ? [props.lat, props.lng] : DASMARINAS_CENTER, props.lat ? 16 : 13)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap', maxZoom: 19 }).addTo(map)
  L.rectangle(bounds, { color: '#dc2626', weight: 1, fillOpacity: 0.02, dashArray: '4 4' }).addTo(map)
  if (props.lat && props.lng) marker = L.marker([props.lat, props.lng], { icon: pinIcon }).addTo(map)
  map.on('click', (e) => {
    if (placePin(e.latlng.lat, e.latlng.lng, 'manual')) emit('picked', { lat: e.latlng.lat, lng: e.latlng.lng, source: 'manual' })
  })
  // The map is often mounted inside a screen that's still sliding in.
  setTimeout(() => map?.invalidateSize(), 350)
})

// Parent can move the pin (e.g. from typing an address).
watch(() => [props.lat, props.lng], ([lat, lng]) => {
  if (!map || lat == null || lng == null) return
  if (!marker) marker = L.marker([lat, lng], { icon: pinIcon }).addTo(map)
  else marker.setLatLng([lat, lng])
  map.setView([lat, lng], Math.max(map.getZoom(), 15))
})

onBeforeUnmount(() => { map?.remove(); map = null; marker = null })
</script>
