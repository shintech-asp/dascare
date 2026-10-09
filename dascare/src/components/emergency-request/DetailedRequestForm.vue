<!--
  components/emergency-request/DetailedRequestForm.vue

  "Standard" track: the full incident form — category, severity, quick
  EMS-style prompts, free-text description, optional evidence photos,
  address + map pin (click, current-location, forward/reverse geocode),
  optional schedule-for-later, contact info. This is the same form that
  used to live directly on RequestEmergency.vue; it's been pulled out
  as its own component (no page chrome, no router import) so it can be
  reused on a guest-facing route later without dragging the citizen
  dashboard shell along with it.
-->
<template>
  <div class="overflow-hidden rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#050e1a] shadow-xl shadow-base-300/40 dark:shadow-black/30">

    <!-- Signal bar — medical-blue accent, same gradient language as the app header -->
    <div class="h-[3px] w-full bg-gradient-to-r from-base-100 via-[#1976D2] to-base-100 dark:from-[#050e1a] dark:via-[#4aa3f0] dark:to-[#050e1a]"></div>

    <!-- Header -->
    <div class="px-5 sm:px-7 pt-6 pb-1">
      <div class="inline-flex items-center gap-2 border-l-2 border-[#1976D2] pl-3 dark:border-[#7fb3ec]">
        <span class="font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">
          Full detail · Routine dispatch
        </span>
      </div>
      <h2 class="mt-2 flex items-center gap-2.5 text-xl font-black tracking-tight text-slate-950 dark:text-white">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#1976D2]/10 dark:bg-[#7fb3ec]/10">
          <Icon icon="lucide:clipboard-list" width="19" class="text-[#1976D2] dark:text-[#7fb3ec]" />
        </span>
        Standard Request
      </h2>
    </div>

    <div class="p-4 sm:p-6 sm:pt-4">
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 lg:gap-8">

      <!-- ================= LEFT: incident details ================= -->
      <div class="min-w-0 lg:col-span-3 space-y-5">

        <!-- Category -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">Type of Emergency</label>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-2.5">
            <button
              v-for="cat in categories"
              :key="cat.id"
              type="button"
              @click="form.emergency_category_id = cat.id"
              class="cat-btn flex flex-col items-center gap-1.5 rounded-2xl border-2 px-2 py-3.5 text-center transition-all min-w-0"
              :class="form.emergency_category_id === cat.id
                ? 'border-red-500 bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-300 shadow-sm shadow-red-500/20'
                : 'border-slate-200 dark:border-white/10 text-slate-500 dark:text-white/45 hover:border-red-300 dark:hover:border-red-400/40 hover:bg-red-50/60 dark:hover:bg-red-500/5'"
            >
              <Icon :icon="cat.icon" width="19" class="cat-icon" />
              <span class="text-[0.68rem] font-bold leading-tight">{{ cat.name }}</span>
            </button>
          </div>
        </div>

        <!-- Severity -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">Severity</label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <button
              v-for="level in severityLevels"
              :key="level.value"
              type="button"
              @click="form.severity = level.value"
              class="px-3 py-2.5 rounded-xl text-xs font-bold border-2 transition-all"
              :class="form.severity === level.value ? level.activeClass + ' shadow-sm' : 'border-slate-200 dark:border-white/10 text-slate-500 dark:text-white/45 hover:border-slate-300 dark:hover:border-white/20'"
            >
              {{ level.label }}
            </button>
          </div>
        </div>

        <!-- Quick incident details — folded into the free-text description -->
        <!-- on submit (schema only has one `description` column). -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">Quick Details</label>
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

          <label class="block text-[0.68rem] text-slate-500 dark:text-white/40 mb-1">What's happening?</label>
          <textarea
            v-model="form.description"
            rows="4"
            required
            minlength="10"
            placeholder="Describe the situation — visible injuries, immediate hazards, what led up to it, anything the crew should know before arriving."
            class="w-full rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-white/30 focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
          ></textarea>
        </div>

        <!-- Schedule for later (standard mode only — instant is always "now") -->
        <div>
          <label class="flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">
            <input type="checkbox" v-model="isScheduled" class="rounded border-slate-300 dark:border-white/20 text-[#1976D2] focus:ring-[#1976D2]" />
            Schedule for a specific date/time
            <span class="font-normal text-slate-400 dark:text-white/30">(optional — for planned transport, not urgent dispatch)</span>
          </label>
          <input
            v-if="isScheduled"
            v-model="form.scheduled_for"
            type="datetime-local"
            :min="minScheduleValue"
            class="w-full rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
          />
        </div>

        <!-- Evidence photos (optional) -->
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

        <!-- Requester name — only needed for guest submissions -->
        <div v-if="isGuest">
          <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">Your Full Name</label>
          <input
            v-model="form.requester_name"
            type="text" required placeholder="Juan Dela Cruz"
            class="w-full rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-white/30 focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
          />
        </div>

        <!-- Contact number -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">Contact Number</label>
          <input
            v-model="form.requester_phone"
            type="tel" required maxlength="13"
            placeholder="09XXXXXXXXX"
            class="w-full rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-white/30 focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
          />
          <p v-if="form.requester_phone && !isPhoneValid" class="text-xs text-red-500 mt-1">
            Enter a valid PH mobile number (09XXXXXXXXX or +639XXXXXXXXX).
          </p>
        </div>
      </div>

      <!-- ================= RIGHT: location + map ================= -->
      <div class="min-w-0 lg:col-span-2">
        <div class="lg:sticky lg:top-6 space-y-3">

          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">Address</label>
            <input
              v-model="form.address_text"
              type="text" required
              placeholder="House/unit number, street, subdivision"
              @blur="forwardGeocode"
              class="w-full min-w-0 rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-white/30 focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="min-w-0">
              <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">Barangay</label>
              <select
                v-if="!barangaysLoadFailed"
                v-model="form.barangay"
                required :disabled="loadingBarangays"
                @change="forwardGeocode"
                class="w-full min-w-0 rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3 py-2.5 text-sm text-slate-900 dark:text-white disabled:opacity-60 focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
              >
                <option value="" disabled>{{ loadingBarangays ? 'Loading…' : 'Select barangay' }}</option>
                <option v-for="b in barangaysList" :key="b" :value="b">{{ b }}</option>
              </select>
              <input
                v-else
                v-model="form.barangay"
                type="text" required maxlength="120"
                @blur="forwardGeocode"
                placeholder="Enter barangay"
                class="w-full min-w-0 rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3 py-2.5 text-sm text-slate-900 dark:text-white focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
              />
              <p v-if="barangaysLoadFailed" class="text-[0.65rem] text-red-500 mt-1">
                Couldn't load the live list — enter it manually, or
                <button type="button" @click="fetchDasmarinasBarangays" class="underline font-semibold">retry</button>.
              </p>
            </div>
            <div class="min-w-0">
              <label class="block text-xs font-semibold text-slate-600 dark:text-white/60 mb-1.5">Landmark</label>
              <input
                v-model="form.landmark"
                type="text"
                placeholder="Nearest known landmark"
                class="w-full min-w-0 rounded-xl border-2 border-base-300 dark:border-white/10 bg-base-200 dark:bg-white/5 px-3 py-2.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-white/30 focus:outline-none focus:border-[#1976D2] focus:ring-2 focus:ring-[#1976D2]/25 transition-colors"
              />
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-x-3 gap-y-1 justify-between">
            <label class="block text-xs font-semibold text-slate-600 dark:text-white/60">Pin the Location</label>
            <button type="button" @click="useCurrentLocation" :disabled="locatingUser"
              class="flex items-center gap-1 text-xs font-bold text-[#1976D2] dark:text-[#7fb3ec] hover:underline disabled:opacity-50 shrink-0">
              <Icon icon="lucide:map-pin" width="13" />
              {{ locatingUser ? 'Locating…' : 'Use my current location' }}
            </button>
          </div>

          <div ref="mapContainer" class="w-full h-72 sm:h-80 lg:h-[360px] rounded-2xl border-2 border-slate-200 dark:border-white/10 overflow-hidden"></div>

          <p v-if="geocoding" class="text-[0.68rem] text-slate-400 dark:text-white/30">Looking up the location…</p>
          <p v-else-if="geocodeNote" class="text-[0.68rem] text-slate-400 dark:text-white/30">{{ geocodeNote }}</p>
          <p v-if="mapError" class="text-[0.68rem] text-red-500">{{ mapError }}</p>

          <!-- Legend for the nearby-facilities layer -->
          <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[0.65rem] text-slate-500 dark:text-white/40 pt-1">
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-600 inline-block shrink-0"></span> Hospital</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-600 inline-block shrink-0"></span> Rescue org</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-slate-800 dark:bg-white inline-block shrink-0"></span> Your pin</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Medical records notice — citizens only. Guests have no account/ -->
    <!-- record to pull from, so create.php never attaches anything for -->
    <!-- them and this section just doesn't render. -->
    <div v-if="!isGuest" class="mt-6 flex items-start gap-2.5 rounded-2xl border border-red-200 dark:border-red-500/20 bg-red-50/60 dark:bg-red-500/5 px-4 py-3">
      <Icon icon="lucide:heart-pulse" width="16" class="text-red-600 dark:text-red-300 flex-shrink-0 mt-0.5" />
      <p class="text-xs leading-relaxed text-red-800 dark:text-red-200/80">
        Your saved emergency information (blood type, allergies, medications, emergency contact) will be shared with
        dispatch and the responding crew automatically.
        <RouterLink to="/citizen/medical-records" class="font-bold underline underline-offset-2">
          Keep them up to date
        </RouterLink>.
      </p>
    </div>

    <button
      type="button"
      :disabled="submitting || !isFormValid"
      @click="submitRequest"
      class="group w-full flex items-center justify-center gap-2.5 rounded-2xl bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed
             text-white font-black py-4 text-base shadow-lg shadow-red-600/30 dark:shadow-red-950/40 transition-all hover:shadow-xl active:scale-[0.99] mt-6"
    >
      <Icon v-if="submitting" icon="lucide:loader-2" width="18" class="animate-spin" />
      <Icon v-else icon="lucide:siren" width="18" class="transition-transform group-hover:scale-110" />
      {{ submitting ? 'Submitting...' : 'Submit Emergency Request' }}
    </button>
    </div>
  </div>
