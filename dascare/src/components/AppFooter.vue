<template>
  <footer class="dascare-footer relative border-t border-white/10 bg-[#050e1a] text-white">
    <!-- signal-strength accent bar, matching Hero.vue's / AppHeader.vue's palette -->
    <div class="h-[3px] w-full bg-gradient-to-r from-[#050e1a] via-[#1976D2] to-[#050e1a]"></div>

    <!-- faint radial glow behind the brand column, echoing the hero background -->
    <div class="pointer-events-none absolute -left-24 -top-24 -z-0 h-72 w-72 rounded-full bg-[#1976D2]/10 blur-3xl"></div>

    <div class="relative mx-auto max-w-7xl px-5 py-16 sm:px-6">
      <div class="grid gap-12 sm:grid-cols-2 lg:grid-cols-[1.3fr_1fr_1fr_1fr]">
        <!-- Brand -->
        <div>
          <RouterLink to="/" class="group flex items-center gap-3">
            <span class="relative grid h-11 w-11 shrink-0 place-items-center">
              <span class="pointer-events-none absolute -inset-1 rounded-2xl border-2 border-[#1976D2] opacity-30" style="animation: dascare-logo-ring 2.4s ease-in-out infinite;"></span>
              <span class="relative grid h-11 w-11 place-items-center overflow-hidden rounded-2xl bg-gradient-to-br from-[#1976D2] to-[#0F2A43] ring-1 ring-white/15 transition-transform group-hover:scale-105">
                <img
                  v-if="!logoFailed"
                  :src="LOGO_SRC"
                  alt="Dascare logo"
                  class="h-full w-full object-cover"
                  @error="logoFailed = true"
                />
                <svg v-else viewBox="0 0 24 24" fill="none" class="h-6 w-6 text-white">
                  <path d="M4 15v-3a8 8 0 0 1 16 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                  <rect x="2.5" y="14" width="4" height="6" rx="1.4" stroke="currentColor" stroke-width="1.8" />
                  <rect x="17.5" y="14" width="4" height="6" rx="1.4" stroke="currentColor" stroke-width="1.8" />
                  <path d="M17.5 20c0 1.1-1 2-3 2h-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                </svg>
              </span>
            </span>
            <span class="flex flex-col leading-none">
              <span class="text-lg font-black tracking-wide">Dascare</span>
              <span class="mt-0.5 font-mono text-[10px] uppercase tracking-[0.25em] text-[#7fb3ec]">Ambulance Dispatch</span>
            </span>
          </RouterLink>

          <p class="mt-5 max-w-xs text-sm leading-6 text-white/60">
            Coordinated emergency response for Dasmariñas, connecting citizens, dispatchers,
            rescue personnel, and ambulance coordinators on one platform.
          </p>

          <div class="mt-5 flex items-center gap-2 text-xs text-white/40">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none">
              <path d="M12 21s-7-6.1-7-11a7 7 0 1 1 14 0c0 4.9-7 11-7 11Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
              <circle cx="12" cy="10" r="2.3" stroke="currentColor" stroke-width="1.6" />
            </svg>
            Dasmariñas City, Cavite
          </div>
        </div>

        <!-- Platform: section anchors on the landing page -->
        <div class="border-b border-white/10 pb-4 sm:border-0 sm:pb-0">
          <button
            type="button"
            class="footer-accordion-trigger flex w-full items-center justify-between text-left sm:pointer-events-none"
            :aria-expanded="open.platform"
            @click="toggle('platform')"
          >
            <p class="footer-heading font-mono text-[11px] font-bold uppercase tracking-[0.25em] text-[#7fb3ec]">Platform</p>
            <svg class="footer-chevron h-4 w-4 shrink-0 text-white/40 sm:hidden" :class="{ 'is-open': open.platform }" viewBox="0 0 24 24" fill="none">
              <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
          <div class="footer-accordion-panel" :class="{ 'is-open': open.platform }">
            <div class="footer-accordion-inner">
              <ul class="mt-4 space-y-2.5 text-sm text-white/70">
                <li><RouterLink to="/#how-it-works" class="footer-link">How it works</RouterLink></li>
                <li><RouterLink to="/#decision-support" class="footer-link">Decision support</RouterLink></li>
                <li><RouterLink to="/#roles" class="footer-link">Built for every role</RouterLink></li>
                <li><RouterLink to="/#mobile-app" class="footer-link">Mobile app</RouterLink></li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Get started: account entry points -->
        <div class="border-b border-white/10 pb-4 sm:border-0 sm:pb-0">
          <button
            type="button"
            class="footer-accordion-trigger flex w-full items-center justify-between text-left sm:pointer-events-none"
            :aria-expanded="open.getStarted"
            @click="toggle('getStarted')"
          >
            <p class="footer-heading font-mono text-[11px] font-bold uppercase tracking-[0.25em] text-[#7fb3ec]">Get started</p>
            <svg class="footer-chevron h-4 w-4 shrink-0 text-white/40 sm:hidden" :class="{ 'is-open': open.getStarted }" viewBox="0 0 24 24" fill="none">
              <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
          <div class="footer-accordion-panel" :class="{ 'is-open': open.getStarted }">
            <div class="footer-accordion-inner">
              <ul class="mt-4 space-y-2.5 text-sm text-white/70">
                <li>
                  <button type="button" class="footer-link" @click="requestAssistance">
                    Request assistance
                  </button>
                </li>
                <li><RouterLink to="/login" class="footer-link">Log in</RouterLink></li>
                <li><RouterLink to="/register" class="footer-link">Create an account</RouterLink></li>
                <li><RouterLink to="/apply/organization" class="footer-link">Apply as an organization</RouterLink></li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Company + legal -->
        <div>
          <button
            type="button"
            class="footer-accordion-trigger flex w-full items-center justify-between text-left sm:pointer-events-none"
            :aria-expanded="open.company"
            @click="toggle('company')"
          >
            <p class="footer-heading font-mono text-[11px] font-bold uppercase tracking-[0.25em] text-[#7fb3ec]">Company</p>
            <svg class="footer-chevron h-4 w-4 shrink-0 text-white/40 sm:hidden" :class="{ 'is-open': open.company }" viewBox="0 0 24 24" fill="none">
              <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
          <div class="footer-accordion-panel" :class="{ 'is-open': open.company }">
            <div class="footer-accordion-inner">
              <ul class="mt-4 space-y-2.5 text-sm text-white/70">
                <li><RouterLink to="/about" class="footer-link">About DASCARE</RouterLink></li>
                <li><RouterLink to="/privacy-policy" class="footer-link">Privacy policy</RouterLink></li>
                <li><RouterLink to="/terms-of-service" class="footer-link">Terms of service</RouterLink></li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Emergency notice — its own row so it reads as a callout, not a list item -->
      <div class="mt-12 flex items-start gap-3 rounded-2xl border border-amber-400/20 bg-amber-400/5 px-4 py-3.5 sm:mt-14">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-400" viewBox="0 0 24 24" fill="none">
          <path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <p class="text-xs leading-relaxed text-amber-200/80">
          For life-threatening emergencies, contact your official local emergency hotline directly.
          This academic system supports official response — it does not replace it.
        </p>
      </div>

      <div class="mt-8 flex flex-col gap-3 border-t border-white/10 pt-6 text-xs text-white/40 sm:flex-row sm:items-center sm:justify-between">
        <span>&copy; {{ year }} Dasmariñas Coordinated Ambulance Rescue for Emergencies</span>
        <span class="flex items-center gap-1.5 font-mono uppercase tracking-[0.2em]">
          <span class="relative flex h-1.5 w-1.5">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
          </span>
          System online
        </span>
      </div>
    </div>
  </footer>
