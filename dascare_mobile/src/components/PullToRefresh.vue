<template>
  <!-- Pull down from the top of the page to refresh, like native lists. -->
  <div @touchstart.passive="onStart" @touchmove="onMove" @touchend="onEnd" @touchcancel="onEnd">
    <div class="flex items-end justify-center overflow-hidden transition-[height] duration-200" :style="{ height: `${indicator}px` }">
      <span class="mb-2 grid h-9 w-9 place-items-center rounded-full bg-base-100 shadow dark:bg-[#0d2943]">
        <Icon icon="lucide:refresh-cw" width="18" class="text-red-600 dark:text-red-400" :class="refreshing ? 'animate-spin' : ''" :style="{ transform: refreshing ? '' : `rotate(${pull * 3}deg)` }" />
      </span>
    </div>
    <slot />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({ onRefresh: { type: Function, required: true } })
const THRESHOLD = 70
const pull = ref(0)
const refreshing = ref(false)
let startY = null

const indicator = computed(() => (refreshing.value ? 56 : Math.min(pull.value, 90)))

function onStart(e) {
  startY = window.scrollY <= 0 && !refreshing.value ? e.touches[0].clientY : null
}
function onMove(e) {
  if (startY === null) return
  const dy = e.touches[0].clientY - startY
  pull.value = dy > 0 ? dy * 0.5 : 0
  if (dy > 8 && e.cancelable) e.preventDefault()
}
async function onEnd() {
  if (startY === null) return
  startY = null
  if (pull.value >= THRESHOLD) {
    refreshing.value = true
    try { await props.onRefresh() } finally { refreshing.value = false }
  }
  pull.value = 0
}
</script>
