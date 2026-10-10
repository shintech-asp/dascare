<template>
  <header
    class="dascare-header sticky top-0 z-50 border-b border-slate-200 bg-white text-slate-900 shadow-sm transition-shadow duration-300 dark:border-white/10 dark:bg-[#050e1a] dark:text-white dark:shadow-[0_2px_20px_rgba(0,0,0,0.5)]"
    :class="isScrolled ? 'shadow-lg dark:shadow-[0_4px_28px_rgba(0,0,0,0.6)]' : ''"
  >
    <!-- signal-strength accent bar, matching Hero.vue's palette -->
    <div class="h-[3px] w-full bg-gradient-to-r from-white via-[#1976D2] to-white dark:from-[#050e1a] dark:via-[#4aa3f0] dark:to-[#050e1a]"></div>

    <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
      <!-- Brand -->
      <RouterLink to="/" class="group flex items-center gap-3">
        <span class="relative grid h-11 w-11 shrink-0 place-items-center">
          <span class="pointer-events-none absolute -inset-1 rounded-2xl border-2 border-[#1976D2] opacity-30 dark:border-[#7fb3ec]" style="animation: dascare-logo-ring 2.4s ease-in-out infinite;"></span>
          <span class="relative grid h-11 w-11 place-items-center overflow-hidden rounded-2xl bg-gradient-to-br from-[#1976D2] to-[#0F2A43] ring-1 ring-black/5 transition-transform group-hover:scale-105 dark:ring-white/15">
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
          <span
            class="w-fit text-lg font-black tracking-wide"
            @mouseenter="isDascareHovered = true"
            @mouseleave="isDascareHovered = false"
          >Dascare</span>
          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-0.5"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-0.5"
            mode="out-in"
          >
            <span
              v-if="isDascareHovered"
              key="meaning"
              class="mt-0.5 max-w-[220px] whitespace-normal font-mono text-[9px] font-medium leading-tight uppercase tracking-[0.08em] text-[#1976D2] dark:text-[#7fb3ec]"
            >
              Dasmariñas Coordinated Ambulance Rescue for Emergencies
            </span>
            <span
              v-else
              key="tagline"
              class="mt-0.5 font-mono text-[10px] uppercase tracking-[0.25em] text-[#1976D2] dark:text-[#7fb3ec]"
            >
              Ambulance Dispatch
            </span>
          </Transition>
          <span class="mt-0.5 flex items-center gap-1 text-[10px] text-slate-400 dark:text-white/40">
            <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none">
              <path d="M12 21s-7-6.1-7-11a7 7 0 1 1 14 0c0 4.9-7 11-7 11Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
              <circle cx="12" cy="10" r="2.3" stroke="currentColor" stroke-width="1.6" />
            </svg>
            Dasmariñas City, Cavite
          </span>
        </span>
      </RouterLink>

      <!-- Desktop nav -->
      <nav class="hidden items-center gap-1.5 text-sm md:flex">
        <!-- Landing page home link -->
        <RouterLink
          to="/"
          class="nav-home-hover group flex items-center gap-1.5 rounded-full px-3.5 py-2 font-medium transition-colors"
          :class="route.path === '/'
            ? 'bg-[#1976D2]/10 text-[#1976D2] ring-1 ring-[#1976D2]/25 dark:bg-[#7fb3ec]/10 dark:text-[#7fb3ec] dark:ring-[#7fb3ec]/25'
            : 'text-slate-600 dark:text-white/80'"
        >
          <svg class="nav-icon h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none"><path d="M3 11.5 12 4l9 7.5M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span>Home</span>
        </RouterLink>

        <div class="mx-2 h-6 w-px bg-slate-200 dark:bg-white/15" v-if="!isAuthPage"></div>


        <template v-if="!isAuthPage && user">
          <button
            class="btn-underline-hover btn btn-ghost btn-sm rounded-lg font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-white/90 dark:hover:bg-white/10 dark:hover:text-white"
            @click="router.push(dashboardPath())"
          >
            Dashboard
          </button>
          <button
            class="btn-shake-hover btn btn-sm ml-1 rounded-lg border-none bg-slate-100 font-medium text-slate-900 hover:bg-slate-200 dark:bg-white/10 dark:text-white dark:hover:bg-white/20"
            @click="signOut"
          >
            Log out
          </button>
        </template>
        <template v-else-if="!isAuthPage">
          <RouterLink
            to="/login"
            class="btn-arrow-hover btn btn-ghost btn-sm gap-1 rounded-lg font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-white/90 dark:hover:bg-white/10 dark:hover:text-white"
          >
            <span>Log in</span>
            <svg class="arrow-icon h-3.5 w-3.5" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </RouterLink>
          <RouterLink
            to="/register"
            class="btn-shine-hover btn btn-sm ml-1 rounded-lg border-none bg-red-600 font-medium text-white shadow-md shadow-red-600/25 hover:bg-red-700 dark:shadow-red-950/40"
          >
            Create account
          </RouterLink>
        </template>

        <div class="mx-2 h-6 w-px bg-slate-200 dark:bg-white/15" v-if="!isAuthPage"></div>

        <!-- Theme toggle -->
        <label class="swap swap-rotate btn btn-ghost btn-sm btn-circle" aria-label="Toggle theme">
          <input type="checkbox" :checked="theme === 'dark'" @change="toggleTheme" />
          <svg class="swap-off h-5 w-5 fill-current text-amber-500 dark:text-amber-300" viewBox="0 0 24 24">
            <path d="M12 6.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Zm0-4.5a1 1 0 0 1 1 1v1.5a1 1 0 1 1-2 0V3a1 1 0 0 1 1-1Zm0 18a1 1 0 0 1 1 1V21a1 1 0 1 1-2 0v-1.5a1 1 0 0 1 1-1Zm10-8a1 1 0 0 1-1 1h-1.5a1 1 0 1 1 0-2H21a1 1 0 0 1 1 1ZM4.5 12a1 1 0 0 1-1 1H2a1 1 0 1 1 0-2h1.5a1 1 0 0 1 1 1Zm14.02-6.52a1 1 0 0 1 0 1.42l-1.06 1.06a1 1 0 1 1-1.42-1.42l1.06-1.06a1 1 0 0 1 1.42 0ZM7.96 18.02a1 1 0 0 1 0 1.42l-1.06 1.06a1 1 0 1 1-1.42-1.42l1.06-1.06a1 1 0 0 1 1.42 0Zm10.98 1.42a1 1 0 0 1-1.42 0l-1.06-1.06a1 1 0 1 1 1.42-1.42l1.06 1.06a1 1 0 0 1 0 1.42ZM6.9 6.9a1 1 0 0 1-1.42 0L4.42 5.84A1 1 0 1 1 5.84 4.4L6.9 5.48a1 1 0 0 1 0 1.42Z"/>
          </svg>
          <svg class="swap-on h-5 w-5 fill-current text-slate-700 dark:text-slate-200" viewBox="0 0 24 24">
            <path d="M21.64 13a1 1 0 0 0-1.05-.14 8.05 8.05 0 0 1-3.37.73 8.15 8.15 0 0 1-8.14-8.14c0-1.16.25-2.29.73-3.37A1 1 0 0 0 8.6 1a10.14 10.14 0 1 0 13 13.36 1 1 0 0 0 .04-1.36Z"/>
          </svg>
        </label>
      </nav>

      <!-- Mobile controls -->
      <div class="flex items-center gap-2 md:hidden">
        <RouterLink
          v-if="isAuthPage"
          to="/"
          class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium text-slate-600 dark:text-white/80"
          :class="isLandingHomeActive ? 'bg-[#1976D2]/10 text-[#1976D2] dark:bg-[#7fb3ec]/10 dark:text-[#7fb3ec]' : ''"
        >
          <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none"><path d="M3 11.5 12 4l9 7.5M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span>Home</span>
        </RouterLink>
        <label class="swap swap-rotate btn btn-ghost btn-sm btn-circle" aria-label="Toggle theme">
          <input type="checkbox" :checked="theme === 'dark'" @change="toggleTheme" />
          <svg class="swap-off h-5 w-5 fill-current text-amber-500 dark:text-amber-300" viewBox="0 0 24 24">
            <path d="M12 6.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Zm0-4.5a1 1 0 0 1 1 1v1.5a1 1 0 1 1-2 0V3a1 1 0 0 1 1-1Zm0 18a1 1 0 0 1 1 1V21a1 1 0 1 1-2 0v-1.5a1 1 0 0 1 1-1Zm10-8a1 1 0 0 1-1 1h-1.5a1 1 0 1 1 0-2H21a1 1 0 0 1 1 1ZM4.5 12a1 1 0 0 1-1 1H2a1 1 0 1 1 0-2h1.5a1 1 0 0 1 1 1Zm14.02-6.52a1 1 0 0 1 0 1.42l-1.06 1.06a1 1 0 1 1-1.42-1.42l1.06-1.06a1 1 0 0 1 1.42 0ZM7.96 18.02a1 1 0 0 1 0 1.42l-1.06 1.06a1 1 0 1 1-1.42-1.42l1.06-1.06a1 1 0 0 1 1.42 0Zm10.98 1.42a1 1 0 0 1-1.42 0l-1.06-1.06a1 1 0 1 1 1.42-1.42l1.06 1.06a1 1 0 0 1 0 1.42ZM6.9 6.9a1 1 0 0 1-1.42 0L4.42 5.84A1 1 0 1 1 5.84 4.4L6.9 5.48a1 1 0 0 1 0 1.42Z"/>
          </svg>
          <svg class="swap-on h-5 w-5 fill-current text-slate-700 dark:text-slate-200" viewBox="0 0 24 24">
            <path d="M21.64 13a1 1 0 0 0-1.05-.14 8.05 8.05 0 0 1-3.37.73 8.15 8.15 0 0 1-8.14-8.14c0-1.16.25-2.29.73-3.37A1 1 0 0 0 8.6 1a10.14 10.14 0 1 0 13 13.36 1 1 0 0 0 .04-1.36Z"/>
          </svg>
        </label>
      </div>
    </div>

    <!-- ============================================================ -->
    <!--  MOBILE BOTTOM NAV — top nav (above) handles brand + theme,   -->
    <!--  this handles primary navigation + the emergency-request FAB  -->
    <!-- ============================================================ -->
    <nav
      v-if="!isAuthPage"
      class="fixed bottom-0 left-0 z-50 w-full border-t border-slate-200 bg-white shadow-[0_-4px_20px_rgba(0,0,0,0.08)] dark:border-white/10 dark:bg-[#050e1a] md:hidden"
    >
      <ul class="relative flex items-center justify-around px-2 py-2.5">

        <!-- Home -->
        <li>
          <RouterLink to="/" class="flex flex-col items-center gap-0.5 px-2 transition-colors"
            :class="isLandingHomeActive ? 'text-red-600 dark:text-red-400' : 'text-slate-500 hover:text-red-600 dark:text-white/50 dark:hover:text-red-400'">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 11.5 12 4l9 7.5" />
              <path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9" />
            </svg>
            <span class="text-[9px] font-semibold">Home</span>
          </RouterLink>
        </li>

        <!-- How it works -->
        <li>
          <button @click="go('/#how-it-works')" class="flex flex-col items-center gap-0.5 px-2 transition-colors" :class="activeSection === 'how-it-works' ? 'text-red-600 dark:text-red-400' : 'text-slate-500 hover:text-red-600 dark:text-white/50 dark:hover:text-red-400'">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z" />
            </svg>
            <span class="text-[9px] font-semibold">Guide</span>
          </button>
        </li>

        <!-- spacer for the centre FAB -->
        <li class="w-16" />

        <template v-if="user">
          <li>
            <button @click="router.push(dashboardPath())" class="flex flex-col items-center gap-0.5 px-2 transition-colors text-slate-500 hover:text-red-600 dark:text-white/50 dark:hover:text-red-400">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="8" height="8" rx="1.5" /><rect x="13" y="3" width="8" height="8" rx="1.5" />
                <rect x="3" y="13" width="8" height="8" rx="1.5" /><rect x="13" y="13" width="8" height="8" rx="1.5" />
              </svg>
              <span class="text-[9px] font-semibold">Dashboard</span>
            </button>
          </li>
          <li>
            <button @click="signOut" class="flex flex-col items-center gap-0.5 px-2 transition-colors text-slate-500 hover:text-red-600 dark:text-white/50 dark:hover:text-red-400">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                <path d="M16 17l5-5-5-5" /><path d="M21 12H9" />
              </svg>
              <span class="text-[9px] font-semibold">Log out</span>
            </button>
          </li>
        </template>
        <template v-else>
          <li>
            <button @click="go('/login')" class="flex flex-col items-center gap-0.5 px-2 transition-colors text-slate-500 hover:text-red-600 dark:text-white/50 dark:hover:text-red-400">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                <path d="M10 17l5-5-5-5" /><path d="M15 12H3" />
              </svg>
              <span class="text-[9px] font-semibold">Log in</span>
            </button>
          </li>
          <li>
            <button @click="go('/register')" class="flex flex-col items-center gap-0.5 px-2 transition-colors text-slate-500 hover:text-red-600 dark:text-white/50 dark:hover:text-red-400">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" />
                <path d="M19 8v6M22 11h-6" />
              </svg>
              <span class="text-[9px] font-semibold">Sign up</span>
            </button>
          </li>
        </template>

        <!-- Centre FAB — same "instant rescue" action as Hero.vue's -->
        <!-- primary CTA, siren-red and raised above the bar.        -->
        <li class="absolute -top-7 left-1/2 -translate-x-1/2">
          <button
            @click="triggerEmergencyRequest"
            aria-label="Request emergency assistance"
            class="flex h-14 w-14 flex-col items-center justify-center rounded-full border-4 border-white bg-red-600 text-white shadow-lg shadow-red-600/40 transition-all hover:scale-110 hover:bg-red-700 active:scale-95 dark:border-[#050e1a]"
          >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
              <path d="M12 9v4" /><path d="M12 17h.01" />
            </svg>
            <span class="mt-0.5 text-[8px] font-bold uppercase tracking-wide">SOS</span>
          </button>
        </li>
      </ul>
    </nav>
  </header>
