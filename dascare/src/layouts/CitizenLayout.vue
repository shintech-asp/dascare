<template>
  <div class="flex min-h-screen min-w-[320px] bg-base-200 dark:bg-[#050e1a]">
    <CitizenSidebar v-model:open="mobileOpen" />

    <div class="flex flex-col flex-1 min-w-0">
      <CitizenTopbar :sidebar-open="mobileOpen" @toggle-sidebar="mobileOpen = !mobileOpen" />

      <main class="flex-1">
        <router-view v-slot="{ Component, route: r }">
          <Transition name="page-fade" mode="out-in">
            <component :is="Component" :key="r.path" />
          </Transition>
        </router-view>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import CitizenSidebar from '@/layouts/components/CitizenSidebar.vue'
import CitizenTopbar from '@/layouts/components/CitizenTopbar.vue'

// No shop-accent theming here the way ArtisanLayout has (--accent-*) —
// DASCARE isn't multi-tenant per-user the way Likhavite shops are, so the
// citizen side just uses the fixed navy/red brand palette from hero.vue
// directly in each component instead of a computed CSS-var ramp.
const mobileOpen = ref(false)
</script>

<style scoped>
.page-fade-enter-active,
.page-fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.page-fade-enter-from { opacity: 0; transform: translateY(8px); }
.page-fade-leave-to { opacity: 0; transform: translateY(-4px); }
</style>