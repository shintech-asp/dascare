<template>
  <section class="min-h-screen bg-base-200 pb-10 dark:bg-[#050e1a]">
    <section class="relative overflow-hidden border-b border-base-300 bg-base-100 dark:border-white/10 dark:bg-[#071829]">
      <div class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-30" style="background: radial-gradient(ellipse at top left, rgba(25,118,210,0.08), transparent 55%);"></div>
      <div class="relative mx-auto max-w-6xl px-4 py-7 sm:px-6 lg:px-8">
        <span class="inline-flex items-center gap-2 rounded-full bg-[#1976D2]/10 px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.15em] text-[#1976D2] dark:text-[#7fb3ec]">
          <Icon icon="lucide:building-2" width="13" /> Organization onboarding
        </span>
        <h1 class="mt-3 text-2xl font-black text-slate-900 dark:text-white">Application Status</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-white/45">Track the LGU review of your organization before operational access is activated.</p>
      </div>
    </section>

    <div class="mx-auto max-w-6xl px-4 pt-6 sm:px-6 lg:px-8">
      <div v-if="loading" class="rounded-3xl border border-base-300 bg-base-100 py-20 text-center dark:border-white/10 dark:bg-[#071829]">
        <div class="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-[#1976D2] border-t-transparent"></div>
        <p class="mt-3 text-sm text-slate-400 dark:text-white/35">Loading application…</p>
      </div>

      <div v-else-if="errorMessage" class="rounded-3xl border border-red-200 bg-base-100 p-8 text-center dark:border-red-500/20 dark:bg-[#071829]">
        <Icon icon="lucide:triangle-alert" width="28" class="mx-auto text-red-500" />
        <p class="mt-3 text-sm font-semibold text-red-700 dark:text-red-300">{{ errorMessage }}</p>
        <button type="button" class="mt-4 text-xs font-bold text-[#1976D2] hover:underline dark:text-[#7fb3ec]" @click="loadStatus">Try again</button>
      </div>

      <template v-else-if="organization">
        <section class="overflow-hidden rounded-3xl border border-base-300 bg-base-100 dark:border-white/10 dark:bg-[#071829]">
          <div class="border-b border-base-300 p-5 dark:border-white/10 sm:p-7">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex items-start gap-4">
                <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl" :class="statusIconClass">
                  <Icon :icon="statusIcon" width="25" />
                </span>
                <div>
                  <p class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-white/30">{{ organization.application_reference }}</p>
                  <h2 class="mt-1 text-xl font-black text-slate-900 dark:text-white">{{ organization.name }}</h2>
                  <p class="mt-1 text-sm text-slate-500 dark:text-white/40">Submitted {{ formatDate(organization.application_submitted_at) }}</p>
                </div>
              </div>
              <div class="flex flex-col items-start sm:items-end">
                <span class="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-xs font-black" :class="statusBadgeClass">
                  <span class="h-2 w-2 rounded-full bg-current opacity-70"></span>{{ statusLabel }}
                </span>

              <RouterLink
                v-if="organization.application_status === 'approved' && organization.status === 'active'"
                to="/organization"
                class="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white no-underline transition hover:bg-emerald-700"
              >
                <Icon icon="lucide:layout-dashboard" width="14" /> Open Organization Dashboard
              </RouterLink>

              </div>
            </div>
          </div>

          <div class="grid gap-6 p-5 sm:p-7 lg:grid-cols-[minmax(0,1fr)_330px]">
            <div class="space-y-6">
              <div class="rounded-2xl border border-base-300 p-4 dark:border-white/10 sm:p-5">
                <h3 class="text-sm font-black text-slate-900 dark:text-white">What happens next</h3>
                <div class="mt-4 space-y-4">
                  <TimelineItem :active="true" icon="lucide:send" title="Application submitted" :text="`Reference ${organization.application_reference}`" />
                  <TimelineItem :active="organization.application_status === 'pending'" icon="lucide:scan-search" title="Platform review" text="A Platform Executive reviews the organization details and submitted documents." />
                  <TimelineItem :active="organization.application_status === 'approved'" icon="lucide:badge-check" title="Organization activation" text="After approval, the organization workspace unlocks and staff/fleet setup can begin." />
                </div>
              </div>

              <div v-if="organization.verification_note" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/10 sm:p-5">
                <div class="flex items-start gap-3">
                  <Icon icon="lucide:message-square-warning" width="18" class="mt-0.5 shrink-0 text-amber-600 dark:text-amber-300" />
                  <div>
                    <p class="text-xs font-black uppercase tracking-wide text-amber-700 dark:text-amber-200">Reviewer note</p>
                    <p class="mt-1 text-sm leading-6 text-amber-800 dark:text-amber-100">{{ organization.verification_note }}</p>
                    <p v-if="organization.application_reviewed_at" class="mt-2 text-[0.68rem] text-amber-700/70 dark:text-amber-200/60">Reviewed {{ formatDate(organization.application_reviewed_at) }}<span v-if="organization.reviewer_name"> by {{ organization.reviewer_name.trim() }}</span></p>
                  </div>
                </div>
              </div>

              <div class="grid gap-4 sm:grid-cols-2">
                <InfoCard label="Organization type" :value="typeLabel(organization.organization_type)" icon="lucide:building" />
                <InfoCard label="Organization contact" :value="organization.phone || '—'" icon="lucide:phone" />
                <InfoCard label="Base / station" :value="organization.address_line || '—'" icon="lucide:map-pin" class="sm:col-span-2" />
                <InfoCard label="Service areas" :value="serviceAreas.join(', ') || '—'" icon="lucide:map-pinned" class="sm:col-span-2" />
              </div>
            </div>

            <aside class="space-y-4">
              <div class="rounded-2xl border border-base-300 p-4 dark:border-white/10 sm:p-5">
                <div class="flex items-center justify-between gap-3">
                  <h3 class="text-sm font-black text-slate-900 dark:text-white">Submitted documents</h3>
                  <span class="text-[0.68rem] font-bold text-slate-400 dark:text-white/30">{{ documents.length }}</span>
                </div>
                <div class="mt-4 space-y-3">
                  <div v-for="doc in documents" :key="doc.id" class="rounded-xl bg-base-200/50 p-3 dark:bg-white/[0.035]">
                    <div class="flex items-start justify-between gap-2">
                      <div class="min-w-0">
                        <p class="truncate text-xs font-bold text-slate-700 dark:text-white/60">{{ doc.original_name || documentLabel(doc.doc_type) }}</p>
                        <p class="mt-0.5 text-[0.65rem] text-slate-400 dark:text-white/30">{{ documentLabel(doc.doc_type) }} · {{ formatFileSize(doc.file_size) }}</p>
                      </div>
                      <span class="rounded-full px-2 py-0.5 text-[0.62rem] font-bold" :class="documentStatusClass(doc.status)">{{ doc.status }}</span>
                    </div>
                    <p v-if="doc.rejection_reason" class="mt-2 text-[0.68rem] leading-5 text-red-600 dark:text-red-300">{{ doc.rejection_reason }}</p>
                  </div>
                </div>
              </div>

              <div class="rounded-2xl border border-[#1976D2]/20 bg-[#1976D2]/5 p-4 text-xs leading-5 text-slate-600 dark:text-white/45">
                <div class="flex gap-2">
                  <Icon icon="lucide:lock-keyhole" width="15" class="mt-0.5 shrink-0 text-[#1976D2] dark:text-[#7fb3ec]" />
                  <p v-if="organization.application_status !== 'approved'">Fleet, personnel, roles, and dispatch modules remain locked until the organization is approved. Employees will later be invited from inside the approved organization workspace rather than applying publicly.</p>
                  <p v-else>Your organization is approved. Operational setup is now unlocked; employees will be invited from inside this organization workspace rather than applying publicly.</p>
                </div>
              </div>
            </aside>
          </div>
        </section>
      </template>
    </div>
  </section>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import { fetchOrganizationApplicationStatus } from '@/services/organizationApplications'