</template>
<script setup>
// Images must be imported so the production build bundles them (a plain '../img/…' string only works on the dev server).
import logoBgUrl from '../../img/logoo-bg.jpg'
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useSession } from '@/composables/useSession'
import { useTheme } from '@/composables/useTheme'

const router = useRouter()
const route = useRoute()
const { user, logout, dashboardPath } = useSession()

/* ---------------------------------------------------------------- */
/*  Brand logo — swap LOGO_SRC for the real asset path when ready.   */
/*  Falls back to the inline ambulance icon if the image 404s.       */
/* ---------------------------------------------------------------- */
const LOGO_SRC = logoBgUrl
const logoFailed = ref(false)

/* ---------------------------------------------------------------- */
/*  Brand text swap — hovering "DASCARE" reveals what it stands for  */
/* ---------------------------------------------------------------- */
const isDascareHovered = ref(false)

const { theme, toggleTheme } = useTheme()

/* ---------------------------------------------------------------- */
/*  Scroll elevation — header gains depth once the page scrolls      */
/* ---------------------------------------------------------------- */
const isScrolled = ref(false)
function onScroll() {
  isScrolled.value = window.scrollY > 10
}

/* ---------------------------------------------------------------- */
/*  Bottom nav is its own primary navigation on mobile, so it hides  */
/*  on the auth pages (which render their own self-contained layout) */
/* ---------------------------------------------------------------- */
const isAuthPage = computed(() => ['Login', 'Register', 'OrganizationApplication'].includes(route.name))

