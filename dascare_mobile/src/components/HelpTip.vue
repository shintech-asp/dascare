<template>
  <!-- Small "?" next to a field or heading. Desktop: hover (or keyboard focus)
       shows the tip; phone/touch: tap to open, tap elsewhere to close.
         <HelpTip text="What this does…" title="Optional heading" />
       Same file in dascare_mobile/src/components/HelpTip.vue — keep in sync. -->
  <span class="inline-flex align-middle">
    <button
      ref="btn"
      type="button"
      class="ml-1 grid h-5 w-5 place-items-center rounded-full text-slate-400 transition-colors hover:text-[#1976D2] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1976D2]/50 dark:text-white/35 dark:hover:text-[#7fb3ec]"
      :class="open ? 'text-[#1976D2] dark:text-[#7fb3ec]' : ''"
      :aria-label="`Help${title ? ': ' + title : ''}`"
      :aria-expanded="open"
      @click.stop.prevent="toggle"
      @mouseenter="hoverOpen"
      @mouseleave="hoverClose"
      @focus="hoverOpen"
      @blur="close"
      @keydown.esc="close"
    >
      <Icon icon="lucide:circle-help" width="15" height="15" />
    </button>
    <Teleport to="body">
      <div
        v-if="open"
        ref="pop"
        role="tooltip"
        class="fixed z-[10080] w-max max-w-[min(18rem,calc(100vw-1.5rem))] rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-left text-xs font-medium normal-case leading-5 tracking-normal text-slate-600 shadow-xl dark:border-white/15 dark:bg-[#0d2943] dark:text-white/75"
        :style="style"
        @mouseenter="hoverOpen"
        @mouseleave="hoverClose"
      >
        <p v-if="title" class="mb-0.5 font-black text-slate-900 dark:text-white">{{ title }}</p>
        <slot>{{ text }}</slot>
      </div>
    </Teleport>
  </span>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue'
import { Icon } from '@iconify/vue'

defineProps({ text: { type: String, default: '' }, title: { type: String, default: '' } })

const btn = ref(null)
const pop = ref(null)
const open = ref(false)
const style = ref({ top: '0px', left: '0px', visibility: 'hidden' })
let closeTimer = null
const canHover = () => typeof window !== 'undefined' && window.matchMedia?.('(hover: hover)').matches

async function place() {
  await nextTick()
  const b = btn.value?.getBoundingClientRect()
  const p = pop.value?.getBoundingClientRect()
  if (!b || !p) return
  const gap = 8
  let left = Math.min(Math.max(12, b.left + b.width / 2 - p.width / 2), window.innerWidth - p.width - 12)
  let top = b.bottom + gap
  if (top + p.height > window.innerHeight - 12 && b.top - gap - p.height > 12) top = b.top - gap - p.height
  style.value = { top: `${Math.round(top)}px`, left: `${Math.round(left)}px` }
}
function onOutside(e) {
  if (btn.value?.contains(e.target) || pop.value?.contains(e.target)) return
  close()
}
function show() {
  clearTimeout(closeTimer)
  if (open.value) return
  style.value = { top: '0px', left: '0px', visibility: 'hidden' }
  open.value = true
  place()
  document.addEventListener('pointerdown', onOutside, true)
  window.addEventListener('scroll', close, true)
  window.addEventListener('resize', close)
}
function close() {
  clearTimeout(closeTimer)
  open.value = false
  document.removeEventListener('pointerdown', onOutside, true)
  window.removeEventListener('scroll', close, true)
  window.removeEventListener('resize', close)
}
const toggle = () => (open.value ? close() : show())
const hoverOpen = () => { if (canHover()) show() }
const hoverClose = () => { if (canHover()) { clearTimeout(closeTimer); closeTimer = setTimeout(close, 120) } }
onBeforeUnmount(close)
</script>
