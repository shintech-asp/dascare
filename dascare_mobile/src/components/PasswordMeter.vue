<template>
  <!-- Live feedback under a "new password" field: strength bar, which rules are
       met, and (when `confirm` is given) whether the two entries match.
       Rules mirror dascare_api/reusables/password_helpers.php.
       Same file in dascare_mobile/src/components/PasswordMeter.vue — keep in sync. -->
  <div v-if="password || confirm" class="mt-2 space-y-2" aria-live="polite">
    <template v-if="password">
      <div class="flex items-center gap-2">
        <div class="flex flex-1 gap-1">
          <span v-for="i in 4" :key="i" class="h-1.5 flex-1 rounded-full transition-colors" :class="i <= level ? bar : 'bg-slate-200 dark:bg-white/10'"></span>
        </div>
        <span class="w-14 text-right text-[0.7rem] font-black" :class="text">{{ label }}</span>
      </div>
      <ul class="grid grid-cols-2 gap-x-3 gap-y-1 text-[0.7rem]">
        <li v-for="r in rules" :key="r.label" class="flex items-center gap-1.5" :class="r.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-white/40'">
          <Icon :icon="r.ok ? 'lucide:check-circle-2' : 'lucide:circle'" width="13" class="flex-shrink-0" />{{ r.label }}
        </li>
      </ul>
      <p v-if="containsIdentity" class="flex items-center gap-1.5 text-[0.7rem] font-bold text-red-600 dark:text-red-400"><Icon icon="lucide:alert-circle" width="13" />Don't use your name or email in the password.</p>
    </template>
    <p v-if="confirm !== undefined && confirm !== ''" class="flex items-center gap-1.5 text-[0.75rem] font-bold" :class="matches ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
      <Icon :icon="matches ? 'lucide:check-circle-2' : 'lucide:x-circle'" width="14" />{{ matches ? 'Passwords match' : 'Passwords do not match' }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'

const props = defineProps({
  password: { type: String, default: '' },
  confirm: { type: String, default: undefined }, // omit to hide the match line
  exclude: { type: Array, default: () => [] }, // name / email the password must not contain
})

const rules = computed(() => [
  { label: '10+ characters', ok: props.password.length >= 10 },
  { label: 'Lowercase letter', ok: /[a-z]/.test(props.password) },
  { label: 'Uppercase letter', ok: /[A-Z]/.test(props.password) },
  { label: 'A number', ok: /[0-9]/.test(props.password) },
  { label: 'A symbol (!@#…)', ok: /[^A-Za-z0-9]/.test(props.password) },
])
const passed = computed(() => rules.value.filter((r) => r.ok).length)
const containsIdentity = computed(() => {
  const flat = props.password.toLowerCase().replace(/[^a-z0-9]/g, '')
  return props.exclude.some((v) => {
    const s = String(v || '').trim()
    if (!s) return false
    return [s, s.includes('@') ? s.split('@')[0] : null].filter(Boolean).some((c) => {
      const k = c.toLowerCase().replace(/[^a-z0-9]/g, '')
      return k.length >= 3 && flat.includes(k)
    })
  })
})
const level = computed(() => (passed.value <= 2 ? 1 : passed.value === 3 ? 2 : passed.value === 4 ? 3 : (containsIdentity.value ? 3 : 4)))
const label = computed(() => ['', 'Weak', 'Fair', 'Good', 'Strong'][level.value])
const bar = computed(() => ['', 'bg-red-500', 'bg-amber-500', 'bg-blue-500', 'bg-emerald-500'][level.value])
const text = computed(() => ['', 'text-red-500', 'text-amber-500', 'text-blue-500', 'text-emerald-500'][level.value])
const matches = computed(() => props.password === props.confirm)
</script>