const landingSectionIds = ['how-it-works', 'decision-support', 'roles', 'mobile-app']
const activeSection = ref('')
let sectionObserver = null

const isLandingHomeActive = computed(() => route.path === '/' && !activeSection.value)

function sectionActiveClass(id) {
  return route.path === '/' && activeSection.value === id
    ? '!text-[#1976D2] bg-[#1976D2]/10 ring-1 ring-[#1976D2]/25 dark:!text-[#7fb3ec] dark:bg-[#7fb3ec]/10 dark:ring-[#7fb3ec]/25'
    : ''
}

function syncActiveSectionFromViewport() {
  if (route.path !== '/') {
    activeSection.value = ''
    return
  }

  const headerOffset = 120
  const current = landingSectionIds
    .map((id) => document.getElementById(id))
    .filter(Boolean)
    .find((section) => {
      const rect = section.getBoundingClientRect()
      return rect.top <= headerOffset && rect.bottom > headerOffset
    })

  activeSection.value = current?.id || ''
}

function observeLandingSections() {
  sectionObserver?.disconnect()
  sectionObserver = null

  if (route.path !== '/') {
    activeSection.value = ''
    return
  }

  nextTick(() => {
    const sections = landingSectionIds.map((id) => document.getElementById(id)).filter(Boolean)
    if (!sections.length) return

    sectionObserver = new IntersectionObserver(syncActiveSectionFromViewport, {
      rootMargin: '-105px 0px -70% 0px',
      threshold: [0, 0.01, 0.25],
    })
    sections.forEach((section) => sectionObserver.observe(section))
    syncActiveSectionFromViewport()
  })
}