import { useSession } from '@/composables/useSession'

const { fetchSession } = useSession()
const loading = ref(true)
const errorMessage = ref('')
const organization = ref(null)
const serviceAreas = ref([])
const documents = ref([])

const typeMap = {
  city_rescue: 'City Rescue / LGU Unit',
  barangay_rescue: 'Barangay Rescue Unit',
  hospital: 'Hospital Ambulance Service',
  private_ambulance: 'Private Ambulance / Rescue Service',
  other: 'Other Rescue Organization',
}
const typeLabel = value => typeMap[value] || value || '—'

const statusLabel = computed(() => {
  if (organization.value?.status === 'suspended') return 'Suspended'
  const status = organization.value?.application_status
  if (status === 'approved') return 'Approved'
  if (status === 'revision_requested') return 'Revision Requested'
  if (status === 'rejected') return 'Rejected'
  return 'Pending Review'
})
const statusIcon = computed(() => {
  if (organization.value?.status === 'suspended') return 'lucide:shield-alert'
  if (organization.value?.application_status === 'approved') return 'lucide:badge-check'
  if (organization.value?.application_status === 'revision_requested') return 'lucide:rotate-ccw'
  if (organization.value?.application_status === 'rejected') return 'lucide:x-circle'
  return 'lucide:hourglass'
})
const statusIconClass = computed(() => {
  if (organization.value?.application_status === 'approved') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
  if (organization.value?.application_status === 'revision_requested') return 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
  if (organization.value?.application_status === 'rejected' || organization.value?.status === 'suspended') return 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'
  return 'bg-[#1976D2]/10 text-[#1976D2] dark:text-[#7fb3ec]'
})
const statusBadgeClass = computed(() => {
  if (organization.value?.application_status === 'approved') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
  if (organization.value?.application_status === 'revision_requested') return 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
  if (organization.value?.application_status === 'rejected' || organization.value?.status === 'suspended') return 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'
  return 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300'
})

