<template>
    <div
      class="emergency-glow relative w-full sm:w-[380px] max-w-[380px] rounded-2xl overflow-hidden
             shadow-xl flex flex-col
             bg-base-100 dark:bg-gradient-to-b dark:from-[#0c1a2c] dark:to-[#060d17]
             border border-base-300 dark:border-white/10
             animate-toast-in"
      @mouseenter="pause"
      @mouseleave="resume"
    >
        <!-- Siren strip — alternating navy/red flash, like an ambulance lightbar -->
        <div class="flex h-[3px] w-full shrink-0" aria-hidden="true">
          <span class="siren-led siren-led-blue flex-1"></span>
          <span class="siren-led siren-led-red flex-1"></span>
        </div>

        <!-- Scanline texture, matching the dispatch console -->
        <div class="pointer-events-none absolute inset-0 text-slate-400 opacity-[0.04] dark:text-white dark:opacity-[0.04]"
          style="background-image: repeating-linear-gradient(0deg, currentColor 0px, currentColor 1px, transparent 1px, transparent 3px);">
        </div>

        <!-- Content -->
        <div class="relative flex items-start gap-3 px-4 py-3 sm:px-5 sm:py-3.5">
            <!-- Icon -->
            <div class="relative w-9 h-9 sm:w-10 sm:h-10 flex-shrink-0">
                <span class="siren-ping absolute inline-flex h-full w-full rounded-full opacity-30" :class="pingBgClass"></span>
                <div class="relative w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center
                            ring-2 ring-base-100 dark:ring-[#0F2A43] shadow-md" :class="iconBgClass">
                    <Icon :icon="iconName" class="w-4 h-4 sm:w-[18px] sm:h-[18px] text-white" />
                </div>
            </div>

            <!-- Text -->
            <div class="flex-1 min-w-0 pt-0.5">
                <p class="font-mono text-[10px] font-bold uppercase tracking-[0.2em] leading-tight mb-1" :class="eyebrowClass">
                    {{ title }}
                </p>
                <p class="text-[12.5px] sm:text-[13px] leading-snug text-slate-600 dark:text-white/70">
                    {{ message }}
                </p>
            </div>

            <!-- Close -->
            <button @click="dismiss"
                class="text-slate-400 dark:text-white/40 opacity-80 hover:opacity-100
                       hover:text-slate-700 dark:hover:text-white
                       transition text-xs sm:text-sm leading-none mt-0.5 flex-shrink-0"
                aria-label="Dismiss notification">
                ✕
            </button>
        </div>

        <!-- Progress -->
        <div class="h-[3px] w-full bg-base-200 dark:bg-white/5">
            <div class="h-full transition-all duration-100 ease-linear" :class="progressClass"
                :style="{ width: progress + '%' }" />
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { Icon } from '@iconify/vue'

const props = defineProps({
    message: String,
    title: String,
    type: { type: String, default: 'info' },
    duration: { type: Number, default: 3500 },
    onClose: Function
})

const progress = ref(100)

let raf
let paused = false
let remaining = props.duration
let startTime = performance.now()

const tick = (time) => {
    if (paused) return

    const elapsed = time - startTime
    remaining = Math.max(props.duration - elapsed, 0)
    progress.value = (remaining / props.duration) * 100

    if (remaining === 0) {
        dismiss()
    } else {
        raf = requestAnimationFrame(tick)
    }
}

const pause = () => {
    paused = true
    cancelAnimationFrame(raf)
}

const resume = () => {
    paused = false
    startTime = performance.now() - (props.duration - remaining)
    raf = requestAnimationFrame(tick)
}

const dismiss = () => {
    cancelAnimationFrame(raf)
    props.onClose?.()
}

onMounted(() => {
    raf = requestAnimationFrame(tick)
})

onBeforeUnmount(() => {
    cancelAnimationFrame(raf)
})

/* Dispatch-console palette — same emerald / red / amber / brand-blue status
   colors as AlertModal, so a toast reads as a quick version of the same
   signal rather than a different visual system. */
const toastConfig = {
    success: {
        eyebrow: 'Success',
        iconBg: 'bg-emerald-500',
        eyebrowColor: 'text-emerald-500 dark:text-emerald-400',
        progress: 'bg-emerald-500',
        barColor: '#10b981',
        icon: 'line-md:confirm'
    },
    error: {
        eyebrow: 'Error',
        iconBg: 'bg-red-500',
        eyebrowColor: 'text-red-500 dark:text-red-400',
        progress: 'bg-red-500',
        barColor: '#ef4444',
        icon: 'line-md:close'
    },
    warning: {
        eyebrow: 'Warning',
        iconBg: 'bg-amber-500',
        eyebrowColor: 'text-amber-500 dark:text-amber-400',
        progress: 'bg-amber-500',
        barColor: '#f59e0b',
        icon: 'line-md:alert'
    },
    info: {
        eyebrow: 'Info',
        iconBg: 'bg-[#1976D2]',
        eyebrowColor: 'text-[#1976D2] dark:text-[#7fb3ec]',
        progress: 'bg-[#1976D2]',
        barColor: '#1976D2',
        icon: 'line-md:alert-circle'
    }
}

const iconBgClass = computed(() => toastConfig[props.type]?.iconBg || toastConfig.info.iconBg)
const eyebrowClass = computed(() => toastConfig[props.type]?.eyebrowColor || toastConfig.info.eyebrowColor)
const progressClass = computed(() => toastConfig[props.type]?.progress || toastConfig.info.progress)
const iconName = computed(() => toastConfig[props.type]?.icon || toastConfig.info.icon)
const pingBgClass = computed(() => toastConfig[props.type]?.iconBg || toastConfig.info.iconBg)
const title = computed(() => props.title || toastConfig[props.type]?.eyebrow || 'Notice')
</script>

<style scoped>
@keyframes toast-in {
    from {
        opacity: 0;
        transform: translateY(-10px) scale(0.97);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.animate-toast-in {
    animation: toast-in 0.3s ease-out;
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

/* Ambient card glow — the toast breathes between navy and red */
@keyframes emergency-glow {
    0%, 100% { box-shadow: 0 0 0 1px rgba(25, 118, 210, 0.3), 0 0 18px rgba(25, 118, 210, 0.22); }
    50% { box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.3), 0 0 18px rgba(239, 68, 68, 0.22); }
}
.emergency-glow {
    animation: emergency-glow 2.6s ease-in-out infinite;
}

/* Icon ring — a single soft pulse in the toast's own status color */
.siren-ping {
    animation: siren-ping-expand 1.6s cubic-bezier(0, 0, 0.2, 1) infinite;
}
@keyframes siren-ping-expand {
    0% { transform: scale(0.8); opacity: 0.5; }
    100% { transform: scale(1.6); opacity: 0; }
}
</style>