function go(path) {
  router.push(path)
}

const signOut = async () => {
  await logout()
  router.replace('/')
}

/* ---------------------------------------------------------------- */
/*  Centre FAB — mirrors Hero.vue's "Instant rescue" CTA. Hero owns  */
/*  the actual guest-request modal, so from anywhere else this just  */
/*  gets the person home and asks it to open on the instant tab.     */
/* ---------------------------------------------------------------- */
function triggerEmergencyRequest() {
  if (route.path !== '/') {
    router.push('/').then(() => {
      nextTick(() => window.dispatchEvent(new CustomEvent('open-guest-modal', { detail: 'instant' })))
    })
  } else {
    window.dispatchEvent(new CustomEvent('open-guest-modal', { detail: 'instant' }))
  }
}

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true })
  window.addEventListener('scroll', syncActiveSectionFromViewport, { passive: true })
  onScroll()
  observeLandingSections()
})

watch(() => route.fullPath, observeLandingSections)

onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll)
  window.removeEventListener('scroll', syncActiveSectionFromViewport)
  sectionObserver?.disconnect()
})
</script>

<style scoped>
/*
  The app's global focus-visible ring (in main.css) is red, reserved
  elsewhere for emergency actions. None of the controls in this header
  are emergency actions, so we override the ring color locally to
  medical blue instead of touching the global rule.
*/
.dascare-header :focus-visible {
  outline-color: rgba(25, 118, 210, 0.55);
}

