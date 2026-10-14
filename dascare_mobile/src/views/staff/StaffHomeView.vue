<template>
  <!-- Staff home (organization/barangay, Phase 4): who/where you are, what
       needs you now (missions to acknowledge), and the organization at a
       glance. Same data as the web's organization overview. -->
  <PullToRefresh :on-refresh="load">
    <div class="space-y-4 px-4 pt-4">
      <div>
        <p class="text-sm text-slate-500 dark:text-white/45">{{ greeting }},</p>
        <h2 class="text-2xl font-black tracking-tight text-slate-950 dark:text-white">{{ firstName }}</h2>
        <p class="mt-0.5 truncate text-xs font-semibold text-slate-500 dark:text-white/45">
          {{ organization?.name || 'Your organization' }} · {{ roleLabel }}
        </p>
      </div>

      <!-- Organization not active (pending approval / suspended) -->
      <div v-if="organization && organization.status && organization.status !== 'active'" :class="[ui.card, 'border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/5']">
        <p class="flex items-center gap-2 text-sm font-black text-amber-800 dark:text-amber-200"><Icon icon="lucide:triangle-alert" width="16" /> Organization {{ organization.status }}</p>
        <p class="mt-1 text-xs leading-5 text-amber-900/70 dark:text-amber-100/60">Incident offers and missions start once the platform approves and activates your organization.</p>
      </div>

      <!-- Needs you: assigned missions not yet acknowledged -->
      <section v-for="m in pending" :key="m.id" class="relative overflow-hidden rounded-3xl border border-red-200 bg-base-100 p-5 shadow-sm dark:border-red-500/20 dark:bg-[#071829]">
        <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-red-500/10 blur-3xl"></div>
        <p class="relative flex items-center gap-1.5 text-[0.65rem] font-black uppercase tracking-wider text-red-600 dark:text-red-300">
          <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500"></span> New mission · you're the {{ roleText(m.my_role) }}
        </p>
        <p class="relative mt-2 font-mono text-xs font-semibold text-slate-500 dark:text-white/45">{{ m.reference_number }} · Unit {{ m.unit_code }}</p>
        <p class="relative mt-0.5 text-base font-bold text-slate-900 dark:text-white">{{ m.category_name }} · <span class="capitalize">{{ m.severity }}</span></p>
        <p class="relative mt-0.5 text-sm text-slate-600 dark:text-white/60">{{ m.address_text }}<template v-if="m.barangay">, {{ m.barangay }}</template></p>
        <button type="button" :disabled="acking === m.id" :class="[ui.primaryButton, 'relative mt-4']" @click="acknowledge(m)">
          <Icon :icon="acking === m.id ? 'lucide:loader-circle' : 'lucide:check-circle-2'" width="18" :class="acking === m.id ? 'animate-spin' : ''" /> Acknowledge mission
        </button>
      </section>

      <!-- At a glance -->
      <div class="grid grid-cols-3 gap-3">
        <component :is="t.to ? 'RouterLink' : 'div'" v-for="t in tiles" :key="t.label" :to="t.to"
          :class="[ui.card, 'tap block p-3.5 no-underline']">
          <Icon :icon="t.icon" width="18" :class="t.accent" />
          <p class="mt-2 text-2xl font-black tabular-nums text-slate-950 dark:text-white">{{ t.value ?? '—' }}</p>
          <p class="text-[0.68rem] font-semibold leading-4 text-slate-500 dark:text-white/45">{{ t.label }}</p>
        </component>
      </div>

      <!-- Sections -->
      <section :class="[ui.card, 'overflow-hidden']">
        <div class="flex items-center justify-between px-5 pb-1 pt-4">
          <h3 class="text-sm font-black text-slate-900 dark:text-white">Quick access</h3>
          <RouterLink :to="{ name: 'StaffMore' }" replace class="py-1 text-xs font-bold text-red-600 no-underline dark:text-red-300">All sections</RouterLink>
        </div>
        <div class="grid grid-cols-4 gap-1 p-3">
          <component :is="s.route ? 'RouterLink' : 'div'" v-for="s in quick" :key="s.key" :to="s.route || undefined"
            class="tap flex flex-col items-center gap-1.5 rounded-2xl px-1 py-3 text-center no-underline" :class="s.route ? '' : 'opacity-40'">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon :icon="s.icon" width="20" /></span>
            <span class="text-[0.65rem] font-semibold leading-tight text-slate-600 dark:text-white/60">{{ s.label }}</span>
          </component>
        </div>
      </section>
    </div>
  </PullToRefresh>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import PullToRefresh from '@/components/PullToRefresh.vue'
