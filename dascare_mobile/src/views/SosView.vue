<template>
  <div class="min-h-screen bg-base-200 dark:bg-[#050e1a]">
    <ScreenHeader title="Emergency SOS" :fallback="isGuest ? '/welcome' : '/home'" />

    <main class="space-y-4 px-4 pb-[calc(var(--safe-bottom)+2rem)] pt-3">
      <!-- ================= Sent ================= -->
      <section v-if="result" class="overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-6 text-center shadow-xl shadow-base-300/40 dark:border-white/10 dark:bg-[#071829] dark:shadow-black/30">
        <Icon icon="lucide:circle-check-big" width="52" class="mx-auto text-emerald-500" />
        <h2 class="mt-3 text-xl font-black text-slate-900 dark:text-white">Request sent</h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-white/55">
          Reference <span class="selectable font-mono font-bold">{{ result.reference_number }}</span> — dispatch has your location.
        </p>

        <!-- Linked by duplicate detection — same notice as the web -->
        <div v-if="result.mergedInto" class="mt-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-left dark:border-red-500/20 dark:bg-red-500/5">
          <p class="flex items-start gap-2 text-sm font-black text-red-800 dark:text-red-200">
            <Icon icon="lucide:git-merge" width="16" class="mt-0.5 flex-shrink-0" />
            Linked to {{ result.mergedInto.reference_number }} — a unit is already being handled for this emergency
          </p>
          <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-white/55">
            Someone {{ result.mergedInto.distance_m }} m away reported it first, so one unit covers both reports instead of sending two.
          </p>
          <p v-if="separated" class="mt-3 text-xs font-bold text-emerald-700 dark:text-emerald-300">
            <Icon icon="lucide:circle-check" width="13" class="mr-0.5 inline" /> Done — dispatch is finding a separate unit for your report.
          </p>
          <button v-else type="button" :disabled="separating" class="tap mt-3 flex w-full items-center justify-center gap-1.5 rounded-xl border border-red-300 bg-white px-3 py-3 text-xs font-bold text-red-700 disabled:opacity-50 dark:border-red-500/30 dark:bg-white/5 dark:text-red-300" @click="requestSeparately">
            <Icon :icon="separating ? 'lucide:loader-circle' : 'lucide:split'" width="14" :class="separating ? 'animate-spin' : ''" />
            Not the same emergency? Request separately
          </button>
        </div>

        <p v-if="result.guestStatus?.flagged" class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
          This was guest request #{{ result.guestStatus.ordinal }} today for this phone number, so it's marked for a quick extra
          check by a dispatcher — it's still on its way to them exactly like any other request.
        </p>

        <div class="mt-5 flex items-center gap-2.5 rounded-2xl bg-base-200 p-4 text-left dark:bg-white/5">
          <Icon icon="lucide:map-pin" width="18" class="flex-shrink-0 text-red-600 dark:text-red-400" />
          <p class="text-xs leading-5 text-slate-600 dark:text-white/55">Stay where you are if it's safe. {{ isGuest ? 'Keep your phone on in case the responders need to reach you.' : 'Dispatch may call your registered number.' }}</p>
        </div>

        <p v-if="isGuest" class="mt-4 text-xs text-slate-500 dark:text-white/40">
          Guest requests aren't saved to an account. This phone keeps the reference so you can follow up here.
        </p>

        <div class="mt-5 space-y-2">
          <RouterLink :to="{ name: 'Track', params: { id: result.id } }" replace class="tap flex w-full items-center justify-center gap-2 rounded-2xl bg-red-600 py-3.5 font-bold text-white no-underline shadow-lg shadow-red-600/25">
            <Icon icon="lucide:radar" width="18" /> Track this request
          </RouterLink>
          <RouterLink :to="isGuest ? '/welcome' : '/home'" replace class="tap flex w-full items-center justify-center rounded-2xl bg-slate-900 py-3.5 font-bold text-white no-underline dark:bg-white dark:text-slate-900">Done</RouterLink>
          <a href="tel:911" class="tap flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-red-200 py-3 text-sm font-bold text-red-700 no-underline dark:border-red-500/25 dark:text-red-300">
            <Icon icon="lucide:phone-call" width="16" /> Also call 911
          </a>
        </div>
      </section>

      <!-- ================= Form — mirrors the web's EmergencyRequestForm ================= -->
      <template v-else>
        <p v-if="isGuest && guestUsed !== null" class="rounded-xl bg-[#f8f3e8] px-3 py-2 text-center text-xs font-semibold text-slate-500 dark:bg-white/5 dark:text-white/45">
          {{ guestUsed }} of {{ guestLimit }} guest {{ guestUsed === 1 ? 'request' : 'requests' }} sent today on this device — a genuine emergency always goes through regardless.
        </p>

        <section class="overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-xl shadow-base-300/40 dark:border-white/10 dark:bg-[#071829] dark:shadow-black/30">
          <div class="h-[3px] w-full bg-gradient-to-r from-base-100 via-red-500 to-base-100 dark:from-[#071829] dark:to-[#071829]"></div>

          <div class="relative overflow-hidden px-6 pb-5 pt-7 text-center">
            <div class="pointer-events-none absolute left-1/2 top-1/2 h-44 w-44 -translate-x-1/2 -translate-y-1/2 animate-ping rounded-full bg-red-500/10 [animation-duration:2.2s]"></div>
            <div class="relative inline-flex items-center gap-2 border-l-2 border-red-600 pl-3 dark:border-red-400">
              <span class="font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">Fastest response · No forms</span>
            </div>
            <Icon icon="lucide:siren" width="46" class="relative mx-auto mt-4 text-red-600 dark:text-red-400" />
            <h2 class="relative mt-3 text-2xl font-black tracking-tight text-slate-950 dark:text-white">Request <span class="text-red-600 dark:text-red-400">Rescue</span></h2>
            <p class="relative mx-auto mt-1.5 max-w-xs text-xs leading-relaxed text-slate-500 dark:text-white/45">One tap. Your location goes straight to dispatch — no forms, no typing.</p>
          </div>

          <!-- GPS status strip -->
          <div class="flex items-center justify-between gap-3 border-y px-5 py-3 text-xs font-bold uppercase tracking-wider transition-colors" :class="gpsStrip">
            <span class="flex items-center gap-2.5">
              <span class="grid h-6 w-6 flex-shrink-0 place-items-center rounded-full bg-white/70 dark:bg-black/20">
                <Icon :icon="gpsIcon" width="14" :class="{ 'animate-spin': locating }" />
              </span>
              {{ gpsStatusText }}
            </span>
            <button v-if="!locating && !locationOk" type="button" class="shrink-0 py-1 underline decoration-2 underline-offset-2" @click="captureGps(true)">Retry GPS</button>
          </div>

          <div v-if="gpsError && !showMap" class="space-y-3 border-b border-base-300 bg-base-200 px-5 py-4 dark:border-white/10 dark:bg-white/5">
            <p class="text-xs leading-relaxed text-slate-500 dark:text-white/45">{{ gpsErrorText }}</p>
            <button type="button" class="tap flex w-full items-center justify-center gap-1.5 rounded-xl border-2 border-base-300 bg-base-100 px-3 py-3 text-xs font-bold text-slate-700 dark:border-white/15 dark:bg-white/5 dark:text-white/70" @click="showMap = true">
              <Icon icon="lucide:map-pin" width="14" /> Pin location manually
            </button>
          </div>

          <div class="space-y-5 p-5">
            <div v-if="showMap">
              <MapPinPicker :lat="form.latitude" :lng="form.longitude" label="Tap your location on the map"
                :hint="geocoding ? 'Looking up the address…' : (form.address_text || 'Tap the map to drop or move the pin.')"
                @update="onMapPin" />
            </div>

            <div v-if="!isGuest" class="flex items-start gap-2.5 rounded-2xl border border-red-200 bg-red-50/60 px-4 py-3 dark:border-red-500/20 dark:bg-red-500/5">
              <Icon icon="lucide:heart-pulse" width="16" class="mt-0.5 flex-shrink-0 text-red-600 dark:text-red-300" />
              <p class="text-xs leading-relaxed text-red-800 dark:text-red-200/80">Your saved emergency information will be shared with the responding crew automatically.</p>
            </div>

            <!-- Could not send -->
            <div v-if="sendError" ref="sendErrorEl" class="scroll-mt-24 rounded-2xl border border-red-200 bg-red-50 p-4 dark:border-red-500/20 dark:bg-red-500/10">
              <p class="flex items-center gap-2 text-sm font-black text-red-800 dark:text-red-200"><Icon icon="lucide:wifi-off" width="16" /> Couldn't send your request</p>
              <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-white/55">{{ sendError }}</p>
              <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" class="tap rounded-xl bg-red-600 py-3 text-sm font-bold text-white" @click="submit">Try again</button>
                <a href="tel:911" class="tap flex items-center justify-center gap-1.5 rounded-xl border-2 border-red-300 py-3 text-sm font-bold text-red-700 no-underline dark:border-red-500/30 dark:text-red-300"><Icon icon="lucide:phone-call" width="15" /> Call 911</a>
              </div>
            </div>

            <!-- The big button — same look as the web -->
            <button type="button" :disabled="submitting"
              class="mx-auto flex aspect-square w-full max-w-[230px] flex-col items-center justify-center gap-3 rounded-full border-[8px] border-red-400/70 bg-red-600 px-8 text-center text-white shadow-[0_18px_45px_rgba(220,38,38,0.32)] transition-transform active:scale-[0.96] disabled:opacity-60 dark:border-red-400/50 dark:shadow-red-950/40"
              @click="submit">
              <Icon :icon="submitting ? 'lucide:loader-2' : 'lucide:zap'" width="30" :class="submitting ? 'animate-spin' : ''" />
              <span class="max-w-[150px] text-lg font-black uppercase leading-tight tracking-tight">{{ submitting ? 'Sending…' : 'Request Rescue Now' }}</span>
            </button>
            <p class="text-center text-[0.7rem] text-slate-400 dark:text-white/30">
              {{ isGuest ? 'Your location is sent to dispatch — stay where you are if possible.' : `Dispatch will call ${form.requester_phone || 'your registered number'} — stay where you are if possible.` }}
            </p>

            <!-- ================= Optional details ================= -->
            <div class="pt-1">
              <button type="button" class="tap flex w-full items-center justify-center gap-1.5 py-2 text-xs font-bold text-slate-500 dark:text-white/45" @click="toggleDetails">
                <Icon :icon="showDetails ? 'lucide:chevron-up' : 'lucide:chevron-down'" width="14" />
                {{ showDetails ? 'Hide details' : 'Add details (optional)' }}
              </button>

              <div v-if="showDetails" class="mt-4 space-y-5 border-t border-base-300 pt-5 dark:border-white/10">
                <div>
                  <label :class="ui.label">What's happening? <span class="font-normal text-slate-400 dark:text-white/30">(optional)</span></label>
                  <div class="mb-3 grid grid-cols-3 gap-2">
                    <div>
                      <span class="mb-1 block text-[0.68rem] text-slate-500 dark:text-white/40">Patients</span>
                      <input v-model.number="quick.patientCount" type="number" inputmode="numeric" min="1" max="50" :class="small" />
                    </div>
                    <div>
                      <span class="mb-1 block text-[0.68rem] text-slate-500 dark:text-white/40">Conscious?</span>
                      <select v-model="quick.conscious" :class="small"><option value="">Unknown</option><option value="yes">Yes</option><option value="no">No</option></select>
                    </div>
                    <div>
                      <span class="mb-1 block text-[0.68rem] text-slate-500 dark:text-white/40">Breathing?</span>
                      <select v-model="quick.breathing" :class="small"><option value="">Unknown</option><option value="yes">Yes</option><option value="no">No</option></select>
                    </div>
                  </div>
                  <textarea v-model="form.description" rows="3" maxlength="1900" placeholder="Describe the situation — visible injuries, immediate hazards, anything the crew should know before arriving." :class="[ui.input, 'resize-none text-sm']"></textarea>
                </div>

                <div>
                  <label :class="ui.label">Address <span class="font-normal text-slate-400 dark:text-white/30">(optional)</span></label>
                  <input v-model="form.address_text" maxlength="255" placeholder="House/unit number, street, subdivision" :class="ui.input" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div class="min-w-0">
                    <label :class="ui.label">Barangay</label>
                    <select v-if="!barangaysFailed" v-model="form.barangay" :disabled="loadingBarangays" :class="ui.input">
                      <option value="">{{ loadingBarangays ? 'Loading…' : 'Select' }}</option>
                      <option v-if="form.barangay && !barangays.includes(form.barangay)" :value="form.barangay">{{ form.barangay }}</option>
                      <option v-for="b in barangays" :key="b" :value="b">{{ b }}</option>
                    </select>
                    <input v-else v-model="form.barangay" maxlength="120" placeholder="Barangay" :class="ui.input" />
                  </div>
                  <div class="min-w-0">
                    <label :class="ui.label">Landmark</label>
                    <input v-model="form.landmark" maxlength="255" placeholder="Nearest landmark" :class="ui.input" />
                  </div>
                </div>
                <button v-if="!showMap" type="button" class="tap flex items-center gap-1.5 py-1 text-xs font-bold text-[#1976D2] dark:text-[#7fb3ec]" @click="showMap = true">
                  <Icon icon="lucide:map-pin" width="13" /> Adjust location on map
                </button>

                <div>
                  <label :class="ui.label">Photos <span class="font-normal text-slate-400 dark:text-white/30">(optional, up to 3)</span></label>
                  <div class="flex flex-wrap gap-2">
                    <div v-for="(p, i) in photos" :key="p.preview" class="relative">
                      <img :src="p.preview" class="h-20 w-20 rounded-xl border-2 border-slate-200 object-cover dark:border-white/10" alt="" />
                      <button type="button" class="absolute -right-1.5 -top-1.5 grid h-6 w-6 place-items-center rounded-full bg-slate-800 text-white" aria-label="Remove photo" @click="photos.splice(i, 1)"><Icon icon="lucide:x" width="12" /></button>
                    </div>
                    <template v-if="photos.length < 3">
                      <button type="button" class="tap grid h-20 w-20 place-items-center rounded-xl border-2 border-dashed border-slate-300 text-slate-500 dark:border-white/15 dark:text-white/50" aria-label="Take photo" @click="addPhotos('camera')">
                        <span class="flex flex-col items-center gap-1 text-[0.65rem] font-bold"><Icon icon="lucide:camera" width="20" class="text-red-600 dark:text-red-400" />Camera</span>
                      </button>
                      <button type="button" class="tap grid h-20 w-20 place-items-center rounded-xl border-2 border-dashed border-slate-300 text-slate-500 dark:border-white/15 dark:text-white/50" aria-label="Choose photos" @click="addPhotos('gallery')">
                        <span class="flex flex-col items-center gap-1 text-[0.65rem] font-bold"><Icon icon="lucide:image" width="20" class="text-[#1976D2] dark:text-[#7fb3ec]" />Gallery</span>
                      </button>
                    </template>
                  </div>
                  <p class="mt-1 text-[0.68rem] text-slate-400 dark:text-white/30">JPG, PNG, or WEBP, max 5MB each.</p>
                </div>

                <div v-if="isGuest" class="space-y-3">
                  <div>
                    <label :class="ui.label">Your full name <span class="font-normal text-slate-400 dark:text-white/30">(optional)</span></label>
                    <input v-model="form.requester_name" maxlength="160" autocomplete="name" placeholder="Juan Dela Cruz" :class="ui.input" />
                  </div>
                  <div>
                    <label :class="ui.label">Contact number <span class="font-normal text-slate-400 dark:text-white/30">(optional)</span></label>
                    <input v-model="form.requester_phone" type="tel" inputmode="tel" maxlength="13" placeholder="09XXXXXXXXX" :class="ui.input" />
                    <p v-if="form.requester_phone && !phoneValid" class="mt-1 text-xs text-red-500">Enter a valid PH mobile number (09XXXXXXXXX or +639XXXXXXXXX).</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-center gap-2.5 border-t border-base-300 bg-base-200 px-5 py-3.5 dark:border-white/10 dark:bg-white/5">
            <span class="grid h-6 w-6 flex-shrink-0 place-items-center rounded-full bg-red-100 dark:bg-red-500/15"><Icon icon="lucide:phone-call" width="13" class="text-red-600 dark:text-red-400" /></span>
            <span class="text-xs font-bold text-red-700 dark:text-red-300">Life-threatening? Call your local emergency hotline too.</span>
          </div>
        </section>
      </template>
    </main>
  </div>
