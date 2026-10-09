<template>
  <div class="min-h-screen bg-base-200 dark:bg-[#050e1a]">
    <ScreenHeader title="Emergency information" fallback="/profile" />

    <main class="space-y-4 px-4 pb-[calc(var(--safe-bottom)+6.5rem)] pt-4">
      <!-- Same rule as the web (router requiresKyc on Medical Records) -->
      <template v-if="!isVerified">
        <KycCard :status="kycStatus" feature="your emergency medical information" />
      </template>

      <div v-else-if="loading" class="flex justify-center py-16"><div class="h-8 w-8 animate-spin rounded-full border-4 border-red-600 border-t-transparent"></div></div>

      <div v-else-if="loadError" class="rounded-3xl border border-dashed border-red-200 py-14 text-center dark:border-red-500/20">
        <Icon icon="lucide:alert-triangle" width="28" class="mx-auto mb-3 text-red-400" />
        <p class="text-sm font-semibold text-slate-600 dark:text-white/50">{{ loadError }}</p>
        <button type="button" class="mt-3 py-2 text-xs font-bold text-red-600 dark:text-red-300" @click="load">Try again</button>
      </div>

      <!-- Same sections, fields and copy as the web's MedicalRecords.vue -->
      <form v-else class="space-y-4" @submit.prevent="save">
        <div class="flex gap-3 rounded-2xl border border-red-200 bg-red-50/60 px-4 py-3.5 dark:border-red-500/20 dark:bg-red-500/5">
          <Icon icon="lucide:siren" width="18" class="mt-0.5 flex-shrink-0 text-red-600 dark:text-red-300" />
          <p class="text-xs leading-relaxed text-red-800 dark:text-red-200/80">Dispatchers and responding crews can see this the moment you file an emergency request — keep it current so they know what to expect before they arrive.</p>
        </div>
        <p v-if="lastUpdated" class="text-[0.68rem] font-semibold text-slate-400 dark:text-white/35">Last updated {{ lastUpdated }}</p>

        <section :class="[ui.card, 'p-5']">
          <SectionHeader icon="lucide:droplet" title="Critical Info" subtitle="The first thing responders check." />
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label :class="ui.label">Blood type</label>
              <select v-model="form.blood_type" :class="ui.input">
                <option value="unknown">Unknown</option>
                <option v-for="bt in BLOOD_TYPES" :key="bt" :value="bt">{{ bt }}</option>
              </select>
            </div>
            <label class="flex items-end gap-2 pb-4">
              <input v-model="form.organ_donor" type="checkbox" class="h-5 w-5 rounded border-slate-300 accent-red-600 dark:border-white/20" />
              <span class="text-sm font-medium text-slate-700 dark:text-white/70">Organ donor</span>
            </label>
          </div>
        </section>

        <section v-for="f in textSections" :key="f.key" :class="[ui.card, 'p-5']">
          <SectionHeader :icon="f.icon" :title="f.title" :subtitle="f.subtitle" />
          <textarea v-model="form[f.key]" :rows="f.rows || 2" :placeholder="f.placeholder" :class="[ui.input, 'resize-none text-sm']"></textarea>
        </section>

        <section :class="[ui.card, 'space-y-3 p-5']">
          <SectionHeader icon="lucide:phone-call" title="Emergency Contact" subtitle="Who should be notified if you're not able to speak for yourself." />
          <div><label :class="ui.label">Full name</label><input v-model="form.emergency_contact_name" maxlength="160" placeholder="Juan Dela Cruz" :class="ui.input" /></div>
          <div class="grid grid-cols-2 gap-3">
            <div><label :class="ui.label">Phone</label><input v-model="form.emergency_contact_phone" type="tel" inputmode="tel" maxlength="30" placeholder="09xx xxx xxxx" :class="ui.input" /></div>
            <div><label :class="ui.label">Relationship</label><input v-model="form.emergency_contact_relationship" maxlength="80" placeholder="Spouse, parent…" :class="ui.input" /></div>
          </div>
        </section>

        <section :class="[ui.card, 'space-y-3 p-5']">
          <SectionHeader icon="lucide:stethoscope" title="Primary Physician" subtitle="Optional — helpful for hospital handoff." />
          <div class="grid grid-cols-2 gap-3">
            <div><label :class="ui.label">Name</label><input v-model="form.primary_physician_name" maxlength="160" placeholder="Dr. Santos" :class="ui.input" /></div>
            <div><label :class="ui.label">Phone</label><input v-model="form.primary_physician_phone" type="tel" inputmode="tel" maxlength="30" placeholder="09xx xxx xxxx" :class="ui.input" /></div>
          </div>
        </section>

        <section :class="[ui.card, 'space-y-3 p-5']">
          <SectionHeader icon="lucide:shield" title="Insurance" subtitle="Optional — speeds up hospital admission." />
          <div class="grid grid-cols-2 gap-3">
            <div><label :class="ui.label">Provider</label><input v-model="form.insurance_provider" maxlength="160" placeholder="PhilHealth…" :class="ui.input" /></div>
            <div><label :class="ui.label">Policy / member no.</label><input v-model="form.insurance_policy_number" maxlength="100" placeholder="Policy number" :class="ui.input" /></div>
          </div>
        </section>

        <section :class="[ui.card, 'p-5']">
          <SectionHeader icon="lucide:notebook-pen" title="Additional Notes" subtitle="Anything else — recent surgery, pregnancy, DNR, etc." />
          <textarea v-model="form.additional_notes" rows="3" placeholder="Anything responders should know that isn't covered above" :class="[ui.input, 'resize-none text-sm']"></textarea>
        </section>
      </form>
    </main>

    <!-- Sticky save bar, like the web -->
    <div v-if="isVerified && !loading && !loadError" class="fixed inset-x-0 bottom-0 z-30 border-t border-base-300 bg-base-100/95 px-4 pb-[calc(var(--safe-bottom)+0.75rem)] pt-3 backdrop-blur dark:border-white/10 dark:bg-[#071829]/95">
      <button type="button" :disabled="saving || !dirty" :class="ui.primaryButton" @click="save">
        <Icon :icon="saving ? 'lucide:loader-circle' : 'lucide:save'" width="18" :class="saving ? 'animate-spin' : ''" />
        {{ saving ? 'Saving…' : dirty ? 'Save' : 'Saved' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, h, onMounted, reactive, ref } from 'vue'
import { Icon } from '@iconify/vue'
import ScreenHeader from '@/components/ScreenHeader.vue'
import KycCard from '@/components/KycCard.vue'
import * as ui from '@/components/ui/styles'
import api, { apiMessage } from '@/services/api'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'

const { kycStatus, isVerified } = useSession()
const toast = useToast()

const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
const textSections = [
  { key: 'allergies', icon: 'lucide:alert-octagon', title: 'Allergies', subtitle: 'Medications, food, or anything else you react to.', placeholder: 'e.g. Penicillin, peanuts, latex' },
  { key: 'medications', icon: 'lucide:pill', title: 'Current Medications', subtitle: "What you're taking regularly, with dosage if known.", placeholder: 'e.g. Metformin 500mg twice daily' },
  { key: 'medical_conditions', icon: 'lucide:activity', title: 'Medical Conditions', subtitle: 'Chronic conditions or diagnoses responders should know about.', placeholder: 'e.g. Asthma, type 2 diabetes, epilepsy' },
  { key: 'disabilities_mobility_notes', icon: 'lucide:accessibility', title: 'Mobility & Communication Needs', subtitle: 'Mobility aids, hearing/vision needs, or anything that helps on arrival.', placeholder: 'e.g. Uses a wheelchair, hard of hearing' },
]
const emptyForm = () => ({
  blood_type: 'unknown', allergies: '', medications: '', medical_conditions: '', disabilities_mobility_notes: '', organ_donor: false,
  emergency_contact_name: '', emergency_contact_phone: '', emergency_contact_relationship: '',
  primary_physician_name: '', primary_physician_phone: '', insurance_provider: '', insurance_policy_number: '', additional_notes: '',
})

const form = reactive(emptyForm())
const savedSnapshot = ref(JSON.stringify(form))
const dirty = computed(() => JSON.stringify(form) !== savedSnapshot.value)
const loading = ref(true)
const loadError = ref('')
const saving = ref(false)
const lastUpdated = ref('')
const formatDate = (s) => (s ? new Date(String(s).replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '')

// Same endpoints as the web: citizen/get.php + citizen/save.php.
async function load() {
  if (!isVerified.value) { loading.value = false; return }
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/citizen/get.php')
    const record = data?.has_record && data.record ? data.record : {}
    const next = emptyForm()
    for (const k of Object.keys(next)) if (record[k] != null) next[k] = record[k]
    Object.assign(form, next)
    savedSnapshot.value = JSON.stringify(form)
    lastUpdated.value = formatDate(record.updated_at)
  } catch (err) {
    loadError.value = apiMessage(err, 'Could not load your emergency information. Please try again.')
  } finally {
    loading.value = false
  }
}

async function save() {
  document.activeElement?.blur()
  saving.value = true
  try {
    const { data } = await api.post('/citizen/save.php', form)
    savedSnapshot.value = JSON.stringify(form)
    lastUpdated.value = formatDate(new Date().toISOString().slice(0, 19).replace('T', ' '))
    toast.success(data?.message || 'Medical records updated.', 'Saved')
  } catch (err) {
    toast.error(apiMessage(err, 'Could not save your emergency information. Please try again.'), 'Not saved')
  } finally {
    saving.value = false
  }
}

// Same section header as the web page.
const SectionHeader = (props) => h('div', { class: 'mb-3.5 flex items-start gap-2.5' }, [
  h('div', { class: 'flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300' }, [h(Icon, { icon: props.icon, width: 16 })]),
  h('div', { class: 'min-w-0' }, [
    h('p', { class: 'text-sm font-bold text-slate-900 dark:text-white' }, props.title),
    h('p', { class: 'mt-0.5 text-xs text-slate-400 dark:text-white/40' }, props.subtitle),
  ]),
])

onMounted(load)
</script>
