<template>
  <!-- Audit Log (staff, Phase 4) — the web's organization audit log:
       membership, role, fleet, dispatch and organization changes. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader title="Audit Log" fallback="/staff/more" />
    <main class="space-y-3 px-4 pt-3">
      <form class="relative" @submit.prevent="load">
        <Icon icon="lucide:search" width="18" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input v-model="search" type="search" :class="[ui.input, 'pl-10']" placeholder="Search activity…" />
      </form>
      <div v-if="loading" class="space-y-2"><div v-for="n in 4" :key="n" :class="[ui.card, 'h-16 animate-pulse']"></div></div>
      <section v-else :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
        <div v-for="i in merged" :key="`${i.source}-${i.id}`" class="p-4">
          <p class="text-sm font-bold capitalize text-slate-900 dark:text-white">{{ pretty(i.action) }}</p>
          <p class="mt-0.5 text-xs text-slate-500 dark:text-white/45">{{ i.actor_name || 'System' }} · <span class="capitalize">{{ pretty(i.entity_type) }}</span> · {{ format(i.created_at) }}</p>
        </div>
        <p v-if="!merged.length" class="p-8 text-center text-sm text-slate-500">No matching activity.</p>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import ScreenHeader from '@/components/ScreenHeader.vue'
import * as ui from '@/components/ui/styles'
import { fetchOrganizationAudit } from '@/services/org/organizationCompletion'

const search = ref('')
const items = ref([])
const rbac = ref([])
const loading = ref(true)
const merged = computed(() => [...items.value.map((x) => ({ ...x, source: 'audit' })), ...rbac.value.map((x) => ({ ...x, source: 'rbac' }))]
  .sort((a, b) => new Date(String(b.created_at).replace(' ', 'T')) - new Date(String(a.created_at).replace(' ', 'T'))).slice(0, 100))
const pretty = (v) => String(v || '').replaceAll('.', ' › ').replaceAll('_', ' ')
const format = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—')
async function load() {
  loading.value = true
  try { const d = await fetchOrganizationAudit({ search: search.value, page: 1 }); items.value = d.items || []; rbac.value = d.rbac_items || [] } finally { loading.value = false }
}
onMounted(load)
</script>
