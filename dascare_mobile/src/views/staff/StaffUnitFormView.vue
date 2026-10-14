<template>
  <!-- Add / edit an ambulance (staff, Phase 4) — the web's Fleet form. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader :title="editId ? 'Edit ambulance' : 'Add ambulance'" :fallback="editId ? `/staff/fleet/${editId}` : '/staff/fleet'" />
    <form class="space-y-3 px-4 pt-3" @submit.prevent="save">
      <div :class="[ui.card, 'space-y-3 p-4']">
        <div class="grid grid-cols-2 gap-3">
          <div><label :class="ui.label">Unit code</label><input v-model="form.unit_code" :class="ui.input" required placeholder="AMB-02" /></div>
          <div><label :class="ui.label">Plate number</label><input v-model="form.plate_number" :class="ui.input" required /></div>
        </div>
        <div><label :class="ui.label">Type</label>
          <select v-model="form.ambulance_type" :class="ui.input"><option value="basic_life_support">Basic Life Support</option><option value="advanced_life_support">Advanced Life Support</option><option value="patient_transport">Patient Transport</option><option value="rescue_unit">Rescue Unit</option></select>
        </div>
        <div class="grid grid-cols-[1fr_6rem] gap-3">
          <div><label :class="ui.label">Make & model</label><input v-model="form.vehicle_make_model" :class="ui.input" /></div>
          <div><label :class="ui.label">Year</label><input v-model="form.model_year" inputmode="numeric" :class="ui.input" /></div>
        </div>
        <div><label :class="ui.label">Patient capacity</label><input v-model="form.capacity" inputmode="numeric" :class="ui.input" /></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label :class="ui.label">Registration expiry</label><input v-model="form.registration_expiry" type="date" :class="ui.input" /></div>
          <div><label :class="ui.label">Inspection expiry</label><input v-model="form.inspection_expiry" type="date" :class="ui.input" /></div>
        </div>
        <div><label :class="ui.label">Capability notes</label><textarea v-model="form.capability_notes" rows="3" :class="ui.input" placeholder="Equipment, special capabilities…"></textarea></div>
      </div>
      <button type="submit" :disabled="saving" :class="ui.primaryButton"><Icon :icon="saving ? 'lucide:loader-circle' : 'lucide:save'" width="18" :class="saving ? 'animate-spin' : ''" /> {{ editId ? 'Save changes' : 'Add ambulance' }}</button>
      <p v-if="!editId" class="px-2 text-center text-xs text-slate-500">New units start offline. Record a readiness check, then set them available.</p>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ScreenHeader from '@/components/ScreenHeader.vue'
import * as ui from '@/components/ui/styles'
import { apiMessage } from '@/services/api'
import { createFleetUnit, fetchFleetUnit, updateFleetUnit } from '@/services/org/organizationFleet'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const editId = computed(() => Number(route.query.id) || null)
const saving = ref(false)
const form = ref({ id: null, unit_code: '', plate_number: '', vehicle_make_model: '', model_year: '', ambulance_type: 'basic_life_support', capacity: 1, registration_expiry: '', inspection_expiry: '', capability_notes: '' })

async function save() {
  saving.value = true
  try {
    const r = editId.value ? await updateFleetUnit(form.value) : await createFleetUnit(form.value)
    toast.success(r.message || 'Saved.', 'Saved')
    const newId = editId.value || r.id || r.unit?.id || r.ambulance_id
    router.replace(newId ? { name: 'StaffUnit', params: { id: newId } } : { name: 'StaffFleet' })
  } catch (err) {
    toast.error(apiMessage(err, 'Could not save the ambulance.'), 'Not saved')
  } finally {
    saving.value = false
  }
}
onMounted(async () => {
  if (!editId.value) return
  try {
    const u = (await fetchFleetUnit(editId.value)).unit
    form.value = { id: u.id, unit_code: u.unit_code || '', plate_number: u.plate_number || '', vehicle_make_model: u.vehicle_make_model || '', model_year: u.model_year || '', ambulance_type: u.ambulance_type || 'basic_life_support', capacity: u.capacity || 1, registration_expiry: u.registration_expiry || '', inspection_expiry: u.inspection_expiry || '', capability_notes: u.capability_notes || '' }
  } catch (err) {
    toast.error(apiMessage(err, 'Could not load the ambulance.'), 'Error')
  }
})
</script>