</template>

<script setup>
// Images must be imported so the production build bundles them (a plain '../img/…' string only works on the dev server).
import logoBgUrl from '../../img/logoo-bg.jpg'
import { nextTick, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const year = new Date().getFullYear()

const router = useRouter()
const route = useRoute()

async function requestAssistance() {
  // Hero.vue reads this after navigation, so the Instant Rescue modal
  // still opens when the user clicks from another public page.
  sessionStorage.setItem('dascare:open-guest-modal', 'instant')

  if (route.path !== '/') {
    await router.push('/')
  }

  await nextTick()

  window.dispatchEvent(
    new CustomEvent('open-guest-modal', {
      detail: 'instant',
    }),
  )

  window.scrollTo({
    top: 0,
    behavior: 'smooth',
  })
}

/* ---------------------------------------------------------------- */
/*  Brand logo — same source + fallback behavior as AppHeader.vue    */
/* ---------------------------------------------------------------- */
const LOGO_SRC = logoBgUrl
const logoFailed = ref(false)

/* ---------------------------------------------------------------- */
/*  Link columns collapse into an accordion on mobile (below sm:).   */
/*  The `sm:pointer-events-none` on the trigger + the CSS override   */
/*  below keep every panel permanently expanded at sm: and up, so    */
/*  desktop/tablet layout is unaffected.                             */
/* ---------------------------------------------------------------- */
const open = reactive({
  platform: false,
  getStarted: false,
  company: false,
})

function toggle(key) {
  const nextState = !open[key]

  // Keep the mobile footer tidy by opening only one group at a time.
  Object.keys(open).forEach((group) => {
    open[group] = false
  })

  open[key] = nextState
}
</script>

<style scoped>
/*
  Match the header's medical-blue focus ring instead of the app's
  default red one, which is reserved for emergency actions.
*/
.dascare-footer :focus-visible {
  outline-color: rgba(25, 118, 210, 0.55);
}

@keyframes dascare-logo-ring {
  0%, 100% { transform: scale(1); opacity: 0.3; }
  50% { transform: scale(1.14); opacity: 0.08; }
}

/* ---------------------------------------------------------------- */
/*  Footer links — small arrow slides in on hover, like a nudge      */
/*  toward the destination rather than a plain color change.         */
/* ---------------------------------------------------------------- */
.footer-heading {
  position: relative;
  padding-bottom: 0.6rem;
}
.footer-heading::after {
  content: '';
  position: absolute;
  left: 0;
  bottom: 0;
  width: 22px;
  height: 2px;
  border-radius: 999px;
  background: linear-gradient(90deg, #1976D2, transparent);
}

.footer-link {
  position: relative;
  display: inline-flex;
  align-items: center;
  transition: color 0.2s ease, transform 0.2s ease;
}
.footer-link:hover {
  color: #ffffff;
  transform: translateX(3px);
}
.footer-link::before {
  content: '';
  width: 0;
  height: 1px;
  margin-right: 0;
  background: #7fb3ec;
  transition: width 0.2s ease, margin-right 0.2s ease;
}
.footer-link:hover::before {
  width: 8px;
  margin-right: 6px;
}

/* ---------------------------------------------------------------- */
/*  Mobile accordion                                                 */
/* ---------------------------------------------------------------- */
.footer-accordion-trigger {
  min-height: 2.5rem;
}

.footer-chevron {
  transition:
    transform 0.25s ease,
    color 0.2s ease;
}

.footer-chevron.is-open {
  transform: rotate(180deg);
  color: rgba(255, 255, 255, 0.75);
}

.footer-accordion-panel {
  display: grid;
  grid-template-rows: 0fr;
  opacity: 0;
  visibility: hidden;
  transition:
    grid-template-rows 0.3s ease,
    opacity 0.22s ease,
    visibility 0.22s ease;
}

.footer-accordion-panel.is-open {
  grid-template-rows: 1fr;
  opacity: 1;
  visibility: visible;
}

.footer-accordion-inner {
  min-height: 0;
  overflow: hidden;
}

/* Footer columns are always expanded from the sm breakpoint upward. */
@media (min-width: 640px) {
  .footer-accordion-panel,
  .footer-accordion-panel.is-open {
    display: block;
    opacity: 1;
    visibility: visible;
  }

  .footer-accordion-inner {
    overflow: visible;
  }
}

@media (prefers-reduced-motion: reduce) {
  .footer-chevron,
  .footer-accordion-panel,
  .footer-link,
  .footer-link::before {
    transition: none;
  }
}

</style>