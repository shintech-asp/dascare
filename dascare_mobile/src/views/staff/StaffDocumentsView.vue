<template>
  <!-- Documents & Credentials (staff, Phase 4) — the web's page: the
       organization's application documents and each ambulance's
       registration/inspection dates with expiry alerts. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader title="Documents & Credentials" fallback="/staff/more" />
    <main class="space-y-3 px-4 pt-3">
      <div class="grid grid-cols-4 gap-2">
        <div v-for="s in statTiles" :key="s.label" :class="[ui.card, 'p-2.5 text-center']">
          <p class="text-lg font-black text-slate-950 dark:text-white">{{ s.value }}</p>
          <p class="text-[0.6rem] font-semibold leading-tight text-slate-500 dark:text-white/45">{{ s.label }}</p>
        </div>
      </div>
      <p class="px-2 pt-1 text-[0.68rem] font-black uppercase tracking-[0.16em] text-slate-400 dark:text-white/35">Organization documents</p>
      <section :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
        <div v-for="d in documents" :key="d.id" class="p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0"><p class="text-sm font-bold capitalize text-slate-900 dark:text-white">{{ pretty(d.doc_type) }}</p><p class="truncate text-xs text-slate-500 dark:text-white/45">{{ d.original_name || 'Submitted application file' }}</p></div>
            <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[0.62rem] font-black uppercase" :class="d.status === 'approved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : d.status === 'rejected' ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'">{{ d.status }}</span>
          </div>
          <p v-if="d.rejection_reason" class="mt-1 text-xs text-red-600 dark:text-red-300">{{ d.rejection_reason }}</p>
        </div>
        <p v-if="loaded && !documents.length" class="p-6 text-center text-sm text-slate-500">No organization documents recorded.</p>
      </section>
      <p class="px-2 pt-2 text-[0.68rem] font-black uppercase tracking-[0.16em] text-slate-400 dark:text-white/35">Fleet credentials</p>
      <section :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
        <div v-for="u in fleet" :key="u.id" class="p-4">
          <p class="text-sm font-bold text-slate-900 dark:text-white">{{ u.unit_code }} · {{ u.plate_number }}</p>
          <div class="mt-1 grid grid-cols-2 gap-2 text-xs text-slate-500 dark:text-white/45"><span>Registration<br><b class="text-slate-700 dark:text-white/70">{{ u.registration_expiry || 'Not recorded' }}</b></span><span>Inspection<br><b class="text-slate-700 dark:text-white/70">{{ u.inspection_expiry || 'Not recorded' }}</b></span></div>
          <p v-for="a in u.credential_alerts || []" :key="a.label" class="mt-1 text-xs font-bold" :class="a.expired ? 'text-red-600 dark:text-red-300' : 'text-amber-600 dark:text-amber-300'">{{ a.label }} {{ a.expired ? 'expired' : 'expires soon' }} · {{ a.date }}</p>
        </div>
        <p v-if="loaded && !fleet.length" class="p-6 text-center text-sm text-slate-500">No fleet credentials yet.</p>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import ScreenHeader from '@/components/ScreenHeader.vue'
import * as ui from '@/components/ui/styles'
import { fetchOrganizationDocuments } from '@/services/org/organizationCompletion'

const documents = ref([])
const fleet = ref([])
const stats = ref({})
const loaded = ref(false)
const pretty = (v) => String(v || '').replaceAll('_', ' ')
const statTiles = computed(() => [{ label: 'Documents', value: stats.value.documents ?? 0 }, { label: 'Approved', value: stats.value.approved ?? 0 }, { label: 'Pending', value: stats.value.pending ?? 0 }, { label: 'Alerts', value: stats.value.credential_alerts ?? 0 }])
onMounted(async () => {
  try { const d = await fetchOrganizationDocuments(); documents.value = d.documents || []; fleet.value = d.fleet_credentials || []; stats.value = d.stats || {} } finally { loaded.value = true }
})
</script>