</template>

<script setup>
// Mobile version of the web's merged request form
// (dascare/src/components/emergency-request/EmergencyRequestForm.vue): same
// near-field-less design, same payload to citizen/create.php. Differences:
// the phone's GPS/camera, haptic confirmation, an explicit offline state,
// and guest SOS keys kept on the phone (see composables/useGuestKeys.js).
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { Geolocation } from '@capacitor/geolocation'
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera'
import { Haptics, NotificationType } from '@capacitor/haptics'
import ScreenHeader from '@/components/ScreenHeader.vue'
import MapPinPicker from '@/components/MapPinPicker.vue'
import * as ui from '@/components/ui/styles'
import { refreshRealtimeAccess } from '@/services/realtime'
import api, { apiMessage } from '@/services/api'
import { useSession } from '@/composables/useSession'
import { useGuestKeys } from '@/composables/useGuestKeys'
import { useToast } from '@/composables/useToast'
import { enablePush } from '@/services/push'
import { cameraDeniedText, isPermissionDenied, isUserCancel, locationDeniedText } from '@/utils/permissions'

const GENERIC_CATEGORY_ID = 8 // "Other" — dispatch classifies on the call (same as the web)
const BOUNDS = { south: 14.26, north: 14.40, west: 120.86, east: 121.0 } // matches create.php
const MAX_PHOTO_BYTES = 5 * 1024 * 1024

