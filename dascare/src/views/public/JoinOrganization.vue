<template>
  <section class="relative min-h-screen overflow-hidden bg-slate-100 px-4 py-10 dark:bg-[#050e1a] sm:px-6 sm:py-14">
    <div class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-30" style="background: radial-gradient(ellipse at top left, rgba(25,118,210,.09), transparent 50%), radial-gradient(ellipse at bottom right, rgba(220,38,38,.07), transparent 48%);"></div>
    <div class="relative mx-auto max-w-xl">
      <RouterLink to="/" class="mb-5 inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-[#1976D2] dark:text-white/45"><Icon icon="lucide:arrow-left" width="15" /> DASCARE public site</RouterLink>

      <div class="overflow-hidden rounded-[30px] border border-slate-200 bg-white shadow-xl dark:border-white/10 dark:bg-[#071829]">
        <div class="h-2 bg-gradient-to-r from-red-600 via-[#1976D2] to-red-600"></div>
        <div v-if="loading" class="p-10 text-center"><Icon icon="lucide:loader-circle" width="28" class="mx-auto animate-spin text-[#1976D2]" /><p class="mt-3 text-sm font-bold text-slate-500 dark:text-white/45">Checking your organization invitation…</p></div>

        <div v-else-if="errorMessage" class="p-7 text-center sm:p-9">
          <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon icon="lucide:mail-x" width="28" /></span>
          <h1 class="mt-5 text-2xl font-black text-slate-950 dark:text-white">Invitation unavailable</h1>
          <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-white/45">{{ errorMessage }}</p>
          <RouterLink to="/login" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-[#1976D2] px-5 py-3 text-sm font-bold text-white">Go to Login <Icon icon="lucide:arrow-right" width="15" /></RouterLink>
        </div>

        <div v-else-if="complete" class="p-7 text-center sm:p-9">
          <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"><Icon icon="lucide:badge-check" width="28" /></span>
          <p class="mt-5 text-[0.68rem] font-black uppercase tracking-[0.14em] text-emerald-600 dark:text-emerald-300">Account activated</p>
          <h1 class="mt-2 text-2xl font-black text-slate-950 dark:text-white">Welcome to {{ invitation.organization_name }}</h1>
          <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-white/45">Your organization employee account is ready. Sign in using <strong class="text-slate-700 dark:text-white/70">{{ invitation.email }}</strong> and the password you just created.</p>
          <RouterLink to="/login" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700">Continue to Login <Icon icon="lucide:arrow-right" width="15" /></RouterLink>
        </div>

        <form v-else class="p-6 sm:p-8" @submit.prevent="acceptInvite">
          <div class="flex items-start gap-4">
            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-[#1976D2]/10 text-[#1976D2] dark:text-[#7fb3ec]"><Icon icon="lucide:building-2" width="24" /></span>
            <div><p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-[#1976D2] dark:text-[#7fb3ec]">Organization invitation</p><h1 class="mt-1 text-2xl font-black text-slate-950 dark:text-white">Join {{ invitation.organization_name }}</h1><p class="mt-1 text-sm leading-6 text-slate-500 dark:text-white/45">You were invited to DASCARE as <strong class="text-slate-700 dark:text-white/65">{{ invitation.role_name }}</strong>.</p></div>
          </div>

          <div class="mt-6 grid gap-3 rounded-2xl bg-slate-50 p-4 dark:bg-white/[0.035] sm:grid-cols-2">
            <Info label="Employee" :value="`${invitation.first_name} ${invitation.last_name}`" />
            <Info label="Email" :value="invitation.email" />
            <Info label="Mobile" :value="invitation.phone" />
            <Info label="Invitation expires" :value="formatDate(invitation.expires_at)" />
          </div>

          <div class="mt-6 space-y-4">
            <label class="block"><span class="mb-1.5 block text-[0.68rem] font-black uppercase tracking-wide text-slate-400 dark:text-white/30">Create password</span><input v-model="password" class="field" type="password" autocomplete="new-password" /></label>
            <label class="block"><span class="mb-1.5 block text-[0.68rem] font-black uppercase tracking-wide text-slate-400 dark:text-white/30">Confirm password</span><input v-model="passwordConfirmation" class="field" type="password" autocomplete="new-password" /></label>
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-xs leading-5 text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-200"><div class="flex gap-2"><Icon icon="lucide:shield-check" width="15" class="mt-0.5 shrink-0" /><span>Use at least 10 characters with uppercase, lowercase, a number, and a special character. Organization employees do not need citizen KYC.</span></div></div>
          </div>

          <p v-if="formError" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">{{ formError }}</p>
          <button type="submit" class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 py-3 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50" :disabled="submitting"><Icon :icon="submitting ? 'lucide:loader-circle' : 'lucide:user-check'" width="16" :class="submitting ? 'animate-spin' : ''" />{{ submitting ? 'Activating account…' : 'Accept Invitation & Create Account' }}</button>
        </form>
      </div>
    </div>
  </section>
</template>

<script setup>
import { defineComponent, h, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Icon } from '@iconify/vue'
import { acceptOrganizationInvitation, previewOrganizationInvitation } from '@/services/organizationManagement'

const route=useRoute(); const token=String(route.query.token||'')
const loading=ref(true), errorMessage=ref(''), invitation=ref(null), password=ref(''), passwordConfirmation=ref(''), formError=ref(''), submitting=ref(false), complete=ref(false)
const Info=defineComponent({props:{label:String,value:String},setup(p){return()=>h('div',{},[h('p',{class:'text-[0.62rem] font-black uppercase tracking-wide text-slate-400 dark:text-white/25'},p.label),h('p',{class:'mt-1 text-xs font-bold text-slate-700 dark:text-white/60'},p.value||'—')])}})
const formatDate=v=>v?new Date(v.replace(' ','T')).toLocaleString([], {dateStyle:'medium',timeStyle:'short'}):'—'
async function load(){ if(!token){errorMessage.value='This invitation link is missing its secure token.';loading.value=false;return} try{const d=await previewOrganizationInvitation(token);invitation.value=d.invitation}catch(e){errorMessage.value=e?.response?.data?.message||'This invitation could not be loaded.'}finally{loading.value=false} }
async function acceptInvite(){formError.value='';if(password.value!==passwordConfirmation.value){formError.value='Passwords do not match.';return}submitting.value=true;try{await acceptOrganizationInvitation({token,password:password.value,password_confirmation:passwordConfirmation.value});complete.value=true}catch(e){formError.value=e?.response?.data?.message||'Could not activate this organization account.'}finally{submitting.value=false}}
onMounted(load)
</script>

<style scoped>
.field {
  width: 100%;
  border-radius: 0.75rem;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  padding: 0.75rem 0.875rem;
  font-size: 0.875rem;
  line-height: 1.25rem;
  color: #1e293b;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
}
.field:focus { border-color: #1976d2; box-shadow: 0 0 0 2px rgba(25,118,210,.10); }
:global([data-theme="dark"]) .field { border-color: rgba(255,255,255,.10); background: #0a2038; color: #fff; }
</style>
