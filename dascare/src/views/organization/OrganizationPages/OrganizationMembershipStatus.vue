<template>
  <section class="grid min-h-screen place-items-center bg-base-200 px-4 py-10 dark:bg-[#081b2e]">
    <div class="w-full max-w-xl rounded-[30px] border border-base-300 bg-base-100 p-7 text-center shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-9">
      <span class="mx-auto grid h-16 w-16 place-items-center rounded-3xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300"><Icon :icon="terminated ? 'lucide:user-x' : 'lucide:pause-circle'" width="28"/></span>
      <p class="mt-5 text-[.68rem] font-black uppercase tracking-[.14em] text-amber-600 dark:text-amber-300">Organization access</p>
      <h1 class="mt-2 text-2xl font-black text-slate-950 dark:text-white">{{ terminated ? 'Membership terminated' : 'Membership access paused' }}</h1>
      <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-slate-500 dark:text-white/45">{{ terminated ? 'Your organization membership is no longer active. Contact your Organization Admin if you believe this is incorrect.' : 'Your Organization Admin has temporarily paused your workspace access. Contact them for reactivation.' }}</p>
      <div class="mt-6 rounded-2xl border border-base-300 bg-base-200/60 p-4 text-left dark:border-white/10 dark:bg-white/[.025]"><p class="text-[.64rem] font-black uppercase tracking-wide text-slate-400">Organization</p><p class="mt-1 text-sm font-black text-slate-800 dark:text-white/70">{{ organization?.name || 'DASCARE organization' }}</p><p class="mt-2 text-xs text-slate-400">Membership status: <strong class="capitalize text-slate-600 dark:text-white/55">{{ organization?.membershipStatus || 'inactive' }}</strong></p></div>
      <button class="mt-6 inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-red-700" @click="signOut"><Icon icon="lucide:log-out" width="16"/>Sign out</button>
    </div>
  </section>
</template>
<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { Icon } from '@iconify/vue'
import { useSession } from '@/composables/useSession'
const router=useRouter(); const { organization, logout }=useSession(); const terminated=computed(()=>organization.value?.membershipStatus==='terminated')
async function signOut(){await logout();router.replace('/login')}
</script>
