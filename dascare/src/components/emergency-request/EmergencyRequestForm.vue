<!--
  components/emergency-request/EmergencyRequestForm.vue

  The single merged request form (replaces the old Instant + Standard split).

  Design goal, per DASCARE spec: as little typing as possible, because it's
  an emergency. There is no category picker and no severity picker anymore —
  every request is treated as critical and category "Other" (8); dispatch /
  the crew sort out specifics on the call or on scene. GPS is captured and
  reverse-geocoded automatically on mount; a citizen with an account needs
  to do nothing but tap the button.

  "Add details (optional)" is a collapsed section carrying everything the old
  Standard form offered — description, landmark, evidence photos, and a map
  to fine-tune the pin / address / barangay — for anyone who wants to add
  more, but nothing in it is required.

  The big button stays clickable even on an empty form: the backend fills
  safe defaults for every field except location (which can't be guessed) and
  phone (which falls back to the account's number for a logged-in citizen).
  That's what lets someone who can't fill anything in still get help.

  Deliberately does NOT know about routing/session-guards — it only reads
  useSession() for a prefillable phone number and to decide the guest name/
  phone fields, so it drops onto a future public/guest route unchanged.

  Talks to the same citizen/create.php endpoint, request_mode='instant'.
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
        Request <span class="text-red-600 dark:text-red-400">Rescue</span>
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

    <!-- GPS failed — offer the manual pin instead of dead-ending -->
    <div v-if="gpsError && !showManualPin" class="px-6 py-4 border-b border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 space-y-3">
      <p class="text-xs leading-relaxed text-slate-500 dark:text-white/45">
        Couldn't get your GPS. You can still send this — pin your location on the map below.
      </p>
      <button type="button" @click="openManualPin"
        class="w-full flex items-center justify-center gap-1.5 rounded-xl border-2 border-base-300 dark:border-white/15 bg-base-100 dark:bg-white/5 px-3 py-2.5 text-xs font-bold text-slate-700 dark:text-white/70 transition-colors hover:border-red-300 hover:text-red-700 dark:hover:border-red-400/40 dark:hover:text-red-300">
        <Icon icon="lucide:map-pin" width="14" />
        Pin location manually
      </button>
    </div>

    <div class="p-6 space-y-5">

      <!-- Manual pin / adjust-location map — shown when GPS fails, or when the -->
      <!-- citizen opens it from the optional details to fine-tune the pin. Same -->
      <!-- Leaflet setup and Dasmariñas geofence as the rest of the app. -->
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

      <!-- Medical records notice — citizens only; guests have no record to attach. -->
      <div v-if="!isGuest" class="flex items-start gap-2.5 rounded-2xl border border-red-200 dark:border-red-500/20 bg-red-50/60 dark:bg-red-500/5 px-4 py-3">
        <Icon icon="lucide:heart-pulse" width="16" class="text-red-600 dark:text-red-300 flex-shrink-0 mt-0.5" />
        <p class="text-xs leading-relaxed text-red-800 dark:text-red-200/80">
          Your saved emergency information will be shared with the responding crew automatically.
        </p>
      </div>

      <!-- The big button — same weight & shadow language as before -->
      <button
        type="button"
        :disabled="submitting"
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

      <!-- ================= Optional details ================= -->
      <!-- Everything the old Standard form offered, collapsed by default so -->
      <!-- the fast path stays a single tap. Nothing here is required. -->
      <div class="pt-1">
        <button
          type="button"
          @click="toggleDetails"
          class="w-full flex items-center justify-center gap-1.5 text-xs font-bold text-slate-500 dark:text-white/45 hover:text-slate-700 dark:hover:text-white/70 transition-colors"
        >
          <Icon :icon="showDetails ? 'lucide:chevron-up' : 'lucide:chevron-down'" width="14" />
          {{ showDetails ? 'Hide details' : 'Add details (optional)' }}
        </button>

        <div v-if="showDetails" class="mt-4 space-y-5 border-t border-base-300 dark:border-white/10 pt-5">

          <!-- What's happening -->
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">
              What's happening? <span class="font-normal text-slate-400 dark:text-white/30">(optional)</span>
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
              <div class="min-w-0">
                <label class="block text-[0.68rem] text-slate-500 dark:text-white/40 mb-1">Patients involved</label>
                <input
                  v-model.number="quickDetails.patientCount"
                  type="number" min="1" max="50"
                  class="w-full min-w-0 rounded-lg border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-2.5 py-1.5 text-sm text-slate-900 dark:text-white focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
                />
              </div>
              <div class="min-w-0">
                <label class="block text-[0.68rem] text-slate-500 dark:text-white/40 mb-1">Conscious?</label>
                <select v-model="quickDetails.conscious" class="w-full min-w-0 rounded-lg border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-2.5 py-1.5 text-sm text-slate-900 dark:text-white focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors">
                  <option value="">Unknown</option>
                  <option value="yes">Yes</option>
                  <option value="no">No</option>
                </select>
              </div>
              <div class="min-w-0">
                <label class="block text-[0.68rem] text-slate-500 dark:text-white/40 mb-1">Breathing?</label>
                <select v-model="quickDetails.breathing" class="w-full min-w-0 rounded-lg border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-2.5 py-1.5 text-sm text-slate-900 dark:text-white focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors">
                  <option value="">Unknown</option>
                  <option value="yes">Yes</option>
                  <option value="no">No</option>
                </select>
              </div>
            </div>
            <textarea
              v-model="form.description"
              rows="3"
              placeholder="Describe the situation — visible injuries, immediate hazards, anything the crew should know before arriving."
              class="w-full rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-white/30 focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
            ></textarea>
          </div>

          <!-- Address + barangay + landmark -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="min-w-0 sm:col-span-2">
              <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">Address <span class="font-normal text-slate-400 dark:text-white/30">(optional)</span></label>
              <input
                v-model="form.address_text"
                type="text"
                placeholder="House/unit number, street, subdivision"
                class="w-full min-w-0 rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-white/30 focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
              />
            </div>
            <div class="min-w-0">
              <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">Barangay <span class="font-normal text-slate-400 dark:text-white/30">(optional)</span></label>
              <select
                v-if="!barangaysLoadFailed"
                v-model="form.barangay"
                :disabled="loadingBarangays"
                class="w-full min-w-0 rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3 py-2.5 text-sm text-slate-900 dark:text-white disabled:opacity-60 focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
              >
                <option value="">{{ loadingBarangays ? 'Loading…' : 'Select barangay' }}</option>
                <option v-for="b in barangaysList" :key="b" :value="b">{{ b }}</option>
              </select>
              <input
                v-else
                v-model="form.barangay"
                type="text" maxlength="120"
                placeholder="Enter barangay"
                class="w-full min-w-0 rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3 py-2.5 text-sm text-slate-900 dark:text-white focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
              />
            </div>
            <div class="min-w-0">
              <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">Landmark <span class="font-normal text-slate-400 dark:text-white/30">(optional)</span></label>
              <input
                v-model="form.landmark"
                type="text"
                placeholder="Nearest known landmark"
                class="w-full min-w-0 rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-white/30 focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
              />
            </div>
          </div>

          <!-- Adjust location on map -->
          <div>
            <button v-if="!showManualPin" type="button" @click="openManualPin"
              class="flex items-center gap-1.5 text-xs font-bold text-[#1976D2] dark:text-[#7fb3ec] hover:underline">
              <Icon icon="lucide:map-pin" width="13" />
              Adjust location on map
            </button>
            <p v-else class="text-[0.68rem] text-slate-400 dark:text-white/30">Use the map above to adjust the pin.</p>
          </div>

          <!-- Evidence photos -->
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">
              Photos <span class="font-normal text-slate-400 dark:text-white/30">(optional, up to 3)</span>
            </label>
            <div class="rounded-2xl border-2 border-dashed border-slate-200 dark:border-white/10 p-3">
              <input
                type="file" accept="image/jpeg,image/png,image/webp" multiple
                :disabled="photos.length >= 3"
                @change="onPhotosChange"
                class="w-full text-xs text-slate-500 dark:text-white/45 file:mr-3 file:rounded-lg file:border-0 file:bg-[#1976D2] file:text-white file:px-3.5 file:py-1.5 file:text-xs file:font-semibold file:cursor-pointer disabled:opacity-50"
              />
              <div v-if="photos.length" class="mt-2.5 flex flex-wrap gap-2">
                <div v-for="(p, i) in photos" :key="i" class="relative">
                  <img :src="p.preview" class="w-16 h-16 rounded-xl object-cover border-2 border-slate-200 dark:border-white/10" />
                  <button type="button" @click="removePhoto(i)"
                    class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-slate-800 text-white flex items-center justify-center shadow-sm">
                    <Icon icon="lucide:x" width="11" />
                  </button>
                </div>
              </div>
            </div>
            <p class="text-[0.68rem] text-slate-400 dark:text-white/30 mt-1">JPG, PNG, or WEBP, max 5MB each.</p>
          </div>

          <!-- Guest callback details — optional. A guest can add a name/number -->
          <!-- here if they want, but it's never required: the responding -->
          <!-- organization gathers requester details on the call / on scene. -->
          <div v-if="isGuest" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="min-w-0">
              <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">
                Your Full Name <span class="font-normal text-slate-400 dark:text-white/30">(optional)</span>
              </label>
              <input
                v-model="form.requester_name"
                type="text" placeholder="Juan Dela Cruz"
                class="w-full min-w-0 rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-white/30 focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
              />
            </div>
            <div class="min-w-0">
              <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">
                Contact Number <span class="font-normal text-slate-400 dark:text-white/30">(optional)</span>
              </label>
              <input
                v-model="form.requester_phone"
                type="tel" maxlength="13"
                placeholder="09XXXXXXXXX"
                class="w-full min-w-0 rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-white/30 focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
              />
              <p v-if="form.requester_phone && !isPhoneValid" class="text-xs text-red-500 mt-1">
                Enter a valid PH mobile number (09XXXXXXXXX or +639XXXXXXXXX).
              </p>
            </div>
          </div>
        </div>
      </div>
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
import { refreshRealtimeAccess } from '@/services/realtime'
import { reactive, ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Icon } from '@iconify/vue'
import { useAlert } from '@/composables/useAlert'
import { useToast } from '@/composables/useToast'
import { useSession } from '@/composables/useSession'

const props = defineProps({
  // Lets a guest-facing page force guest behavior explicitly rather than
  // relying purely on "no session user".
  forceGuest: { type: Boolean, default: false },
})
const emit = defineEmits(['submitted'])

const alert = useAlert()
const toast = useToast()
const { user } = useSession()
const API_BASE = import.meta.env.VITE_API_BASE_URL

const isGuest = computed(() => props.forceGuest || !user.value)

// No category / severity pickers anymore — every request is critical and
// category "Other" (id 8, mirrors emergency_categories seed data). Dispatch
// and the crew classify it on the call or on scene.
const GENERIC_CATEGORY_ID = 8

const submitting = ref(false)
const showDetails = ref(false)

const form = reactive({
  address_text: '',
  barangay: '',
  landmark: '',
  latitude: null,
  longitude: null,
  requester_phone: '',
  requester_name: '',
  description: '',
})

const quickDetails = reactive({
  patientCount: 1,
  conscious: '',
  breathing: '',
})

// Citizen already has a phone on file — used silently. Still editable for a
// guest (who gets an explicit field); a logged-in citizen never sees it.
if (user.value?.phone) form.requester_phone = user.value.phone

const locationOk = computed(() => form.latitude !== null && form.longitude !== null)
const isPhoneValid = computed(() => /^(\+639\d{9}|09\d{9})$/.test((form.requester_phone || '').replace(/[\s-]/g, '')))

// ------------------------------------------------------------------
// GPS — captured automatically on mount. Same Dasmariñas geofence and
// reverse-geocoding source (Nominatim) as before.
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
    toast.error('Location is not supported on this device/browser. Pin it manually instead.', 'GPS unavailable')
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
        toast.error('Your current location is outside Dasmariñas. Pin the incident location manually.', 'Outside service area')
        return
      }
      showManualPin.value = false
      setLocation(lat, lng)
      await reverseGeocode(lat, lng)
    },
    () => {
      locating.value = false
      gpsError.value = true
      // No alert on the first automatic attempt — a dismissed permission
      // prompt shouldn't feel like an error popping up unprompted.
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
    const brgyGuess = addr.village || addr.suburb || addr.neighbourhood || addr.quarter || ''
    if (brgyGuess) {
      const target = brgyGuess.toLowerCase().trim()
      const matched = barangaysList.value.find(b => b.toLowerCase() === target)
        || barangaysList.value.find(b => b.toLowerCase().includes(target) || target.includes(b.toLowerCase()))
      form.barangay = matched || brgyGuess
    }
  } catch (err) {
    console.error('Reverse geocoding failed', err)
    // Address/barangay stay empty — the backend fills safe defaults.
  } finally {
    geocoding.value = false
  }
}