</template>

<script setup>
import axios from 'axios'
import { reactive, ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Icon } from '@iconify/vue'
import { useAlert } from '@/composables/useAlert'
import { useSession } from '@/composables/useSession'

const props = defineProps({
  // See InstantRescueForm.vue for why this exists alongside session
  // auto-detection — lets a guest route be explicit about guest UX.
  forceGuest: { type: Boolean, default: false },
})
const emit = defineEmits(['submitted'])

const alert = useAlert()
const { user } = useSession()
const API_BASE = import.meta.env.VITE_API_BASE_URL

const isGuest = computed(() => props.forceGuest || !user.value)

// Mirrors emergency_categories seed data in dascare.sql (id, code, name).
const categories = [
  { id: 1, name: 'Medical', icon: 'lucide:stethoscope' },
  { id: 2, name: 'Road Accident', icon: 'lucide:car-front' },
  { id: 3, name: 'Trauma/Injury', icon: 'lucide:bandage' },
  { id: 4, name: 'Maternal', icon: 'lucide:baby' },
  { id: 5, name: 'Cardiac', icon: 'lucide:heart-pulse' },
  { id: 6, name: 'Fire-related', icon: 'lucide:flame' },
  { id: 7, name: 'Disaster', icon: 'lucide:cloud-lightning' },
  { id: 8, name: 'Other', icon: 'lucide:more-horizontal' },
]

