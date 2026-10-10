<template>
  <section class="relative min-h-screen overflow-hidden bg-base-200 px-4 py-10 dark:bg-[#050e1a] sm:px-6 sm:py-14">
    <div class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-30" style="background: radial-gradient(ellipse at top left, rgba(25,118,210,0.08), transparent 50%), radial-gradient(ellipse at bottom right, rgba(220,38,38,0.06), transparent 50%);"></div>

    <div class="relative mx-auto max-w-6xl">
      <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <span class="inline-flex items-center gap-2 rounded-full bg-[#1976D2]/10 px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.16em] text-[#1976D2] dark:text-[#7fb3ec]">
            <Icon icon="lucide:building-2" width="14" /> Organization onboarding
          </span>
          <h1 class="mt-3 text-2xl font-black tracking-tight text-slate-950 dark:text-white sm:text-3xl">Apply to join DASCARE</h1>
          <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-white/45">
            Ambulance and rescue organizations can submit their information for LGU-level review. The first representative becomes the Organization Admin after approval.
          </p>
        </div>
        <RouterLink to="/" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-[#1976D2] dark:text-white/45 dark:hover:text-[#7fb3ec]">
          <Icon icon="lucide:arrow-left" width="16" /> Back to public site
        </RouterLink>
      </div>

      <div v-if="stage === 'form'" class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="h-fit rounded-3xl border border-base-300 bg-base-100 p-5 dark:border-white/10 dark:bg-[#071829] lg:sticky lg:top-24">
          <p class="text-[0.65rem] font-black uppercase tracking-[0.14em] text-slate-400 dark:text-white/30">Application progress</p>
          <ol class="mt-4 space-y-2">
            <li v-for="item in steps" :key="item.number">
              <button type="button" class="flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-left transition-colors" :class="currentStep === item.number ? 'bg-[#1976D2]/10' : 'hover:bg-base-200 dark:hover:bg-white/5'" @click="jumpToStep(item.number)">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl text-xs font-black" :class="currentStep === item.number ? 'bg-[#1976D2] text-white' : currentStep > item.number ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-base-200 text-slate-400 dark:bg-white/5 dark:text-white/30'">
                  <Icon v-if="currentStep > item.number" icon="lucide:check" width="15" />
                  <span v-else>{{ item.number }}</span>
                </span>
                <span>
                  <span class="block text-sm font-bold text-slate-800 dark:text-white/75">{{ item.title }}</span>
                  <span class="block text-[0.68rem] text-slate-400 dark:text-white/30">{{ item.caption }}</span>
                </span>
              </button>
            </li>
          </ol>
          <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
            <div class="flex items-start gap-2">
              <Icon icon="lucide:info" width="15" class="mt-0.5 shrink-0" />
              <span>Submitting an application does not immediately activate dispatch access. Platform Executive approval is required first.</span>
            </div>
          </div>
        </aside>

        <form class="rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#071829]" @submit.prevent="submitApplication">
          <header class="border-b border-base-300 px-5 py-5 dark:border-white/10 sm:px-7">
            <p class="text-[0.65rem] font-black uppercase tracking-[0.15em] text-[#1976D2] dark:text-[#7fb3ec]">Step {{ currentStep }} of 4</p>
            <h2 class="mt-1 text-xl font-black text-slate-900 dark:text-white">{{ activeStep.title }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-white/40">{{ activeStep.description }}</p>
          </header>

          <div class="p-5 sm:p-7">
            <section v-show="currentStep === 1" class="space-y-5">
              <div class="rounded-2xl border border-base-300 bg-base-200/40 p-4 dark:border-white/10 dark:bg-white/[0.025]">
                <div class="flex items-start gap-3">
                  <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon icon="lucide:user-cog" width="18" /></span>
                  <div>
                    <h3 class="text-sm font-black text-slate-900 dark:text-white">Primary organization representative</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-white/40">This person receives the verification email and becomes the initial Organization Admin if the application is approved.</p>
                  </div>
                </div>
              </div>

              <div class="grid gap-4 sm:grid-cols-2">
                <Field label="First name"><input v-model.trim="form.first_name" class="field" autocomplete="given-name" maxlength="80" /></Field>
                <Field label="Last name"><input v-model.trim="form.last_name" class="field" autocomplete="family-name" maxlength="80" /></Field>
                <Field label="Email address"><input v-model.trim="form.email" class="field" type="email" autocomplete="email" maxlength="190" /></Field>
                <Field label="Mobile number" hint="PH mobile number, e.g. 09123456789"><input v-model.trim="form.phone" class="field" type="tel" maxlength="13" autocomplete="tel" /></Field>
                <Field label="Password"><input v-model="form.password" class="field" type="password" autocomplete="new-password" /></Field>
                <Field label="Confirm password"><input v-model="form.password_confirmation" class="field" type="password" autocomplete="new-password" /></Field>
              </div>
            </section>

            <section v-show="currentStep === 2" class="space-y-5">
              <div class="grid gap-4 sm:grid-cols-2">
                <Field label="Organization name" class="sm:col-span-2"><input v-model.trim="form.organization_name" class="field" maxlength="150" /></Field>
                <Field label="Organization type">
                  <select v-model="form.organization_type" class="field">
                    <option value="" disabled>Select organization type</option>
                    <option v-for="type in organizationTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                  </select>
                </Field>
                <Field label="Registration / authorization reference" hint="If applicable"><input v-model.trim="form.registration_number" class="field" maxlength="100" /></Field>
                <Field label="Accreditation / supervising body" hint="If applicable"><input v-model.trim="form.accreditation_body" class="field" maxlength="150" /></Field>
                <Field label="Organization email"><input v-model.trim="form.organization_email" class="field" type="email" maxlength="190" /></Field>
                <Field label="Organization contact number"><input v-model.trim="form.organization_phone" class="field" type="tel" maxlength="18" /></Field>
                <Field label="Base / station address" class="sm:col-span-2"><textarea v-model.trim="form.address_line" class="field min-h-[96px] resize-none" maxlength="255" placeholder="Street, subdivision / area, barangay, Dasmariñas City, Cavite"></textarea></Field>
              </div>

              <div class="rounded-2xl border border-base-300 p-4 dark:border-white/10">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p class="text-sm font-black text-slate-800 dark:text-white/75">Pin the organization base</p>
                    <p class="text-xs text-slate-400 dark:text-white/30">The station/base location must be within Dasmariñas City.</p>
                  </div>
                  <button type="button" class="inline-flex items-center gap-1.5 rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-[#1976D2] disabled:opacity-50 dark:border-white/10 dark:text-[#7fb3ec]" :disabled="locating" @click="useCurrentLocation">
                    <Icon icon="lucide:locate-fixed" width="14" /> {{ locating ? 'Locating…' : 'Use current location' }}
                  </button>
                </div>
                <div ref="mapContainer" class="h-[320px] w-full overflow-hidden rounded-xl border border-base-300 bg-base-200 dark:border-white/10 dark:bg-[#0a2038]"></div>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[0.68rem] text-slate-400 dark:text-white/30">
                  <span>Click the map to place or move the base marker.</span>
                  <span v-if="form.latitude !== null">{{ Number(form.latitude).toFixed(5) }}, {{ Number(form.longitude).toFixed(5) }}</span>
                </div>
              </div>
            </section>

            <section v-show="currentStep === 3" class="space-y-6">
              <div>
                <div class="flex flex-wrap items-end justify-between gap-3">
                  <div>
                    <h3 class="text-sm font-black text-slate-900 dark:text-white">Service area</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-white/40">Select the Dasmariñas barangays the organization normally serves. Coverage can be revised later.</p>
                  </div>
                  <span class="rounded-full bg-[#1976D2]/10 px-2.5 py-1 text-[0.68rem] font-bold text-[#1976D2] dark:text-[#7fb3ec]">{{ form.service_areas.length }} selected</span>
                </div>

                <div v-if="!barangayLoadFailed" class="mt-4 rounded-2xl border border-base-300 p-4 dark:border-white/10">
                  <div class="relative">
                    <Icon icon="lucide:search" width="15" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input v-model="barangaySearch" class="field pl-10" :placeholder="loadingBarangays ? 'Loading barangays…' : 'Search barangays'" :disabled="loadingBarangays" />
                  </div>
                  <div class="mt-3 max-h-64 overflow-y-auto pr-1">
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                      <label v-for="barangay in filteredBarangays" :key="barangay" class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5 text-xs font-semibold transition-colors" :class="form.service_areas.includes(barangay) ? 'border-[#1976D2]/40 bg-[#1976D2]/10 text-[#1976D2] dark:text-[#7fb3ec]' : 'border-base-300 text-slate-600 hover:bg-base-200 dark:border-white/10 dark:text-white/50 dark:hover:bg-white/5'">
                        <input type="checkbox" class="checkbox checkbox-xs" :checked="form.service_areas.includes(barangay)" @change="toggleServiceArea(barangay)" />
                        <span>{{ barangay }}</span>
                      </label>
                    </div>
                  </div>
                </div>

                <div v-else class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
                  <p class="text-xs font-bold text-amber-800 dark:text-amber-200">Barangay directory is unavailable. Add service areas manually.</p>
                  <div class="mt-3 flex gap-2">
                    <input v-model.trim="manualBarangay" class="field bg-white dark:bg-[#071829]" placeholder="Barangay name" @keyup.enter.prevent="addManualBarangay" />
                    <button type="button" class="rounded-xl bg-amber-600 px-4 text-xs font-bold text-white" @click="addManualBarangay">Add</button>
                  </div>
                </div>

                <div v-if="form.service_areas.length" class="mt-3 flex flex-wrap gap-2">
                  <button v-for="area in form.service_areas" :key="area" type="button" class="inline-flex items-center gap-1.5 rounded-full bg-base-200 px-3 py-1.5 text-[0.68rem] font-bold text-slate-500 dark:bg-white/5 dark:text-white/40" @click="toggleServiceArea(area)">
                    {{ area }} <Icon icon="lucide:x" width="12" />
                  </button>
                </div>
              </div>

              <div class="border-t border-base-300 pt-6 dark:border-white/10">
                <h3 class="text-sm font-black text-slate-900 dark:text-white">Supporting documents</h3>
                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-white/40">Document requirements may differ by organization classification. Submit the basic proof below; Platform Executive reviewers can request revisions or additional evidence later.</p>

                <div class="mt-4 grid gap-4 lg:grid-cols-3">
                  <DocumentPicker v-model="files.registration_document" title="Registration / authorization" description="Required" required />
                  <DocumentPicker v-model="files.operating_document" title="Operating / accreditation" description="Optional supporting evidence" />
                  <DocumentPicker v-model="files.supporting_document" title="Additional document" description="Optional" />
                </div>
              </div>
            </section>

            <section v-show="currentStep === 4" class="space-y-5">
              <div class="grid gap-4 lg:grid-cols-2">
                <ReviewCard title="Representative" icon="lucide:user-cog">
                  <ReviewRow label="Name" :value="`${form.first_name} ${form.last_name}`" />
                  <ReviewRow label="Email" :value="form.email" />
                  <ReviewRow label="Mobile" :value="form.phone" />
                </ReviewCard>
                <ReviewCard title="Organization" icon="lucide:building-2">
                  <ReviewRow label="Name" :value="form.organization_name" />
                  <ReviewRow label="Type" :value="organizationTypeLabel" />
                  <ReviewRow label="Contact" :value="form.organization_phone || form.phone" />
                  <ReviewRow label="Base" :value="form.address_line" />
                </ReviewCard>
                <ReviewCard title="Coverage" icon="lucide:map-pinned">
                  <p class="text-xs leading-6 text-slate-600 dark:text-white/55">{{ form.service_areas.join(', ') || 'No service areas selected' }}</p>
                </ReviewCard>
                <ReviewCard title="Documents" icon="lucide:files">
                  <ReviewRow label="Registration" :value="files.registration_document?.name || 'Missing'" />
                  <ReviewRow label="Operating" :value="files.operating_document?.name || 'Not provided'" />
                  <ReviewRow label="Additional" :value="files.supporting_document?.name || 'Not provided'" />
                </ReviewCard>
              </div>

              <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-base-300 bg-base-200/40 p-4 dark:border-white/10 dark:bg-white/[0.025]">
                <input v-model="form.attested" type="checkbox" class="checkbox checkbox-sm mt-0.5" />
                <span class="text-xs leading-5 text-slate-600 dark:text-white/50">
                  I confirm that I am authorized to submit this application for the organization and that the information and documents provided are accurate to the best of my knowledge. I understand that submission does not guarantee approval.
                </span>
              </label>
            </section>

            <div v-if="errorMessage" class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">
              {{ errorMessage }}
            </div>
          </div>

          <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 px-5 py-4 dark:border-white/10 sm:px-7">
            <button v-if="currentStep > 1" type="button" class="inline-flex items-center gap-2 rounded-xl border border-base-300 px-4 py-2.5 text-sm font-bold text-slate-600 dark:border-white/10 dark:text-white/55" :disabled="submitting" @click="currentStep--">
              <Icon icon="lucide:arrow-left" width="15" /> Back
            </button>
            <span v-else></span>

            <button v-if="currentStep < 4" type="button" class="inline-flex items-center gap-2 rounded-xl bg-[#1976D2] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#1565c0]" @click="nextStep">
              Continue <Icon icon="lucide:arrow-right" width="15" />
            </button>
            <button v-else type="submit" class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-red-600/20 hover:bg-red-700 disabled:opacity-50" :disabled="submitting">
              <Icon :icon="submitting ? 'lucide:loader-circle' : 'lucide:send'" width="15" :class="submitting ? 'animate-spin' : ''" />
              {{ submitting ? 'Submitting…' : 'Submit Application' }}
            </button>
          </footer>
        </form>
      </div>

      <section v-else-if="stage === 'otp'" class="mx-auto max-w-lg rounded-3xl border border-base-300 bg-base-100 p-6 text-center shadow-xl dark:border-white/10 dark:bg-[#071829] sm:p-8">
        <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-[#1976D2]/10 text-[#1976D2] dark:text-[#7fb3ec]"><Icon icon="lucide:mail-check" width="28" /></span>
        <p class="mt-5 font-mono text-[0.65rem] font-bold uppercase tracking-[0.16em] text-[#1976D2] dark:text-[#7fb3ec]">{{ applicationReference }}</p>
        <h2 class="mt-2 text-2xl font-black text-slate-900 dark:text-white">Verify the representative email</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-white/45">We sent a six-digit code to <strong class="text-slate-700 dark:text-white/70">{{ submittedEmail }}</strong>. Verification activates the applicant account so you can track the LGU review.</p>

        <input v-model="otp" inputmode="numeric" maxlength="6" class="mt-6 w-full rounded-2xl border border-base-300 bg-base-100 px-4 py-4 text-center font-mono text-2xl font-black tracking-[0.45em] text-slate-900 outline-none focus:border-[#1976D2] dark:border-white/10 dark:bg-[#0a2038] dark:text-white" placeholder="000000" @input="otp = otp.replace(/\D/g, '').slice(0, 6)" />

        <p v-if="otpError" class="mt-3 text-sm font-semibold text-red-600 dark:text-red-300">{{ otpError }}</p>

        <button type="button" class="mt-5 w-full rounded-xl bg-red-600 py-3 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50" :disabled="verifyingOtp || otp.length !== 6" @click="verifyOtp">
          {{ verifyingOtp ? 'Verifying…' : 'Verify Email' }}
        </button>
        <button type="button" class="mt-3 text-xs font-bold text-[#1976D2] hover:underline disabled:opacity-50 dark:text-[#7fb3ec]" :disabled="resendingOtp" @click="resendOtp">
          {{ resendingOtp ? 'Sending…' : 'Resend verification code' }}
        </button>
      </section>

      <section v-else class="mx-auto max-w-xl rounded-3xl border border-emerald-200 bg-base-100 p-7 text-center shadow-xl dark:border-emerald-500/20 dark:bg-[#071829] sm:p-9">
        <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"><Icon icon="lucide:badge-check" width="28" /></span>
        <p class="mt-5 font-mono text-[0.65rem] font-bold uppercase tracking-[0.16em] text-emerald-600 dark:text-emerald-300">Application received · {{ applicationReference }}</p>
        <h2 class="mt-2 text-2xl font-black text-slate-900 dark:text-white">Your applicant account is ready</h2>
        <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-white/45">The organization is now queued for Platform Executive review. Sign in with the representative account to view the application status. Operational organization features remain locked until approval.</p>
        <RouterLink to="/login" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-[#1976D2] px-5 py-3 text-sm font-bold text-white hover:bg-[#1565c0]">
          Continue to Login <Icon icon="lucide:arrow-right" width="15" />
        </RouterLink>
      </section>
    </div>
  </section>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { useToast } from '@/composables/useToast'
import { resendOrganizationApplicantOtp, submitOrganizationApplication, verifyOrganizationApplicantEmail } from '@/services/organizationApplications'

const toast = useToast()
const stage = ref('form')
const currentStep = ref(1)
const submitting = ref(false)
const errorMessage = ref('')
const submittedEmail = ref('')
const applicationReference = ref('')
const otp = ref('')
const otpError = ref('')
const verifyingOtp = ref(false)
const resendingOtp = ref(false)

const steps = [
  { number: 1, title: 'Representative', caption: 'Applicant account', description: 'Create the primary representative account that will own the application and become the initial Organization Admin after approval.' },
  { number: 2, title: 'Organization', caption: 'Identity and base', description: 'Provide the organization identity, contact details, and primary station/base location.' },
  { number: 3, title: 'Coverage & documents', caption: 'Service and evidence', description: 'Declare normal service coverage and upload the core documents needed for initial review.' },
  { number: 4, title: 'Review & submit', caption: 'Final confirmation', description: 'Review the application, attest that the information is accurate, and submit it to the Platform Executive review queue.' },
]
const activeStep = computed(() => steps[currentStep.value - 1])

const organizationTypes = [
  { value: 'city_rescue', label: 'City Rescue / LGU Unit' },
  { value: 'barangay_rescue', label: 'Barangay Rescue Unit' },
  { value: 'hospital', label: 'Hospital Ambulance Service' },
  { value: 'private_ambulance', label: 'Private Ambulance / Rescue Service' },
  { value: 'other', label: 'Other Rescue Organization' },
]
const organizationTypeLabel = computed(() => organizationTypes.find(type => type.value === form.value.organization_type)?.label || '—')

const form = ref({
  first_name: '', last_name: '', email: '', phone: '', password: '', password_confirmation: '',
  organization_name: '', organization_type: '', registration_number: '', accreditation_body: '',
  organization_email: '', organization_phone: '', address_line: '', latitude: null, longitude: null,
  service_areas: [], attested: false,
})
const files = ref({ registration_document: null, operating_document: null, supporting_document: null })

const Field = defineComponent({
  name: 'OrganizationApplicationField',
  props: { label: String, hint: String },
  setup(props, { slots, attrs }) {
    return () => h('label', { class: ['block', attrs.class] }, [
      h('span', { class: 'mb-1.5 block text-sm font-bold text-slate-700 dark:text-white/65' }, props.label),
      slots.default?.(),
      props.hint ? h('span', { class: 'mt-1.5 block text-[0.68rem] text-slate-400 dark:text-white/30' }, props.hint) : null,
    ])
  },
})

const ReviewCard = defineComponent({
  name: 'OrganizationReviewCard',
  props: { title: String, icon: String },
  setup(props, { slots }) {
    return () => h('article', { class: 'rounded-2xl border border-base-300 p-4 dark:border-white/10' }, [
      h('div', { class: 'mb-3 flex items-center gap-2' }, [
        h(Icon, { icon: props.icon, width: 16, class: 'text-[#1976D2] dark:text-[#7fb3ec]' }),
        h('h3', { class: 'text-sm font-black text-slate-900 dark:text-white' }, props.title),
      ]),
      slots.default?.(),
    ])
  },
})
const ReviewRow = defineComponent({
  name: 'OrganizationReviewRow',
  props: { label: String, value: [String, Number] },
  setup(props) {
    return () => h('div', { class: 'flex items-start justify-between gap-4 border-t border-base-300 py-2.5 text-xs first:border-0 dark:border-white/10' }, [
      h('span', { class: 'text-slate-400 dark:text-white/30' }, props.label),
      h('span', { class: 'max-w-[65%] break-words text-right font-semibold text-slate-700 dark:text-white/60' }, props.value || '—'),
    ])
  },
})

const DocumentPicker = defineComponent({
  name: 'OrganizationDocumentPicker',
  props: { modelValue: Object, title: String, description: String, required: Boolean },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const input = ref(null)
    const choose = () => input.value?.click()
    const changed = (event) => {
      const file = event.target.files?.[0] || null
      if (file && file.size > 8 * 1024 * 1024) {
        toast.error('Documents must be 8MB or smaller.')
        event.target.value = ''
        return
      }
      emit('update:modelValue', file)
    }
    return () => h('div', { class: 'rounded-2xl border border-dashed border-base-300 bg-base-200/30 p-4 dark:border-white/10 dark:bg-white/[0.02]' }, [
      h('input', { ref: input, type: 'file', accept: '.pdf,image/jpeg,image/png,image/webp', class: 'hidden', onChange: changed }),
      h('div', { class: 'flex items-start gap-3' }, [
        h('span', { class: 'grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#1976D2]/10 text-[#1976D2] dark:text-[#7fb3ec]' }, [h(Icon, { icon: 'lucide:file-up', width: 16 })]),
        h('div', { class: 'min-w-0 flex-1' }, [
          h('p', { class: 'text-xs font-black text-slate-800 dark:text-white/70' }, [props.title, props.required ? h('span', { class: 'ml-1 text-red-500' }, '*') : null]),
          h('p', { class: 'mt-0.5 text-[0.68rem] text-slate-400 dark:text-white/30' }, props.description),
          props.modelValue
            ? h('p', { class: 'mt-3 truncate text-[0.7rem] font-bold text-emerald-600 dark:text-emerald-300' }, props.modelValue.name)
            : h('p', { class: 'mt-3 text-[0.68rem] text-slate-400 dark:text-white/25' }, 'PDF, JPG, PNG or WEBP · max 8MB'),
          h('button', { type: 'button', class: 'mt-3 text-xs font-bold text-[#1976D2] hover:underline dark:text-[#7fb3ec]', onClick: choose }, props.modelValue ? 'Replace file' : 'Choose file'),
        ]),
      ]),
    ])
  },
})

function stepOneValid() {
  if (form.value.first_name.length < 2 || form.value.last_name.length < 2) return 'Enter the representative’s complete name.'
  if (!/^\S+@\S+\.\S+$/.test(form.value.email)) return 'Enter a valid representative email address.'
  if (!/^(09\d{9}|\+639\d{9})$/.test(form.value.phone.replace(/[\s-]/g, ''))) return 'Enter a valid PH mobile number.'
  if (form.value.password.length < 8) return 'Use a password with at least 8 characters.'
  if (form.value.password !== form.value.password_confirmation) return 'Password confirmation does not match.'
  return ''
}
function stepTwoValid() {
  if (form.value.organization_name.length < 3) return 'Enter the organization name.'
  if (!form.value.organization_type) return 'Select an organization type.'
  if (form.value.address_line.length < 8) return 'Enter the base/station address.'
  if (form.value.latitude === null || form.value.longitude === null) return 'Pin the organization base on the map.'
  return ''
}
function stepThreeValid() {
  if (!form.value.service_areas.length) return 'Select at least one service-area barangay.'
  if (!files.value.registration_document) return 'Attach the required registration or authorization document.'
  return ''
}
function validationFor(step) {
  if (step === 1) return stepOneValid()
  if (step === 2) return stepTwoValid()
  if (step === 3) return stepThreeValid()
  if (step === 4 && !form.value.attested) return 'Confirm the application attestation before submitting.'
  return ''
}
function nextStep() {
  const error = validationFor(currentStep.value)
  if (error) { errorMessage.value = error; return }
  errorMessage.value = ''
  currentStep.value = Math.min(4, currentStep.value + 1)
}
function jumpToStep(step) {
  if (step <= currentStep.value) currentStep.value = step
}

const PSGC_BASE = 'https://psgc.gitlab.io/api'
const DASMARINAS_CITY_CODE = '042106000'
const barangays = ref([])
const barangaySearch = ref('')
const loadingBarangays = ref(false)
const barangayLoadFailed = ref(false)
const manualBarangay = ref('')
const filteredBarangays = computed(() => {
  const q = barangaySearch.value.trim().toLowerCase()
  return q ? barangays.value.filter(item => item.toLowerCase().includes(q)) : barangays.value
})
async function loadBarangays() {
  loadingBarangays.value = true
  barangayLoadFailed.value = false
  try {
    const response = await fetch(`${PSGC_BASE}/cities-municipalities/${DASMARINAS_CITY_CODE}/barangays/`)
    if (!response.ok) throw new Error('Barangay lookup failed')
    const data = await response.json()
    barangays.value = [...new Set(data.map(item => item.name).filter(Boolean))].sort((a, b) => a.localeCompare(b))
    if (!barangays.value.length) throw new Error('No barangays returned')
  } catch (error) {
    console.error(error)
    barangayLoadFailed.value = true
  } finally {
    loadingBarangays.value = false
  }
}
function toggleServiceArea(area) {
  const list = form.value.service_areas
  const index = list.indexOf(area)
  if (index >= 0) list.splice(index, 1)
  else list.push(area)
}
function addManualBarangay() {
  const area = manualBarangay.value.trim()
  if (!area) return
  if (!form.value.service_areas.includes(area)) form.value.service_areas.push(area)
  manualBarangay.value = ''
}

const mapContainer = ref(null)
const locating = ref(false)
let map = null
let marker = null
const DASMARINAS_CENTER = [14.3294, 120.9367]
const DASMARINAS_BOUNDS = [[14.26, 120.87], [14.40, 121.00]]
function loadLeaflet() {
  return new Promise((resolve, reject) => {
    if (window.L) { resolve(window.L); return }
    if (!document.querySelector('link[data-dascare-leaflet]')) {
      const link = document.createElement('link')
      link.rel = 'stylesheet'; link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'; link.dataset.dascareLeaflet = '1'
      document.head.appendChild(link)
    }
    const existing = document.querySelector('script[data-dascare-leaflet]')
    if (existing) {
      existing.addEventListener('load', () => resolve(window.L), { once: true })
      existing.addEventListener('error', reject, { once: true })
      return
    }
    const script = document.createElement('script')
    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'; script.dataset.dascareLeaflet = '1'
    script.onload = () => resolve(window.L); script.onerror = reject
    document.body.appendChild(script)
  })
}
function setMapPoint(lat, lng, zoom = 16) {
  form.value.latitude = lat
  form.value.longitude = lng
  if (!map || !window.L) return
  if (marker) marker.setLatLng([lat, lng])
  else marker = window.L.marker([lat, lng]).addTo(map)
  map.setView([lat, lng], zoom)
}
async function initMap() {
  await nextTick()
  if (!mapContainer.value || map) return
  try {
    const L = await loadLeaflet()
    map = L.map(mapContainer.value, { zoomControl: true, maxBounds: DASMARINAS_BOUNDS }).setView(DASMARINAS_CENTER, 13)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors &copy; CARTO' }).addTo(map)
    L.rectangle(DASMARINAS_BOUNDS, { color: '#1976D2', weight: 1, fillOpacity: 0.02, dashArray: '4 4' }).addTo(map)
    map.on('click', ({ latlng }) => setMapPoint(latlng.lat, latlng.lng))
    setTimeout(() => map?.invalidateSize(), 80)
  } catch (error) {
    console.error('Map failed to initialize', error)
    toast.error('The map could not load. Check your internet connection and try again.')
  }
}
function useCurrentLocation() {
  if (!navigator.geolocation) { toast.error('Location is not supported by this browser.'); return }
  locating.value = true
  navigator.geolocation.getCurrentPosition(
    position => {
      locating.value = false
      const { latitude, longitude } = position.coords
      if (latitude < 14.26 || latitude > 14.40 || longitude < 120.87 || longitude > 121.00) {
        toast.warning('Your current location is outside the Dasmariñas service boundary. Please pin the organization base manually.')
        return
      }
      setMapPoint(latitude, longitude)
    },
    () => { locating.value = false; toast.error('We could not access your current location.') },
    { enableHighAccuracy: true, timeout: 10000 },
  )
}
watch(currentStep, async step => {
  errorMessage.value = ''
  if (step === 2) {
    await initMap()
    setTimeout(() => map?.invalidateSize(), 100)
  }
})

async function submitApplication() {
  for (let step = 1; step <= 4; step++) {
    const error = validationFor(step)
    if (error) { currentStep.value = step; errorMessage.value = error; return }
  }
  submitting.value = true
  errorMessage.value = ''
  try {
    const payload = new FormData()
    const plainFields = [
      'first_name', 'last_name', 'email', 'phone', 'password', 'organization_name', 'organization_type',
      'registration_number', 'accreditation_body', 'organization_email', 'organization_phone', 'address_line',
    ]
    for (const key of plainFields) payload.append(key, form.value[key] ?? '')
    payload.append('latitude', String(form.value.latitude))
    payload.append('longitude', String(form.value.longitude))
    payload.append('service_areas', JSON.stringify(form.value.service_areas))
    payload.append('attested', form.value.attested ? '1' : '0')
    for (const [key, file] of Object.entries(files.value)) if (file) payload.append(key, file)

    const result = await submitOrganizationApplication(payload)
    if (!result?.success) throw new Error(result?.message || 'Application submission failed.')
    submittedEmail.value = result.email
    applicationReference.value = result.application_reference
    stage.value = 'otp'
    toast.success('Application submitted. Verify the representative email to continue.')
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || error?.message || 'Could not submit the application. Please try again.'
  } finally {
    submitting.value = false
  }
}
async function verifyOtp() {
  otpError.value = ''
  verifyingOtp.value = true
  try {
    const result = await verifyOrganizationApplicantEmail(submittedEmail.value, otp.value)
    if (!result?.success) throw new Error(result?.message || 'Verification failed.')
    stage.value = 'complete'
    toast.success('Representative email verified.')
  } catch (error) {
    otpError.value = error?.response?.data?.message || error?.message || 'Invalid or expired verification code.'
  } finally {
    verifyingOtp.value = false
  }
}
async function resendOtp() {
  otpError.value = ''
  resendingOtp.value = true
  try {
    const result = await resendOrganizationApplicantOtp(submittedEmail.value)
    if (!result?.success) throw new Error(result?.message || 'Could not resend the code.')
    toast.success('A new verification code was sent.')
  } catch (error) {
    otpError.value = error?.response?.data?.message || error?.message || 'Could not resend the code.'
  } finally {
    resendingOtp.value = false
  }
}

onMounted(loadBarangays)
onBeforeUnmount(() => {
  if (map) map.remove()
  map = null
  marker = null
})
</script>

<style scoped>
.field {
  width: 100%;
  border-radius: 0.75rem;
  border: 1px solid #dbe2ea;
  background: #fff;
  padding: 0.7rem 0.85rem;
  font-size: 0.875rem;
  color: #0f172a;
  outline: none;
  transition: border-color .18s ease, box-shadow .18s ease;
}
:global(.dark) .field {
  border-color: rgba(255,255,255,.1);
  background: #0a2038;
  color: #fff;
}
.field:focus {
  border-color: rgba(25, 118, 210, .55);
  box-shadow: 0 0 0 3px rgba(25, 118, 210, .09);
}
</style>