/* ---------------------------------------------------------------- */
/*  Logo ring — soft pulse around the brand mark, signalling "live"  */
/*  (merged in from the previous Header.vue design)                  */
/* ---------------------------------------------------------------- */
@keyframes dascare-logo-ring {
  0%, 100% { transform: scale(1); opacity: 0.3; }
  50% { transform: scale(1.14); opacity: 0.08; }
}

/* ---------------------------------------------------------------- */
/*  Dashboard — underline sweeps in from center                      */
/* ---------------------------------------------------------------- */
.btn-underline-hover {
  position: relative;
}
.btn-underline-hover::after {
  content: '';
  position: absolute;
  left: 50%;
  bottom: 4px;
  width: 0;
  height: 2px;
  background: #1976D2;
  border-radius: 999px;
  transform: translateX(-50%);
  transition: width 0.25s ease;
}
:global([data-theme='dark']) .btn-underline-hover::after {
  background: #7fb3ec;
}
.btn-underline-hover:hover::after {
  width: calc(100% - 24px);
}

/* ---------------------------------------------------------------- */
/*  Log out — a small warning "wobble", nothing destructive-looking  */
/*  until the user actually confirms via the click handler           */
/* ---------------------------------------------------------------- */
.btn-shake-hover:hover {
  animation: btn-wobble 0.4s ease;
}
@keyframes btn-wobble {
  0%, 100% { transform: rotate(0deg); }
  25% { transform: rotate(-2deg); }
  75% { transform: rotate(2deg); }
}

/* ---------------------------------------------------------------- */
/*  Log in — arrow glides out from the text on hover                 */
/* ---------------------------------------------------------------- */
.btn-arrow-hover .arrow-icon {
  transform: translateX(-6px);
  opacity: 0;
  transition: transform 0.25s ease, opacity 0.25s ease;
}
.btn-arrow-hover:hover .arrow-icon {
  transform: translateX(0);
  opacity: 1;
}

