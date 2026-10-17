<template>
  <!-- Emergency / accident type chips (config/emergencyTypes.js). Optional on
       the SOS form (nothing picked = "Other"); used by the organization's
       "Edit details" too. v-model is the category id (or null).
       Same file in dascare/src/components/emergency-request/EmergencyTypePicker.vue (web) — keep in sync. -->
  <div class="flex flex-wrap gap-2">
    <button v-for="t in types" :key="t.id" type="button"
      class="inline-flex min-w-0 items-center gap-1.5 rounded-full border px-3 py-2 text-xs font-bold transition"
      :class="modelValue === t.id ? 'border-red-600 bg-red-600 text-white' : 'border-base-300 bg-base-100 text-slate-600 hover:border-red-300 dark:border-white/10 dark:bg-white/5 dark:text-white/65'"
      :aria-pressed="modelValue === t.id"
      @click="$emit('update:modelValue', modelValue === t.id && clearable ? null : t.id)">
      <Icon :icon="t.icon" width="14" class="flex-shrink-0" />
      <span class="truncate">{{ t.label }}</span>
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'
import { EMERGENCY_TYPES, OTHER_EMERGENCY_TYPE } from '@/config/emergencyTypes'

const props = defineProps({
  modelValue: { type: Number, default: null },
  // SOS form: tap again to un-pick; "Other" is implied, so it isn't shown.
  clearable: { type: Boolean, default: false },
  hideOther: { type: Boolean, default: false },
})
defineEmits(['update:modelValue'])
const types = computed(() => (props.hideOther ? EMERGENCY_TYPES.filter((t) => t.id !== OTHER_EMERGENCY_TYPE) : EMERGENCY_TYPES))
</script>