const { user, isLoggedIn } = useSession()
const guestKeys = useGuestKeys()
const toast = useToast()
const isGuest = computed(() => !isLoggedIn.value)

const form = reactive({ address_text: '', barangay: '', landmark: '', latitude: null, longitude: null, requester_phone: '', requester_name: '', description: '' })
const quick = reactive({ patientCount: 1, conscious: '', breathing: '' })
const photos = ref([]) // [{ blob, preview }]
const small = 'w-full min-w-0 rounded-lg border border-slate-200 bg-[#f8f3e8] px-2 py-2.5 text-sm text-slate-800 focus:border-red-600 focus:outline-none dark:border-white/10 dark:bg-white/5 dark:text-white'

if (user.value?.phone) form.requester_phone = user.value.phone
const phoneValid = computed(() => ui.PH_MOBILE.test((form.requester_phone || '').replace(/[\s-]/g, '')))
const inDasmarinas = (lat, lng) => lat >= BOUNDS.south && lat <= BOUNDS.north && lng >= BOUNDS.west && lng <= BOUNDS.east

// ------------------------------------------------------------------
// GPS (captured automatically, like the web)
// ------------------------------------------------------------------
const locating = ref(false)
const gpsError = ref(false)
const GPS_FAILED_TEXT = 'Couldn\'t get your GPS — make sure Location is on. You can still send this by pinning your location on the map.'
const gpsErrorText = ref(GPS_FAILED_TEXT)
const showMap = ref(false)
const geocoding = ref(false)
const locationOk = computed(() => form.latitude !== null && form.longitude !== null)

