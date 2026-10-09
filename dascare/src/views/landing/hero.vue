<template>
  <section class="relative isolate overflow-hidden bg-base-200 text-base-content dark:bg-[#050e1a]">
    <!-- Big, soft siren glows flicker at random spots every few seconds —
         background only, no route line. -->
    <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
      <svg viewBox="0 0 600 400" preserveAspectRatio="xMidYMid slice" class="h-full w-full">
        <defs>
          <radialGradient id="dascare-glow-blue" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#1976D2" stop-opacity="0.6" />
            <stop offset="100%" stop-color="#1976D2" stop-opacity="0" />
          </radialGradient>
          <radialGradient id="dascare-glow-red" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#ef4444" stop-opacity="0.6" />
            <stop offset="100%" stop-color="#ef4444" stop-opacity="0" />
          </radialGradient>
        </defs>

        <g v-for="siren in sirens" :key="siren.id" :transform="`translate(${siren.x},${siren.y})`">
          <circle r="70" fill="url(#dascare-glow-blue)" class="siren-flicker-blue" />
          <circle r="70" fill="url(#dascare-glow-red)" class="siren-flicker-red" />
        </g>
      </svg>
    </div>

    <div class="relative mx-auto grid max-w-7xl gap-16 px-5 py-24 lg:grid-cols-[1.08fr_1fr] lg:items-center lg:py-32">
      <div>
        <div class="inline-flex items-center gap-2.5 border-l-2 border-red-600 pl-3 dark:border-red-400">
          <span class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">
            Dasmariñas · Citywide coverage
          </span>
        </div>

        <h1 class="mt-6 text-4xl font-black leading-[1.05] tracking-tight text-slate-950 sm:text-5xl lg:text-[3.4rem] dark:text-white">
          Every second between the call<br class="hidden sm:block" />
          and the <span class="text-red-600 dark:text-red-400">ambulance.</span>
        </h1>

        <p class="mt-6 max-w-lg text-lg leading-8 text-slate-600 dark:text-[#a9c6e8]">
          DASCARE is the one system Dasmariñas dispatchers, rescue teams, and ambulance operators
          run on — from the moment a report comes in to the moment help arrives.
        </p>

        <p class="mt-8 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-white/40">
          Need help right now? No account required.
        </p>
        <div class="mt-3">
          <button type="button" @click="openGuestModal()"
            class="group flex w-full items-center gap-3 rounded-2xl bg-red-600 px-5 py-4 text-left shadow-lg shadow-red-600/25 transition-colors hover:bg-red-700 dark:shadow-red-950/40">
            <Icon icon="lucide:siren" width="24" class="shrink-0 text-white" />
            <span>
              <span class="block text-base font-bold text-white">Request rescue</span>
              <span class="block text-xs text-red-100/90">One tap — your location goes straight to dispatch</span>
            </span>
          </button>
        </div>

        <!-- The count below is a soft, device-level heads-up — the real
             (phone-keyed) threshold only ever adds a dispatcher check; it
             never blocks a submission. See useGuestRequestLimit.js. -->
        <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-white/45">
          <template v-if="used === null">
            A genuine emergency always goes through — guest requests just get a quick dispatcher
            check after the first {{ softLimitCopy }} in a day.
          </template>
          <template v-else-if="used < (limit ?? 3)">
            {{ used }} of {{ limit }} guest {{ used === 1 ? 'request' : 'requests' }} sent today on this device.
          </template>
          <template v-else>
            You've sent {{ used }} guest {{ used === 1 ? 'request' : 'requests' }} today — further ones
            still go through, they'll just get an extra check.
            <RouterLink to="/register" class="underline decoration-2 underline-offset-2">Create a free account</RouterLink>
            to skip that.
          </template>
        </p>

        <div class="mt-6 flex items-center gap-3 text-sm">
          <span class="text-slate-500 dark:text-white/40">Have an account?</span>
          <RouterLink to="/login" class="font-semibold text-slate-700 hover:underline dark:text-white/80">Log in</RouterLink>
          <span class="text-slate-300 dark:text-white/15">·</span>
          <RouterLink to="/register" class="font-semibold text-slate-700 hover:underline dark:text-white/80">Create an account</RouterLink>
        </div>

        <div class="mt-6 flex items-start gap-2.5 border-l-2 border-amber-400/70 pl-3 dark:border-amber-400/40">
          <p class="text-sm leading-relaxed text-slate-500 dark:text-white/40">
            For life-threatening emergencies, call your local emergency hotline first. DASCARE
            supports official response — it does not replace it.
          </p>
        </div>
      </div>

      <!-- Right column is the image. Pass heroImageSrc for the real photo;
           until then this shows a plain placeholder, not a fake widget.
           Wrapped in a perspective container so the card can tilt in 3D. -->
      <div class="hero-image-perspective">
        <div class="hero-image-tilt relative overflow-hidden rounded-3xl border border-slate-200/80 dark:border-white/10">
          <img v-if="heroImageSrc" :src="heroImageSrc" alt="DASCARE responders on scene"
            class="aspect-[4/5] w-full object-cover lg:aspect-auto lg:h-full lg:min-h-[520px]" />
          <!-- No image yet: fall back to the live heartline pulse instead of a
               generic "image missing" placeholder. -->
          <div v-else
            class="flex aspect-[4/5] w-full flex-col items-center justify-center gap-3 bg-base-100 dark:bg-white/5 lg:aspect-auto lg:h-full lg:min-h-[520px]">
            <svg viewBox="0 0 300 90" preserveAspectRatio="none" class="h-24 w-4/5 max-w-xs text-red-500 dark:text-red-400"
              style="filter: drop-shadow(0 0 5px currentColor);">
              <g class="pulse-scroll" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity="0.9">
                <path d="M0,45 L58,45 L72,30 L86,62 L100,8 L114,80 L128,45 L146,45 L300,45" />
                <path d="M0,45 L58,45 L72,30 L86,62 L100,8 L114,80 L128,45 L146,45 L300,45" transform="translate(300,0)" />
              </g>
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Guest request modal — the same merged EmergencyRequestForm used on the -->
    <!-- citizen dashboard, with forceGuest so name/phone are always collected -->
    <!-- inline regardless of any other active session. -->
    <Teleport to="body">
      <div v-if="showGuestModal"
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-950/60 px-3 py-4 backdrop-blur-sm sm:items-center sm:px-4 sm:py-8"
        @click.self="closeGuestModal">
        <div class="flex max-h-[95vh] w-full max-w-lg flex-col rounded-3xl bg-base-100 text-base-content shadow-2xl sm:max-h-[90vh] sm:max-w-2xl lg:max-w-4xl xl:max-w-5xl dark:bg-[#050e1a]">
          <div v-if="submittedResult" class="p-6 text-center">
            <Icon icon="lucide:circle-check-big" width="48" class="mx-auto text-emerald-500" />
            <h3 class="mt-3 text-lg font-bold text-slate-900 dark:text-white">Request sent</h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-white/55">
              Reference <span class="font-mono font-bold">{{ submittedResult.reference_number }}</span> —
              a dispatcher will review it shortly.
            </p>

            <!-- Duplicate detection linked this report to one already being
                 handled nearby — same red notice + escape hatch as TrackRequest. -->
            <div v-if="submittedResult.mergedInto" class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-left dark:border-red-500/20 dark:bg-red-500/5">
              <p class="flex items-start gap-2 text-sm font-black text-red-800 dark:text-red-200">
                <Icon icon="lucide:git-merge" width="16" class="mt-0.5 flex-shrink-0" />
                Linked to {{ submittedResult.mergedInto.reference_number }} — a unit is already being handled for this emergency
              </p>
              <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-white/55">
                Someone {{ submittedResult.mergedInto.distance_m }} m away reported it first, so one unit covers both reports instead of sending two.
              </p>
              <p v-if="separated" class="mt-3 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                <Icon icon="lucide:circle-check" width="13" class="mr-0.5 inline" /> Done — dispatch is finding a separate unit for your report.
              </p>
              <button v-else type="button" :disabled="separating" @click="requestSeparately"
                class="mt-3 inline-flex items-center gap-1.5 rounded-xl border border-red-300 bg-white px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-100 disabled:opacity-50 dark:border-red-500/30 dark:bg-white/5 dark:text-red-300 dark:hover:bg-red-500/10">
                <Icon :icon="separating ? 'lucide:loader-circle' : 'lucide:split'" width="14" :class="separating ? 'animate-spin' : ''" />
                Not the same emergency? Request separately
              </button>
            </div>

            <p v-if="submittedResult.guestStatus?.flagged"
              class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
              This was guest request #{{ submittedResult.guestStatus.ordinal }} today for this phone number,
              so it's marked for a quick extra check by a dispatcher — it's still on its way to them
              exactly like any other request.
            </p>

            <p class="mt-3 text-xs text-slate-500 dark:text-white/40">
              Guest requests aren't saved to an account, so this reference number is the only way to
              follow up.
              <RouterLink to="/register" class="underline decoration-2 underline-offset-2">Create a free account</RouterLink>
              to track requests going forward.
            </p>
            <div class="mt-5 flex flex-col justify-center gap-2 sm:flex-row">
              <button type="button" @click="submittedResult = null; separated = false"
                class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-[#f3ecdd] dark:border-white/15 dark:text-white dark:hover:bg-white/5">
                Submit another
              </button>
              <button type="button" @click="closeGuestModal"
                class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:opacity-90 dark:bg-white dark:text-slate-900">
                Close
              </button>
            </div>
          </div>

          <div v-else class="flex min-h-0 flex-col">
            <div class="sticky top-0 z-10 flex items-center justify-end rounded-t-3xl bg-base-100 px-5 pb-3 pt-5 dark:bg-[#050e1a] sm:pb-4">
              <button type="button" @click="closeGuestModal"
                class="text-slate-400 hover:text-slate-600 dark:text-white/40 dark:hover:text-white/70">
                <Icon icon="lucide:x" width="20" />
              </button>
            </div>

            <div class="min-h-0 overflow-y-auto">
              <p v-if="used !== null"
                class="mx-5 mt-3 rounded-xl bg-[#f8f3e8] px-3 py-2 text-center text-xs font-semibold text-slate-500 dark:bg-white/5 dark:text-white/45">
                {{ used }} of {{ limit }} guest {{ used === 1 ? 'request' : 'requests' }} sent today on this device —
                a genuine emergency always goes through regardless.
              </p>

              <div class="p-4 sm:p-5">
                <EmergencyRequestForm force-guest @submitted="onGuestSubmitted" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Icon } from '@iconify/vue'