// Mirrors emergency_requests.severity enum.
const severityLevels = [
  { value: 'low', label: 'Low', activeClass: 'border-emerald-500 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' },
  { value: 'moderate', label: 'Moderate', activeClass: 'border-amber-500 bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300' },
  { value: 'high', label: 'High', activeClass: 'border-orange-500 bg-orange-50 dark:bg-orange-500/10 text-orange-700 dark:text-orange-300' },
  { value: 'critical', label: 'Critical', activeClass: 'border-red-500 bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-300' },
]

const submitting = ref(false)

const form = reactive({
  emergency_category_id: null,
  severity: 'moderate',
  description: '',
  address_text: '',
  barangay: '',
  landmark: '',
  latitude: null,
  longitude: null,
  requester_phone: '',
  requester_name: '',
  scheduled_for: '',
})

const quickDetails = reactive({
  patientCount: 1,
  conscious: '',
  breathing: '',
})

// Schedule-for-later toggle. request_mode stays 'standard' either way —
// only scheduled_for changes — the column is nullable and only meaningful
// when request_mode = standard per the schema comment.
const isScheduled = ref(false)
const minScheduleValue = computed(() => {
  const d = new Date(Date.now() + 15 * 60 * 1000) // at least 15 min out
  d.setSeconds(0, 0)
  return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
})

