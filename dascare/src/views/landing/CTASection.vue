<template>
  <section class="bg-base-100 px-5 py-20 transition-colors dark:bg-[#050e1a]">
    <div class="mx-auto max-w-7xl">
      <div
        class="relative flex flex-col items-start gap-8 overflow-hidden rounded-3xl bg-gradient-to-br from-[#0F2A43] to-[#123a5e] px-8 py-12 shadow-2xl shadow-slate-900/10 sm:px-12 lg:flex-row lg:items-center lg:justify-between dark:from-[#0a2038] dark:to-[#0F2A43] dark:shadow-black/40"
      >
        <!-- Signal accent bar, echoing the header. -->
        <div
          class="pointer-events-none absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-[#0F2A43] via-[#1976D2] to-[#0F2A43]"
        ></div>

        <div class="relative max-w-xl">
          <div class="inline-flex items-center gap-2.5 border-l-2 border-emerald-400 pl-3">
            <span
              class="inline-flex items-center gap-2 font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-[#7fb3ec]"
            >
              <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"></span>
              System ready
            </span>
          </div>

          <h2 class="mt-4 text-3xl font-black tracking-tight text-white sm:text-4xl">
            Ready to request help or access your account?
          </h2>

          <p class="mt-4 text-lg leading-7 text-[#a9c6e8]">
            Request emergency assistance without an account, or log in to track requests
            and manage your DASCARE account.
          </p>
        </div>

        <div class="relative grid w-full gap-3 sm:grid-cols-2 lg:w-auto lg:min-w-[430px]">
          <button
            type="button"
            @click="openGuestRequest('instant')"
            class="group flex items-center gap-3 rounded-2xl bg-red-600 px-5 py-4 text-left shadow-lg shadow-black/20 transition-colors hover:bg-red-700"
          >
            <Icon icon="lucide:siren" width="24" class="shrink-0 text-white" />
            <span>
              <span class="block text-base font-bold text-white">Instant rescue</span>
              <span class="block text-xs text-red-100/90">
                Immediate danger — fastest response
              </span>
            </span>
          </button>

          <button
            type="button"
            @click="openGuestRequest('standard')"
            class="flex items-center gap-3 rounded-2xl border border-white/20 bg-white/5 px-5 py-4 text-left transition-colors hover:border-white/35 hover:bg-white/10"
          >
            <Icon icon="lucide:clipboard-list" width="24" class="shrink-0 text-white/65" />
            <span>
              <span class="block text-base font-bold text-white">Standard request</span>
              <span class="block text-xs text-white/55">Not urgent — routine dispatch</span>
            </span>
          </button>

          <div class="grid gap-3 sm:col-span-2 sm:grid-cols-2">
            <RouterLink
              to="/login"
              class="flex items-center justify-center gap-2 rounded-xl border border-white/20 px-6 py-3 font-semibold text-white transition-colors hover:bg-white/10"
            >
              <Icon icon="lucide:log-in" width="19" />
              Login
            </RouterLink>

            <RouterLink
              to="/register"
              class="flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3 font-semibold text-[#0F2A43] transition-colors hover:bg-slate-100"
            >
              <Icon icon="lucide:user-plus" width="19" />
              Register
            </RouterLink>
          </div>

          <RouterLink
            to="/apply/organization"
            class="sm:col-span-2 flex items-center justify-between gap-3 rounded-xl border border-[#7fb3ec]/35 bg-[#1976D2]/10 px-5 py-3.5 text-left transition-colors hover:border-[#7fb3ec]/55 hover:bg-[#1976D2]/20"
          >
            <span class="flex items-center gap-3">
              <Icon icon="lucide:building-2" width="20" class="text-[#7fb3ec]" />
              <span>
                <span class="block text-sm font-bold text-white">Apply as a rescue organization</span>
                <span class="block text-xs text-white/50">For ambulance, rescue, hospital, and LGU service providers</span>
              </span>
            </span>
            <Icon icon="lucide:arrow-right" width="17" class="shrink-0 text-[#7fb3ec]" />
          </RouterLink>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { Icon } from '@iconify/vue'

function openGuestRequest(type) {
  window.dispatchEvent(
    new CustomEvent('open-guest-modal', {
      detail: type === 'standard' ? 'standard' : 'instant'
    })
  )

  window.scrollTo({
    top: 0,
    behavior: 'smooth'
  })
}
</script>