import { useGuestRequestLimit } from '@/composables/useGuestRequestLimit'
import EmergencyRequestForm from '@/components/emergency-request/EmergencyRequestForm.vue'
import { unmergeOwnReport } from '@/services/dispatchOperations'
import { useAlert } from '@/composables/useAlert'

const alert = useAlert()

// Optional real photo for the right column — leave unset to show the placeholder.
const props = defineProps({
  heroImageSrc: { type: String, default: '../../img/hero.png' }
})

// Background siren lights — 3 spots that flicker briefly then move to new
// random positions every 5s. Kept in the upper portion of the viewBox
// (y: 40–180) so the glow stays clear of the bottom edge instead of
// bleeding into the section below.
function randomSirenSpot(id) {
  return { id, x: 90 + Math.random() * 420, y: 40 + Math.random() * 140 }
}
const sirens = ref([randomSirenSpot(0), randomSirenSpot(1), randomSirenSpot(2)])
let sirenNextId = 3
let sirenTimer = null

const { limit, used, fetchStatus, applySubmissionResult } = useGuestRequestLimit()
const softLimitCopy = computed(() => `${limit.value ?? 3} requests`)

const showGuestModal = ref(false)
const submittedResult = ref(null)

function openGuestModal() {
  submittedResult.value = null
  showGuestModal.value = true
}

