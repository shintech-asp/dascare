<template>
  <div class="min-h-screen bg-base-200 dark:bg-[#050e1a]">
    <ScreenHeader title="Verify identity" fallback="/home" />

    <main class="px-4 pb-[calc(var(--safe-bottom)+2rem)] pt-4">
      <div v-if="loading" class="py-16 text-center text-sm text-slate-500 dark:text-white/40">Loading your verification status…</div>

      <!-- Approved — same copy as the web's Verification.vue -->
      <section v-else-if="status === 2" :class="[ui.card, 'p-7 text-center']">
        <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-emerald-50 dark:bg-emerald-500/10">
          <Icon icon="lucide:shield-check" width="34" class="text-emerald-600 dark:text-emerald-400" />
        </div>
        <span :class="ui.eyebrow"><span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>Account Access</span>
        <h2 class="mt-4 text-xl font-bold text-slate-900 dark:text-white">You're already verified</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-white/45">Your account has full access to request history, live tracking and medical records.</p>
        <RouterLink to="/home" replace :class="[ui.secondaryButton, 'mt-6 no-underline']">Back to Home</RouterLink>
      </section>

      <!-- Pending review -->
      <section v-else-if="status === 1" :class="[ui.card, 'p-7 text-center']">
        <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-yellow-50 dark:bg-yellow-500/10">
          <Icon icon="lucide:hourglass" width="30" class="text-yellow-600 dark:text-yellow-400" />
        </div>
        <span :class="ui.eyebrow"><span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>Account Access</span>
        <h2 class="mt-4 text-xl font-bold text-slate-900 dark:text-white">Verification Under Review</h2>
        <p v-if="user?.kyc_submitted_at" class="mt-2 text-sm text-slate-500 dark:text-white/45">Submitted on {{ formatDate(user.kyc_submitted_at) }}.</p>
        <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-white/45">
          This usually takes <span class="font-semibold text-yellow-600 dark:text-yellow-400">24–48 hours</span>. You'll be notified once a decision is made.
        </p>
        <RouterLink to="/sos" class="tap mt-6 flex items-center justify-center gap-2 rounded-2xl bg-red-600 py-3.5 text-sm font-bold text-white no-underline">
          <Icon icon="lucide:siren" width="16" /> Request Emergency Assistance
        </RouterLink>
      </section>

      <!-- Not submitted / rejected / resubmission requested -->
      <form v-else class="space-y-4" @submit.prevent="confirmAndSubmit">
        <section :class="[ui.card, 'p-6 text-center']">
          <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl" :class="status === 3 ? 'bg-red-50 dark:bg-red-500/10' : status === 4 ? 'bg-amber-50 dark:bg-amber-500/10' : 'bg-[#1976D2]/10'">
            <Icon :icon="status === 3 ? 'lucide:x-circle' : status === 4 ? 'lucide:rotate-ccw' : 'lucide:shield-alert'" width="28"
              :class="status === 3 ? 'text-red-600 dark:text-red-400' : status === 4 ? 'text-amber-600 dark:text-amber-400' : 'text-[#1976D2] dark:text-[#7fb3ec]'" />
          </div>
          <span :class="ui.eyebrow"><span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>Account Access</span>
          <h2 class="mt-3 text-xl font-bold text-slate-900 dark:text-white">{{ status === 3 ? 'Re-submit Verification' : status === 4 ? 'Resubmission Requested' : 'Verify Your Account' }}</h2>
          <button v-if="(status === 3 || status === 4) && user?.kyc_note" type="button" class="tap mt-4 flex w-full items-center justify-between gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-left dark:border-red-500/20 dark:bg-red-500/10" @click="showReason">
            <span class="flex min-w-0 items-center gap-2 text-sm font-semibold text-red-700 dark:text-red-300">
              <Icon icon="lucide:alert-circle" width="16" class="shrink-0" />
              <span class="truncate">{{ status === 4 ? 'Administrator requested a new submission' : 'Previous submission was rejected' }}</span>
            </span>
            <span class="shrink-0 text-xs font-bold text-red-700 underline underline-offset-2 dark:text-red-300">View reason</span>
          </button>
          <p class="mt-4 text-sm leading-relaxed text-slate-500 dark:text-white/45">
            Upload a valid government-issued ID to verify your DASCARE account and unlock verified-account features.
            Emergency assistance remains available even while verification is pending or requires correction.
          </p>
        </section>

        <!-- 1 · About you -->
        <section :class="[ui.card, 'space-y-4 p-5']">
          <h3 class="flex items-center gap-2 text-sm font-black text-slate-900 dark:text-white"><span class="grid h-6 w-6 place-items-center rounded-full bg-red-600 text-[0.7rem] text-white">1</span>About you</h3>
          <div>
            <label :class="ui.label">Birthdate</label>
            <input v-model="birthdate" type="date" required :min="minBirthdate" :max="maxBirthdate" :class="ui.input" />
            <p v-if="birthdate && !birthdateValid" class="mt-1.5 text-xs text-red-500">You must be between 13 and 85 years old.</p>
            <span v-else-if="ageGroup" class="mt-1.5 inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold" :class="ageChip.class">{{ ageChip.text }}</span>
            <p v-else class="mt-1.5 text-xs text-slate-400 dark:text-white/30">Must be at least 13 years old (max 85).</p>
          </div>
          <div>
            <label :class="ui.label">Phone number</label>
            <input v-model="phoneNumber" type="tel" inputmode="numeric" maxlength="11" required placeholder="09XXXXXXXXX" :class="ui.input" @input="phoneNumber = phoneNumber.replace(/\D/g, '').slice(0, 11)" />
            <p v-if="phoneNumber && !phoneValid" class="mt-1.5 text-xs text-red-500">Enter an 11-digit PH mobile number starting with 09.</p>
          </div>
        </section>

        <!-- 2 · Your ID -->
        <section :class="[ui.card, 'space-y-4 p-5']">
          <h3 class="flex items-center gap-2 text-sm font-black text-slate-900 dark:text-white"><span class="grid h-6 w-6 place-items-center rounded-full bg-red-600 text-[0.7rem] text-white">2</span>Your ID</h3>
          <div>
            <label :class="ui.label">ID type</label>
            <select v-model="idType" required :disabled="!birthdateValid" :class="ui.input">
              <option value="" disabled>{{ birthdateValid ? 'Select an ID type' : 'Enter your birthdate first' }}</option>
              <option v-for="opt in eligibleIdTypes" :key="opt" :value="opt">{{ opt }}</option>
            </select>
            <p v-if="!birthdateValid" class="mt-1.5 text-xs text-slate-400 dark:text-white/30">Available ID types depend on your age.</p>
          </div>
          <div>
            <label :class="ui.label">Photo of your ID</label>
            <div v-if="idPreview" class="relative overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10">
              <img :src="idPreview" alt="ID preview" class="max-h-56 w-full bg-base-200 object-contain dark:bg-white/5" />
              <button type="button" class="tap absolute right-2 top-2 grid h-9 w-9 place-items-center rounded-full bg-slate-900/70 text-white" aria-label="Remove photo" @click="clearPhoto">
                <Icon icon="lucide:x" width="18" />
              </button>
            </div>
            <div v-else class="grid grid-cols-2 gap-3">
              <button type="button" :disabled="!idType" class="tap flex flex-col items-center gap-1.5 rounded-2xl border-2 border-dashed border-slate-300 py-5 text-xs font-bold text-slate-600 disabled:opacity-50 dark:border-white/15 dark:text-white/60" @click="pickPhoto('camera')">
                <Icon icon="lucide:camera" width="24" class="text-red-600 dark:text-red-400" /> Take photo
              </button>
              <button type="button" :disabled="!idType" class="tap flex flex-col items-center gap-1.5 rounded-2xl border-2 border-dashed border-slate-300 py-5 text-xs font-bold text-slate-600 disabled:opacity-50 dark:border-white/15 dark:text-white/60" @click="pickPhoto('gallery')">
                <Icon icon="lucide:image" width="24" class="text-[#1976D2] dark:text-[#7fb3ec]" /> From gallery
              </button>
            </div>
            <p class="mt-1.5 text-xs text-slate-400 dark:text-white/30">Make sure your name, photo and ID number are readable. Max 5MB.</p>
          </div>
        </section>

        <!-- 3 · Your address -->
        <section :class="[ui.card, 'space-y-4 p-5']">
          <h3 class="flex items-center gap-2 text-sm font-black text-slate-900 dark:text-white"><span class="grid h-6 w-6 place-items-center rounded-full bg-red-600 text-[0.7rem] text-white">3</span>Your address</h3>
          <div>
            <label :class="ui.label">Address / subdivision</label>
            <textarea v-model="address" rows="2" maxlength="255" required placeholder="House/unit no., street, subdivision" :class="[ui.input, 'resize-none']" @blur="forwardGeocode"></textarea>
          </div>
          <div>
            <label :class="ui.label">Barangay</label>
            <select v-if="!barangaysFailed" v-model="barangay" required :disabled="loadingBarangays" :class="ui.input" @change="forwardGeocode">
              <option value="" disabled>{{ loadingBarangays ? 'Loading barangays…' : 'Select a barangay' }}</option>
              <option v-for="b in barangays" :key="b" :value="b">{{ b }}</option>
            </select>
            <template v-else>
              <input v-model.trim="barangay" required maxlength="120" placeholder="Enter your barangay" :class="ui.input" @blur="forwardGeocode" />
              <p class="mt-1.5 text-xs text-red-500">Couldn't load barangays — enter yours, or <button type="button" class="font-semibold underline" @click="loadBarangays">retry</button>.</p>
            </template>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label :class="ui.label">City</label>
              <input value="Dasmariñas, Cavite" disabled :class="ui.input" />
            </div>
            <div>
              <label :class="ui.label">Zip code</label>
              <select v-model="zipCode" required :class="ui.input">
                <option value="" disabled>Select</option>
                <option v-for="z in ALLOWED_ZIPS" :key="z" :value="z">{{ z }}</option>
              </select>
            </div>
          </div>
          <MapPinPicker :lat="latitude" :lng="longitude" label="Pin your address" :hint="geocodeNote || 'Tap the map to drop a pin, or use your current location.'"
            @update="onPin" @picked="onPicked" />
        </section>

        <button type="submit" :disabled="submitting || !formValid" :class="ui.primaryButton">
          <Icon v-if="submitting" icon="lucide:loader-circle" width="20" class="animate-spin" />
          {{ submitting ? 'Submitting…' : 'Submit for Verification' }}
        </button>
        <p v-if="!formValid" class="text-center text-xs text-slate-400 dark:text-white/35">Fill in every section and pin your address to continue.</p>
      </form>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera'
