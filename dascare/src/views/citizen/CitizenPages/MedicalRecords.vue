<template>
  <section class="min-h-screen bg-base-200 dark:bg-[#050e1a] pb-28">

    <!-- ================= Header ================= -->
    <section class="bg-base-100 dark:bg-[#071829] border-b border-base-300 dark:border-white/10 relative overflow-hidden">
      <div
        class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-30"
        style="background: radial-gradient(ellipse at top left, rgba(220,38,38,0.06), transparent 55%);"
      ></div>
      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 relative">
        <span class="inline-flex items-center gap-2 rounded-full bg-red-50 dark:bg-red-500/10 px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.15em] text-red-700 dark:text-red-300">
          <span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>
          Citizen Portal
        </span>
        <h1 class="mt-2 text-xl sm:text-2xl font-bold text-slate-900 dark:text-white">Emergency Information</h1>
        <p class="text-sm text-slate-500 dark:text-white/45">
          Keep only the emergency details responders may need during an active rescue.
        </p>
      </div>
    </section>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">

      <!-- ================= Info banner ================= -->
      <div
        v-if="!loading && !loadError"
        class="mb-6 flex gap-3 rounded-2xl border border-red-200 dark:border-red-500/20 bg-red-50/60 dark:bg-red-500/5 px-4 py-3.5"
      >
        <Icon icon="lucide:siren" width="18" class="text-red-600 dark:text-red-300 flex-shrink-0 mt-0.5" />
        <p class="text-xs leading-relaxed text-red-800 dark:text-red-200/80">
          Dispatchers and responding crews can see this the moment you file an emergency request —
          keep it current so they know what to expect before they arrive.
        </p>
      </div>

      <!-- ================= States ================= -->
      <div v-if="loading" class="flex justify-center py-16">
        <div class="w-8 h-8 border-4 border-red-600 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <div v-else-if="loadError" class="rounded-3xl border border-dashed border-red-200 dark:border-red-500/20 py-16 text-center">
        <Icon icon="lucide:alert-triangle" width="28" class="mx-auto text-red-400 dark:text-red-400/60 mb-3" />
        <p class="text-sm font-semibold text-slate-600 dark:text-white/50">{{ loadError }}</p>
        <button type="button" @click="fetchRecord" class="mt-3 text-xs font-bold text-red-600 dark:text-red-300 hover:underline">
          Try again
        </button>
      </div>

      <!-- ================= Form ================= -->
      <form v-else @submit.prevent="saveRecord">

        <p v-if="lastUpdated" class="-mt-1 mb-3 text-[0.68rem] font-semibold text-slate-400 dark:text-white/35">
          Last updated {{ lastUpdated }}
        </p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

          <!-- Critical info (full width — the one card that always spans) -->
          <div class="lg:col-span-2 rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-5 sm:p-6">
            <SectionHeader icon="lucide:droplet" title="Critical Info" subtitle="The first thing responders check." />

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div>
                <FieldLabel>Blood Type</FieldLabel>
                <select v-model="form.blood_type" class="field-input">
                  <option value="unknown">Unknown</option>
                  <option v-for="bt in BLOOD_TYPES" :key="bt" :value="bt">{{ bt }}</option>
                </select>
              </div>
              <div class="col-span-2 sm:col-span-3 flex items-end pb-1.5">
                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                  <input type="checkbox" v-model="form.organ_donor" class="h-4 w-4 rounded border-slate-300 dark:border-white/20 text-red-600 focus:ring-red-500" />
                  <span class="text-sm font-medium text-slate-700 dark:text-white/70">Registered organ donor</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Allergies | Medications -->
          <div class="rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-5 sm:p-6">
            <SectionHeader icon="lucide:alert-octagon" title="Allergies" subtitle="Medications, food, or anything else you react to." />
            <textarea
              v-model="form.allergies"
              rows="2"
              placeholder="e.g. Penicillin, peanuts, latex"
              class="field-input resize-none"
            ></textarea>
          </div>

          <div class="rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-5 sm:p-6">
            <SectionHeader icon="lucide:pill" title="Current Medications" subtitle="What you're taking regularly, with dosage if known." />
            <textarea
              v-model="form.medications"
              rows="2"
              placeholder="e.g. Metformin 500mg twice daily"
              class="field-input resize-none"
            ></textarea>
          </div>

          <!-- Conditions | Mobility & communication -->
          <div class="rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-5 sm:p-6">
            <SectionHeader icon="lucide:activity" title="Medical Conditions" subtitle="Chronic conditions or diagnoses responders should know about." />
            <textarea
              v-model="form.medical_conditions"
              rows="2"
              placeholder="e.g. Asthma, type 2 diabetes, epilepsy"
              class="field-input resize-none"
            ></textarea>
          </div>

          <div class="rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-5 sm:p-6">
            <SectionHeader icon="lucide:accessibility" title="Mobility & Communication Needs" subtitle="Mobility aids, hearing/vision needs, or anything that helps on arrival." />
            <textarea
              v-model="form.disabilities_mobility_notes"
              rows="2"
              placeholder="e.g. Uses a wheelchair, hard of hearing"
              class="field-input resize-none"
            ></textarea>
          </div>

          <!-- Emergency contact (full width — 3 fields read better in a row) -->
          <div class="lg:col-span-2 rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-5 sm:p-6">
            <SectionHeader icon="lucide:phone-call" title="Emergency Contact" subtitle="Who should be notified if you're not able to speak for yourself." />
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <div class="col-span-2 sm:col-span-1">
                <FieldLabel>Full name</FieldLabel>
                <input v-model="form.emergency_contact_name" type="text" placeholder="Juan Dela Cruz" class="field-input" />
              </div>
              <div>
                <FieldLabel>Phone</FieldLabel>
                <input v-model="form.emergency_contact_phone" type="tel" placeholder="09xx xxx xxxx" class="field-input" />
              </div>
              <div>
                <FieldLabel>Relationship</FieldLabel>
                <input v-model="form.emergency_contact_relationship" type="text" placeholder="Spouse, parent, sibling…" class="field-input" />
              </div>
            </div>
          </div>

          <!-- Physician | Insurance -->
          <div class="rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-5 sm:p-6">
            <SectionHeader icon="lucide:stethoscope" title="Primary Physician" subtitle="Optional — helpful for hospital handoff." />
            <div class="grid grid-cols-2 gap-3">
              <div>
                <FieldLabel>Name</FieldLabel>
                <input v-model="form.primary_physician_name" type="text" placeholder="Dr. Santos" class="field-input" />
              </div>
              <div>
                <FieldLabel>Phone</FieldLabel>
                <input v-model="form.primary_physician_phone" type="tel" placeholder="09xx xxx xxxx" class="field-input" />
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-5 sm:p-6">
            <SectionHeader icon="lucide:shield" title="Insurance" subtitle="Optional — speeds up hospital admission." />
            <div class="grid grid-cols-2 gap-3">
              <div>
                <FieldLabel>Provider</FieldLabel>
                <input v-model="form.insurance_provider" type="text" placeholder="PhilHealth, Maxicare…" class="field-input" />
              </div>
              <div>
                <FieldLabel>Policy / member no.</FieldLabel>
                <input v-model="form.insurance_policy_number" type="text" placeholder="Policy number" class="field-input" />
              </div>
            </div>
          </div>

          <!-- Additional notes (full width) -->
          <div class="lg:col-span-2 rounded-3xl border border-base-300 dark:border-white/10 bg-base-100 dark:bg-[#071829] p-5 sm:p-6">
            <SectionHeader icon="lucide:notebook-pen" title="Additional Notes" subtitle="Anything else — recent surgery, pregnancy, DNR, etc." />
            <textarea
              v-model="form.additional_notes"
              rows="3"
              placeholder="Anything responders should know that isn't covered above"
              class="field-input resize-none"
            ></textarea>
          </div>
        </div>
      </form>
    </div>

    <!-- ================= Sticky save bar ================= -->
    <div
      v-if="!loading && !loadError"
      class="fixed bottom-0 inset-x-0 bg-base-100/95 dark:bg-[#071829]/95 backdrop-blur border-t border-base-300 dark:border-white/10 px-4 sm:px-6 py-3.5"
    >
      <div class="max-w-6xl mx-auto flex items-center justify-between gap-3">
        <p
          v-if="saveMessage"
          class="text-xs font-semibold flex items-center gap-1.5"
          :class="saveError ? 'text-red-600 dark:text-red-300' : 'text-emerald-600 dark:text-emerald-300'"
        >
          <Icon :icon="saveError ? 'lucide:alert-triangle' : 'lucide:check-circle-2'" width="15" />
          {{ saveMessage }}
        </p>
        <span v-else></span>

        <button
          type="button"
          @click="saveRecord"
          :disabled="saving"
          class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold bg-red-600 hover:bg-red-700 disabled:opacity-60
                 text-white shadow-lg shadow-red-200 dark:shadow-red-950/40 transition-colors"
        >
          <div v-if="saving" class="w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin"></div>
          <Icon v-else icon="lucide:save" width="15" />
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import axios from 'axios'
import { ref, reactive, h } from 'vue'
import { onMounted } from 'vue'
import { Icon } from '@iconify/vue'