function closeGuestModal() {
  showGuestModal.value = false
}

// "Not the same emergency?" on a merged guest report. citizen/unmerge.php
// trusts it because create.php remembered this id in the guest's session.
const separating = ref(false)
const separated = ref(false)
async function requestSeparately() {
  separating.value = true
  try {
    await unmergeOwnReport(submittedResult.value.id)
    separated.value = true
  } catch (err) {
    alert.error(err.response?.data?.message || err.message || 'Something went wrong. Please try again.', 'Error')
  } finally {
    separating.value = false
  }
}

function onGuestSubmitted(result) {
  separated.value = false
  submittedResult.value = result
  // create.php returns the guest's authoritative (phone-keyed) status on a
  // successful submit — apply it locally so the count updates instantly.
  if (result?.guestStatus) applySubmissionResult(result.guestStatus)
}

// AppHeader's mobile SOS button can't reach this modal directly (it may be
// routed here from another page first), so it dispatches this event instead
// once it knows we're on '/'. There's just one request form now, so the
// event detail no longer selects a tab — it only triggers the open.
function onOpenGuestModal() {
  openGuestModal()
}

onMounted(() => {
  fetchStatus()

  const pendingGuestModal = sessionStorage.getItem('dascare:open-guest-modal')
  if (pendingGuestModal) {
    sessionStorage.removeItem('dascare:open-guest-modal')
    openGuestModal()
  }

  sirenTimer = setInterval(() => {
    sirens.value = [randomSirenSpot(sirenNextId++), randomSirenSpot(sirenNextId++), randomSirenSpot(sirenNextId++)]
  }, 5000)
  window.addEventListener('open-guest-modal', onOpenGuestModal)
})