// ------------------------------------------------------------------
// PSGC — Dasmariñas barangays only (service area is one city). Loaded lazily
// the first time the optional details are opened.
// ------------------------------------------------------------------
const PSGC_BASE = 'https://psgc.gitlab.io/api'
const DASMARINAS_CITY_CODE = '042106000'

const barangaysList = ref([])
const loadingBarangays = ref(false)
const barangaysLoadFailed = ref(false)
let barangaysLoaded = false

async function fetchDasmarinasBarangays() {
  if (barangaysLoaded) return
  loadingBarangays.value = true
  barangaysLoadFailed.value = false
  try {
    const res = await fetch(`${PSGC_BASE}/cities-municipalities/${DASMARINAS_CITY_CODE}/barangays/`)
    if (!res.ok) throw new Error(`PSGC request failed: ${res.status}`)
    const data = await res.json()
    const list = (Array.isArray(data) ? data : []).map(b => b.name).sort()
    if (!list.length) throw new Error('empty barangay list')
    barangaysList.value = list
    barangaysLoaded = true
  } catch (err) {
    console.error('Failed to load Dasmariñas barangays from PSGC', err)
    barangaysLoadFailed.value = true // frontend falls back to free text
  } finally {
    loadingBarangays.value = false
  }
}

function toggleDetails() {
  showDetails.value = !showDetails.value
  if (showDetails.value) fetchDasmarinasBarangays()
}

