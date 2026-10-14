<template>
  <div class="min-h-screen bg-base-200 dark:bg-[#050e1a]">
    <!-- Top bar -->
    <!-- Same app bar as AppShell (citizen); this is the organization/barangay
         staff version (Phase 4). -->
    <header class="sticky top-0 z-30 border-b bg-base-200/90 pt-[var(--safe-top)] backdrop-blur transition-colors dark:bg-[#050e1a]/90"
      :class="scrolled ? 'border-base-300/70 dark:border-white/10' : 'border-transparent'">
      <div class="flex h-16 items-center justify-between gap-3 px-4">
        <div v-if="route.meta.tab === 'home'" class="min-w-0">
          <BrandLockup compact />
        </div>
        <h1 v-else class="text-lg font-black tracking-tight text-slate-950 transition-opacity duration-200 dark:text-white" :class="scrolled ? 'opacity-100' : 'opacity-0'" :aria-hidden="!scrolled">{{ route.meta.title }}</h1>
        <button type="button" class="tap grid h-10 w-10 place-items-center rounded-xl text-amber-500 dark:text-[#7fb3ec]" aria-label="Toggle theme" @click="toggleTheme">
          <Icon :icon="theme === 'dark' ? 'lucide:moon' : 'lucide:sun'" width="21" />
        </button>
      </div>
    </header>

    <!-- Tab content (kept alive so switching tabs keeps scroll/state) -->
    <main class="pb-[calc(var(--safe-bottom)+6rem)]">
      <h1 v-if="route.meta.tab !== 'home'" class="px-5 pb-1 pt-1 text-[2rem] font-black leading-tight tracking-tight text-slate-950 dark:text-white">{{ route.meta.title }}</h1>
      <RouterView v-slot="{ Component, route: child }">
        <KeepAlive>
          <component :is="Component" :key="child.name" />
        </KeepAlive>
      </RouterView>
    </main>

    <!-- Bottom bar — same look as the citizen app, without the SOS button:
         staff respond to emergencies, they don't request them. Tabs depend on
         the member's permissions (config/staffSections.js). -->
    <nav class="fixed bottom-0 left-0 z-40 w-full border-t border-slate-200 bg-white pb-[var(--safe-bottom)] shadow-[0_-4px_20px_rgba(0,0,0,0.08)] dark:border-white/10 dark:bg-[#050e1a]">
      <ul class="grid items-end px-1 pt-2 pb-1.5" :style="{ gridTemplateColumns: `repeat(${tabs.length}, minmax(0, 1fr))` }">
        <li v-for="tab in tabs" :key="tab.name"><TabLink :tab="tab" /></li>
      </ul>
    </nav>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref } from 'vue'
import { Haptics, ImpactStyle } from '@capacitor/haptics'
import { RouterLink, useRoute } from 'vue-router'
import { Icon } from '@iconify/vue'
import BrandLockup from '@/components/BrandLockup.vue'
import { useTheme } from '@/composables/useTheme'
import { useUnreadCount } from '@/composables/useUnreadCount'
import { useLiveUpdates } from '@/composables/useLiveUpdates'
import { realtimeChannels } from '@/services/realtime'
import { useSession } from '@/composables/useSession'
import { fetchOrganizationMissions } from '@/services/org/dispatchOperations'
import { resumeSharingIfActive } from '@/services/missionGps'
import { ACTIVE_MISSION } from '@/utils/missionStatus'

const route = useRoute()
const { theme, toggleTheme } = useTheme()
const unread = useUnreadCount()

// Unread badge on the Alerts tab: updates the moment a notification arrives
// or is read elsewhere (live updates), refreshes on app resume, and polls
// every 30 s while live updates are unavailable.
useLiveUpdates(() => unread.refresh(), { channels: () => [realtimeChannels.value?.user], events: ['notification.created', 'notification.read'], pollMs: 30000, livePollMs: 120000 })
onMounted(() => unread.refresh())

// App restarted mid-mission: resume GPS sharing if that mission is still
// active and this member is on its crew (services/missionGps.js).
onMounted(async () => {
  try {
    const d = await fetchOrganizationMissions()
    const mine = (d.missions || []).filter((m) => ACTIVE_MISSION.includes(m.assignment_status) && (m.crew || []).some((c) => c.organization_member_id === d.current_member_id))
    await resumeSharingIfActive(mine.map((m) => m.id))
  } catch { /* offline: try next launch */ }
})

// Large title → app-bar title once the page scrolls.
const scrolled = ref(false)
const onScroll = () => { scrolled.value = window.scrollY > 28 }
onMounted(() => { onScroll(); window.addEventListener('scroll', onScroll, { passive: true }) })
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))

// A light tick on tab changes, a firmer one on SOS (no-op in the browser).
function buzz(style = ImpactStyle.Light) { Haptics.impact({ style }).catch(() => {}) }

const { hasPermission } = useSession()
// Bottom tabs for organization/barangay staff. A tab whose section needs a
// permission the member lacks is left out.
const ALL_TABS = [
  { name: 'StaffHome', tab: 'home', label: 'Home', icon: 'lucide:house' },
  { name: 'StaffMissions', tab: 'missions', label: 'Missions', icon: 'lucide:route', permissions: ['dispatch.dispatch_assignments.read'] },
  { name: 'StaffOffers', tab: 'offers', label: 'Offers', icon: 'lucide:inbox', permissions: ['dispatch.dispatch_assignments.read', 'dispatch.dispatch_assignments.approve'] },
  { name: 'StaffAlerts', tab: 'notifications', label: 'Alerts', icon: 'lucide:bell' },
  { name: 'StaffMore', tab: 'more', label: 'More', icon: 'lucide:layout-grid' },
]
const tabs = computed(() => ALL_TABS.filter((t) => t.built !== false && (!t.permissions || hasPermission(...t.permissions))))

const TabLink = defineComponent({
  props: { tab: Object },
  setup(props) {
    return () => {
      const active = route.meta.tab === props.tab.tab
      return h(RouterLink, {
        to: { name: props.tab.name },
        replace: true,
        onClick: () => { if (!active) buzz() },
        class: ['tap flex flex-col items-center gap-1 py-1 no-underline transition-colors', active ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-white/50'],
      }, () => [
        h('span', { class: ['relative flex h-8 w-14 items-center justify-center rounded-full transition-colors duration-200', active ? 'bg-red-50 dark:bg-red-500/15' : ''] }, [
          h(Icon, { icon: props.tab.icon, width: 22 }),
          props.tab.tab === 'notifications' && unread.count.value > 0
            ? h('span', { class: 'absolute -right-2 -top-1.5 grid h-4 min-w-4 place-items-center rounded-full bg-red-600 px-1 text-[9px] font-black leading-none text-white ring-2 ring-white dark:ring-[#050e1a]' }, unread.count.value > 9 ? '9+' : String(unread.count.value))
            : null,
        ]),
        h('span', { class: 'text-[10px] font-semibold' }, props.tab.label),
      ])
    }
  },
})
</script>
