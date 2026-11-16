<template>
  <div class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
    <header class="text-center">
      <p class="text-[0.7rem] font-black uppercase tracking-[0.2em] text-red-600 dark:text-red-300">Sponsors</p>
      <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-white">Become a DASCARE sponsor</h1>
      <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-600 dark:text-white/60">Sponsors help pay for the servers, maps and SMS that keep emergency response free in Dasmariñas. In return your name (and logo, for partners) is shown on our website and app — never inside an emergency screen.</p>
    </header>

    <section class="mt-8 rounded-3xl border border-base-300 bg-base-100 p-6 dark:border-white/10 dark:bg-[#0d2943]">
      <h2 class="text-sm font-black text-slate-900 dark:text-white">Sponsor policy</h2>
      <ul class="mt-3 list-disc space-y-1.5 pl-5 text-xs leading-5 text-slate-600 dark:text-white/60">
        <li>Sponsors are clearly labelled <b>"Sponsor"</b>. They are shown only on the landing page, the Support page and the app's About/Support screens — <b>never</b> on SOS, tracking or any emergency screen, and never as pop-ups.</li>
        <li>Sponsors get <b>no user data</b> — no names, phone numbers, locations or medical information — and no targeted promotion.</li>
        <li>Accepted: healthcare providers, pharmacies, health insurers/HMOs, first-aid and safety training, schools, local businesses, civic groups and government offices.</li>
        <li>Not accepted: political parties or candidates, gambling, alcohol, tobacco/vape, lending apps, supplements or products making health claims, and anything misleading or that imitates an emergency alert.</li>
        <li>Every sponsor is verified by the DASCARE admin before being shown, and can be paused or removed at any time. Sponsorship does not change how requests are dispatched.</li>
      </ul>
    </section>

    <form class="mt-6 space-y-4 rounded-3xl border border-base-300 bg-base-100 p-6 shadow-sm dark:border-white/10 dark:bg-[#0d2943]" @submit.prevent="submit">
      <div v-if="done" class="rounded-2xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200">{{ done }}</div>
      <template v-else>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block text-xs font-bold text-slate-500">Organization name<input v-model.trim="f.organization_name" maxlength="120" class="field" /></label>
          <label class="block text-xs font-bold text-slate-500">Contact person<input v-model.trim="f.contact_name" maxlength="120" autocomplete="name" class="field" /></label>
          <label class="block text-xs font-bold text-slate-500">Email<input v-model.trim="f.email" type="email" maxlength="190" autocomplete="email" class="field" /></label>
          <label class="block text-xs font-bold text-slate-500">Mobile number <span class="font-normal">(optional)</span><input v-model.trim="f.phone" maxlength="13" placeholder="09123456789" class="field" /></label>
          <label class="block text-xs font-bold text-slate-500">Category
            <select v-model="f.category" class="field"><option value="" disabled>Select…</option><option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option></select>
          </label>
          <label class="block text-xs font-bold text-slate-500">Website <span class="font-normal">(optional)</span><input v-model.trim="f.website_url" maxlength="255" placeholder="https://" class="field" /></label>
        </div>
        <label class="block text-xs font-bold text-slate-500">Message <span class="font-normal">(optional)</span><textarea v-model.trim="f.message" rows="3" maxlength="1000" class="field" placeholder="How would you like to support DASCARE?"></textarea></label>
        <label class="flex items-start gap-2 text-xs text-slate-600 dark:text-white/60"><input v-model="f.agreed_policy" type="checkbox" class="checkbox checkbox-xs mt-0.5" /> I have read the sponsor policy above and my organization qualifies.</label>
        <p v-if="error" class="text-xs font-bold text-red-600">{{ error }}</p>
        <button type="submit" :disabled="sending" class="w-full rounded-2xl bg-red-600 px-5 py-3 text-sm font-black text-white disabled:opacity-50">{{ sending ? 'Sending…' : 'Send inquiry' }}</button>
      </template>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500"><RouterLink to="/support" class="font-bold text-red-600 hover:underline dark:text-red-400">← Back to Support DASCARE</RouterLink></p>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { fetchSupportPublic, sendSponsorInquiry } from '@/services/support'
import { isPersonName, looksLikeText } from '@/utils/validation'

const categories = ref({})
const sending = ref(false)
const error = ref('')
const done = ref('')
const f = reactive({ organization_name: '', contact_name: '', email: '', phone: '', category: '', website_url: '', message: '', agreed_policy: false })

onMounted(async () => { try { categories.value = (await fetchSupportPublic()).sponsor_categories || {} } catch { /* form still works without labels */ } })

async function submit() {
  error.value = ''
  if (f.organization_name.length < 2 || !looksLikeText(f.organization_name, 2)) { error.value = 'Enter your organization name.'; return }
  if (!isPersonName(f.contact_name)) { error.value = "Enter the contact person's name (letters only)."; return }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email)) { error.value = 'Enter a valid email address.'; return }
  if (f.phone && !/^(\+63|0)9\d{9}$/.test(f.phone)) { error.value = 'Enter a valid Philippine mobile number, or leave it blank.'; return }
  if (!f.category) { error.value = 'Choose the category that fits your organization.'; return }
  if (f.website_url && !/^https:\/\/\S+\.\S+$/i.test(f.website_url)) { error.value = 'The website must start with https://'; return }
  if (f.message && !looksLikeText(f.message, 8)) { error.value = 'The message must be readable text.'; return }
  if (!f.agreed_policy) { error.value = 'Please confirm you have read the sponsor policy.'; return }
  sending.value = true
  try { done.value = (await sendSponsorInquiry({ ...f })).message } catch (e) { error.value = e?.response?.data?.message || 'Unable to send your inquiry. Please try again.' } finally { sending.value = false }
}
</script>

<style scoped>
.field { margin-top: .375rem; width: 100%; border-radius: .75rem; border: 1px solid hsl(var(--bc) / .12); background: transparent; padding: .625rem .75rem; font-size: .875rem; color: inherit; }
.field:focus { outline: none; border-color: #dc2626; }
</style>
