<template>
  <Teleport to="body">
    <transition name="modal">
      <div
        v-if="visibleState"
        class="fixed inset-0 z-[10050] flex items-center justify-center px-5 sm:px-4"
      >
        <!-- Backdrop -->
        <div
          class="absolute inset-0 bg-black/60 backdrop-blur-md"
          @click="close(false)"
        ></div>

        <!-- Modal Container -->
        <transition name="content">
          <div
            class="emergency-glow relative w-full max-w-[300px] sm:max-w-sm rounded-2xl overflow-hidden shadow-2xl flex flex-col items-center
                   bg-base-100 dark:bg-gradient-to-b dark:from-[#0c1a2c] dark:to-[#060d17]
                   border border-base-300 dark:border-white/10"
          >
            <!-- Siren strip — alternating navy/red flash, like an ambulance lightbar -->
            <div class="flex h-[3px] w-full shrink-0" aria-hidden="true">
              <span class="siren-led siren-led-blue flex-1"></span>
              <span class="siren-led siren-led-red flex-1"></span>
            </div>

            <!-- Scanline texture, matching the dispatch console -->
            <div class="pointer-events-none absolute inset-0 text-slate-400 opacity-[0.05] dark:text-white dark:opacity-[0.05]"
              style="background-image: repeating-linear-gradient(0deg, currentColor 0px, currentColor 1px, transparent 1px, transparent 3px);">
            </div>

            <!-- Content Container -->
            <div class="relative z-10 p-5 sm:p-7 w-full">
              <!-- Icon -->
              <div class="relative mb-3 sm:mb-4 flex justify-center">
                <span class="absolute w-[56px] h-[56px] sm:w-[70px] sm:h-[70px] m-auto flex items-center justify-center">
                  <span class="siren-ping siren-ping-blue absolute inline-flex h-full w-full rounded-full opacity-30"></span>
                  <span class="siren-ping siren-ping-red absolute inline-flex h-full w-full rounded-full opacity-30"></span>
                </span>

                <div
                  :class="iconBg"
                  class="relative w-[52px] h-[52px] sm:w-16 sm:h-16 rounded-full flex items-center justify-center
                         ring-4 ring-base-100 dark:ring-[#0F2A43]
                         animate-scale-bounce shadow-lg z-10
                         group hover:scale-110 transition-transform duration-300"
                >
                  <Icon :icon="iconName" class="text-white text-2xl sm:text-3xl relative z-10 animate-icon-spin" />
                </div>
              </div>

              <!-- Eyebrow label -->
              <div class="flex items-center justify-center gap-1.5 sm:gap-2 mb-1.5 sm:mb-2 animate-fade-in-down">
                <span class="inline-block w-4 sm:w-6 h-px bg-base-300 dark:bg-white/15"></span>
                <span :class="eyebrowColor" class="font-mono text-[9px] sm:text-[10px] font-bold tracking-[0.25em] uppercase">{{ eyebrowText }}</span>
                <span class="inline-block w-4 sm:w-6 h-px bg-base-300 dark:bg-white/15"></span>
              </div>

              <!-- Title -->
              <h2 class="text-lg sm:text-xl font-bold mb-1.5 sm:mb-2 text-center text-slate-900 dark:text-white animate-fade-in-down">
                {{ titleState }}
              </h2>

              <!-- Message -->
              <p
                class="text-slate-500 dark:text-white/60 text-center mb-5 sm:mb-6 text-[13px] sm:text-sm leading-relaxed animate-fade-in"
                style="animation-delay: 0.1s"
              >
                {{ messageState }}
              </p>

              <!-- Actions -->
              <div class="flex gap-2 flex-col sm:flex-row">
                <button
                  v-if="showCancel || typeState === 'confirm'"
                  @click="close(false)"
                  :class="cancelHoverBorder"
                  class="flex-1 px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-lg border
                         border-base-300 dark:border-white/15
                         bg-base-100 dark:bg-white/5
                         text-slate-600 dark:text-white/80
                         font-semibold text-[13px] sm:text-sm
                         hover:bg-base-200 dark:hover:bg-white/10
                         hover:scale-105 active:scale-95
                         transition-all duration-300
                         animate-fade-in"
                  style="animation-delay: 0.2s"
                >
                  Cancel
                </button>

                <button
                  @click="close(true)"
                  :class="buttonBg"
                  :style="buttonGlowStyle"
                  class="btn-shine btn-glow-pulse flex-1 px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-lg text-white font-bold text-[13px] sm:text-sm
                         hover:scale-105 active:scale-95
                         transition-all duration-300
                         shadow-lg hover:shadow-xl
                         relative overflow-hidden group
                         animate-fade-in"
                  style="animation-delay: 0.25s"
                >
                  <span class="relative z-10 flex items-center justify-center gap-1.5">
                    {{ confirmButtonText }}
                    <Icon
                      icon="line-md:arrow-right"
                      class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform"
                    />
                  </span>
                </button>
              </div>
            </div>
          </div>
        </transition>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'
import { useAlert } from '@/composables/useAlert'

const alert = useAlert()

const props = defineProps({
  visible: Boolean,
  type: {
    type: String,
    default: 'success',
  },
  title: {
    type: String,
    default: '',
  },
  message: {
    type: String,
    required: true,
  },
  confirmText: {
    type: String,
    default: 'OK',
  },
  showCancel: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:visible', 'confirm'])

const close = (confirmed) => {
  if (alert.confirmAction?.value) {
    alert.confirmAction.value(confirmed)
    alert.visible.value = false
    return
  }

  emit('update:visible', false)
  emit('confirm', confirmed)
}

// Dispatch-console palette — each type reads as a real status signal
// (emerald clear / red critical / amber caution / brand-blue info) rather
// than the earthy artisan-market tones, so the alert matches the header's
// dark-navy, mono-label, signal-bar language.
const config = {
  success: {
    icon: 'line-md:confirm-circle',
    eyebrow: 'All Set',
    eyebrowColor: 'text-emerald-500 dark:text-emerald-400',
    iconBg: 'bg-emerald-500',
    pingBg: 'bg-emerald-400',
    buttonBg: 'bg-emerald-500 hover:bg-emerald-600',
    barColor: '#10b981',
    cancelHoverBorder: 'hover:border-emerald-500/40 dark:hover:border-emerald-400/30',
  },
  error: {
    icon: 'line-md:cancel',
    eyebrow: 'Alert',
    eyebrowColor: 'text-red-500 dark:text-red-400',
    iconBg: 'bg-red-500',
    pingBg: 'bg-red-400',
    buttonBg: 'bg-red-500 hover:bg-red-600',
    barColor: '#ef4444',
    cancelHoverBorder: 'hover:border-red-500/40 dark:hover:border-red-400/30',
  },
  warning: {
    icon: 'line-md:alert',
    eyebrow: 'Heads Up',
    eyebrowColor: 'text-amber-500 dark:text-amber-400',
    iconBg: 'bg-amber-500',
    pingBg: 'bg-amber-400',
    buttonBg: 'bg-amber-500 hover:bg-amber-600',
    barColor: '#f59e0b',
    cancelHoverBorder: 'hover:border-amber-500/40 dark:hover:border-amber-400/30',
  },
  info: {
    icon: 'line-md:alert-circle',
    eyebrow: 'Notice',
    eyebrowColor: 'text-[#1976D2] dark:text-[#7fb3ec]',
    iconBg: 'bg-[#1976D2]',
    pingBg: 'bg-[#4aa3f0]',
    buttonBg: 'bg-[#1976D2] hover:bg-[#1565c0]',
    barColor: '#1976D2',
    cancelHoverBorder: 'hover:border-[#1976D2]/40 dark:hover:border-[#7fb3ec]/30',
  },
  confirm: {
    icon: 'line-md:question-circle',
    eyebrow: 'Confirm Action',
    eyebrowColor: 'text-slate-500 dark:text-white/70',
    iconBg: 'bg-slate-600',
    pingBg: 'bg-slate-400',
    buttonBg: 'bg-[#1976D2] hover:bg-[#1565c0]',
    barColor: '#64748b',
    cancelHoverBorder: 'hover:border-slate-400/50 dark:hover:border-white/30',
  },
}

const visibleState = computed(() => {
  return typeof alert.visible.value === 'boolean' ? alert.visible.value : props.visible
})

const typeState = computed(() => {
  return alert.type.value || props.type
})

const titleState = computed(() => {
  return alert.title.value || props.title
})

const messageState = computed(() => {
  return alert.message.value || props.message
})

const confirmButtonText = computed(() => {
  if (typeState.value === 'confirm') return 'Confirm'
  return props.confirmText || 'OK'
})

const iconName = computed(() => config[typeState.value]?.icon || config.info.icon)
const iconBg = computed(() => config[typeState.value]?.iconBg || config.info.iconBg)
const buttonBg = computed(() => config[typeState.value]?.buttonBg || config.info.buttonBg)
const eyebrowText = computed(() => config[typeState.value]?.eyebrow || config.info.eyebrow)
const eyebrowColor = computed(() => config[typeState.value]?.eyebrowColor || config.info.eyebrowColor)
const cancelHoverBorder = computed(() => config[typeState.value]?.cancelHoverBorder || config.info.cancelHoverBorder)
const hexToRgb = (hex) => {
  const m = hex.replace('#', '')
  const bigint = parseInt(m, 16)
  return `${(bigint >> 16) & 255}, ${(bigint >> 8) & 255}, ${bigint & 255}`
}
const buttonGlowStyle = computed(() => {
  const color = config[typeState.value]?.barColor || config.info.barColor
  return { '--glow-rgb': hexToRgb(color) }
})
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.content-enter-active {
  transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.content-enter-from {
  opacity: 0;
  transform: scale(0.92) translateY(-16px);
}

@keyframes scale-bounce {
  0% {
    transform: scale(0);
    opacity: 0;
  }
  50% {
    transform: scale(1.1);
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

@keyframes icon-spin {
  0% {
    transform: rotate(-10deg) scale(0.8);
    opacity: 0;
  }
  100% {
    transform: rotate(0deg) scale(1);
    opacity: 1;
  }
}

@keyframes fade-in-down {
  0% {
    opacity: 0;
    transform: translateY(-8px);
  }
  100% {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fade-in {
  0% {
    opacity: 0;
    transform: translateY(6px);
  }
  100% {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-scale-bounce {
  animation: scale-bounce 0.45s ease-out;
}

.animate-icon-spin {
  animation: icon-spin 0.45s ease-out;
}

.animate-fade-in-down {
  animation: fade-in-down 0.4s ease-out both;
}

.animate-fade-in {
  animation: fade-in 0.4s ease-out both;
}

/* Glossy shine sweep on the confirm button, matching the header's CTA */
.btn-shine::before {
  content: '';
  position: absolute;
  top: 0;
  left: -75%;
  width: 50%;
  height: 100%;
  background: linear-gradient(115deg, transparent 0%, rgba(255, 255, 255, 0.5) 50%, transparent 100%);
  transform: skewX(-20deg);
  transition: left 0.5s ease;
}

.btn-shine:hover::before {
  left: 130%;
}

/* Idle glow pulse — the confirm button breathes in its own accent color */
@keyframes btn-glow-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(var(--glow-rgb), 0); }
  50% { box-shadow: 0 0 14px 3px rgba(var(--glow-rgb), 0.55); }
}
.btn-glow-pulse {
  animation: btn-glow-pulse 2s ease-in-out infinite;
}

/* Siren strip — alternating navy/red flash across the top edge */
.siren-led {
  animation: siren-flash 1.1s ease-in-out infinite;
}
.siren-led-blue {
  background: #1976D2;
}
.siren-led-red {
  background: #ef4444;
  animation-delay: 0.55s;
}
@keyframes siren-flash {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.15; }
}

/* Ambient card glow — the whole modal breathes between navy and red,
   like ambulance lights washing over the console */
@keyframes emergency-glow {
  0%, 100% { box-shadow: 0 0 0 1px rgba(25, 118, 210, 0.35), 0 0 26px rgba(25, 118, 210, 0.28); }
  50% { box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.35), 0 0 26px rgba(239, 68, 68, 0.28); }
}
.emergency-glow {
  animation: emergency-glow 2.6s ease-in-out infinite;
}

/* Dual radar rings — blue and red pings expand out of phase */
.siren-ping {
  animation: siren-ping-expand 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
}
.siren-ping-blue {
  background: #1976D2;
}
.siren-ping-red {
  background: #ef4444;
  animation-delay: 0.9s;
}
@keyframes siren-ping-expand {
  0% { transform: scale(0.75); opacity: 0.45; }
  100% { transform: scale(1.5); opacity: 0; }
}
</style>