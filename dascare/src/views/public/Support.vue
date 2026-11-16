<template>
  <div class="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6 sm:py-14">
    <!-- Result of a payment (PayMongo sends the donor back here) -->
    <div v-if="banner" class="mb-6 rounded-3xl border p-5" :class="banner.tone === 'ok' ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/25 dark:bg-emerald-500/10' : banner.tone === 'wait' ? 'border-amber-200 bg-amber-50 dark:border-amber-500/25 dark:bg-amber-500/10' : 'border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/5'" role="status">
      <p class="flex items-center gap-2 text-sm font-black" :class="banner.tone === 'ok' ? 'text-emerald-800 dark:text-emerald-200' : banner.tone === 'wait' ? 'text-amber-800 dark:text-amber-200' : 'text-slate-700 dark:text-white/70'">
        <Icon :icon="banner.tone === 'ok' ? 'lucide:heart-handshake' : banner.tone === 'wait' ? 'lucide:loader-circle' : 'lucide:circle-x'" width="18" :class="banner.tone === 'wait' ? 'animate-spin' : ''" /> {{ banner.title }}
      </p>
      <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-white/60">{{ banner.text }}</p>
    </div>

    <header class="text-center">
      <p class="text-[0.7rem] font-black uppercase tracking-[0.2em] text-red-600 dark:text-red-300">Support DASCARE</p>
      <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-white sm:text-4xl">Help keep emergency response free</h1>
      <p v-if="data" class="mx-auto mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-white/60">{{ data.intro }}</p>
      <p class="mx-auto mt-2 max-w-2xl text-xs font-semibold text-slate-500 dark:text-white/45">Requesting an ambulance is always free and has no ads. Donating is completely voluntary.</p>
    </header>

    <div v-if="loading" class="mt-10 h-48 animate-pulse rounded-3xl bg-base-200 dark:bg-white/5"></div>
    <p v-else-if="loadError" class="mt-10 text-center text-sm text-red-600">{{ loadError }}</p>

    <template v-else-if="data">
      <p v-if="!data.enabled" class="mt-8 rounded-2xl bg-base-200 p-4 text-center text-sm text-slate-600 dark:bg-white/5 dark:text-white/60">Donations are paused for now. Thank you for your interest — please check back soon.</p>

      <div v-else class="mt-8 grid gap-6 lg:grid-cols-[1.1fr_.9fr]">
        <!-- Donate -->
        <section class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
          <h2 class="text-lg font-black text-slate-900 dark:text-white">Make a donation</h2>

          <div v-if="data.campaigns.length > 1" class="mt-4">
            <label class="mb-1.5 block text-xs font-bold text-slate-500">What it supports</label>
            <select v-model.number="form.campaign_id" class="w-full rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5">
              <option v-for="c in data.campaigns" :key="c.id" :value="c.id">{{ c.title }}</option>
            </select>
          </div>

          <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
            <button v-for="p in data.presets" :key="p" type="button" class="rounded-xl border px-3 py-3 text-sm font-black transition-colors"
              :class="Number(form.amount) === p ? 'border-red-500 bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'border-base-300 text-slate-700 hover:border-red-200 dark:border-white/10 dark:text-white/70'" @click="form.amount = p">₱{{ p.toLocaleString('en-PH') }}</button>
          </div>
          <label class="mt-3 block text-xs font-bold text-slate-500">Or enter an amount (₱{{ data.min_amount }} – ₱{{ data.max_amount.toLocaleString('en-PH') }})
            <input v-model.number="form.amount" type="number" inputmode="numeric" :min="data.min_amount" :max="data.max_amount" step="1" class="mt-1.5 w-full rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5" placeholder="Amount in pesos" />
          </label>

          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <label class="block text-xs font-bold text-slate-500">Your name <span class="font-normal">(optional)</span>
              <input v-model.trim="form.donor_name" maxlength="120" autocomplete="name" class="mt-1.5 w-full rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5" />
            </label>
            <label class="block text-xs font-bold text-slate-500">Email for a receipt <span class="font-normal">(optional)</span>
              <input v-model.trim="form.donor_email" type="email" maxlength="190" autocomplete="email" class="mt-1.5 w-full rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5" />
            </label>
          </div>
          <label class="mt-3 block text-xs font-bold text-slate-500">A short message <span class="font-normal">(optional)</span>
            <input v-model.trim="form.message" maxlength="200" class="mt-1.5 w-full rounded-xl border border-base-300 bg-base-100 px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5" />
          </label>
          <div class="mt-3 space-y-2 text-xs text-slate-600 dark:text-white/60">
            <label class="flex items-center gap-2"><input v-model="form.anonymous" type="checkbox" class="checkbox checkbox-xs" /> Donate anonymously</label>
            <label v-if="data.show_wall && !form.anonymous" class="flex items-center gap-2"><input v-model="form.show_on_wall" type="checkbox" class="checkbox checkbox-xs" :disabled="!form.donor_name" /> Show my name on the thank-you wall</label>
          </div>

          <p v-if="error" class="mt-3 text-xs font-bold text-red-600">{{ error }}</p>
          <button type="button" :disabled="submitting || !data.online_ready" class="mt-5 flex w-full items-center justify-center gap-2 rounded-2xl bg-red-600 px-5 py-3.5 text-sm font-black text-white shadow-md disabled:opacity-50" @click="donate">
            <Icon :icon="submitting ? 'lucide:loader-circle' : 'lucide:heart'" width="17" :class="submitting ? 'animate-spin' : ''" />
            {{ submitting ? 'Opening secure checkout…' : (form.amount >= data.min_amount ? `Donate ₱${Number(form.amount).toLocaleString('en-PH')}` : 'Donate') }}
          </button>
          <p v-if="!data.online_ready" class="mt-2 text-center text-xs text-amber-700 dark:text-amber-300">Online donations are being set up. Please check back soon.</p>
          <p class="mt-3 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[0.68rem] text-slate-400">
            <span class="inline-flex items-center gap-1"><Icon icon="lucide:lock" width="12" /> Secure payment by PayMongo</span>
            <span v-for="m in data.methods" :key="m.key">{{ m.label }}</span>
          </p>
          <p class="mt-3 text-[0.68rem] leading-4 text-slate-400">Donations are voluntary, go to {{ data.recipient }} and are not refundable once paid. We never see or store your card details. A thank-you email is not an official BIR receipt.</p>
        </section>

        <!-- Goals + where it goes -->
        <aside class="space-y-6">
          <section v-for="c in data.campaigns" :key="c.id" class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
            <p class="text-[0.65rem] font-black uppercase tracking-[0.16em] text-red-600 dark:text-red-300">{{ periodLabel(c) }}</p>
            <h3 class="mt-1 text-base font-black text-slate-900 dark:text-white">{{ c.title }}</h3>
            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-white/50">{{ c.purpose }}</p>
            <div class="mt-4 h-3 overflow-hidden rounded-full bg-base-200 dark:bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-red-500 to-red-600 transition-all" :style="{ width: c.percent + '%' }"></div></div>
            <p class="mt-2 flex items-baseline justify-between text-xs text-slate-500 dark:text-white/50"><span><b class="text-base text-slate-900 dark:text-white">{{ formatPeso(c.raised_centavos) }}</b> raised</span><span>goal {{ formatPeso(c.goal_centavos) }} · {{ c.percent }}%</span></p>
            <p class="mt-1 text-[0.68rem] text-slate-400">{{ c.donors }} donation{{ c.donors === 1 ? '' : 's' }} in this period</p>
          </section>

          <section v-if="data.breakdown.length" class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
            <h3 class="text-base font-black text-slate-900 dark:text-white">Where the money goes</h3>
            <ul class="mt-3 space-y-2.5">
              <li v-for="b in data.breakdown" :key="b.label">
                <div class="flex justify-between text-xs font-semibold text-slate-600 dark:text-white/65"><span>{{ b.label }}</span><span>{{ b.percent }}%</span></div>
                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-base-200 dark:bg-white/10"><div class="h-full rounded-full bg-slate-400 dark:bg-white/40" :style="{ width: b.percent + '%' }"></div></div>
              </li>
            </ul>
          </section>
        </aside>
      </div>

      <section v-if="data.wall.length" class="mt-10 rounded-3xl border border-base-300 bg-base-100 p-6 dark:border-white/10 dark:bg-[#0d2943]">
        <h2 class="text-center text-base font-black text-slate-900 dark:text-white">Thank you to our donors</h2>
        <div class="mt-4 flex flex-wrap justify-center gap-2">
          <span v-for="(w, i) in data.wall" :key="i" class="rounded-full bg-base-200 px-3 py-1.5 text-xs font-bold text-slate-600 dark:bg-white/10 dark:text-white/70">{{ w.name }}</span>
        </div>
      </section>

      <section v-if="data.sponsors.length" class="mt-10">
        <p class="text-center text-[0.68rem] font-black uppercase tracking-[0.18em] text-slate-400 dark:text-white/35">Sponsors</p>
        <div class="mt-4 flex flex-wrap items-center justify-center gap-4">
          <component :is="s.website_url ? 'a' : 'div'" v-for="s in data.sponsors" :key="s.id" :href="s.website_url || undefined" target="_blank" rel="noopener sponsored" class="flex items-center gap-3 rounded-2xl border border-base-300 bg-base-100 px-4 py-3 shadow-sm dark:border-white/10 dark:bg-white/[.04]">
            <img v-if="s.has_logo" :src="sponsorLogoUrl(s.id)" :alt="s.name" class="h-10 w-10 object-contain" loading="lazy" />
            <span class="text-sm font-black text-slate-800 dark:text-white/80">{{ s.name }}</span>
          </component>
        </div>
      </section>

      <p class="mt-10 text-center text-sm text-slate-500 dark:text-white/50">Run a business or organization? <RouterLink to="/support/sponsor" class="font-bold text-red-600 hover:underline dark:text-red-400">Become a sponsor</RouterLink></p>
    </template>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Icon } from '@iconify/vue'
