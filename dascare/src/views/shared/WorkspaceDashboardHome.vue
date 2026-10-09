<template>
  <section class="min-h-screen bg-slate-50 pb-12 dark:bg-[#081b2e]">
    <div class="mx-auto max-w-[1500px] px-4 py-7 sm:px-6 lg:px-8">
      <div class="relative overflow-hidden rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-8">
        <div class="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full bg-red-500/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-28 right-40 h-56 w-56 rounded-full bg-blue-500/10 blur-3xl"></div>

        <div class="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
          <div class="max-w-3xl">
            <div class="mb-4 flex flex-wrap items-center gap-2">
              <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-[0.7rem] font-black uppercase tracking-[0.12em] text-red-700 dark:bg-red-500/10 dark:text-red-300">
                <span class="h-2 w-2 rounded-full bg-red-500"></span>
                {{ config.eyebrow }}
              </span>
              <span class="rounded-full border border-slate-200 px-3 py-1 text-[0.7rem] font-bold text-slate-500 dark:border-white/10 dark:text-white/45">Dashboard preview</span>
            </div>
            <h1 class="text-3xl font-black tracking-tight text-slate-950 dark:text-white sm:text-4xl">{{ config.title }}</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500 dark:text-white/50 sm:text-base">{{ config.description }}</p>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/[0.04]">
              <p class="text-[0.65rem] font-black uppercase tracking-[0.12em] text-slate-400 dark:text-white/30">Signed in as</p>
              <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ user?.roleLabel }}</p>
            </div>
            <div v-if="organization?.name" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/[0.04]">
              <p class="text-[0.65rem] font-black uppercase tracking-[0.12em] text-slate-400 dark:text-white/30">Organization</p>
              <p class="mt-1 max-w-48 truncate text-sm font-bold text-slate-900 dark:text-white">{{ organization.name }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article v-for="stat in config.stats" :key="stat.label" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition-transform hover:-translate-y-0.5 dark:border-white/10 dark:bg-[#0d2943]">
          <div class="flex items-start justify-between gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-600 dark:bg-white/[0.06] dark:text-white/60">
              <Icon :icon="stat.icon" width="21" />
            </div>
            <span class="rounded-full bg-slate-100 px-2 py-1 text-[0.62rem] font-bold uppercase tracking-wide text-slate-400 dark:bg-white/5 dark:text-white/30">Placeholder</span>
          </div>
          <p class="mt-5 text-3xl font-black text-slate-950 dark:text-white">—</p>
          <p class="mt-1 text-sm font-bold text-slate-700 dark:text-white/70">{{ stat.label }}</p>
          <p class="mt-1 text-xs leading-5 text-slate-400 dark:text-white/35">{{ stat.hint }}</p>
        </article>
      </div>

      <div class="mt-6 grid gap-6 xl:grid-cols-[1.4fr_0.8fr]">
        <section class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-6">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">Workspace modules</p>
              <h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">Ready for implementation</h2>
            </div>
            <Icon icon="lucide:panels-top-left" width="22" class="text-slate-300 dark:text-white/20" />
          </div>

          <div class="mt-5 grid gap-3 md:grid-cols-2">
            <RouterLink v-for="item in quickLinks" :key="item.to" :to="item.to" class="group flex items-center gap-3 rounded-2xl border border-slate-200 p-4 no-underline transition-all hover:border-red-200 hover:bg-red-50/50 dark:border-white/10 dark:hover:border-red-500/20 dark:hover:bg-red-500/5">
              <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition-colors group-hover:bg-red-100 group-hover:text-red-600 dark:bg-white/5 dark:text-white/40 dark:group-hover:bg-red-500/10 dark:group-hover:text-red-300">
                <Icon :icon="item.icon" width="19" />
              </span>
              <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-bold text-slate-800 dark:text-white/75">{{ item.label }}</span>
                <span class="mt-0.5 block text-xs text-slate-400 dark:text-white/30">Open placeholder page</span>
              </span>
              <Icon icon="lucide:arrow-up-right" width="16" class="text-slate-300 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-red-500 dark:text-white/20" />
            </RouterLink>
          </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-6">
          <p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">Implementation status</p>
          <h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">Dashboard foundation</h2>

          <div class="mt-5 space-y-4">
            <div v-for="step in config.foundation" :key="step.label" class="flex gap-3">
              <div class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl" :class="step.done ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-white/30'">
                <Icon :icon="step.done ? 'lucide:check' : 'lucide:clock-3'" width="16" />
              </div>
              <div>
                <p class="text-sm font-bold text-slate-800 dark:text-white/75">{{ step.label }}</p>
                <p class="mt-0.5 text-xs leading-5 text-slate-400 dark:text-white/35">{{ step.note }}</p>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useSession } from '@/composables/useSession'
import { dashboardWorkspaces } from '@/config/dashboardNavigation'

const props = defineProps({ workspaceKey: { type: String, required: true } })
const { user, organization, hasAnyPermission, hasAllPermissions } = useSession()

const workspace = dashboardWorkspaces[props.workspaceKey]
const configs = {
  technical: {
    ...workspace,
    stats: [
      { label: 'Platform Services', icon: 'lucide:server', hint: 'API, database, mail, and map services.' },
      { label: 'Open System Alerts', icon: 'lucide:triangle-alert', hint: 'Errors and service warnings requiring review.' },
      { label: 'Latest Backup', icon: 'lucide:database-backup', hint: 'Backup readiness and recovery status.' },
      { label: 'Security Events', icon: 'lucide:shield-check', hint: 'Recent authentication and access activity.' },
    ],
  },
  platform: {
    ...workspace,
    stats: [
      { label: 'Pending Applications', icon: 'lucide:building-2', hint: 'Organizations awaiting city-level review.' },
      { label: 'Active Incidents', icon: 'lucide:siren', hint: 'City-wide incidents currently in progress.' },
      { label: 'Available Organizations', icon: 'lucide:badge-check', hint: 'Approved organizations accepting requests.' },
      { label: 'Compliance Alerts', icon: 'lucide:file-warning', hint: 'Credentials or documents needing attention.' },
    ],
  },
  organization: {
    ...workspace,
    stats: [
      { label: 'Active Missions', icon: 'lucide:route', hint: 'Assignments currently being handled.' },
      { label: 'Available Ambulances', icon: 'lucide:ambulance', hint: 'Units currently eligible for assignment.' },
      { label: 'On-duty Personnel', icon: 'lucide:users-round', hint: 'Members marked available for operations.' },
      { label: 'Pending Offers', icon: 'lucide:inbox', hint: 'Timed incident offers awaiting action.' },
    ],
  },
}

const config = computed(() => ({
  ...configs[props.workspaceKey],
  foundation: [
    { label: 'Role-based routing', note: 'Each fixed account type lands in the correct workspace.', done: true },
    { label: 'Responsive dashboard layout', note: 'Sidebar, topbar, mobile navigation, and dark mode are ready.', done: true },
    { label: 'Permission-aware navigation', note: 'Organization staff tabs follow their RBAC permissions.', done: true },
    { label: 'Operational data endpoints', note: 'Connect each placeholder module as its backend workflow is built.', done: false },
  ],
}))

const canSee = (item) => {
  if (!item.permissions?.length) return true
  return item.permissionMode === 'all'
    ? hasAllPermissions(...item.permissions)
    : hasAnyPermission(...item.permissions)
}

const quickLinks = computed(() => workspace.navGroups
  .flatMap((group) => group.items)
  .filter((item) => item.to !== workspace.basePath && !item.to.endsWith('/settings') && canSee(item))
  .slice(0, 6))
</script>