const isPhoneValid = computed(() => /^(\+639\d{9}|09\d{9})$/.test(form.requester_phone.replace(/[\s-]/g, '')))

const isFormValid = computed(() =>
  !!form.emergency_category_id &&
  form.description.trim().length >= 10 &&
  form.address_text.trim().length >= 5 &&
  !!form.barangay.trim() &&
  form.latitude !== null && form.longitude !== null &&
  isPhoneValid.value &&
  (!isGuest.value || form.requester_name.trim().length >= 2) &&
  (!isScheduled.value || !!form.scheduled_for)
)

// Prefill contact number for a logged-in citizen — still editable, in case
// the emergency involves a different callback number.
if (user.value?.phone) form.requester_phone = user.value.phone

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
// PSGC — Dasmariñas barangays only (service area is one city).
// ------------------------------------------------------------------
const PSGC_BASE = 'https://psgc.gitlab.io/api'
const DASMARINAS_CITY_CODE = '042106000'

const barangaysList = ref([])
const loadingBarangays = ref(false)
const barangaysLoadFailed = ref(false)

async function fetchDasmarinasBarangays() {
  loadingBarangays.value = true
  barangaysLoadFailed.value = false
  try {
    const res = await fetch(`${PSGC_BASE}/cities-municipalities/${DASMARINAS_CITY_CODE}/barangays/`)
    if (!res.ok) throw new Error(`PSGC request failed: ${res.status}`)
    const data = await res.json()
    const list = (Array.isArray(data) ? data : []).map(b => b.name).sort()
    if (!list.length) throw new Error('empty barangay list')
    barangaysList.value = list
  } catch (err) {
    console.error('Failed to load Dasmariñas barangays from PSGC', err)
    barangaysLoadFailed.value = true // frontend falls back to free text
  } finally {
    loadingBarangays.value = false
  }
}

// ------------------------------------------------------------------
// Map — Leaflet from CDN (no npm dependency), locked to Dasmariñas.
// Click-to-pin, "use current location", forward/reverse geocoding via
// Nominatim, plus a nearby hospitals/rescue-orgs layer.
// ------------------------------------------------------------------
const DASMARINAS_BOUNDS = [[14.26, 120.86], [14.40, 121.00]]
const DASMARINAS_CENTER = [14.3294, 120.9367]

const mapContainer = ref(null)
const locatingUser = ref(false)
const geocoding = ref(false)
const geocodeNote = ref('')
const mapError = ref('')
// 'manual' once the user taps the map / uses current location; 'auto' when
// placed by forward-geocoding the typed address. Manual always wins.
const pinSource = ref(null)

let leafletMap = null
let leafletMarker = null