onUnmounted(() => {
  clearInterval(sirenTimer)
  window.removeEventListener('open-guest-modal', onOpenGuestModal)
})
</script>

<style scoped>
.hero-image-perspective {
  perspective: 1600px;
}

.hero-image-tilt {
  transform: rotateY(-8deg) rotateX(2deg);
  transform-style: preserve-3d;
  box-shadow:
    -24px 28px 60px -18px rgba(15, 23, 42, 0.35),
    -8px 10px 24px -6px rgba(15, 23, 42, 0.25);
  transition: transform 0.4s ease, box-shadow 0.4s ease;
}

.hero-image-tilt:hover {
  transform: rotateY(-4deg) rotateX(1deg);
  box-shadow:
    -14px 18px 44px -16px rgba(15, 23, 42, 0.3),
    -6px 8px 18px -6px rgba(15, 23, 42, 0.2);
}

:global(.dark) .hero-image-tilt {
  box-shadow:
    -24px 28px 60px -18px rgba(0, 0, 0, 0.55),
    -8px 10px 24px -6px rgba(0, 0, 0, 0.4);
}

@media (max-width: 1023px) {
  .hero-image-tilt {
    transform: none;
    box-shadow: 0 20px 40px -16px rgba(15, 23, 42, 0.3);
  }
}

@media (prefers-reduced-motion: reduce) {
  .hero-image-tilt {
    transition: none;
  }
}

.pulse-scroll {
  transform-box: fill-box;
  animation: dascare-pulse-scroll 3s linear infinite;
}

.siren-flicker-blue {
  animation: dascare-siren-glow 3.6s ease-in-out 1 forwards;
}

.siren-flicker-red {
  animation: dascare-siren-glow 3.6s ease-in-out 1 forwards;
  animation-delay: 0.4s;
}

@keyframes dascare-pulse-scroll {
  0% { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}

@keyframes dascare-siren-glow {
  0% { opacity: 0; }
  50% { opacity: 0.45; }
  100% { opacity: 0; }
}

@media (prefers-reduced-motion: reduce) {
  .pulse-scroll,
  .siren-flicker-blue,
  .siren-flicker-red {
    animation: none;
    opacity: 0;
  }
}
</style>