import { createDonation, fetchDonationStatus, fetchSupportPublic, formatPeso, sponsorLogoUrl } from '@/services/support'
import { isPersonName } from '@/utils/validation'

const route = useRoute()
const loading = ref(true)
const loadError = ref('')
const data = ref(null)
const submitting = ref(false)
const error = ref('')
const form = reactive({ amount: 100, campaign_id: null, donor_name: '', donor_email: '', message: '', anonymous: false, show_on_wall: false })
const banner = ref(null)
let pollTimer = null

const periodLabel = (c) => (c.period === 'month' ? 'Monthly goal' : c.period === 'year' ? 'Yearly goal' : 'Fundraising goal')

async function load() {
  try {
    data.value = await fetchSupportPublic()
    form.campaign_id = data.value.campaigns[0]?.id ?? null
    if (data.value.presets?.length && !data.value.presets.includes(form.amount)) form.amount = data.value.presets[Math.min(1, data.value.presets.length - 1)]
  } catch (e) {
    loadError.value = e?.response?.data?.message || 'Unable to load this page. Please try again.'
  } finally {
    loading.value = false
  }
}

async function donate() {
  error.value = ''
  const amount = Number(form.amount)
  if (!Number.isFinite(amount) || amount < data.value.min_amount || amount > data.value.max_amount) { error.value = `Donations are from ₱${data.value.min_amount} to ₱${data.value.max_amount.toLocaleString('en-PH')}.`; return }
  if (form.donor_name && !isPersonName(form.donor_name)) { error.value = 'Name can only contain letters, spaces, apostrophes, periods and hyphens.'; return }
  if (form.donor_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.donor_email)) { error.value = 'Enter a valid email address, or leave it blank.'; return }
  submitting.value = true
  try {
    const d = await createDonation({ amount, campaign_id: form.campaign_id, donor_name: form.donor_name, donor_email: form.donor_email, message: form.message, anonymous: form.anonymous, show_on_wall: form.show_on_wall && !form.anonymous })
    window.location.href = d.checkout_url
  } catch (e) {
    error.value = e?.response?.data?.message || 'Unable to start the donation. Please try again.'
    submitting.value = false
  }
}