function isWithinDasmarinas(lat, lng) {
  const [[south, west], [north, east]] = DASMARINAS_BOUNDS
  return lat >= south && lat <= north && lng >= west && lng <= east
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

function setPin(lat, lng) {
  form.latitude = lat
  form.longitude = lng
  if (!leafletMap) return
  if (leafletMarker) {
    leafletMarker.setLatLng([lat, lng])
  } else {
    leafletMarker = window.L.marker([lat, lng]).addTo(leafletMap)
  }
}

async function loadNearbyFacilities(L) {
  try {
    const res = await axios.get(`${API_BASE}/map/nearby_facilities.php`)
    const { hospitals = [], organizations = [] } = res.data || {}

    hospitals.forEach(h => {
      if (h.latitude == null || h.longitude == null) return
      L.circleMarker([h.latitude, h.longitude], {
        radius: 7, color: '#dc2626', fillColor: '#dc2626', fillOpacity: 0.85, weight: 1.5,
      })
        .bindPopup(`<strong>${h.name}</strong><br>${h.address_text || ''}${h.phone ? `<br>${h.phone}` : ''}`)
        .addTo(leafletMap)
    })

    organizations.forEach(o => {
      if (o.latitude == null || o.longitude == null) return
      const areas = (o.service_areas || []).slice(0, 6).join(', ')
      L.circleMarker([o.latitude, o.longitude], {
        radius: 7, color: '#2563eb', fillColor: '#2563eb', fillOpacity: 0.85, weight: 1.5,
      })
        .bindPopup(`<strong>${o.name}</strong><br>${o.address_line || ''}${areas ? `<br><em>Serves:</em> ${areas}` : ''}`)
        .addTo(leafletMap)
    })
  } catch (err) {
    console.warn('Could not load nearby facilities layer', err)
  }
}

async function initMap() {
  try {
    const L = await loadLeaflet()
    await nextTick()
    if (!mapContainer.value || leafletMap) return

    const bounds = L.latLngBounds(DASMARINAS_BOUNDS)

    leafletMap = L.map(mapContainer.value, {
      maxBounds: bounds.pad(0.05),
      maxBoundsViscosity: 1.0,
      minZoom: 12,
    }).setView(DASMARINAS_CENTER, 13)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(leafletMap)

    L.rectangle(bounds, { color: '#dc2626', weight: 1, fillOpacity: 0.02, dashArray: '4 4' }).addTo(leafletMap)

    leafletMap.on('click', (e) => {
      const { lat, lng } = e.latlng
      if (!isWithinDasmarinas(lat, lng)) {
        mapError.value = 'Please pin a location within Dasmariñas City.'
        return
      }
      mapError.value = ''
      setPin(lat, lng)
      pinSource.value = 'manual'
      reverseGeocode(lat, lng)
    })

    await loadNearbyFacilities(L)
  } catch (err) {
    console.error('Map failed to load', err)
    mapError.value = 'Unable to load the map. You can still fill in the rest of the form.'
  }
}

function useCurrentLocation() {
  if (!navigator.geolocation) {
    mapError.value = 'Location is not supported on this device/browser.'
    return
  }
  locatingUser.value = true
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      const { latitude: lat, longitude: lng } = pos.coords
      locatingUser.value = false
      if (!isWithinDasmarinas(lat, lng)) {
        mapError.value = 'Your current location is outside Dasmariñas — please pin the incident location manually.'
        return
      }
      mapError.value = ''
      setPin(lat, lng)
      pinSource.value = 'manual'
      if (leafletMap) leafletMap.setView([lat, lng], 16)
      reverseGeocode(lat, lng)
    },
    () => {
      mapError.value = 'Unable to get your current location. Please pin it on the map instead.'
      locatingUser.value = false
    },
    { enableHighAccuracy: true, timeout: 10000 }
  )
}