const API_BASE = import.meta.env.VITE_API_BASE_URL

const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']

const loading = ref(true)
const loadError = ref('')
const saving = ref(false)
const saveMessage = ref('')
const saveError = ref(false)
const lastUpdated = ref('')

const emptyForm = () => ({
  blood_type: 'unknown',
  allergies: '',
  medications: '',
  medical_conditions: '',
  disabilities_mobility_notes: '',
  organ_donor: false,
  emergency_contact_name: '',
  emergency_contact_phone: '',
  emergency_contact_relationship: '',
  primary_physician_name: '',
  primary_physician_phone: '',
  insurance_provider: '',
  insurance_policy_number: '',
  additional_notes: '',
})

const form = reactive(emptyForm())

const formatDate = (s) =>
  s ? new Date(s.replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : ''

// Backed by citizen/medical_records/get.php — the signed-in citizen's own
// medical_records row (one row per user, same shape save.php upserts).
const fetchRecord = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const res = await axios.get(`${API_BASE}/citizen/get.php`, { withCredentials: true })
    if (res.data?.has_record && res.data.record) {
      Object.assign(form, emptyForm(), res.data.record)
      lastUpdated.value = formatDate(res.data.record.updated_at)
    } else {
      Object.assign(form, emptyForm())
      lastUpdated.value = ''
    }
  } catch (err) {
    console.error(err)
    loadError.value = err.response?.data?.message || 'Could not load your emergency information. Please try again.'
  } finally {
    loading.value = false
  }
}