import ScreenHeader from '@/components/ScreenHeader.vue'
import MapPinPicker from '@/components/MapPinPicker.vue'
import * as ui from '@/components/ui/styles'
import api, { apiMessage } from '@/services/api'
import { useSession } from '@/composables/useSession'
import { useAlert } from '@/composables/useAlert'
import { useToast } from '@/composables/useToast'

const { user, kycStatus: status, fetchSession } = useSession()
const alert = useAlert()
const toast = useToast()

// Kept in sync with dascare_api/citizen/kyc_submit.php (the source of truth,
// which re-validates all of this) — same lists as the web's Verification.vue.
const ALLOWED_ZIPS = ['4114', '4115', '4126']
const ADULT_IDS = ['PhilSys (National ID)', 'Passport', "Driver's License", 'UMID', 'SSS ID', 'GSIS ID', 'PhilHealth ID', 'Pag-IBIG ID', 'Postal ID', "Voter's ID", 'PRC ID', 'PWD ID', 'OFW ID', "Seaman's Book"]
const ID_TYPES_BY_AGE_GROUP = {
  minor: ['PhilSys (National ID)', 'Passport', 'Student ID', 'PWD ID'],
  adult: ADULT_IDS,
  senior: [...ADULT_IDS, 'Senior Citizen ID'],
}
const PSGC_BARANGAYS = 'https://psgc.gitlab.io/api/cities-municipalities/042106000/barangays/'
const MAX_BYTES = 5 * 1024 * 1024

