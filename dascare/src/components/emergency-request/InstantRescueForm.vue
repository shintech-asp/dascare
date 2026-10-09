<!--
  components/emergency-request/InstantRescueForm.vue

  "Instant" track: as close to one action as possible. GPS is captured and
  reverse-geocoded automatically on mount, category defaults to a generic
  "unspecified" type (dispatch sorts it out on the call/at the scene), and
  a citizen with a phone on file sees nothing to fill in at all: GPS
  locks, the button lights up, done.

  GPS is a convenience, not a requirement — it's the *fast path*, not the
  *only path*. If it fails (denied, indoors, no signal, timeout), this
  falls back to a compact inline map for a manual pin instead of dead-
  ending the person, plus a one-tap escape hatch to the Standard tab for
  anyone who'd rather just fill in the full form.

  The only fields that ever show up are ones the backend genuinely cannot
  function without and has no other way to get:
    - a barangay, IF neither GPS reverse-geocoding nor a manual pin
      resolves one (rare — only geocoding itself failing, not GPS)
    - name + phone, ONLY for a guest with no account to pull them from

  Deliberately does NOT know about routing/session-guards — it only reads
  useSession() for a prefillable phone number and to decide the guest name
  field. That's what makes it safe to drop onto a future public/guest
  route unchanged: a guest page simply won't have a session user, so
  isGuest resolves true on its own.

  Talks to the same citizen/create.php endpoint as the
  standard form, with request_mode='instant' and a fixed critical severity
  baseline (dispatcher can always downgrade it after review).