// ------------------------------------------------------------------
// Photos (optional, up to 3)
// ------------------------------------------------------------------
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const photos = ref([]) // [{ file, preview }]

function onPhotosChange(e) {
  const files = Array.from(e.target.files || [])
  e.target.value = ''
  for (const file of files) {
    if (photos.value.length >= 3) break
    if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
      alert.error('Only JPG, PNG, or WEBP images are allowed', 'Invalid file')
      continue
    }
    if (file.size > 5 * 1024 * 1024) {
      alert.error('Each photo must be 5MB or smaller', 'File too large')
      continue
    }
    photos.value.push({ file, preview: URL.createObjectURL(file) })
  }
}
function removePhoto(i) {
  URL.revokeObjectURL(photos.value[i].preview)
  photos.value.splice(i, 1)
}

// ------------------------------------------------------------------
// Manual pin / adjust-location map — a compact Leaflet map, loaded on demand
// (GPS failure, or the "adjust location" action). Same Dasmariñas bounds and
// click-to-pin as everywhere else.
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

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
      attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
      maxZoom: 19,
    }).addTo(leafletMap)

    if (form.latitude) {
      mapMarker = L.marker([form.latitude, form.longitude]).addTo(leafletMap)
    }

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
    mapError.value = 'Unable to load the map. Please try again.'
  }
}