const loading = ref(true)
const submitting = ref(false)

const birthdate = ref('')
const phoneNumber = ref('')
const idType = ref('')
const idBlob = ref(null)
const idPreview = ref('')
const address = ref('')
const barangay = ref('')
const zipCode = ref('')
const latitude = ref(null)
const longitude = ref(null)
const pinSource = ref(null) // 'manual' | 'gps' | 'auto'
const geocodeNote = ref('')

const barangays = ref([])
const loadingBarangays = ref(false)
const barangaysFailed = ref(false)

// ─── Age rules (13–85; minor / adult / senior ID lists) ───
const today = new Date()
const isoMinusYears = (y) => { const d = new Date(today); d.setFullYear(d.getFullYear() - y); return d.toISOString().slice(0, 10) }
const minBirthdate = isoMinusYears(85)
const maxBirthdate = isoMinusYears(13)
function ageOf(value) {
  const [y, m, d] = value.split('-').map(Number)
  if (!y) return null
  let age = today.getFullYear() - y
  if (today.getMonth() + 1 < m || (today.getMonth() + 1 === m && today.getDate() < d)) age--
  return age
}
const age = computed(() => (birthdate.value ? ageOf(birthdate.value) : null))
const birthdateValid = computed(() => age.value !== null && age.value >= 13 && age.value <= 85)
const ageGroup = computed(() => (!birthdateValid.value ? null : age.value < 18 ? 'minor' : age.value >= 60 ? 'senior' : 'adult'))
const eligibleIdTypes = computed(() => ID_TYPES_BY_AGE_GROUP[ageGroup.value] || [])
const ageChip = computed(() => ({
  minor: { text: 'Minor (13–17) — limited IDs available', class: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800/40 dark:bg-blue-500/10 dark:text-blue-300' },
  senior: { text: 'Senior (60+) — Senior Citizen ID available', class: 'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-800/40 dark:bg-purple-500/10 dark:text-purple-300' },
  adult: { text: 'Adult (18–59) — standard IDs available', class: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800/40 dark:bg-emerald-500/10 dark:text-emerald-300' },
}[ageGroup.value]))
watch(eligibleIdTypes, (list) => { if (idType.value && !list.includes(idType.value)) idType.value = '' })

const phoneValid = computed(() => /^09\d{9}$/.test(phoneNumber.value))
const formValid = computed(() =>
  birthdateValid.value && phoneValid.value && !!idType.value && !!idBlob.value &&
  address.value.trim().length >= 5 && !!barangay.value.trim() && ALLOWED_ZIPS.includes(zipCode.value) &&
  latitude.value !== null && longitude.value !== null,
)

// ─── ID photo (camera or gallery) ───
async function pickPhoto(source) {
  try {
    const photo = await Camera.getPhoto({
      source: source === 'camera' ? CameraSource.Camera : CameraSource.Photos,
      resultType: CameraResultType.Uri,
      quality: 80,
      width: 1800,
      correctOrientation: true,
    })
    const blob = await (await fetch(photo.webPath)).blob()
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(blob.type)) { toast.error('Only JPG, PNG, or WEBP images are allowed.', 'Unsupported image'); return }
    if (blob.size > MAX_BYTES) { toast.error('File is too large — max 5MB.', 'Image too large'); return }
    idBlob.value = blob
    idPreview.value = photo.webPath
  } catch (err) {
    if (!String(err?.message || '').toLowerCase().includes('cancel')) {
      toast.error('Couldn’t open the camera or gallery. Check the app’s permissions in Android Settings.', 'Photo not added')
    }
  }
}
function clearPhoto() { idBlob.value = null; idPreview.value = '' }

// ─── Barangays (PSGC, same source as the web) ───
async function loadBarangays() {
  loadingBarangays.value = true
  barangaysFailed.value = false
  try {
    const res = await fetch(PSGC_BARANGAYS)
    if (!res.ok) throw new Error(String(res.status))
    const list = (await res.json()).map((b) => b.name).sort()
    if (!list.length) throw new Error('empty')
    barangays.value = list
  } catch {
    barangaysFailed.value = true
  } finally {
    loadingBarangays.value = false
  }
}
const normalize = (s) => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\(.*?\)/g, '').replace(/[^a-z0-9]+/g, ' ').trim()
function matchBarangay(guess) {
  const t = normalize(guess)
  if (!t) return ''
  return barangays.value.find((b) => normalize(b) === t) || barangays.value.find((b) => normalize(b).includes(t) || t.includes(normalize(b))) || ''
}