-->
<template>
  <div class="overflow-hidden rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#050e1a] shadow-xl shadow-base-300/40 dark:shadow-black/30">

    <!-- Signal bar — same siren-red accent language as the header/hero -->
    <div class="h-[3px] w-full bg-gradient-to-r from-base-100 via-red-500 to-base-100 dark:from-[#050e1a] dark:via-red-500 dark:to-[#050e1a]"></div>

    <!-- Header -->
    <div class="relative px-6 pt-9 pb-6 text-center overflow-hidden">
      <div class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-44 h-44 rounded-full bg-red-500/10 animate-ping-slow"></div>
      <div class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-28 h-28 rounded-full bg-red-500/10 animate-ping-slow" style="animation-delay: 0.7s"></div>

      <div class="relative inline-flex items-center gap-2 border-l-2 border-red-600 pl-3 dark:border-red-400">
        <span class="font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">
          Fastest response · No forms
        </span>
      </div>

      <Icon icon="lucide:siren" width="46" class="relative mx-auto mt-4 text-red-600 dark:text-red-400" />
      <h2 class="relative mt-3 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
        Instant <span class="text-red-600 dark:text-red-400">Rescue</span>
      </h2>
      <p class="relative mt-1.5 max-w-xs mx-auto text-xs leading-relaxed text-slate-500 dark:text-white/45">
        One tap. Your location goes straight to dispatch — no forms, no typing.
      </p>
    </div>

    <!-- GPS status strip -->
    <div
      class="flex items-center justify-between gap-3 px-6 py-3 border-y text-xs font-bold uppercase tracking-wider transition-colors"
      :class="locationOk
        ? 'bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-300'
        : gpsError
          ? 'bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-300'
          : 'bg-base-200 dark:bg-white/5 border-base-300 dark:border-white/10 text-slate-500 dark:text-white/45'"
    >
      <span class="flex items-center gap-2.5">
        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-white/70 dark:bg-black/20">
          <Icon :icon="gpsIcon" width="14" :class="{ 'animate-spin': locating }" />
        </span>
        {{ gpsStatusText }}
      </span>
      <button v-if="gpsError && !showManualPin" type="button" @click="captureGps" class="shrink-0 underline decoration-2 underline-offset-2">
        Retry GPS
      </button>
    </div>

    <!-- GPS failed — offer the two ways forward instead of dead-ending -->
    <div v-if="gpsError && !showManualPin" class="px-6 py-4 border-b border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 space-y-3">
      <p class="text-xs leading-relaxed text-slate-500 dark:text-white/45">
        Couldn't get your GPS. You can still send this — pin your location on the map, or switch to the full request form.
      </p>
      <div class="flex flex-col sm:flex-row gap-2">
        <button type="button" @click="openManualPin"
          class="flex-1 flex items-center justify-center gap-1.5 rounded-xl border-2 border-base-300 dark:border-white/15 bg-base-100 dark:bg-white/5 px-3 py-2.5 text-xs font-bold text-slate-700 dark:text-white/70 transition-colors hover:border-red-300 hover:text-red-700 dark:hover:border-red-400/40 dark:hover:text-red-300">
          <Icon icon="lucide:map-pin" width="14" />
          Pin location manually
        </button>
        <button type="button" @click="$emit('switch-to-standard')"
          class="flex-1 flex items-center justify-center gap-1.5 rounded-xl border-2 border-base-300 dark:border-white/15 bg-base-100 dark:bg-white/5 px-3 py-2.5 text-xs font-bold text-slate-700 dark:text-white/70 transition-colors hover:border-[#1976D2]/50 hover:text-[#1976D2] dark:hover:border-[#7fb3ec]/40 dark:hover:text-[#7fb3ec]">
          <Icon icon="lucide:clipboard-list" width="14" />
          Use Standard form instead
        </button>
      </div>
    </div>

    <div class="p-6 space-y-5">

      <!-- Manual pin fallback map — only mounted when GPS fails and the -->
      <!-- citizen chooses this over switching tabs. Same Leaflet setup as -->
      <!-- the Standard form's map, just without the nearby-facilities layer -->
      <!-- (speed over completeness here). -->
      <div v-if="showManualPin">
        <div class="flex items-center justify-between mb-1.5">
          <label class="block text-xs font-semibold text-slate-600 dark:text-white/60">Tap your location on the map</label>
          <button type="button" @click="captureGps" class="text-[0.68rem] font-bold text-red-600 dark:text-red-300 hover:underline">
            Try GPS again
          </button>
        </div>
        <div ref="mapContainer" class="w-full h-56 rounded-2xl border-2 border-slate-200 dark:border-white/10 overflow-hidden"></div>
        <p v-if="mapError" class="text-[0.68rem] text-red-500 mt-1">{{ mapError }}</p>
        <p v-else-if="geocoding" class="text-[0.68rem] text-slate-400 dark:text-white/30 mt-1">Looking up the address…</p>
        <p v-else-if="form.address_text" class="text-[0.68rem] text-slate-400 dark:text-white/30 mt-1">{{ form.address_text }}</p>
      </div>

      <!-- Barangay fallback — surfaced whenever we have coordinates but -->
      <!-- reverse geocoding couldn't resolve one; create.php requires a -->
      <!-- non-empty barangay either way. -->
      <div v-if="needsManualBarangay">
        <label class="block text-[0.68rem] text-slate-500 dark:text-white/40 mb-1">
          Couldn't auto-detect your barangay — please enter it
        </label>
        <input
          v-model="form.barangay"
          type="text" maxlength="120" placeholder="Barangay"
          class="w-full rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white focus:outline-none focus:border-red-400 focus:ring-2 focus:ring-red-500/30 transition-colors"
        />
      </div>

      <!-- Requester name — guest only, unavoidable: no account to pull it from -->
      <div v-if="isGuest">
        <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">Your Full Name</label>
        <input
          v-model="form.requester_name"
          type="text" required placeholder="Juan Dela Cruz"
          class="w-full rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-white/30 focus:outline-none focus:border-red-400 focus:ring-2 focus:ring-red-500/30 transition-colors"
        />
      </div>

      <!-- Contact number — guest only. A citizen's registered number is used -->
      <!-- silently below; nothing to type or confirm. -->
      <div v-if="isGuest">
        <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">Contact Number</label>
        <input
          v-model="form.requester_phone"
          type="tel" required maxlength="13"
          placeholder="09XXXXXXXXX"
          class="w-full rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-white/30 focus:outline-none focus:border-red-400 focus:ring-2 focus:ring-red-500/30 transition-colors"
        />
        <p v-if="form.requester_phone && !isPhoneValid" class="text-xs text-red-500 mt-1">
          Enter a valid PH mobile number (09XXXXXXXXX or +639XXXXXXXXX).
        </p>
      </div>

      <!-- Medical records notice — citizens only, same reasoning as the -->
      <!-- Standard form: guests have no account/record so create.php -->
      <!-- never attaches anything for them, no need to mention it here. -->
      <div v-if="!isGuest" class="flex items-start gap-2.5 rounded-2xl border border-red-200 dark:border-red-500/20 bg-red-50/60 dark:bg-red-500/5 px-4 py-3">
        <Icon icon="lucide:heart-pulse" width="16" class="text-red-600 dark:text-red-300 flex-shrink-0 mt-0.5" />
        <p class="text-xs leading-relaxed text-red-800 dark:text-red-200/80">
          Your saved emergency information will be shared with the responding crew automatically.
        </p>
      </div>

      <!-- The big button — same weight & shadow language as Hero's CTA -->
      <button
        type="button"
        :disabled="submitting || !isFormValid"
        @click="submitRequest"
        class="group mx-auto flex aspect-square w-full max-w-[230px] flex-col items-center justify-center gap-3 rounded-full border-[8px] border-red-400/70 bg-red-600 px-8 text-center text-white shadow-[0_18px_45px_rgba(220,38,38,0.32)] transition-all hover:scale-[1.02] hover:bg-red-700 hover:shadow-[0_22px_55px_rgba(220,38,38,0.4)] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-400/50 dark:shadow-red-950/40"
      >
        <Icon v-if="submitting" icon="lucide:loader-2" width="28" class="animate-spin" />
        <Icon v-else icon="lucide:zap" width="28" class="transition-transform group-hover:scale-110" />
        <span class="max-w-[150px] text-lg font-black uppercase leading-tight tracking-tight">
          {{ submitting ? 'Sending…' : 'Request Rescue Now' }}
        </span>
      </button>
      <p class="text-center text-[0.68rem] text-slate-400 dark:text-white/30">
        {{ isGuest
          ? 'Your location is sent to dispatch — stay where you are if possible.'
          : `Dispatch will call ${form.requester_phone || 'your registered number'} — stay where you are if possible.` }}
      </p>
    </div>

    <div class="flex items-center justify-center gap-2.5 px-6 py-3.5 bg-base-200 dark:bg-white/5 border-t border-base-300 dark:border-white/10">
      <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-red-100 dark:bg-red-500/15">
        <Icon icon="lucide:phone-call" width="13" class="text-red-600 dark:text-red-400" />
      </span>
      <span class="text-xs font-bold text-red-700 dark:text-red-300">Life-threatening? Call your local emergency hotline too.</span>
    </div>
  </div>