// ------------------------------------------------------------------
// Submit — stays clickable on an empty form. Location is the one thing that
// can't be defaulted; if it's missing we surface the manual pin rather than
// silently doing nothing. Everything else the backend fills in.
// ------------------------------------------------------------------
function buildDescription() {
  const bits = []
  if (quickDetails.patientCount) bits.push(`Patients involved: ${quickDetails.patientCount}`)
  if (quickDetails.conscious) bits.push(`Conscious: ${quickDetails.conscious === 'yes' ? 'Yes' : 'No'}`)
  if (quickDetails.breathing) bits.push(`Breathing: ${quickDetails.breathing === 'yes' ? 'Yes' : 'No'}`)
  const summary = bits.length ? bits.join(' · ') + '\n\n' : ''
  const body = form.description.trim()
  return (summary + body).trim()
}

async function submitRequest() {
  if (submitting.value) return

  // Location is genuinely required — you can't dispatch to an unknown place.
  if (!locationOk.value) {
    if (!gpsError.value && !locating.value) {
      captureGps()
      toast.info('Getting your location — tap again once it locks, or pin it on the map.', 'One moment')
    } else {
      openManualPin()
      toast.error('We still need your location. Please pin it on the map.', 'Location needed')
    }
    return
  }

  // Name and contact are optional — the organization gathers them later. The
  // only soft check is a typo guard: if a guest did type a number, make sure
  // it's a valid one before sending. Blank is fine.
  if (isGuest.value && form.requester_phone && !isPhoneValid.value) {
    toast.error('Please enter a valid PH mobile number, or leave it blank.', 'Check number')
    return
  }

  submitting.value = true
  try {
    const fd = new FormData()
    fd.append('emergency_category_id', GENERIC_CATEGORY_ID)
    fd.append('severity', 'critical')
    fd.append('description', buildDescription())
    fd.append('address_text', form.address_text.trim())
    fd.append('barangay', form.barangay.trim())
    fd.append('landmark', form.landmark.trim())
    fd.append('latitude', form.latitude)
    fd.append('longitude', form.longitude)
    fd.append('request_mode', 'instant')
    // Phone: send whatever we have; the backend falls back to the account's
    // number for a logged-in citizen, so a blank here is fine for them.
    if (form.requester_phone) fd.append('requester_phone', form.requester_phone.trim())
    if (isGuest.value) fd.append('requester_name', form.requester_name.trim())
    photos.value.forEach(p => fd.append('photos[]', p.file))

    const res = await axios.post(`${API_BASE}/citizen/create.php`, fd, {
      withCredentials: true,
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    if (res.data?.success) {
      refreshRealtimeAccess() // follow the new request live
      if (res.data.mergedInto) {
        alert.success(`Reference ${res.data.reference_number} — this emergency was already reported nearby (${res.data.mergedInto.reference_number}), so you're linked to the unit handling it.`, 'Already reported')
      } else {
        alert.success(`Reference ${res.data.reference_number} — dispatch has your location.`, 'Rescue requested')
      }
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
  photos.value.forEach(p => URL.revokeObjectURL(p.preview))
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