async function reverseGeocode(lat, lng) {
  geocoding.value = true
  geocodeNote.value = ''
  try {
    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&addressdetails=1&zoom=18`)
    const data = await res.json()
    const addr = data.address || {}

    const streetParts = [addr.house_number, addr.road].filter(Boolean)
    if (streetParts.length) form.address_text = streetParts.join(' ')

    const brgyGuess = addr.village || addr.suburb || addr.neighbourhood || addr.quarter || ''
    if (brgyGuess) {
      const target = brgyGuess.toLowerCase().trim()
      const matched = barangaysList.value.find(b => b.toLowerCase() === target)
        || barangaysList.value.find(b => b.toLowerCase().includes(target) || target.includes(b.toLowerCase()))
      if (matched) form.barangay = matched
    }

    geocodeNote.value = 'Address auto-filled from your pin — please double-check it.'
  } catch (err) {
    console.error('Reverse geocoding failed', err)
  } finally {
    geocoding.value = false
  }
}

async function forwardGeocode() {
  if (pinSource.value === 'manual') return
  if (!form.address_text && !form.barangay) return

  const query = [form.address_text.trim(), form.barangay, 'Dasmariñas', 'Cavite', 'Philippines'].filter(Boolean).join(', ')
  geocoding.value = true
  try {
    const res = await fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(query)}&countrycodes=ph&limit=1`)
    const data = await res.json()
    const hit = Array.isArray(data) ? data[0] : null
    if (!hit) return

    const lat = parseFloat(hit.lat)
    const lng = parseFloat(hit.lon)
    if (Number.isNaN(lat) || Number.isNaN(lng) || !isWithinDasmarinas(lat, lng)) return
    if (pinSource.value === 'manual') return // a manual pin may have landed while this was in flight

    setPin(lat, lng)
    pinSource.value = 'auto'
    if (leafletMap) leafletMap.setView([lat, lng], form.barangay ? 15 : 13)
    geocodeNote.value = 'Pinned an approximate location — tap the map to fine-tune it.'
  } catch (err) {
    console.error('Forward geocoding failed', err)
  } finally {
    geocoding.value = false
  }
}

// ------------------------------------------------------------------
// Submit
// ------------------------------------------------------------------
function buildFullDescription() {
  const bits = []
  if (quickDetails.patientCount) bits.push(`Patients involved: ${quickDetails.patientCount}`)
  if (quickDetails.conscious) bits.push(`Conscious: ${quickDetails.conscious === 'yes' ? 'Yes' : 'No'}`)
  if (quickDetails.breathing) bits.push(`Breathing: ${quickDetails.breathing === 'yes' ? 'Yes' : 'No'}`)
  const summary = bits.length ? bits.join(' · ') + '\n\n' : ''
  return summary + form.description.trim()
}

async function submitRequest() {
  if (!isFormValid.value) return
  submitting.value = true
  try {
    const fd = new FormData()
    fd.append('emergency_category_id', form.emergency_category_id)
    fd.append('severity', form.severity)
    fd.append('description', buildFullDescription())
    fd.append('address_text', form.address_text.trim())
    fd.append('barangay', form.barangay.trim())
    fd.append('landmark', form.landmark.trim())
    fd.append('latitude', form.latitude)
    fd.append('longitude', form.longitude)
    fd.append('requester_phone', form.requester_phone.trim())
    fd.append('request_mode', 'standard')
    if (isScheduled.value && form.scheduled_for) {
      // datetime-local gives "YYYY-MM-DDTHH:MM" — normalize to "YYYY-MM-DD HH:MM:SS" for MySQL.
      fd.append('scheduled_for', form.scheduled_for.replace('T', ' ') + ':00')
    }
    if (isGuest.value) fd.append('requester_name', form.requester_name.trim())
    photos.value.forEach(p => fd.append('photos[]', p.file))

    const res = await axios.post(`${API_BASE}/citizen/create.php`, fd, {
      withCredentials: true,
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    if (res.data?.success) {
      alert.success(`Reference ${res.data.reference_number} — a dispatcher is reviewing it now.`, 'Request submitted')
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

onMounted(async () => {
  await fetchDasmarinasBarangays()
  await nextTick()
  initMap()
})

onBeforeUnmount(() => {
  photos.value.forEach(p => URL.revokeObjectURL(p.preview))
  if (leafletMap) {
    leafletMap.remove()
    leafletMap = null
    leafletMarker = null
  }
})
</script>

<style scoped>
/* Category tiles get a small icon "pop" on hover/select, echoing the
   header nav's micro-interactions without stealing its exact motion. */
.cat-btn .cat-icon {
  transition: transform 0.2s ease;
}
.cat-btn:hover .cat-icon {
  transform: scale(1.15);
}
</style>