async function checkReturn() {
  const ref = String(route.query.ref || '')
  const status = String(route.query.status || '')
  if (!ref) return
  if (status === 'cancelled') { banner.value = { tone: 'info', title: 'Donation cancelled', text: 'No payment was made. You can try again whenever you like.' }; return }
  banner.value = { tone: 'wait', title: 'Confirming your donation…', text: 'This usually takes a few seconds.' }
  let tries = 0
  const poll = async () => {
    tries += 1
    try {
      const d = await fetchDonationStatus(ref)
      if (d.status === 'paid') {
        banner.value = { tone: 'ok', title: 'Thank you for your donation!', text: `We received ${formatPeso(d.amount_centavos, 2)}${d.method ? ' via ' + d.method : ''}. ${data.value?.thanks || ''} Reference ${d.reference}.` }
        await load()
        return
      }
      if (['failed', 'cancelled', 'expired'].includes(d.status)) { banner.value = { tone: 'info', title: 'Donation not completed', text: 'No payment was made. You can try again whenever you like.' }; return }
    } catch { /* keep trying a few times */ }
    if (tries < 8) pollTimer = setTimeout(poll, 2500)
    else banner.value = { tone: 'wait', title: 'Still confirming…', text: `If you paid, it will be recorded shortly. Keep your reference ${ref} — you do not need to pay again.` }
  }
  poll()
}

onMounted(async () => { await load(); checkReturn() })
onBeforeUnmount(() => clearTimeout(pollTimer))
</script>
