<template>
  <!-- One request in a list — same row as the web's My Requests. -->
  <RouterLink :to="{ name: 'Track', params: { id: request.id } }" class="tap flex items-center gap-3 border-t border-slate-100 px-5 py-4 no-underline first:border-t-0 dark:border-white/5">
    <div class="relative flex-shrink-0">
      <div v-if="isActiveStatus(request.status)" class="absolute inset-0 animate-ping rounded-xl bg-red-500/15 [animation-duration:2.2s]"></div>
      <div class="relative grid h-10 w-10 place-items-center rounded-xl bg-red-50 dark:bg-red-500/10">
        <Icon :icon="categoryIcon(request.emergency_category_name)" width="17" class="text-red-600 dark:text-red-300" />
      </div>
    </div>
    <div class="min-w-0 flex-1">
      <p class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ request.emergency_category_name || 'Emergency request' }}</p>
      <p class="mt-0.5 truncate text-xs text-slate-400 dark:text-white/40"><span class="font-mono">{{ request.reference_number }}</span> · {{ formatDate(request.submitted_at) }}</p>
    </div>
    <div class="flex flex-shrink-0 flex-col items-end gap-1">
      <span class="rounded-full px-2.5 py-1 text-[0.65rem] font-bold" :class="statusBadgeClass(request.status)"><template v-if="request.merged_into_reference">Linked · </template>{{ statusLabel(request.status) }}</span>
      <span v-if="request.attention_level && request.attention_level !== 'normal'" class="rounded-full px-2 py-0.5 text-[0.58rem] font-black uppercase" :class="request.attention_level === 'critical_overdue' ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'">
        {{ request.attention_level === 'critical_overdue' ? 'Needs urgent review' : 'Needs review' }}
      </span>
    </div>
    <Icon icon="lucide:chevron-right" width="16" class="flex-shrink-0 text-slate-300 dark:text-white/20" />
  </RouterLink>
</template>

<script setup>
import { categoryIcon, formatDate, isActiveStatus, statusBadgeClass, statusLabel } from '@/utils/requestStatus'

defineProps({ request: { type: Object, required: true } })
</script>