const gpsIcon = computed(() => (locating.value ? 'lucide:loader-2' : locationOk.value ? 'lucide:map-pin-check' : gpsError.value ? 'lucide:map-pin-off' : 'lucide:locate-fixed'))
const gpsStatusText = computed(() => {
  if (locating.value) return 'Locating you…'
  if (locationOk.value) return showMap.value ? 'Location pinned on map' : 'GPS location locked'
  if (gpsError.value) return 'GPS unavailable'
  return 'Acquiring GPS…'
})
const gpsStrip = computed(() => (locationOk.value
  ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300'
  : gpsError.value
    ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300'
    : 'border-base-300 bg-base-200 text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-white/45'))

async function captureGps(userInitiated = false) {
  locating.value = true
  gpsError.value = false
  try {
    // Ask once. A first "Don't allow" comes back as prompt-with-rationale,
    // not "denied" — anything short of granted means stop here, otherwise
    // getCurrentPosition() would pop the system dialog a second time.
    const perm = await Geolocation.requestPermissions().catch(() => null)
    if (perm && perm.location !== 'granted' && perm.coarseLocation !== 'granted') throw Object.assign(new Error('denied'), { denied: true })
    const pos = await Geolocation.getCurrentPosition({ enableHighAccuracy: true, timeout: 12000, maximumAge: 5000 })
    const { latitude: lat, longitude: lng } = pos.coords
    if (!inDasmarinas(lat, lng)) {
      gpsError.value = true
      gpsErrorText.value = 'Your current location is outside Dasmariñas. Pin the incident location on the map instead.'
      showMap.value = true
      if (userInitiated) toast.error('Your current location is outside Dasmariñas. Pin the incident location manually.', 'Outside service area')
      return
    }
    setLocation(lat, lng)
  } catch (err) {
    gpsError.value = true
    gpsErrorText.value = err?.denied ? locationDeniedText : GPS_FAILED_TEXT
  } finally {
    locating.value = false
  }
}

