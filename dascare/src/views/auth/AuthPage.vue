<template>
  <section class="relative isolate min-h-screen overflow-hidden bg-base-200 text-base-content transition-colors dark:bg-[#050e1a]">
    <!-- Optional background photo — only renders when AUTH_BG_SRC is set -->
    <!-- AND the image actually loads. Heavily washed out with a top-to- -->
    <!-- bottom fade back to the page background so it reads as ambient -->
    <!-- texture behind the glow motif, never competing with the form. -->
    <div v-if="AUTH_BG_SRC && !bgFailed" class="pointer-events-none absolute inset-0 -z-20 overflow-hidden">
      <img :src="AUTH_BG_SRC" alt="" class="h-full w-full object-cover opacity-[1] dark:opacity-[1]"
        @error="bgFailed = true" />
      <div
        class="absolute inset-0 bg-gradient-to-b from-[#f5efe1]/95 via-[#f5efe1]/90 to-[#f5efe1] dark:from-[#050e1a]/95 dark:via-[#050e1a]/90 dark:to-[#050e1a]">
      </div>
    </div>

    <!-- Big, soft siren glows flicker at random spots every few seconds —
         background only, same motif as Hero.vue. -->
    <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
      <svg viewBox="0 0 600 400" preserveAspectRatio="xMidYMid slice" class="h-full w-full">
        <defs>
          <radialGradient id="dascare-auth-glow-blue" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#1976D2" stop-opacity="0.6" />
            <stop offset="100%" stop-color="#1976D2" stop-opacity="0" />
          </radialGradient>
          <radialGradient id="dascare-auth-glow-red" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#ef4444" stop-opacity="0.6" />
            <stop offset="100%" stop-color="#ef4444" stop-opacity="0" />
          </radialGradient>
        </defs>

        <g v-for="siren in sirens" :key="siren.id" :transform="`translate(${siren.x},${siren.y})`">
          <circle r="70" fill="url(#dascare-auth-glow-blue)" class="siren-flicker-blue" />
          <circle r="70" fill="url(#dascare-auth-glow-red)" class="siren-flicker-red" />
        </g>
      </svg>
    </div>

    <div
      class="relative mx-auto grid min-h-screen max-w-7xl gap-0 px-0 lg:grid-cols-[1.08fr_1fr] lg:items-center lg:gap-16 lg:px-5 lg:py-16">

      <!-- ═══════════════════════════════════════════
           LEFT — DISPATCH PANEL (decorative, desktop only)
      ════════════════════════════════════════════ -->
      <div class="relative hidden lg:block">
        <div class="inline-flex items-center gap-2.5 border-l-2 border-red-600 pl-3 dark:border-red-400">
          <span
            class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">
            Dasmariñas · Account access
          </span>
        </div>

        <h1
          class="mt-6 text-4xl font-black leading-[1.05] tracking-tight text-slate-950 xl:text-[3.2rem] dark:text-white">
          One account. Every step<br class="hidden xl:block" />
          of the <span class="text-red-600 dark:text-red-400">response.</span>
        </h1>

        <p class="mt-6 max-w-md text-lg leading-8 text-slate-600 dark:text-[#a9c6e8]">
          Your Dascare account keeps you connected from the moment a report goes out
          to the moment help arrives — reporting, dispatch, and live tracking, all in one place.
        </p>

        <div
          class="relative mt-10 divide-y divide-slate-200 border-t border-slate-200 dark:divide-white/10 dark:border-white/10">
          <div v-for="(item, i) in highlights" :key="item.title" class="flex items-start gap-4 py-5">
            <span class="mt-0.5 shrink-0 font-mono text-xs font-semibold text-[#1976D2] dark:text-[#7fb3ec]">
              CH.{{ i + 1 }}
            </span>
            <div>
              <h2 class="font-bold text-slate-900 dark:text-white">{{ item.title }}</h2>
              <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-[#a9c6e8]">{{ item.description }}</p>
            </div>
          </div>
        </div>

        <!-- Live heartline pulse — same motif as Hero.vue's image placeholder -->
        <div class="mt-10 flex items-center gap-4">
          <svg viewBox="0 0 300 90" preserveAspectRatio="none" class="h-14 w-40 shrink-0 text-red-500 dark:text-red-400"
            style="filter: drop-shadow(0 0 5px currentColor);">
            <g class="pulse-scroll" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round"
              stroke-linejoin="round" opacity="0.9">
              <path d="M0,45 L58,45 L72,30 L86,62 L100,8 L114,80 L128,45 L146,45 L300,45" />
              <path d="M0,45 L58,45 L72,30 L86,62 L100,8 L114,80 L128,45 L146,45 L300,45"
                transform="translate(300,0)" />
            </g>
          </svg>
          <div
            class="flex items-center gap-1.5 font-mono text-[10px] uppercase tracking-[0.2em] text-slate-400 dark:text-white/40">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
            system online · Cavite
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════
           RIGHT — FORM CARD
      ════════════════════════════════════════════ -->
      <div
        class="relative z-10 flex min-h-screen flex-col items-center justify-center gap-6 px-5 py-10 lg:min-h-0 lg:px-0 lg:py-0">

        <!-- Compact mobile-only header, same border-l-2 label + red-accent
             heading treatment as the desktop dispatch panel above. -->
        <div class="w-full max-w-md lg:hidden">
          <div class="inline-flex items-center gap-2.5 border-l-2 border-red-600 pl-3 dark:border-red-400">
            <span
              class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">
              Dasmariñas · Account access
            </span>
          </div>
          <h1 class="mt-3 text-2xl font-black leading-tight tracking-tight text-slate-950 dark:text-white">
            One account, every step of the <span class="text-red-600 dark:text-red-400">response.</span>
          </h1>
        </div>

        <div
          class="w-full max-w-md overflow-hidden rounded-3xl border border-slate-200/80 bg-base-100 shadow-2xl shadow-slate-300/40 dark:border-white/10 dark:bg-[#071829] dark:shadow-black/40">

          <!-- Brand strip -->
          <div
            class="flex items-center justify-between border-b border-slate-200 bg-[#f8f3e8] px-7 py-5 dark:border-white/10 dark:bg-white/[0.03]">
            <RouterLink to="/" class="flex items-center gap-2">
              <span
                class="relative flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gradient-to-br from-[#1976D2] to-[#0F2A43] ring-1 ring-black/5 dark:ring-white/15">
                <img v-if="!logoFailed" :src="LOGO_SRC" alt="Dascare logo" class="h-full w-full object-cover"
                  @error="logoFailed = true" />

                <svg v-else viewBox="0 0 24 24" fill="none" class="h-5 w-5 text-white">
                  <path d="M4 15v-3a8 8 0 0 1 16 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                  <rect x="2.5" y="14" width="4" height="6" rx="1.4" stroke="currentColor" stroke-width="1.8" />
                  <rect x="17.5" y="14" width="4" height="6" rx="1.4" stroke="currentColor" stroke-width="1.8" />
                  <path d="M17.5 20c0 1.1-1 2-3 2h-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                </svg>
              </span>

              <span class="flex flex-col leading-none">
                <span class="w-fit font-black tracking-tight text-slate-950 dark:text-white"
                  @mouseenter="isDascareHovered = true" @mouseleave="isDascareHovered = false">
                  Dascare
                </span>

                <Transition enter-active-class="transition duration-200 ease-out"
                  enter-from-class="opacity-0 -translate-y-0.5" enter-to-class="opacity-100 translate-y-0"
                  leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 translate-y-0"
                  leave-to-class="opacity-0 -translate-y-0.5" mode="out-in">
                  <span v-if="isDascareHovered" key="meaning"
                    class="mt-0.5 max-w-[180px] whitespace-normal font-mono text-[8px] font-medium leading-tight uppercase tracking-[0.06em] text-[#1976D2] dark:text-[#7fb3ec]">
                    Dasmariñas Coordinated Ambulance Rescue for Emergencies
                  </span>
                </Transition>
              </span>
            </RouterLink>
            <span class="font-mono text-[11px] uppercase tracking-[0.2em] text-slate-400 dark:text-white/40">
              {{ isActive ? 'sign up' : 'log in' }}
            </span>
          </div>

          <div class="px-7 py-8 sm:px-9">

            <!-- ═══════════ LOG IN ═══════════ -->
            <form v-if="!isActive" :key="'login'" class="flex flex-col" @submit.prevent="login">
              <h2 class="text-2xl font-black tracking-tight text-slate-950 dark:text-white">Welcome back</h2>
              <p class="mt-1 text-sm text-slate-500 dark:text-[#a9c6e8]">Log in to continue to your dashboard.</p>

              <label class="mt-6 mb-1.5 text-xs font-semibold text-slate-500 dark:text-white/50">Email</label>
              <div class="relative">
                <Icon icon="line-md:email" width="18" height="18"
                  class="absolute left-3 top-3.5 text-slate-400 dark:text-white/30" />
                <input v-model="loginEmail" type="email" required placeholder="you@example.com"
                  class="w-full rounded-xl border border-slate-200 bg-[#f8f3e8] py-3 pl-11 pr-4 text-slate-800 placeholder:text-slate-400 transition-all focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-white/30" />
              </div>

              <label class="mt-4 mb-1.5 text-xs font-semibold text-slate-500 dark:text-white/50">Password</label>
              <div class="relative">
                <Icon icon="mdi:lock-outline" width="20" height="20"
                  class="absolute left-3 top-3.5 text-slate-400 dark:text-white/30" />
                <input :type="showLoginPassword ? 'text' : 'password'" v-model="loginPassword" required
                  placeholder="••••••••"
                  class="w-full rounded-xl border border-slate-200 bg-[#f8f3e8] py-3 pl-11 pr-12 text-slate-800 placeholder:text-slate-400 transition-all focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-white/30" />
                <button type="button" @click="showLoginPassword = !showLoginPassword"
                  class="absolute right-3 top-3 text-slate-400 hover:text-red-600 dark:text-white/40 dark:hover:text-red-400">
                  <Icon :icon="showLoginPassword ? 'line-md:watch' : 'line-md:watch-off'" width="22" height="22" />
                </button>
              </div>

              <label class="mt-3 flex items-center gap-2 text-xs text-slate-500 dark:text-white/50">
                <input type="checkbox" v-model="rememberDevice"
                  class="h-3.5 w-3.5 rounded border-slate-300 accent-red-600 dark:border-white/20" />
                Remember this device
              </label>

              <p class="mt-3 cursor-pointer text-right text-xs font-semibold text-red-600 hover:underline dark:text-red-400"
                @click="openForgotPassword">
                Forgot password?
              </p>

              <button type="submit" :disabled="isLoginLoading"
                class="mt-5 flex items-center justify-center gap-2 rounded-2xl bg-red-600 py-3.5 font-bold text-white shadow-lg shadow-red-600/25 transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60 dark:shadow-red-950/40">
                <svg v-if="isLoginLoading" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                {{ isLoginLoading ? 'Checking account…' : 'Log In' }}
              </button>

              <p class="mt-6 text-center text-xs text-slate-400 dark:text-white/40">
                Don't have an account?
                <span class="cursor-pointer font-semibold text-red-600 hover:underline dark:text-red-400"
                  @click="switchTo(true)">Sign up</span>
              </p>
            </form>

            <!-- ═══════════ SIGN UP ═══════════ -->
            <form v-else :key="'register'" class="flex flex-col" @submit.prevent="openRegisterConfirm">
              <h2 class="text-2xl font-black tracking-tight text-slate-950 dark:text-white">Create your account</h2>
              <p class="mt-1 text-sm text-slate-500 dark:text-[#a9c6e8]">Public sign-up creates a citizen account.</p>

              <div class="mt-5 grid grid-cols-2 gap-3">
                <div>
                  <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-white/50">First name</label>
                  <input v-model="registerFirstName" required placeholder="Juan"
                    class="w-full rounded-xl border border-slate-200 bg-[#f8f3e8] px-4 py-3 text-slate-800 placeholder:text-slate-400 transition-all focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-white/30" />
                </div>
                <div>
                  <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-white/50">Last name</label>
                  <input v-model="registerLastName" required placeholder="Dela Cruz"
                    class="w-full rounded-xl border border-slate-200 bg-[#f8f3e8] px-4 py-3 text-slate-800 placeholder:text-slate-400 transition-all focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-white/30" />
                </div>
              </div>

              <label class="mt-4 mb-1.5 text-xs font-semibold text-slate-500 dark:text-white/50">Email</label>
              <input v-model="registerEmail" type="email" required placeholder="you@example.com"
                class="w-full rounded-xl border border-slate-200 bg-[#f8f3e8] px-4 py-3 text-slate-800 placeholder:text-slate-400 transition-all focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-white/30" />
              <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">We'll send a verification code to this address.
              </p>

              <label class="mt-4 mb-1.5 text-xs font-semibold text-slate-500 dark:text-white/50">Mobile number</label>
              <input v-model="registerPhone" required placeholder="09XXXXXXXXX"
                class="w-full rounded-xl border border-slate-200 bg-[#f8f3e8] px-4 py-3 text-slate-800 placeholder:text-slate-400 transition-all focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-white/30" />
              <p v-if="registerPhone && !phoneValid" class="mt-1 text-xs text-red-500">Enter a valid PH mobile number
                (09XXXXXXXXX or +639XXXXXXXXX).</p>

              <label class="mt-4 mb-1.5 text-xs font-semibold text-slate-500 dark:text-white/50">Password</label>
              <div class="relative">
                <input :type="showRegisterPassword ? 'text' : 'password'" v-model="registerPassword" required
                  placeholder="Create a password"
                  class="w-full rounded-xl border border-slate-200 bg-[#f8f3e8] px-4 py-3 pr-12 text-slate-800 placeholder:text-slate-400 transition-all focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-white/30" />
                <button type="button" @click="showRegisterPassword = !showRegisterPassword"
                  class="absolute right-3 top-3 text-slate-400 hover:text-red-600 dark:text-white/40 dark:hover:text-red-400">
                  <Icon :icon="showRegisterPassword ? 'line-md:watch' : 'line-md:watch-off'" width="22" height="22" />
                </button>
              </div>
              <div v-if="registerPassword.length > 0" class="mt-2">
                <div class="flex gap-1">
                  <div v-for="i in 4" :key="i" class="h-1 flex-1 rounded-full transition-all duration-300" :class="i <= passwordStrength
                    ? (passwordStrength <= 1 ? 'bg-red-500' : passwordStrength === 2 ? 'bg-amber-500' : passwordStrength === 3 ? 'bg-blue-500' : 'bg-emerald-500')
                    : 'bg-slate-200 dark:bg-white/10'"></div>
                </div>
                <p class="mt-1 text-xs"
                  :class="passwordStrength <= 1 ? 'text-red-500' : passwordStrength === 2 ? 'text-amber-500' : passwordStrength === 3 ? 'text-blue-500' : 'text-emerald-500'">
                  {{ passwordStrengthText }}
                </p>
                <p class="mt-1 text-[11px] text-slate-400 dark:text-white/40">
                  10+ characters, upper &amp; lower case, a number, and a special character.
                </p>
              </div>

              <label class="mt-4 mb-1.5 text-xs font-semibold text-slate-500 dark:text-white/50">Confirm
                password</label>
              <div class="relative">
                <input :type="showConfirmPassword ? 'text' : 'password'" v-model="registerConfirmPassword" required
                  placeholder="Confirm password"
                  class="w-full rounded-xl border border-slate-200 bg-[#f8f3e8] px-4 py-3 pr-12 text-slate-800 placeholder:text-slate-400 transition-all focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-white/30" />
                <button type="button" @click="showConfirmPassword = !showConfirmPassword"
                  class="absolute right-3 top-3 text-slate-400 hover:text-red-600 dark:text-white/40 dark:hover:text-red-400">
                  <Icon :icon="showConfirmPassword ? 'line-md:watch' : 'line-md:watch-off'" width="22" height="22" />
                </button>
              </div>
              <div v-if="registerConfirmPassword.length > 0" class="mt-2 flex items-center gap-2">
                <Icon :icon="passwordsMatch ? 'mdi:check-circle' : 'mdi:close-circle'" class="h-4 w-4"
                  :class="passwordsMatch ? 'text-emerald-500' : 'text-red-500'" />
                <span class="text-xs" :class="passwordsMatch ? 'text-emerald-500' : 'text-red-500'">
                  {{ passwordsMatch ? 'Passwords match' : 'Passwords do not match' }}
                </span>
              </div>

              <label class="mt-4 flex items-start gap-3 text-sm text-slate-500 dark:text-white/50">
                <input type="checkbox" v-model="agreeTerms"
                  class="mt-1 h-4 w-4 rounded border-slate-300 accent-red-600 dark:border-white/20" />
                <span class="leading-relaxed">
                  I agree to the <button type="button"
                    class="font-semibold text-red-600 hover:underline dark:text-red-400" @click="goToTerms">Terms of
                    Service</button>
                  and <button type="button" class="font-semibold text-red-600 hover:underline dark:text-red-400"
                    @click="goToPrivacy">Privacy Policy</button>.
                </span>
              </label>
              <p v-if="termsError" class="mt-2 text-xs text-red-500">{{ termsError }}</p>

              <button type="submit" :disabled="!agreeTerms || isSendingEmail"
                class="mt-5 flex items-center justify-center gap-2 rounded-2xl bg-red-600 py-3.5 font-bold text-white shadow-lg shadow-red-600/25 transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60 dark:shadow-red-950/40">
                <svg v-if="isSendingEmail" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                {{ isSendingEmail ? 'Sending email…' : 'Sign Up' }}
              </button>

              <p class="mt-6 text-center text-xs text-slate-400 dark:text-white/40">
                Already have an account?
                <span class="cursor-pointer font-semibold text-red-600 hover:underline dark:text-red-400"
                  @click="switchTo(false)">Log in</span>
              </p>
            </form>

          </div>
        </div>

        <!-- Guest emergency shortcut — for anyone who landed here just to
             get help fast, not to manage an account. Reuses the same
             guest request modal that already lives on the landing page
             (Hero.vue), via the open-guest-modal event it listens for —
             see requestAsGuest() below. -->
        <div
          class="w-full max-w-md rounded-2xl border-2 border-dashed border-red-200 bg-red-50/60 px-5 py-4 dark:border-red-500/25 dark:bg-red-500/5">
          <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-red-700 dark:text-red-300">
            <Icon icon="lucide:siren" width="15" />
            Need help right now?
          </p>
          <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-white/45">
            Don't wait to sign in — you can send an emergency request as a guest, no account required.
          </p>
          <button type="button" @click="requestAsGuest('instant')"
            class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 py-2.5 text-sm font-bold text-white shadow-md shadow-red-600/20 transition-colors hover:bg-red-700 dark:shadow-red-950/30">
            <Icon icon="lucide:siren" width="16" />
            Request Emergency as Guest
          </button>
          <button type="button" @click="requestAsGuest('standard')"
            class="mt-2 w-full text-center text-[0.7rem] font-semibold text-slate-500 transition-colors hover:text-red-600 hover:underline dark:text-white/40 dark:hover:text-red-300">
            Not urgent? File a standard request instead
          </button>
        </div>
      </div>
    </div>

    <!-- ═══════════ OTP MODAL ═══════════ -->
    <div v-if="showOtpModal"
      class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-950/60 backdrop-blur-sm px-4">
      <div
        class="w-full max-w-md rounded-3xl border border-slate-200/80 bg-base-100 p-8 shadow-2xl shadow-slate-300/40 dark:border-white/10 dark:bg-[#071829] dark:shadow-black/40">
        <div class="mb-6 text-center">
          <div
            class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-red-50 dark:bg-red-500/10">
            <span class="text-4xl">{{ otpIcon }}</span>
          </div>
          <h2 class="text-xl font-black tracking-tight text-slate-950 dark:text-white">{{ otpTitle }}</h2>
          <p class="text-sm text-slate-500 dark:text-[#a9c6e8]">
            {{ otpDescription }}<br />
            <span class="font-medium text-slate-800 dark:text-white break-all">{{ otpEmail }}</span>
          </p>
        </div>

        <input v-model="otp" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="••••••"
          class="mb-4 w-full rounded-xl border border-slate-200 bg-[#f8f3e8] py-4 text-center text-2xl font-semibold tracking-[0.5em] text-slate-800 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20 dark:border-white/10 dark:bg-white/5 dark:text-white" />

        <p class="mb-4 text-center text-sm text-slate-400 dark:text-white/40">
          Code expires in
          <span class="font-semibold text-slate-800 dark:text-white">{{ Math.floor(otpExpiresIn / 60) }}:{{
            String(otpExpiresIn % 60).padStart(2, '0') }}</span>
        </p>

        <button @click="verifyOtp" :disabled="otp.length !== 6"
          class="w-full rounded-2xl bg-red-600 py-3.5 font-bold text-white shadow-lg shadow-red-600/25 transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60 dark:shadow-red-950/40">
          {{ otpButtonText }}
        </button>

        <p class="mt-4 text-center text-xs text-slate-400 dark:text-white/40">
          Didn't receive the code?
          <span v-if="isSendingEmail" class="font-semibold text-slate-300 dark:text-white/20">Sending…</span>
          <span v-else class="font-semibold"
            :class="canResendOtp ? 'cursor-pointer text-red-600 hover:underline dark:text-red-400' : 'cursor-not-allowed text-slate-300 dark:text-white/20'"
            @click="resendOtp">
            Resend
          </span>
          <br />
          <span v-if="!canResendOtp && !isSendingEmail">
            Can resend after <span class="font-semibold text-slate-500 dark:text-white/50">{{ resendCooldown }}s</span>
          </span>
        </p>

        <button @click="handleExitOtpClick"
          class="mt-3 w-full rounded-2xl border-2 border-slate-200 py-2.5 text-sm font-semibold text-slate-500 transition-colors hover:border-slate-300 hover:bg-[#f3ecdd] dark:border-white/10 dark:text-white/50 dark:hover:border-white/20 dark:hover:bg-white/5">
          {{ otpCancelButtonText }}
        </button>
      </div>
    </div>

    <!-- ═══════════ FORGOT PASSWORD MODAL ═══════════ -->
    <div v-if="showForgotModal" class="fixed inset-0 z-[1000] flex items-center justify-center px-4">
      <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" @click="showForgotModal = false"></div>
      <div
        class="relative w-full max-w-sm rounded-3xl border border-slate-200/80 bg-base-100 p-8 shadow-2xl shadow-slate-300/40 dark:border-white/10 dark:bg-[#071829] dark:shadow-black/40"
        @click.stop>
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-50 dark:bg-red-500/10">
          <span class="text-3xl font-bold text-red-600 dark:text-red-400">?</span>
        </div>
        <h2 class="mb-3 text-center text-xl font-black tracking-tight text-slate-950 dark:text-white">Forgot password
        </h2>
        <p class="mb-6 text-center text-sm text-slate-500 dark:text-[#a9c6e8]">Enter your email and we'll send a
          verification code.</p>
        <input v-model="forgotEmail" type="email" placeholder="you@example.com"
          class="mb-4 w-full rounded-xl border border-slate-200 bg-[#f8f3e8] px-4 py-3 text-slate-800 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20 dark:border-white/10 dark:bg-white/5 dark:text-white" />
        <button @click="sendForgotOtp" :disabled="isSendingEmail"
          class="flex w-full items-center justify-center gap-2 rounded-2xl bg-red-600 py-3.5 font-bold text-white shadow-lg shadow-red-600/25 hover:bg-red-700 disabled:opacity-60 dark:shadow-red-950/40">
          {{ isSendingEmail ? 'Sending email…' : 'Send code' }}
        </button>
      </div>
    </div>

    <!-- ═══════════ RESET PASSWORD MODAL ═══════════ -->
    <div v-if="showResetPasswordModal" class="fixed inset-0 z-[1000] flex items-center justify-center px-4">
      <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" @click="showResetPasswordModal = false"></div>
      <div
        class="relative w-full max-w-md rounded-3xl border border-slate-200/80 bg-base-100 p-8 shadow-2xl shadow-slate-300/40 dark:border-white/10 dark:bg-[#071829] dark:shadow-black/40"
        @click.stop>
        <div class="mb-6 text-center">
          <div
            class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-red-50 dark:bg-red-500/10">
            <span class="text-4xl">🔐</span>
          </div>
          <h2 class="text-xl font-black tracking-tight text-slate-950 dark:text-white">Reset your password</h2>
          <p class="mt-2 text-sm text-slate-500 dark:text-[#a9c6e8]">Choose a strong password you haven't used before.
          </p>
        </div>

        <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-white/50">New password</label>
        <div class="relative mb-2">
          <input :type="showResetPassword ? 'text' : 'password'" v-model="resetPassword"
            placeholder="Enter new password"
            class="w-full rounded-xl border border-slate-200 bg-[#f8f3e8] px-4 py-3 pr-12 text-slate-800 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20 dark:border-white/10 dark:bg-white/5 dark:text-white" />
          <button type="button" @click="showResetPassword = !showResetPassword"
            class="absolute right-3 top-3 text-slate-400 hover:text-red-600 dark:text-white/40 dark:hover:text-red-400">
            <Icon :icon="showResetPassword ? 'line-md:watch' : 'line-md:watch-off'" width="22" height="22" />
          </button>
        </div>
        <div v-if="resetPassword.length > 0" class="mb-4">
          <div class="flex gap-1">
            <div v-for="i in 4" :key="i" class="h-1 flex-1 rounded-full transition-all duration-300" :class="i <= resetPasswordStrength
              ? (resetPasswordStrength <= 1 ? 'bg-red-500' : resetPasswordStrength === 2 ? 'bg-amber-500' : resetPasswordStrength === 3 ? 'bg-blue-500' : 'bg-emerald-500')
              : 'bg-slate-200 dark:bg-white/10'"></div>
          </div>
          <p class="mt-1 text-xs"
            :class="resetPasswordStrength <= 1 ? 'text-red-500' : resetPasswordStrength === 2 ? 'text-amber-500' : resetPasswordStrength === 3 ? 'text-blue-500' : 'text-emerald-500'">
            {{ resetPasswordStrengthText }}
          </p>
        </div>

        <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-white/50">Confirm password</label>
        <div class="relative">
          <input :type="showResetConfirmPassword ? 'text' : 'password'" v-model="resetConfirmPassword"
            placeholder="Re-enter new password"
            class="w-full rounded-xl border border-slate-200 bg-[#f8f3e8] px-4 py-3 pr-12 text-slate-800 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20 dark:border-white/10 dark:bg-white/5 dark:text-white" />
          <button type="button" @click="showResetConfirmPassword = !showResetConfirmPassword"
            class="absolute right-3 top-3 text-slate-400 hover:text-red-600 dark:text-white/40 dark:hover:text-red-400">
            <Icon :icon="showResetConfirmPassword ? 'line-md:watch' : 'line-md:watch-off'" width="22" height="22" />
          </button>
        </div>
        <div v-if="resetConfirmPassword.length > 0" class="mt-2 flex items-center gap-2">
          <Icon :icon="resetPasswordsMatch ? 'mdi:check-circle' : 'mdi:close-circle'" class="h-4 w-4"
            :class="resetPasswordsMatch ? 'text-emerald-500' : 'text-red-500'" />
          <span class="text-xs" :class="resetPasswordsMatch ? 'text-emerald-500' : 'text-red-500'">
            {{ resetPasswordsMatch ? 'Passwords match' : 'Passwords do not match' }}
          </span>
        </div>

        <button @click="resetPasswordSubmit" :disabled="!resetPasswordMeetsRequirements || !resetPasswordsMatch"
          class="mt-6 w-full rounded-2xl bg-red-600 py-3.5 font-bold text-white shadow-lg shadow-red-600/25 hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50 dark:shadow-red-950/40">
          Reset password
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { useRouter, useRoute } from 'vue-router'
import { useSession } from '@/composables/useSession'
import { useAlert } from '@/composables/useAlert'
import { useToast } from '@/composables/useToast'
import { consumePostLoginRedirect } from '@/utils/postLoginRedirect'
import api from '@/services/api'

const router = useRouter()
const route = useRoute()

const LOGO_SRC = '../../img/logoo-bg.jpg' // TODO: replace with actual logo path
const logoFailed = ref(false)

// ------------------------------------------------------------------
// Optional background photo — same "detect, don't assume" pattern as
// LOGO_SRC above. Leave AUTH_BG_SRC empty to render no background at
// all (just the siren-glow motif below). If a path is set, the <img>
// only stays mounted once it actually loads; a 404 clears it via the
// same @error → *Failed flip used for the logo, instead of leaving a
// broken-image icon sitting behind the form.
// ------------------------------------------------------------------
const AUTH_BG_SRC = '../../img/2.png' // TODO: set a real background photo path when available
const bgFailed = ref(false)
// ------------------------------------------------------------------
// Background siren lights — same motif as Hero.vue: 3 spots that
// flicker briefly then move to new random positions every 5s.
// ------------------------------------------------------------------
function randomSirenSpot(id) {
  return { id, x: 90 + Math.random() * 420, y: 90 + Math.random() * 220 }
}
const sirens = ref([randomSirenSpot(0), randomSirenSpot(1), randomSirenSpot(2)])
let sirenNextId = 3
let sirenTimer = null
const {
  user,
  fetchSession,
  login: sessionLogin,
  register: sessionRegister,
  dashboardPath,
} = useSession()

// ------------------------------------------------------------------
// Decorative panel copy. Static for now — same spirit as Hero.vue's
// `highlights` array.
// ------------------------------------------------------------------
const highlights = [
  { title: 'Report quickly', description: 'Submit essential details and a precise incident location.' },
  { title: 'Coordinate dispatch', description: 'Validate requests and assign suitable available ambulances.' },
  { title: 'Track response', description: 'Follow assignment, travel, arrival, and completion statuses.' },
  { title: 'Stay verified', description: 'Email verification and optional two-factor login keep your account secure.' },
]

// ------------------------------------------------------------------
// Toasts for status messages (success/error/info) that don't need a
// decision from the user; the alert modal is reserved for confirm()
// calls further down, where the user actually has to choose.
// ------------------------------------------------------------------
const toast = useToast()
const alert = useAlert()

function showSuccess(message, title) { toast.success(message, title || 'Success') }
function showError(message, title) { toast.error(message, title || 'Error') }
function showInfo(message, title) { toast.info(message, title || 'Info') }

// ------------------------------------------------------------------
// Mode: /login vs /register. Vue Router reuses this component
// instance across those two routes, so we watch route.name directly
// rather than relying only on onMounted — mirrors the other system's
// router-watch for the same reason.
// ------------------------------------------------------------------
const isActive = ref(route.name === 'Register' || route.path === '/register')
watch(
  () => route.name,
  () => { isActive.value = route.name === 'Register' || route.path === '/register' }
)
function switchTo(active) {
  isActive.value = active
  router.replace(active ? { name: 'Register' } : { name: 'Login' })
}

// ------------------------------------------------------------------
// Login state
// ------------------------------------------------------------------
const loginEmail = ref('')
const loginPassword = ref('')
const showLoginPassword = ref(false)
const isLoginLoading = ref(false)
const rememberDevice = ref(false)

// ------------------------------------------------------------------
// Brand wordmark — hovering "Dascare" reveals what it stands for
// (mirrors the interaction in AppHeader.vue)
// ------------------------------------------------------------------
const isDascareHovered = ref(false)

const REMEMBERED_EMAIL_KEY = 'dascare-remembered-email'

// ------------------------------------------------------------------
// Register state
// ------------------------------------------------------------------
const registerFirstName = ref('')
const registerLastName = ref('')
const registerEmail = ref('')
const registerPhone = ref('')
const registerPassword = ref('')
const registerConfirmPassword = ref('')
const showRegisterPassword = ref(false)
const showConfirmPassword = ref(false)
const agreeTerms = ref(false)
const termsError = ref('')
const isSendingEmail = ref(false)

const phoneValid = computed(() => {
  const normalized = registerPhone.value.replace(/[\s-]/g, '')
  return /^(\+639\d{9}|09\d{9})$/.test(normalized)
})

// Mirrors password_helpers.php exactly: 10+ chars, upper, lower,
// number, special character. Scoring below is cosmetic (the strength
// meter); resetPasswordMeetsRequirements / this same shape is what
// actually gates the submit button.
function passwordScore(pwd) {
  if (!pwd) return 0
  let s = 0
  if (pwd.length >= 10) s++
  if (pwd.length >= 14) s++
  if (/[a-z]/.test(pwd) && /[A-Z]/.test(pwd)) s++
  if (/\d/.test(pwd)) s++
  if (/[^A-Za-z0-9]/.test(pwd)) s++
  return Math.min(s, 4)
}
function passwordText(score) {
  if (score <= 1) return 'Weak password'
  if (score === 2) return 'Fair password'
  if (score === 3) return 'Good password'
  return 'Strong password'
}
function meetsBackendRules(pwd) {
  return pwd.length >= 10 && pwd.length <= 128 &&
    /[a-z]/.test(pwd) && /[A-Z]/.test(pwd) &&
    /\d/.test(pwd) && /[^A-Za-z0-9]/.test(pwd)
}

const passwordStrength = computed(() => passwordScore(registerPassword.value))
const passwordStrengthText = computed(() => passwordText(passwordStrength.value))
const passwordsMatch = computed(() =>
  registerPassword.value === registerConfirmPassword.value && registerConfirmPassword.value.length > 0
)

// ------------------------------------------------------------------
// OTP state — shared by register / forgot-password / login-2FA,
// same three contexts session.php's endpoints expect.
// ------------------------------------------------------------------
const otp = ref('')
const otpEmail = ref('')
const otpContext = ref('') // 'register' | 'forgot' | 'login_2fa'
const showOtpModal = ref(false)
const otpExpiresIn = ref(600)
const otpTimer = ref(null)
const canResendOtp = ref(false)
const resendCooldown = ref(60)
const hasTriggeredUnloadCleanup = ref(false)

const otpTitle = computed(() => {
  if (otpContext.value === 'forgot') return 'Verify your reset code'
  if (otpContext.value === 'login_2fa') return 'Verify your login'
  return 'Verify your email'
})
const otpDescription = computed(() => {
  if (otpContext.value === 'forgot') return 'Enter the password reset code sent to'
  if (otpContext.value === 'login_2fa') return 'Enter the login verification code sent to'
  return 'Enter the verification code sent to'
})
const otpButtonText = computed(() => {
  if (otpContext.value === 'forgot') return 'Verify reset code'
  if (otpContext.value === 'login_2fa') return 'Verify login'
  return 'Verify code'
})
const otpCancelButtonText = computed(() => {
  if (otpContext.value === 'forgot') return 'Cancel password reset'
  if (otpContext.value === 'login_2fa') return 'Cancel login'
  return 'Cancel registration'
})
const otpIcon = computed(() => {
  if (otpContext.value === 'forgot') return '🔐'
  if (otpContext.value === 'login_2fa') return '🛡️'
  return '✉️'
})
const exitConfirmTitle = computed(() => {
  if (otpContext.value === 'forgot') return 'Cancel password reset?'
  if (otpContext.value === 'login_2fa') return 'Cancel login verification?'
  return 'Cancel registration?'
})
const exitConfirmMessage = computed(() => {
  if (otpContext.value === 'forgot') return "If you exit now, your password reset will be cancelled and you'll need to request a new code."
  if (otpContext.value === 'login_2fa') return "If you exit now, your login verification will be cancelled and you'll need to log in again."
  return 'If you exit now, your account will not be created and all progress will be lost.'
})

function cleanupOtpState() {
  showOtpModal.value = false
  alert.close()
  otp.value = ''
  otpEmail.value = ''
  otpContext.value = ''
  hasTriggeredUnloadCleanup.value = false
  if (otpTimer.value) { clearInterval(otpTimer.value); otpTimer.value = null }
  otpExpiresIn.value = 600
  resendCooldown.value = 60
  canResendOtp.value = false
}

function resetOtpTimerUiOnly() {
  if (otpTimer.value) clearInterval(otpTimer.value)
  otpExpiresIn.value = 600
  resendCooldown.value = 60
  canResendOtp.value = false

  otpTimer.value = setInterval(() => {
    if (!showOtpModal.value) { clearInterval(otpTimer.value); otpTimer.value = null; return }
    otpExpiresIn.value--
    if (resendCooldown.value > 0) {
      resendCooldown.value--
      if (resendCooldown.value === 0) canResendOtp.value = true
    }
    if (otpExpiresIn.value <= 0) {
      clearInterval(otpTimer.value)
      otpTimer.value = null
      otpExpiresIn.value = 0
      if (showOtpModal.value) showError('Your verification code has expired. Please resend a new one.', 'Code expired')
    }
  }, 1000)
}

// Warn on tab-close mid-registration, and best-effort clean up the
// unverified account via cancel_registration.php — same contract the
// other system relies on.
function shouldProtectRegistrationOnUnload() {
  return showOtpModal.value && otpContext.value === 'register' && !!otpEmail.value
}
function sendCancelRegistrationBeacon(email) {
  if (!email || hasTriggeredUnloadCleanup.value) return
  hasTriggeredUnloadCleanup.value = true
  try {
    // navigator.sendBeacon needs a URL string, not an axios call, so this
    // is the one spot that can't go through the `api` instance directly —
    // it borrows api's configured baseURL instead of hardcoding one.
    const url = `${api.defaults.baseURL}/auth/cancel_registration.php`
    const payload = JSON.stringify({ email })
    if (navigator.sendBeacon) {
      navigator.sendBeacon(url, new Blob([payload], { type: 'application/json' }))
    } else {
      fetch(url, { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json' }, body: payload, keepalive: true }).catch(() => { })
    }
  } catch (err) {
    console.warn('Unable to send cancel-registration beacon.', err)
  }
}
async function cancelPendingRegistration() {
  const email = otpEmail.value
  if (!email || hasTriggeredUnloadCleanup.value) return
  hasTriggeredUnloadCleanup.value = true
  try {
    await api.post('/auth/cancel_registration.php', { email })
    showInfo('Your account was not created.', 'Registration cancelled')
  } catch (err) {
    hasTriggeredUnloadCleanup.value = false
  }
}
function handleBeforeUnload(event) {
  if (!shouldProtectRegistrationOnUnload()) return
  event.preventDefault()
  event.returnValue = ''
}
function handlePageHide() {
  if (!shouldProtectRegistrationOnUnload()) return
  sendCancelRegistrationBeacon(otpEmail.value)
}

// ------------------------------------------------------------------
// Forgot / reset password state
// ------------------------------------------------------------------
const showForgotModal = ref(false)
const forgotEmail = ref('')
const showResetPasswordModal = ref(false)
const resetPassword = ref('')
const resetConfirmPassword = ref('')
const showResetPassword = ref(false)
const showResetConfirmPassword = ref(false)

const resetPasswordStrength = computed(() => passwordScore(resetPassword.value))
const resetPasswordStrengthText = computed(() => passwordText(resetPasswordStrength.value))
const resetPasswordsMatch = computed(() =>
  resetPassword.value === resetConfirmPassword.value && resetConfirmPassword.value.length > 0
)
const resetPasswordMeetsRequirements = computed(() => meetsBackendRules(resetPassword.value))

function openForgotPassword() {
  showForgotModal.value = true
}

async function sendForgotOtp() {
  if (!forgotEmail.value) { showError('Please enter your email', 'Error'); return }
  try {
    isSendingEmail.value = true
    const res = await api.post('/auth/forgot_password.php', { email: forgotEmail.value })
    if (!res.data.success) { showError(res.data.message || 'Unable to send code', 'Error'); return }

    otpEmail.value = forgotEmail.value
    otpContext.value = 'forgot'
    otp.value = ''
    showForgotModal.value = false
    showOtpModal.value = true
    resetOtpTimerUiOnly()
    showSuccess('We sent a password reset code to your email.', 'Verification sent')
  } catch (err) {
    showError(err?.response?.data?.message || 'Email not found or unable to send code', 'Error')
  } finally {
    isSendingEmail.value = false
  }
}

async function resetPasswordSubmit() {
  if (!resetPasswordMeetsRequirements.value) {
    showError('Password must be 10+ characters with upper, lower, a number, and a special character.', 'Password too weak')
    return
  }
  if (!resetPasswordsMatch.value) { showError('Passwords do not match', 'Error'); return }

  try {
    const res = await api.post('/auth/reset_password.php', {
      email: otpEmail.value,
      password: resetPassword.value,
    })
    if (res.data?.success === false) { showError(res.data.message || 'Unable to reset password', 'Error'); return }

    showResetPasswordModal.value = false
    resetPassword.value = ''
    resetConfirmPassword.value = ''
    showSuccess('You can now log in with your new password.', 'Password reset')
    switchTo(false)
  } catch (err) {
    showError(err?.response?.data?.message || 'Something went wrong while resetting your password.', 'Error')
  }
}

// ------------------------------------------------------------------
// Guest emergency shortcut — return to the landing page and let Hero.vue
// open the requested guest form after it has mounted.
// ------------------------------------------------------------------
async function requestAsGuest(tab = 'instant') {
  const requestedTab = tab === 'standard' ? 'standard' : 'instant'

  // sessionStorage makes the hand-off reliable even when Hero.vue has not
  // mounted yet by the time navigation finishes.
  sessionStorage.setItem('dascare:open-guest-modal', requestedTab)
  await router.push('/')

  // Also notify an already-mounted Hero immediately.
  window.dispatchEvent(new CustomEvent('open-guest-modal', {
    detail: requestedTab,
  }))
}

// ------------------------------------------------------------------
// Terms / privacy — open in a new tab so typed fields survive.
// ------------------------------------------------------------------
function goToTerms() {
  window.open(router.resolve({ path: '/terms' }).href, '_blank', 'noopener')
}
function goToPrivacy() {
  window.open(router.resolve({ path: '/privacy' }).href, '_blank', 'noopener')
}

// ------------------------------------------------------------------
// Register flow
// ------------------------------------------------------------------
async function openRegisterConfirm() {
  if (!agreeTerms.value) {
    termsError.value = 'Please accept the Terms of Service before creating an account.'
    showError(termsError.value, 'Terms required')
    return
  }
  termsError.value = ''

  if (!phoneValid.value) { showError('Please enter a valid PH mobile number.', 'Invalid phone'); return }
  if (registerPassword.value !== registerConfirmPassword.value) { showError('Passwords do not match', 'Registration failed'); return }
  if (!meetsBackendRules(registerPassword.value)) {
    showError('Password must be 10+ characters with upper, lower, a number, and a special character.', 'Password too weak')
    return
  }

  const summary = `Name: ${registerFirstName.value} ${registerLastName.value}\nEmail: ${registerEmail.value}\nMobile: ${registerPhone.value}`
  const confirmed = await alert.confirm(summary, 'Confirm your details')
  if (confirmed) await register()
}

async function register() {
  try {
    isSendingEmail.value = true
    const data = await sessionRegister({
      first_name: registerFirstName.value,
      last_name: registerLastName.value,
      email: registerEmail.value,
      phone: registerPhone.value,
      password: registerPassword.value,
    })

    if (data.success) {
      otpEmail.value = registerEmail.value
      otp.value = ''
      otpContext.value = 'register'
      showOtpModal.value = true
      resetOtpTimerUiOnly()
      showSuccess('We sent a 6-digit verification code to your email.', 'Verify your email')
    } else {
      showError(data.message, 'Registration failed')
    }
  } catch (err) {
    showError(err?.response?.data?.message || 'Something went wrong', 'Server error')
  } finally {
    isSendingEmail.value = false
  }
}

// ------------------------------------------------------------------
// Login flow
// ------------------------------------------------------------------
async function login() {
  try {
    isLoginLoading.value = true

    if (rememberDevice.value) {
      localStorage.setItem(REMEMBERED_EMAIL_KEY, loginEmail.value)
    } else {
      localStorage.removeItem(REMEMBERED_EMAIL_KEY)
    }

    const data = await sessionLogin({ email: loginEmail.value, password: loginPassword.value })

    if (data.success && data.requires_2fa) {
      otpEmail.value = loginEmail.value
      otp.value = ''
      otpContext.value = 'login_2fa'
      showOtpModal.value = true
      resetOtpTimerUiOnly()
      showInfo('Your account has two-factor authentication enabled — check your email for a code.', 'Two-factor authentication')
      return
    }

    if (data.success) {
      // sessionLogin() already refreshed the session for this
      // (non-2FA) path, so `user` is populated — no extra fetch needed.
      onAuthenticated(data.user, 'Login successful')
    } else {
      showError(data.message, 'Login failed')
    }
  } catch (err) {
    showError(err?.response?.data?.message || 'Something went wrong', 'Server error')
  } finally {
    isLoginLoading.value = false
  }
}

// Every fixed account type has a live dashboard route. A `redirect`
// query param (set by the router's requiresAuth guard) takes priority over
// the role-default landing spot when the target is still appropriate.
function onAuthenticated(authedUser, alertTitleText) {
  showSuccess(`Welcome ${authedUser.name}!`, alertTitleText)
  const queryRedirect = typeof route.query.redirect === 'string' ? route.query.redirect : null
  const redirect = consumePostLoginRedirect() || queryRedirect
  router.push(redirect || dashboardPath(authedUser.level))
}

// ------------------------------------------------------------------
// OTP verification (register / forgot / login_2fa)
// ------------------------------------------------------------------
async function verifyOtp() {
  if (!otp.value || otp.value.length < 6) { showError('Please enter the 6-digit verification code.', 'Invalid code'); return }

  try {
    if (otpContext.value === 'login_2fa') {
      const res = await api.post('/auth/verify_login_otp.php', { otp: otp.value })
      if (res.data.success) {
        const authedUser = res.data.user
        cleanupOtpState()
        // This path establishes the session itself (unlike sessionLogin(),
        // which only auto-refreshes on the non-2FA path), so pull the
        // fresh session in explicitly before redirecting.
        await fetchSession(true)
        onAuthenticated(authedUser, 'Login verified')
        return
      }
      showError(res.data.message || 'The verification code is incorrect or expired.', 'Invalid code')
      return
    }

    const res = await api.post('/auth/verify_otp.php', {
      email: otpEmail.value,
      otp: otp.value,
      context: otpContext.value,
    })

    if (res.data.success) {
      showOtpModal.value = false

      if (otpContext.value === 'forgot') {
        if (otpTimer.value) { clearInterval(otpTimer.value); otpTimer.value = null }
        otp.value = ''
        showResetPasswordModal.value = true
        return
      }

      cleanupOtpState()
      await nextTick()
      showSuccess('You can now log in.', 'Email verified')
      switchTo(false)
    } else {
      showError(res.data.message || 'The verification code is incorrect or expired.', 'Invalid code')
    }
  } catch (err) {
    showError(err?.response?.data?.message || 'Something went wrong while verifying your code.', 'Verification failed')
  }
}

async function resendOtp() {
  if (!canResendOtp.value) return
  try {
    isSendingEmail.value = true

    if (otpContext.value === 'login_2fa') {
      const res = await api.post('/auth/resend_login_otp.php', {})
      if (!res.data.success) { showError(res.data.message || 'Unable to resend code.', 'Resend failed'); return }
      showSuccess('A new login verification code has been sent.', 'OTP resent')
      resetOtpTimerUiOnly()
      return
    }

    const res = otpContext.value === 'forgot'
      ? await api.post('/auth/forgot_password.php', { email: otpEmail.value })
      : await api.post('/auth/resend_otp.php', { email: otpEmail.value })

    if (!res.data.success) { showError(res.data.message || 'Unable to resend code.', 'Resend failed'); return }
    showSuccess('A new verification code has been sent to your email.', 'OTP resent')
    resetOtpTimerUiOnly()
  } catch (err) {
    showError(err?.response?.data?.message || 'Unable to resend verification code.', 'Resend failed')
  } finally {
    isSendingEmail.value = false
  }
}

async function exitOtp() {
  const currentContext = otpContext.value
  try {
    if (currentContext === 'register' && otpEmail.value) {
      await cancelPendingRegistration()
    } else if (currentContext === 'forgot') {
      showInfo('Password reset cancelled.', 'Cancelled')
    } else if (currentContext === 'login_2fa') {
      showInfo('Login verification cancelled.', 'Cancelled')
    }
  } finally {
    cleanupOtpState()
    if (currentContext !== 'login_2fa') switchTo(false)
  }
}
async function handleExitOtpClick() {
  const confirmed = await alert.confirm(exitConfirmMessage.value, exitConfirmTitle.value)
  if (confirmed) exitOtp()
}

// ------------------------------------------------------------------
// Lifecycle
// ------------------------------------------------------------------
onMounted(() => {
  window.addEventListener('beforeunload', handleBeforeUnload)
  window.addEventListener('pagehide', handlePageHide)

  const remembered = localStorage.getItem(REMEMBERED_EMAIL_KEY)
  if (remembered) {
    loginEmail.value = remembered
    rememberDevice.value = true
  }

  sirenTimer = setInterval(() => {
    sirens.value = [randomSirenSpot(sirenNextId++), randomSirenSpot(sirenNextId++), randomSirenSpot(sirenNextId++)]
  }, 5000)
})
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', handleBeforeUnload)
  window.removeEventListener('pagehide', handlePageHide)
  if (otpTimer.value) { clearInterval(otpTimer.value); otpTimer.value = null }
  clearInterval(sirenTimer)
})
</script>

<style scoped>
.pulse-scroll {
  transform-box: fill-box;
  animation: dascare-pulse-scroll 3s linear infinite;
}

.siren-flicker-blue {
  animation: dascare-siren-flicker 2.2s ease-in-out 1;
}

.siren-flicker-red {
  animation: dascare-siren-flicker 2.2s ease-in-out 1;
  animation-delay: 0.3s;
}

@keyframes dascare-pulse-scroll {
  0% {
    transform: translateX(0);
  }

  100% {
    transform: translateX(-50%);
  }
}

@keyframes dascare-siren-flicker {
  0% {
    opacity: 0;
  }

  25% {
    opacity: 0.5;
  }

  50% {
    opacity: 0.15;
  }

  75% {
    opacity: 0.4;
  }

  100% {
    opacity: 0;
  }
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