// ─── Geocoding (Nominatim, same as the web) ───
function onPin({ lat, lng, source }) {
  latitude.value = lat
  longitude.value = lng
  pinSource.value = source
}
async function onPicked({ lat, lng }) {
  try {
    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&addressdetails=1&zoom=18`)
    const addr = (await res.json()).address || {}
    const street = [addr.house_number, addr.road].filter(Boolean).join(' ')
    if (street && !address.value.trim()) address.value = street
    const brgy = matchBarangay(addr.village || addr.suburb || addr.neighbourhood || addr.quarter)
    if (brgy) barangay.value = brgy
    if (addr.postcode && ALLOWED_ZIPS.includes(addr.postcode)) zipCode.value = addr.postcode
    geocodeNote.value = 'Address details filled from your pin — please double-check them.'
  } catch { /* offline: the user can still type everything */ }
}
async function forwardGeocode() {
  if (pinSource.value === 'manual' || pinSource.value === 'gps') return
  const q = [address.value.trim(), barangay.value, 'Dasmariñas', 'Cavite', 'Philippines'].filter(Boolean).join(', ')
  try {
    const res = await fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(q)}&countrycodes=ph&limit=1`)
    const hit = (await res.json())?.[0]
    if (!hit) return
    const lat = parseFloat(hit.lat), lng = parseFloat(hit.lon)
    if (lat < 14.26 || lat > 14.40 || lng < 120.87 || lng > 121.0) return
    if (pinSource.value === 'manual' || pinSource.value === 'gps') return
    onPin({ lat, lng, source: 'auto' })
    geocodeNote.value = 'Pinned an approximate location from your address — tap the map to fine-tune it.'
  } catch { /* ignore */ }
}