</template>

<script setup>
import axios from 'axios'
import { reactive, ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Icon } from '@iconify/vue'
import { useAlert } from '@/composables/useAlert'
import { useToast } from '@/composables/useToast'
import { useSession } from '@/composables/useSession'

const props = defineProps({
  // Lets a guest-facing page force guest behavior explicitly rather than
  // relying purely on "no session user" — mirrors the guestMode prop the
  // reference InstantRescueForm/DetailedRequestForm used, so this component
  // drops into that kind of route unchanged.
  forceGuest: { type: Boolean, default: false },
})
const emit = defineEmits(['submitted', 'switch-to-standard'])

const alert = useAlert()
const toast = useToast()
const { user } = useSession()
const API_BASE = import.meta.env.VITE_API_BASE_URL

const isGuest = computed(() => props.forceGuest || !user.value)

// No category picker in this flow on purpose — a dire emergency isn't the
// moment to make someone classify their own incident. Falls back to the
// generic "Other Emergency" category (id 8, mirrors emergency_categories
// seed data); dispatch/the crew sort out specifics on the call or on scene.
const GENERIC_CATEGORY_ID = 8

const submitting = ref(false)

const form = reactive({
  address_text: '',
  barangay: '',
  latitude: null,
  longitude: null,
  requester_phone: '',
  requester_name: '',
})

// Citizen already has a phone on file — used silently, never shown as a
// field to fill in. Guests get an explicit (unavoidable) field above.
if (user.value?.phone) form.requester_phone = user.value.phone

// True once we have usable coordinates, whether from device GPS or a
// manual pin on the fallback map — the rest of the form doesn't care
// which source it came from.
const locationOk = computed(() => form.latitude !== null && form.longitude !== null)