/* ---------------------------------------------------------------- */
/*  Create account — glossy diagonal shine sweeps across on hover    */
/* ---------------------------------------------------------------- */
.btn-shine-hover {
  position: relative;
  overflow: hidden;
}
.btn-shine-hover::before {
  content: '';
  position: absolute;
  top: 0;
  left: -75%;
  width: 50%;
  height: 100%;
  background: linear-gradient(
    115deg,
    transparent 0%,
    rgba(255, 255, 255, 0.55) 50%,
    transparent 100%
  );
  transform: skewX(-20deg);
  transition: left 0.5s ease;
}
.btn-shine-hover:hover::before {
  left: 130%;
}

/* ---------------------------------------------------------------- */
/*  Home — blue surface that matches the DASCARE brand               */
/* ---------------------------------------------------------------- */
.nav-home-hover:hover {
  color: #1976D2;
  background-color: rgba(25, 118, 210, 0.10);
}
:global([data-theme='dark']) .nav-home-hover:hover {
  color: #7fb3ec;
  background-color: rgba(127, 179, 236, 0.10);
}

/* ---------------------------------------------------------------- */
/*  How It Works — amber glow, icon flickers like a spark            */
/* ---------------------------------------------------------------- */
.nav-glow-hover {
  position: relative;
  transition: color 0.25s ease, background-color 0.25s ease, box-shadow 0.25s ease;
}
.nav-glow-hover:hover {
  color: #f59e0b;
  background-color: rgba(245, 158, 11, 0.12);
  box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.35), 0 0 18px rgba(245, 158, 11, 0.35);
}
.nav-glow-hover:hover .nav-icon {
  animation: nav-spark 0.6s ease-in-out infinite;
  color: #f59e0b;
}
@keyframes nav-spark {
  0%, 100% { opacity: 1; transform: scale(1) rotate(0deg); }
  50% { opacity: 0.55; transform: scale(1.15) rotate(-8deg); }
}

/* ---------------------------------------------------------------- */
/*  Decision Support — teal fill sweeps in, icon settles/"thinks"    */
/* ---------------------------------------------------------------- */
.nav-fill-hover {
  position: relative;
  overflow: hidden;
  z-index: 0;
  color: inherit;
  transition: color 0.25s ease;
}
.nav-fill-hover::before {
  content: '';
  position: absolute;
  inset: 0;
  background: #14b8a6;
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.35s cubic-bezier(0.65, 0, 0.35, 1);
  z-index: -1;
  border-radius: inherit;
}
.nav-fill-hover:hover {
  color: #ffffff;
}
.nav-fill-hover:hover::before {
  transform: scaleX(1);
}
.nav-fill-hover:hover .nav-icon {
  animation: nav-think 0.7s ease;
}
@keyframes nav-think {
  0%, 100% { transform: translateY(0) rotate(0deg); }
  40% { transform: translateY(-2px) rotate(-6deg); }
  70% { transform: translateY(0) rotate(4deg); }
}

/* ---------------------------------------------------------------- */
/*  Roles — violet pill pops up, icon hops                           */
/* ---------------------------------------------------------------- */
.nav-bounce-hover {
  transition: transform 0.2s ease, color 0.2s ease, background-color 0.2s ease;
}
.nav-bounce-hover:hover {
  color: #a855f7;
  background-color: rgba(168, 85, 247, 0.14);
  animation: nav-bounce 0.5s ease;
}
.nav-bounce-hover:hover .nav-icon {
  animation: nav-icon-hop 0.5s ease;
}
@keyframes nav-bounce {
  0%, 100% { transform: translateY(0); }
  30% { transform: translateY(-6px); }
  55% { transform: translateY(0); }
  75% { transform: translateY(-2px); }
}
@keyframes nav-icon-hop {
  0%, 100% { transform: translateY(0) scale(1); }
  30% { transform: translateY(-3px) scale(1.15); }
}

/* ---------------------------------------------------------------- */
/*  Mobile App — rose tilt, like tipping a phone in your hand        */
/* ---------------------------------------------------------------- */
.nav-tilt-hover {
  transition: transform 0.25s ease, color 0.25s ease, background-color 0.25s ease;
  transform-style: preserve-3d;
}
.nav-tilt-hover:hover {
  color: #fb7185;
  background-color: rgba(251, 113, 133, 0.14);
}
.nav-tilt-hover:hover .nav-icon {
  transform: perspective(200px) rotateY(35deg) rotateZ(-8deg);
}
.nav-tilt-hover .nav-icon {
  transition: transform 0.3s ease;
}
</style>