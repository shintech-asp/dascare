<template>
  <!-- Same 4-bar meter + rules hint as the web sign-up form (AuthPage.vue). -->
  <div v-if="password.length > 0" class="mt-2">
    <div class="flex gap-1">
      <div v-for="i in 4" :key="i" class="h-1 flex-1 rounded-full transition-all duration-300"
        :class="i <= score ? barColor : 'bg-slate-200 dark:bg-white/10'"></div>
    </div>
    <p class="mt-1 text-xs" :class="textColor">{{ passwordText(score) }}</p>
    <p class="mt-1 text-[11px] text-slate-400 dark:text-white/40">10+ characters, upper &amp; lower case, a number, and a special character.</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { passwordScore, passwordText } from '@/components/ui/styles'

const props = defineProps({ password: { type: String, default: '' } })
const score = computed(() => passwordScore(props.password))
const barColor = computed(() => (score.value <= 1 ? 'bg-red-500' : score.value === 2 ? 'bg-amber-500' : score.value === 3 ? 'bg-blue-500' : 'bg-emerald-500'))
const textColor = computed(() => (score.value <= 1 ? 'text-red-500' : score.value === 2 ? 'text-amber-500' : score.value === 3 ? 'text-blue-500' : 'text-emerald-500'))
</script>