const needsManualBarangay = computed(() => locationOk.value && !geocoding.value && !form.barangay)

const isPhoneValid = computed(() => /^(\+639\d{9}|09\d{9})$/.test(form.requester_phone.replace(/[\s-]/g, '')))

const isFormValid = computed(() =>
  locationOk.value &&
  form.address_text.trim().length >= 5 &&
  !!form.barangay.trim() &&
  isPhoneValid.value &&
  (!isGuest.value || form.requester_name.trim().length >= 2)
)

// ------------------------------------------------------------------
// GPS — captured automatically on mount, no map/tap required in the
// happy path. Same Dasmariñas geofence and reverse-geocoding source
// (Nominatim) as the Standard form.
// ------------------------------------------------------------------
const DASMARINAS_BOUNDS = { south: 14.26, north: 14.40, west: 120.86, east: 121.00 }
function isWithinDasmarinas(lat, lng) {
  return lat >= DASMARINAS_BOUNDS.south && lat <= DASMARINAS_BOUNDS.north &&
         lng >= DASMARINAS_BOUNDS.west && lng <= DASMARINAS_BOUNDS.east
}

const locating = ref(false)
const gpsError = ref(false)
const geocoding = ref(false)

const gpsIcon = computed(() => {
  if (locating.value) return 'lucide:loader-2'
  if (locationOk.value) return 'lucide:map-pin-check'
  if (gpsError.value) return 'lucide:map-pin-off'
  return 'lucide:locate-fixed'
})
const gpsStatusText = computed(() => {
  if (locating.value) return 'Locating you…'
  if (locationOk.value) return showManualPin.value ? 'Location pinned on map' : 'GPS location locked'
  if (gpsError.value) return 'GPS unavailable'
  return 'Acquiring GPS…'
})

function captureGps() {
  if (!navigator.geolocation) {
    gpsError.value = true
    toast.error('Location is not supported on this device/browser. Pin it manually or use the Standard form.', 'GPS unavailable')
    return
  }
  locating.value = true
  gpsError.value = false
  navigator.geolocation.getCurrentPosition(
    async (pos) => {
      const { latitude: lat, longitude: lng } = pos.coords
      locating.value = false
      if (!isWithinDasmarinas(lat, lng)) {
        gpsError.value = true
        toast.error('Your current location is outside Dasmariñas. Pin the incident location manually, or use the Standard form.', 'Outside service area')
        return
      }
      showManualPin.value = false
      setLocation(lat, lng)
      await reverseGeocode(lat, lng)
    },
    () => {
      locating.value = false
      gpsError.value = true
      // Don't show an alert on the very first automatic attempt (onMounted) — a
      // permission prompt getting dismissed shouldn't feel like an error
      // popping up unprompted. Manual retries still get one, via the
      // catch below where relevant UI already explains what happened.
    },
    { enableHighAccuracy: true, timeout: 10000 }
  )
}

function setLocation(lat, lng) {
  form.latitude = lat
  form.longitude = lng
  if (mapMarker) mapMarker.setLatLng([lat, lng])
}

