<template>
  <div
    v-if="enabled"
    class="pointer-events-none fixed inset-0 z-[9999] overflow-hidden"
    aria-hidden="true"
  >
    <!-- Short red/blue siren trail -->
    <span
      v-for="dot in trail"
      :key="dot.id"
      class="absolute rounded-full blur-[1px]"
      :class="dot.color === 'red' ? 'bg-red-500' : 'bg-blue-500'"
      :style="{
        left: `${dot.x}px`,
        top: `${dot.y}px`,
        width: `${dot.size}px`,
        height: `${dot.size}px`,
        opacity: dot.opacity,
        transform: `translate(-50%, -50%) scale(${dot.scale})`,
        boxShadow:
          dot.color === 'red'
            ? '0 0 9px rgba(239, 68, 68, 0.75)'
            : '0 0 9px rgba(59, 130, 246, 0.75)',
      }"
    />
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue'

const enabled = ref(false)
const trail = ref([])



let animationFrame = 0
let trailId = 0
let lastTrailAt = 0

function shouldDisableForElement(targetElement) {
  return Boolean(
    targetElement?.closest(
      [
        '[data-disable-cursor-trail]',
        'input',
        'textarea',
        'select',
        '[contenteditable="true"]',
        '.leaflet-container',
      ].join(','),
    ),
  )
}

function onPointerMove(event) {
  if (event.pointerType && event.pointerType !== 'mouse') return


  const now = performance.now()

  if (
    now - lastTrailAt > 45 &&
    !shouldDisableForElement(event.target)
  ) {
    trail.value.push({
      id: trailId++,
      x: event.clientX - 4,
      y: event.clientY - 2,
      color: trailId % 2 === 0 ? 'red' : 'blue',
      opacity: 0.72,
      scale: 1,
      size: 5,
    })

    if (trail.value.length > 7) {
      trail.value.shift()
    }

    lastTrailAt = now
  }
}


function animate() {

  trail.value = trail.value
    .map((dot) => ({
      ...dot,
      opacity: dot.opacity - 0.035,
      scale: Math.max(dot.scale - 0.025, 0.25),
      size: Math.max(dot.size - 0.03, 2.5),
    }))
    .filter((dot) => dot.opacity > 0.03)


  animationFrame = requestAnimationFrame(animate)
}

onMounted(() => {
  const finePointer = window.matchMedia('(pointer: fine)').matches
  const reducedMotion = window.matchMedia(
    '(prefers-reduced-motion: reduce)',
  ).matches

  enabled.value = finePointer && !reducedMotion

  if (!enabled.value) return

  window.addEventListener('pointermove', onPointerMove, { passive: true })

  animationFrame = requestAnimationFrame(animate)
})

onUnmounted(() => {
  window.removeEventListener('pointermove', onPointerMove)
  cancelAnimationFrame(animationFrame)
})
</script>