function setLocation(lat, lng) {
  form.latitude = lat
  form.longitude = lng
  reverseGeocode(lat, lng)
}
function onMapPin({ lat, lng }) {
  gpsError.value = false
  setLocation(lat, lng)
}

async function reverseGeocode(lat, lng) {
  geocoding.value = true
  try {
    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&addressdetails=1&zoom=18`)
    const data = await res.json()
    const addr = data.address || {}
    const street = [addr.house_number, addr.road].filter(Boolean).join(' ')
    form.address_text = street || (data.display_name || '').split(',').slice(0, 2).join(',').trim()
    const guess = addr.village || addr.suburb || addr.neighbourhood || addr.quarter || ''
    if (guess) {
      const t = guess.toLowerCase().trim()
      form.barangay = barangays.value.find((b) => b.toLowerCase() === t) || barangays.value.find((b) => b.toLowerCase().includes(t) || t.includes(b.toLowerCase())) || guess
    }
  } catch { /* offline — the backend fills safe defaults */ } finally {
    geocoding.value = false
  }
}

// ------------------------------------------------------------------
// Optional details
// ------------------------------------------------------------------
const showDetails = ref(false)
const barangays = ref([])
const loadingBarangays = ref(false)
const barangaysFailed = ref(false)

async function loadBarangays() {
  if (barangays.value.length || loadingBarangays.value) return
  loadingBarangays.value = true
  try {
    const res = await fetch('https://psgc.gitlab.io/api/cities-municipalities/042106000/barangays/')
    if (!res.ok) throw new Error(String(res.status))
    barangays.value = (await res.json()).map((b) => b.name).sort()
  } catch {
    barangaysFailed.value = true
  } finally {
    loadingBarangays.value = false
  }
}
function toggleDetails() {
  showDetails.value = !showDetails.value
  if (showDetails.value) loadBarangays()
}

async function blobFromWebPath(webPath) {
  const blob = await (await fetch(webPath)).blob()
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(blob.type)) throw new Error('type')
  if (blob.size > MAX_PHOTO_BYTES) throw new Error('size')
  return blob
}
async function addPhotos(source) {
  try {
    const remaining = 3 - photos.value.length
    const webPaths = source === 'camera'
      ? [(await Camera.getPhoto({ source: CameraSource.Camera, resultType: CameraResultType.Uri, quality: 75, width: 1600, correctOrientation: true })).webPath]
      : (await Camera.pickImages({ limit: remaining, quality: 75, width: 1600 })).photos.map((p) => p.webPath)
    for (const webPath of webPaths.slice(0, remaining)) {
      try {
        photos.value.push({ blob: await blobFromWebPath(webPath), preview: webPath })
      } catch (e) {
        toast.error(e.message === 'size' ? 'Each photo must be 5MB or smaller' : 'Only JPG, PNG, or WEBP images are allowed', 'Photo skipped')
      }
    }
  } catch (err) {
    if (!isUserCancel(err)) toast.error(isPermissionDenied(err) ? cameraDeniedText : 'Couldn’t open the camera or gallery. Please try again.', 'Photo not added')
  }
}

// ------------------------------------------------------------------
// Submit — always tappable; only location is required (same as the web)
// ------------------------------------------------------------------
const submitting = ref(false)
const sendError = ref('')
const sendErrorEl = ref(null)
const result = ref(null)

function buildDescription() {
  const bits = []
  if (quick.patientCount) bits.push(`Patients involved: ${quick.patientCount}`)
  if (quick.conscious) bits.push(`Conscious: ${quick.conscious === 'yes' ? 'Yes' : 'No'}`)
  if (quick.breathing) bits.push(`Breathing: ${quick.breathing === 'yes' ? 'Yes' : 'No'}`)
  const summary = bits.length ? bits.join(' · ') + '\n\n' : ''
  return (summary + form.description.trim()).trim()
}

async function submit() {
  if (submitting.value) return
  document.activeElement?.blur()
  sendError.value = ''
  if (!locationOk.value) {
    if (!locating.value && !gpsError.value) await captureGps(true)
    if (!locationOk.value) {
      showMap.value = true
      toast.error('We still need your location. Please pin it on the map.', 'Location needed')
      return
    }
  }
  if (isGuest.value && form.requester_phone && !phoneValid.value) {
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
    if (form.requester_phone) fd.append('requester_phone', form.requester_phone.trim())
    if (isGuest.value) fd.append('requester_name', form.requester_name.trim())
    photos.value.forEach((p, i) => fd.append('photos[]', p.blob, `photo_${i + 1}.${p.blob.type.split('/')[1] || 'jpg'}`))

    const { data } = await api.post('/citizen/create.php', fd, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 45000 })
    if (!data?.success) throw Object.assign(new Error(data?.message || 'Could not submit request'), { server: true })

    if (data.guestAccessToken) {
      await guestKeys.add({ id: data.id, reference_number: data.reference_number, token: data.guestAccessToken, created_at: new Date().toISOString() })
    }
    result.value = data
    if (!data.guestAccessToken) refreshRealtimeAccess() // follow the new request live
    Haptics.notification({ type: NotificationType.Success }).catch(() => {})
    enablePush() // get "a unit is on the way" even with the app closed
  } catch (err) {
    Haptics.notification({ type: NotificationType.Error }).catch(() => {})
    sendError.value = err.server ? err.message : err.response
      ? apiMessage(err, 'Could not submit request — please try again.')
      : 'No connection to DASCARE. Check your mobile data or Wi-Fi and try again — or call 911 now.'
    nextTick(() => sendErrorEl.value?.scrollIntoView({ behavior: 'smooth', block: 'center' }))
  } finally {
    submitting.value = false
  }
}

// "Not the same emergency?" — citizen/unmerge.php accepts the logged-in
// citizen, or a guest via the X-Guest-Tokens key saved above.
const separating = ref(false)
const separated = ref(false)
async function requestSeparately() {
  separating.value = true
  try {
    const { data } = await api.post('/citizen/unmerge.php', { id: result.value.id })
    if (!data?.success) throw new Error(data?.message)
    separated.value = true
  } catch (err) {
    toast.error(apiMessage(err, 'Something went wrong. Please try again.'), 'Error')
  } finally {
    separating.value = false
  }
}

// Guest daily count, same "X of 3 today" note as the web guest modal.
const guestUsed = ref(null)
const guestLimit = ref(3)
async function loadGuestStatus() {
  try {
    const { data } = await api.get('/citizen/guest_status.php')
    if (data?.success) { guestUsed.value = data.used; guestLimit.value = data.limit }
  } catch { /* informational only */ }
}

onMounted(() => {
  captureGps()
  if (isGuest.value) loadGuestStatus()
})
</script>
