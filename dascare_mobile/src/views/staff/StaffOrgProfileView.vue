<template>
  <!-- Organization Profile (staff, Phase 4) — the web's profile: identity,
       contact, station location (used by DSS distance when a unit has no
       GPS) and service-area barangays (DSS coverage for barangay units). -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader title="Organization Profile" fallback="/staff/more">
      <template #actions>
        <button v-if="org && canUpdate && !editing" type="button" class="tap grid h-11 w-11 place-items-center rounded-xl text-slate-600 dark:text-white/70" aria-label="Edit" @click="startEdit"><Icon icon="lucide:pencil" width="20" /></button>
      </template>
    </ScreenHeader>
    <div v-if="!org" class="space-y-3 px-4 pt-4"><div v-for="n in 3" :key="n" :class="[ui.card, 'h-24 animate-pulse']"></div></div>
    <main v-else class="space-y-3 px-4 pt-3">
      <section :class="[ui.card, 'p-4']">
        <p class="text-lg font-black text-slate-900 dark:text-white">{{ org.name }}</p>
        <p class="text-xs text-slate-500 dark:text-white/45">{{ typeLabel(org.organization_type) }} · {{ org.application_reference }}</p>
        <dl class="mt-3 space-y-1.5 text-sm">
          <div v-if="org.registration_number" class="flex justify-between gap-3"><dt class="text-slate-400">Registration no.</dt><dd class="text-right font-semibold text-slate-700 dark:text-white/70">{{ org.registration_number }}</dd></div>
          <div v-if="org.accreditation_body" class="flex justify-between gap-3"><dt class="text-slate-400">Accreditation</dt><dd class="text-right font-semibold text-slate-700 dark:text-white/70">{{ org.accreditation_body }}</dd></div>
        </dl>
      </section>

      <form :class="[ui.card, 'space-y-3 p-4']" @submit.prevent="save">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 dark:text-white/35">Contact & station</h3>
        <div><label :class="ui.label">Email</label><input v-model="form.email" type="email" :disabled="!editing" :class="ui.input" /></div>
        <div><label :class="ui.label">Phone</label><input v-model="form.phone" inputmode="tel" :disabled="!editing" :class="ui.input" /></div>
        <div><label :class="ui.label">Station address</label><input v-model="form.address_line" :disabled="!editing" :class="ui.input" /></div>
        <MapPinPicker v-if="editing" :lat="form.latitude" :lng="form.longitude" label="Station location" hint="Tap the map to move the station pin (inside Dasmariñas)." @picked="(p) => { form.latitude = p.lat; form.longitude = p.lng }" />
        <p v-else class="text-xs text-slate-500 dark:text-white/45"><Icon icon="lucide:map-pin" width="13" class="mr-1 inline" />{{ form.latitude != null ? `${Number(form.latitude).toFixed(5)}, ${Number(form.longitude).toFixed(5)}` : 'Station location not set' }}</p>

        <div>
          <label :class="ui.label">Service areas (barangays)</label>
          <div class="flex flex-wrap gap-1.5">
            <span v-for="a in form.service_areas" :key="a" class="inline-flex items-center gap-1 rounded-full bg-base-200 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-white/5 dark:text-white/70">
              {{ a }}<button v-if="editing" type="button" class="text-slate-400" :aria-label="`Remove ${a}`" @click="form.service_areas = form.service_areas.filter((x) => x !== a)"><Icon icon="lucide:x" width="12" /></button>
            </span>
            <span v-if="!form.service_areas.length" class="text-xs text-slate-500">{{ org.organization_type === 'city_rescue' ? 'Whole city (city rescue units cover every barangay).' : 'None — add every barangay this unit covers.' }}</span>
          </div>
          <div v-if="editing" class="mt-2 flex gap-2">
            <input v-model="newArea" :class="ui.input" placeholder="Add barangay" @keydown.enter.prevent="addArea" />
            <button type="button" class="tap rounded-2xl bg-base-200 px-4 text-sm font-bold text-slate-700 dark:bg-white/5 dark:text-white/70" @click="addArea">Add</button>
          </div>
          <p v-if="org.organization_type !== 'city_rescue'" class="mt-1 text-[0.7rem] text-slate-400">Dispatch matches barangay names exactly — list every barangay name this unit serves.</p>
        </div>
        <div v-if="editing" class="grid grid-cols-2 gap-2 pt-1">
          <button type="button" :class="ui.secondaryButton" @click="cancelEdit">Cancel</button>
          <button type="submit" :disabled="saving" :class="ui.primaryButton"><Icon v-if="saving" icon="lucide:loader-circle" width="18" class="animate-spin" /> Save</button>
        </div>
      </form>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import ScreenHeader from '@/components/ScreenHeader.vue'
import MapPinPicker from '@/components/MapPinPicker.vue'
import * as ui from '@/components/ui/styles'
import { apiMessage } from '@/services/api'
import { fetchOrganizationProfile, updateOrganizationProfile } from '@/services/org/organizationManagement'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const { hasPermission, fetchSession } = useSession()
const canUpdate = computed(() => hasPermission('org_settings.org_profile.update'))
const org = ref(null)
const form = ref({ email: '', phone: '', address_line: '', latitude: null, longitude: null, service_areas: [] })
const editing = ref(false)
const saving = ref(false)
const newArea = ref('')
let snapshot = null
const typeLabel = (v) => ({ city_rescue: 'City Rescue / LGU Unit', barangay_rescue: 'Barangay Rescue Unit', hospital: 'Hospital Ambulance Service', private_ambulance: 'Private Ambulance / Rescue Service', other: 'Other Rescue Organization' }[v] || v || '—')

function startEdit() { snapshot = JSON.parse(JSON.stringify(form.value)); editing.value = true }
function cancelEdit() { if (snapshot) form.value = snapshot; editing.value = false }
function addArea() { const v = newArea.value.trim(); if (v && !form.value.service_areas.some((a) => a.toLowerCase() === v.toLowerCase())) form.value.service_areas.push(v); newArea.value = '' }
async function save() {
  saving.value = true
  try {
    const r = await updateOrganizationProfile(form.value)
    toast.success(r.message || 'Organization profile updated.', 'Saved')
    editing.value = false
    await fetchSession()
    await load()
  } catch (err) {
    toast.error(apiMessage(err, 'Could not save the profile.'), 'Not saved')
  } finally {
    saving.value = false
  }
}
async function load() {
  const d = await fetchOrganizationProfile()
  org.value = d.organization
  form.value = { email: d.organization.email || '', phone: d.organization.phone || '', address_line: d.organization.address_line || '', latitude: d.organization.latitude, longitude: d.organization.longitude, service_areas: d.service_areas || [] }
}
onMounted(load)
</script>
