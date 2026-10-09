<template>
  <div class="dascare-management-shell flex min-h-screen min-w-[320px] bg-base-200 dark:bg-[#081b2e]">
    <ManagementSidebar v-model:open="mobileOpen" :workspace="workspace" />

    <div class="flex min-w-0 flex-1 flex-col">
      <ManagementTopbar
        :workspace="workspace"
        :sidebar-open="mobileOpen"
        @toggle-sidebar="mobileOpen = !mobileOpen"
      />

      <main class="flex-1">
        <router-view v-slot="{ Component, route }">
          <Transition name="page-fade" mode="out-in">
            <component :is="Component" :key="route.fullPath" />
          </Transition>
        </router-view>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import ManagementSidebar from './ManagementSidebar.vue'
import ManagementTopbar from './ManagementTopbar.vue'
import { dashboardWorkspaces } from '@/config/dashboardNavigation'

const props = defineProps({
  workspaceKey: { type: String, required: true },
})

const mobileOpen = ref(false)
const workspace = computed(() => dashboardWorkspaces[props.workspaceKey])
</script>

<style scoped>
.page-fade-enter-active,
.page-fade-leave-active {
  transition: opacity 0.16s ease, transform 0.16s ease;
}
.page-fade-enter-from { opacity: 0; transform: translateY(8px); }
.page-fade-leave-to { opacity: 0; transform: translateY(-4px); }
</style>

<style>
html[data-theme="light"] .dascare-management-shell [class~="bg-white"] { background-color: var(--color-base-100) !important; }
html[data-theme="light"] .dascare-management-shell [class~="bg-slate-50"] { background-color: var(--color-base-200) !important; }
html[data-theme="light"] .dascare-management-shell [class~="bg-slate-100"] { background-color: var(--color-base-200) !important; }
html[data-theme="light"] .dascare-management-shell [class~="border-slate-200"],
html[data-theme="light"] .dascare-management-shell [class~="border-slate-100"] { border-color: var(--color-base-300) !important; }
html[data-theme="light"] .dascare-management-shell [class~="divide-slate-100"] > :not([hidden]) ~ :not([hidden]) { border-color: var(--color-base-300) !important; }
</style>