const TimelineItem = defineComponent({
  props: { active: Boolean, icon: String, title: String, text: String },
  setup(props) {
    return () => h('div', { class: 'flex gap-3' }, [
      h('span', { class: ['grid h-9 w-9 shrink-0 place-items-center rounded-xl', props.active ? 'bg-[#1976D2]/10 text-[#1976D2] dark:text-[#7fb3ec]' : 'bg-base-200 text-slate-300 dark:bg-white/5 dark:text-white/20'] }, [h(Icon, { icon: props.icon, width: 16 })]),
      h('div', {}, [h('p', { class: 'text-xs font-black text-slate-800 dark:text-white/70' }, props.title), h('p', { class: 'mt-0.5 text-[0.7rem] leading-5 text-slate-400 dark:text-white/30' }, props.text)]),
    ])
  },
})
const InfoCard = defineComponent({
  props: { label: String, value: String, icon: String },
  setup(props, { attrs }) {
    return () => h('div', { class: ['rounded-2xl border border-base-300 p-4 dark:border-white/10', attrs.class] }, [
      h('div', { class: 'flex items-center gap-2 text-slate-400 dark:text-white/30' }, [h(Icon, { icon: props.icon, width: 14 }), h('span', { class: 'text-[0.65rem] font-black uppercase tracking-wide' }, props.label)]),
      h('p', { class: 'mt-2 text-sm font-semibold leading-6 text-slate-700 dark:text-white/60' }, props.value),
    ])
  },
})

const documentLabel = type => ({ registration_document: 'Registration / authorization', operating_authority: 'Operating / accreditation', supporting_document: 'Additional supporting document' }[type] || type)
const documentStatusClass = status => status === 'approved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : status === 'rejected' ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
const formatDate = value => value ? new Date(value.replace(' ', 'T')).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '—'
const formatFileSize = bytes => {
  const value = Number(bytes || 0)
  if (!value) return '—'
  if (value < 1024 * 1024) return `${Math.max(1, Math.round(value / 1024))} KB`
  return `${(value / 1024 / 1024).toFixed(1)} MB`
}

async function loadStatus() {
  loading.value = true
  errorMessage.value = ''
  try {
    const data = await fetchOrganizationApplicationStatus()
    organization.value = data.organization
    serviceAreas.value = data.service_areas || []
    documents.value = data.documents || []
    await fetchSession(true)
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || 'Could not load the organization application.'
  } finally {
    loading.value = false
  }
}

onMounted(loadStatus)
</script>
