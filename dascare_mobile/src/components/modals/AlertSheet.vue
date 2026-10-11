<template>
  <!-- App version of the web's AlertModal: same siren strip, status icon,
       eyebrow and wording, but as a native-style bottom sheet — slides up,
       thumb-sized buttons, tap outside or swipe down to dismiss (= Cancel). -->
  <Teleport to="body">
    <Transition name="sheet-fade">
      <div v-if="alert.visible.value" class="fixed inset-0 z-[10050]" @click.self="close(false)">
        <div class="absolute inset-0 bg-black/50" @click="close(false)"></div>
        <Transition name="sheet-up" appear>
          <section
            v-if="alert.visible.value"
            ref="sheet"
            role="alertdialog"
            :aria-label="alert.title.value"
            class="absolute inset-x-0 bottom-0 overflow-hidden rounded-t-[28px] border-t border-base-300 bg-base-100 shadow-2xl dark:border-white/10 dark:bg-[#0c1a2c]"
            :style="dragStyle"
            @touchstart.passive="onTouchStart"
            @touchmove.passive="onTouchMove"
            @touchend="onTouchEnd"
          >
            <!-- Siren strip (same as the web alert) -->
            <div class="flex h-[3px] w-full" aria-hidden="true">
              <span class="siren-led siren-led-blue flex-1"></span>
              <span class="siren-led siren-led-red flex-1"></span>
            </div>
            <!-- Drag handle -->
            <div class="flex justify-center pt-2.5" aria-hidden="true">
              <span class="h-1.5 w-10 rounded-full bg-slate-300 dark:bg-white/20"></span>
            </div>

            <div class="px-6 pb-[calc(var(--safe-bottom)+1.25rem)] pt-4">
              <div class="flex items-start gap-4">
                <span class="grid h-12 w-12 flex-shrink-0 place-items-center rounded-full text-white shadow-lg" :class="cfg.iconBg">
                  <Icon :icon="cfg.icon" width="26" />
                </span>
                <div class="min-w-0 flex-1">
                  <p class="font-mono text-[0.62rem] font-bold uppercase tracking-[0.22em]" :class="cfg.eyebrowColor">{{ cfg.eyebrow }}</p>
                  <h2 class="mt-0.5 text-lg font-black leading-tight text-slate-900 dark:text-white">{{ alert.title.value }}</h2>
                </div>
              </div>
              <p class="mt-3 text-[0.95rem] leading-relaxed text-slate-600 dark:text-white/65">{{ alert.message.value }}</p>

              <div class="mt-6 space-y-2.5">
                <button type="button" class="tap flex w-full items-center justify-center gap-2 rounded-2xl py-4 text-base font-bold text-white shadow-lg" :class="cfg.buttonBg" @click="close(true)">
                  {{ isConfirm ? 'Confirm' : 'OK' }}
                </button>
                <button v-if="isConfirm" type="button" class="tap flex w-full items-center justify-center rounded-2xl border-2 border-base-300 py-3.5 text-base font-semibold text-slate-600 dark:border-white/10 dark:text-white/70" @click="close(false)">
                  Cancel
                </button>
              </div>
            </div>
          </section>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { Haptics, ImpactStyle, NotificationType } from '@capacitor/haptics'
import { useAlert } from '@/composables/useAlert'

const alert = useAlert()
const sheet = ref(null)

// Same palette and labels as the web's AlertModal.
const CONFIG = {
  success: { icon: 'lucide:circle-check-big', eyebrow: 'All Set', eyebrowColor: 'text-emerald-600 dark:text-emerald-400', iconBg: 'bg-emerald-500', buttonBg: 'bg-emerald-600' },
  error: { icon: 'lucide:circle-x', eyebrow: 'Alert', eyebrowColor: 'text-red-600 dark:text-red-400', iconBg: 'bg-red-500', buttonBg: 'bg-red-600' },
  warning: { icon: 'lucide:triangle-alert', eyebrow: 'Heads Up', eyebrowColor: 'text-amber-600 dark:text-amber-400', iconBg: 'bg-amber-500', buttonBg: 'bg-amber-500' },
  info: { icon: 'lucide:info', eyebrow: 'Notice', eyebrowColor: 'text-[#1976D2] dark:text-[#7fb3ec]', iconBg: 'bg-[#1976D2]', buttonBg: 'bg-[#1976D2]' },
  confirm: { icon: 'lucide:circle-help', eyebrow: 'Confirm Action', eyebrowColor: 'text-slate-500 dark:text-white/70', iconBg: 'bg-slate-600', buttonBg: 'bg-red-600' },
}
const isConfirm = computed(() => alert.type.value === 'confirm')
const cfg = computed(() => CONFIG[alert.type.value] || CONFIG.info)

// A short buzz when a sheet appears: a warning pattern for errors.
watch(() => alert.visible.value, (open) => {
  if (!open) return
  dragY.value = 0
  const t = alert.type.value
  const haptic = t === 'error' || t === 'warning'
    ? Haptics.notification({ type: NotificationType.Warning })
    : Haptics.impact({ style: ImpactStyle.Light })
  haptic.catch(() => {})
})

function close(confirmed) {
  if (alert.confirmAction.value) {
    alert.confirmAction.value(confirmed) // resolves the confirm() promise / callback
    alert.visible.value = false
    return
  }
  alert.visible.value = false
}

// Swipe down to dismiss.
const dragY = ref(0)
let startY = null
const dragStyle = computed(() => (dragY.value > 0 ? { transform: `translateY(${dragY.value}px)`, transition: 'none' } : {}))
function onTouchStart(e) { startY = e.touches[0].clientY }
function onTouchMove(e) { if (startY != null) dragY.value = Math.max(0, e.touches[0].clientY - startY) }
function onTouchEnd() {
  if (dragY.value > 90) close(false)
  dragY.value = 0
  startY = null
}
</script>

<style scoped>
.sheet-fade-enter-active, .sheet-fade-leave-active { transition: opacity 0.2s ease; }
.sheet-fade-enter-from, .sheet-fade-leave-to { opacity: 0; }
.sheet-up-enter-active { transition: transform 0.28s cubic-bezier(0.2, 0.9, 0.3, 1); }
.sheet-up-leave-active { transition: transform 0.2s ease-in; }
.sheet-up-enter-from, .sheet-up-leave-to { transform: translateY(100%); }

/* Siren strip — the same lightbar flash as the web alert */
.siren-led { animation: siren 1.2s steps(1) infinite; }
.siren-led-blue { background: #1976d2; }
.siren-led-red { background: #dc2626; animation-delay: 0.6s; }
@keyframes siren { 0%, 49% { opacity: 1; } 50%, 100% { opacity: 0.25; } }
</style>