import * as ui from '@/components/ui/styles'
import { apiMessage } from '@/services/api'
import { fetchMyPendingMissions, fetchOrganizationMissions, fetchOrganizationOffers, updateMissionStatus } from '@/services/org/dispatchOperations'
import { fetchFleetUnits } from '@/services/org/organizationFleet'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useLiveUpdates } from '@/composables/useLiveUpdates'
import { realtimeChannels } from '@/services/realtime'
import { STAFF_SECTIONS, STAFF_SECTION_PARAMS, STAFF_SECTION_ROUTES } from '@/config/staffSections'

const { user, organization, hasPermission, isOrgAdmin } = useSession()
const toast = useToast()

const pending = ref([])
const counts = ref({ offers: null, missions: null, available: null })
const acking = ref(null)

const firstName = computed(() => (user.value?.name || '').split(' ')[0] || 'there')
const greeting = computed(() => { const h = new Date().getHours(); return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening' })
const roleLabel = computed(() => (isOrgAdmin.value ? 'Organization admin' : (organization.value?.roleNames || organization.value?.role_names || []).join(' · ') || 'Staff'))
const roleText = (r) => String(r || 'crew').replace('_', ' ')

const tiles = computed(() => [
  hasPermission('dispatch.dispatch_assignments.read') && { label: 'Open offers', value: counts.value.offers, icon: 'lucide:inbox', accent: 'text-amber-500', to: STAFF_SECTION_ROUTES.offers ? { name: STAFF_SECTION_ROUTES.offers } : null },
  hasPermission('dispatch.dispatch_assignments.read') && { label: 'Active missions', value: counts.value.missions, icon: 'lucide:route', accent: 'text-blue-600 dark:text-blue-400', to: STAFF_SECTION_ROUTES.missions ? { name: STAFF_SECTION_ROUTES.missions } : null },
  hasPermission('fleet.ambulances.read') && { label: 'Units available', value: counts.value.available, icon: 'lucide:ambulance', accent: 'text-emerald-600 dark:text-emerald-400', to: STAFF_SECTION_ROUTES.fleet ? { name: STAFF_SECTION_ROUTES.fleet } : null },
].filter(Boolean))

// First 8 sections this member can open.
const quick = computed(() => STAFF_SECTIONS.flatMap((g) => g.items)
  .filter((s) => hasPermission(...s.permissions))
  .map((s) => ({ ...s, route: STAFF_SECTION_PARAMS[s.key] || (STAFF_SECTION_ROUTES[s.key] ? { name: STAFF_SECTION_ROUTES[s.key] } : null) }))
  .slice(0, 8))

async function load() {
  const jobs = [fetchMyPendingMissions().then((m) => { pending.value = m }).catch(() => {})]
  if (hasPermission('dispatch.dispatch_assignments.read')) {
    jobs.push(fetchOrganizationOffers().then((d) => { counts.value.offers = (d.offers || []).filter((o) => o.offer_status === 'sent').length }).catch(() => {}))
    jobs.push(fetchOrganizationMissions().then((d) => { counts.value.missions = (d.missions || []).filter((m) => !['completed', 'cancelled', 'reassigned'].includes(m.assignment_status)).length }).catch(() => {}))
  }
  if (hasPermission('fleet.ambulances.read')) {
    jobs.push(fetchFleetUnits().then((d) => { counts.value.available = d.stats?.available ?? (d.units || []).filter((u) => u.status === 'available').length }).catch(() => {}))
  }
  await Promise.all(jobs)
}

async function acknowledge(m) {
  acking.value = m.id
  try {
    await updateMissionStatus(m.id, 'acknowledged')
    toast.success(`${m.reference_number} acknowledged. Head out when ready.`, 'Mission acknowledged')
    await load()
  } catch (err) {
    toast.error(apiMessage(err, 'Unable to acknowledge the mission.'), 'Error')
  } finally {
    acking.value = null
  }
}

// New offers / missions / fleet changes arrive on the org channel, new
// assignments as a notification on the member's own channel.
useLiveUpdates(load, { channels: () => [realtimeChannels.value?.user, realtimeChannels.value?.org], onEvent: (msg) => (msg.name === 'ambulance.location' || msg.name === 'route.updated' ? false : undefined) })
onMounted(load)
</script>