async function reverseGeocode(lat, lng) {
  geocoding.value = true
  try {
    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&addressdetails=1&zoom=18`)
    const data = await res.json()
    const addr = data.address || {}
    const streetParts = [addr.house_number, addr.road].filter(Boolean)
    form.address_text = streetParts.length ? streetParts.join(' ') : (data.display_name || '').split(',').slice(0, 2).join(',').trim()
    form.barangay = addr.village || addr.suburb || addr.neighbourhood || addr.quarter || ''
  } catch (err) {
    console.error('Reverse geocoding failed', err)
    // Address/barangay stay empty — needsManualBarangay + the isFormValid
    // check prompt the citizen to type just the barangay themselves.
  } finally {
    geocoding.value = false
  }
}

// ------------------------------------------------------------------
// Manual pin fallback — a compact Leaflet map, only loaded when GPS has
// failed and the citizen opts into this over switching tabs. Same
// Dasmariñas bounds/geofence as everywhere else, click-to-pin, no nearby
// facilities layer (keeps it light and fast).
// ------------------------------------------------------------------
const showManualPin = ref(false)
const mapContainer = ref(null)
const mapError = ref('')
let leafletMap = null
let mapMarker = null

async function openManualPin() {
  showManualPin.value = true
  await nextTick()
  await initManualMap()
}

function loadLeaflet() {
  return new Promise((resolve, reject) => {
    if (window.L) { resolve(window.L); return }
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
    document.head.appendChild(link)
    const script = document.createElement('script')
    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
    script.onload = () => resolve(window.L)
    script.onerror = () => reject(new Error('Failed to load map'))
    document.head.appendChild(script)
  })
}

async function initManualMap() {
  try {
    const L = await loadLeaflet()
    await nextTick()
    if (!mapContainer.value || leafletMap) return

    const DASMARINAS_CENTER = [14.3294, 120.9367]
    const boundsArr = [[14.26, 120.86], [14.40, 121.00]]
    const bounds = L.latLngBounds(boundsArr)

    leafletMap = L.map(mapContainer.value, {
      maxBounds: bounds.pad(0.05),
      maxBoundsViscosity: 1.0,
      minZoom: 12,
    }).setView(form.latitude ? [form.latitude, form.longitude] : DASMARINAS_CENTER, form.latitude ? 16 : 13)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(leafletMap)

    if (form.latitude) {
      mapMarker = L.marker([form.latitude, form.longitude]).addTo(leafletMap)
    }

    // Clear boundary outline so it's obvious at a glance where the service
    // area ends — same styling as the Standard form's map.
    L.rectangle(bounds, { color: '#dc2626', weight: 1, fillOpacity: 0.02, dashArray: '4 4' }).addTo(leafletMap)

    leafletMap.on('click', (e) => {
      const { lat, lng } = e.latlng
      if (!isWithinDasmarinas(lat, lng)) {
        mapError.value = 'Please pin a location within Dasmariñas City.'
        return
      }
      mapError.value = ''
      if (mapMarker) {
        mapMarker.setLatLng([lat, lng])
      } else {
        mapMarker = L.marker([lat, lng]).addTo(leafletMap)
      }
      setLocation(lat, lng)
      reverseGeocode(lat, lng)
    })
  } catch (err) {
    console.error('Manual pin map failed to load', err)
    mapError.value = 'Unable to load the map. Try the Standard form instead.'
  }
}

// ------------------------------------------------------------------
// Submit
// ------------------------------------------------------------------
async function submitRequest() {
  if (!isFormValid.value) return
  submitting.value = true
  try {
    const fd = new FormData()
    fd.append('emergency_category_id', GENERIC_CATEGORY_ID)
    fd.append('severity', 'critical')
    fd.append('description', 'INSTANT RESCUE REQUEST. Submitted via one-tap instant request. Category unspecified — confirm with requester on contact.')
    fd.append('address_text', form.address_text.trim())
    fd.append('barangay', form.barangay.trim())
    fd.append('landmark', '')
    fd.append('latitude', form.latitude)
    fd.append('longitude', form.longitude)
    fd.append('requester_phone', form.requester_phone.trim())
    fd.append('request_mode', 'instant')
    if (isGuest.value) fd.append('requester_name', form.requester_name.trim())

    const res = await axios.post(`${API_BASE}/citizen/create.php`, fd, {
      withCredentials: true,
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    if (res.data?.success) {
      alert.success(`Reference ${res.data.reference_number} — dispatch has your location.`, 'Rescue requested')
      emit('submitted', res.data)
    } else {
      alert.error(res.data?.message || 'Could not submit request', 'Error')
    }
  } catch (err) {
    console.error(err)
    alert.error(err.response?.data?.message || 'Could not submit request — please try again', 'Error')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  captureGps()
})

onBeforeUnmount(() => {
  if (leafletMap) {
    leafletMap.remove()
    leafletMap = null
    mapMarker = null
  }
})
</script>

<style scoped>
@keyframes ping-slow {
  0% { transform: translate(-50%, -50%) scale(0.85); opacity: 0.6; }
  100% { transform: translate(-50%, -50%) scale(1.6); opacity: 0; }
}
.animate-ping-slow {
  animation: ping-slow 2.2s cubic-bezier(0, 0, 0.2, 1) infinite;
}
</style>