// ─── Submit (same endpoint + fields as the web) ───
async function confirmAndSubmit() {
  const [y, m, d] = birthdate.value.split('-').map(Number)
  const summary = [
    `Birthdate: ${new Date(y, m - 1, d).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })}`,
    `Phone: ${phoneNumber.value}`,
    `ID: ${idType.value}`,
    `Address: ${address.value.trim()}, Brgy. ${barangay.value}, Dasmariñas ${zipCode.value}`,
  ].join('\n')
  if (!(await alert.confirm(summary, 'Confirm your details'))) return

  submitting.value = true
  try {
    const form = new FormData()
    const ext = { 'image/png': 'png', 'image/webp': 'webp' }[idBlob.value.type] || 'jpg'
    form.append('id_type', idType.value)
    form.append('id_image', idBlob.value, `id.${ext}`)
    form.append('phone_number', phoneNumber.value)
    form.append('birthdate', birthdate.value)
    form.append('address', address.value.trim())
    form.append('barangay', barangay.value.trim())
    form.append('zip_code', zipCode.value)
    form.append('latitude', latitude.value)
    form.append('longitude', longitude.value)
    await api.post('/citizen/kyc_submit.php', form, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 60000 })
    await fetchSession()
    toast.success('Your ID was submitted. Review usually takes 24–48 hours.', 'Submitted for review')
  } catch (err) {
    toast.error(apiMessage(err, 'Something went wrong while saving your submission.'), 'Submission failed')
  } finally {
    submitting.value = false
  }
}

function showReason() {
  alert.warning(user.value?.kyc_note || 'No reason was given.', status.value === 4 ? 'Resubmission requested' : 'Submission rejected')
}
const formatDate = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleString() : '')

onMounted(async () => {
  await fetchSession()
  const phone = String(user.value?.phone || '').replace(/^\+63/, '0').replace(/\D/g, '')
  if (/^09\d{9}$/.test(phone)) phoneNumber.value = phone
  loading.value = false
  if (status.value !== 1 && status.value !== 2) loadBarangays()
})
</script>
