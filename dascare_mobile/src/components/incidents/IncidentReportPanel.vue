<template>
  <!-- What the requester sent, for responders: witness or the person in need,
       the description, photos (incl. linked reports') and — for a citizen's
       own request — their medical information. dascare_api/reusables/incident_report.php.
       Same file in dascare/src/components/incidents/IncidentReportPanel.vue (web) — keep in sync. -->
  <section class="rounded-2xl border border-base-300 bg-base-200/35 p-4 dark:border-white/10 dark:bg-white/[.02]">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="text-sm font-black text-slate-900 dark:text-white">Requester's report</h3>
      <span v-if="report" class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[0.62rem] font-black uppercase"
        :class="report.reporter_type === 'witness' ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-white/60'">
        <Icon :icon="report.reporter_type === 'witness' ? 'lucide:eye' : 'lucide:user'" width="12" />
        {{ report.reporter_type === 'witness' ? 'Witness report' : 'Person in need' }}
      </span>
    </div>

    <div v-if="loading && !report" class="mt-3 h-16 animate-pulse rounded-xl bg-base-200 dark:bg-white/5"></div>
    <p v-else-if="error" class="mt-3 text-xs text-red-600">{{ error }}</p>
    <template v-else-if="report">
      <p v-if="report.reporter_type === 'witness'" class="mt-2 text-xs leading-5 text-amber-800 dark:text-amber-200/80">
        Sent by someone who saw the emergency — the patient may not be the caller. Confirm on scene.
      </p>
      <p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-slate-700 dark:text-white/70">{{ report.description }}</p>

      <div v-if="report.photos.length" class="mt-3 flex flex-wrap gap-2">
        <button v-for="p in report.photos" :key="p.id" type="button" class="h-20 w-20 overflow-hidden rounded-xl border border-base-300 bg-base-200 dark:border-white/10 dark:bg-white/5" @click="open(p)">
          <img v-if="urls[p.id]" :src="urls[p.id]" alt="Photo from the requester" class="h-full w-full object-cover" />
          <Icon v-else icon="lucide:image" width="20" class="m-auto mt-7 text-slate-400" />
        </button>
      </div>
      <p v-else class="mt-2 text-xs text-slate-400">No photos attached.</p>

      <!-- Medical information (citizen's own request) -->
      <div v-if="report.medical" class="mt-3 rounded-xl border border-red-200 bg-red-50/60 p-3 text-xs dark:border-red-500/20 dark:bg-red-500/5">
        <p class="flex items-center gap-1.5 font-black text-red-800 dark:text-red-200"><Icon icon="lucide:heart-pulse" width="14" /> Patient's emergency information</p>
        <dl class="mt-2 grid gap-x-4 gap-y-1 sm:grid-cols-2">
          <template v-for="row in medicalRows" :key="row.label">
            <div v-if="row.value" class="min-w-0"><dt class="inline font-bold text-slate-600 dark:text-white/55">{{ row.label }}: </dt><dd class="inline break-words text-slate-700 dark:text-white/70">{{ row.value }}</dd></div>
          </template>
        </dl>
      </div>

      <!-- Linked reports (other people reporting the same emergency) -->
      <div v-for="l in report.linked" :key="l.id" class="mt-3 border-t border-base-300 pt-3 dark:border-white/10">
        <p class="flex flex-wrap items-center gap-2 text-xs font-bold text-slate-600 dark:text-white/55">
          Linked report <span class="font-mono">{{ l.reference_number }}</span>
          <span v-if="l.reporter_type === 'witness'" class="rounded-full bg-amber-100 px-2 py-0.5 text-[0.6rem] font-black uppercase text-amber-800 dark:bg-amber-500/15 dark:text-amber-200">Witness</span>
        </p>
        <p class="mt-1 whitespace-pre-line break-words text-xs text-slate-600 dark:text-white/60">{{ l.description }}</p>
        <div v-if="l.photos.length" class="mt-2 flex flex-wrap gap-2">
          <button v-for="p in l.photos" :key="p.id" type="button" class="h-16 w-16 overflow-hidden rounded-xl border border-base-300 bg-base-200 dark:border-white/10 dark:bg-white/5" @click="open(p)">
            <img v-if="urls[p.id]" :src="urls[p.id]" alt="Photo from a linked report" class="h-full w-full object-cover" />
          </button>
        </div>
      </div>
    </template>

    <!-- Full-size photo -->
    <Teleport to="body">
      <div v-if="viewing" class="fixed inset-0 z-[10070] flex items-center justify-center bg-black/90 p-4" @click="viewing = null">
        <img :src="urls[viewing.id]" alt="Photo from the requester" class="max-h-full max-w-full rounded-xl object-contain" />
        <button type="button" class="absolute right-4 top-[calc(var(--safe-top,0px)+1rem)] grid h-11 w-11 place-items-center rounded-full bg-white/15 text-white" aria-label="Close photo"><Icon icon="lucide:x" width="22" /></button>
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import api from '@/services/api'

const props = defineProps({
  requestId: { type: [Number, String], required: true },
  scope: { type: String, default: 'organization' }, // 'organization' | 'platform'
})
const report = ref(null), loading = ref(false), error = ref(''), viewing = ref(null)
const urls = reactive({})
const base = computed(() => (props.scope === 'platform' ? '/platform/incidents' : '/organizations/incidents'))

const medicalRows = computed(() => {
  const m = report.value?.medical
  if (!m) return []
  return [
    { label: 'Blood type', value: m.blood_type },
    { label: 'Allergies', value: m.allergies },
    { label: 'Medications', value: m.medications },
    { label: 'Conditions', value: m.medical_conditions },
    { label: 'Mobility / disability', value: m.disabilities_mobility_notes },
    { label: 'Emergency contact', value: [m.emergency_contact_name, m.emergency_contact_relationship && `(${m.emergency_contact_relationship})`, m.emergency_contact_phone].filter(Boolean).join(' ') },
    { label: 'Notes', value: m.additional_notes },
  ]
})

// Photos come through the API (cookie on the web, bearer token in the app), so
// they're fetched as blobs rather than linked directly.
async function loadPhoto(p) {
  if (urls[p.id]) return
  try {
    const { data } = await api.get(`${base.value}/media.php`, { params: { id: p.id }, responseType: 'blob' })
    urls[p.id] = URL.createObjectURL(data)
  } catch { /* leave the placeholder */ }
}
async function load() {
  if (!props.requestId) return
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get(`${base.value}/report.php`, { params: { request_id: props.requestId } })
    report.value = data.report
    for (const p of [...data.report.photos, ...data.report.linked.flatMap((l) => l.photos)]) loadPhoto(p)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Unable to load the report.'
  } finally {
    loading.value = false
  }
}
function open(p) { if (urls[p.id]) viewing.value = p }

watch(() => props.requestId, () => { report.value = null; load() }, { immediate: true })
onBeforeUnmount(() => { for (const u of Object.values(urls)) URL.revokeObjectURL(u) })
defineExpose({ load })
</script>