// Backed by citizen/medical_records/save.php — upserts the same row.
const saveRecord = async () => {
  saving.value = true
  saveMessage.value = ''
  saveError.value = false
  try {
    const res = await axios.post(`${API_BASE}/citizen/save.php`, form, { withCredentials: true })
    saveError.value = false
    saveMessage.value = res.data?.message || 'Saved.'
    lastUpdated.value = formatDate(new Date().toISOString().slice(0, 19).replace('T', ' '))
  } catch (err) {
    console.error(err)
    saveError.value = true
    saveMessage.value = err.response?.data?.message || 'Could not save your emergency information. Please try again.'
  } finally {
    saving.value = false
    setTimeout(() => { saveMessage.value = '' }, 4000)
  }
}

onMounted(fetchRecord)

// Small local helpers so every card doesn't repeat the same header/label markup.
const SectionHeader = (props) =>
  h('div', { class: 'flex items-start gap-2.5 mb-3.5' }, [
    h('div', { class: 'flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-300' }, [
      h(Icon, { icon: props.icon, width: 16 }),
    ]),
    h('div', { class: 'min-w-0' }, [
      h('p', { class: 'text-sm font-bold text-slate-900 dark:text-white' }, props.title),
      h('p', { class: 'text-xs text-slate-400 dark:text-white/40 mt-0.5' }, props.subtitle),
    ]),
  ])

const FieldLabel = (props, { slots }) =>
  h('label', { class: 'mb-1 block text-[0.68rem] font-semibold text-slate-500 dark:text-white/40' }, slots.default?.())
</script>

<style scoped>
.field-input {
  width: 100%;
  border-radius: 0.75rem;
  border: 1px solid rgb(226 232 240);
  background-color: rgb(248 250 252);
  padding: 0.55rem 0.75rem;
  font-size: 0.875rem;
  color: rgb(15 23 42);
  outline: none;
  transition: border-color 0.15s, background-color 0.15s;
}
.field-input:focus {
  border-color: rgb(220 38 38);
  background-color: white;
}
:global(.dark) .field-input {
  border-color: rgba(255, 255, 255, 0.1);
  background-color: rgba(255, 255, 255, 0.03);
  color: white;
}
:global(.dark) .field-input:focus {
  border-color: rgb(248 113 113);
  background-color: rgba(255, 255, 255, 0.06);
}
.field-input::placeholder {
  color: rgb(148 163 184);
}
</style>