<template>
  <div class="min-h-screen bg-base-200 dark:bg-[#050e1a]">
    <!-- Top bar -->
    <header class="sticky top-0 z-30 border-b border-base-300/70 bg-base-200/90 pt-[var(--safe-top)] backdrop-blur dark:border-white/10 dark:bg-[#050e1a]/90">
      <div class="flex h-16 items-center justify-between gap-3 px-4">
        <BrandLockup v-if="route.meta.tab === 'home'" compact />
        <h1 v-else class="text-xl font-black tracking-tight text-slate-950 dark:text-white">{{ route.meta.title }}</h1>
        <button type="button" class="tap grid h-10 w-10 place-items-center rounded-xl text-amber-500 dark:text-[#7fb3ec]" aria-label="Toggle theme" @click="toggleTheme">
          <Icon :icon="theme === 'dark' ? 'lucide:moon' : 'lucide:sun'" width="21" />
        </button>
      </div>
    </header>

    <!-- Tab content (kept alive so switching tabs keeps scroll/state) -->
    <main class="pb-[calc(var(--safe-bottom)+6rem)]">
      <RouterView v-slot="{ Component, route: child }">
        <KeepAlive>
          <component :is="Component" :key="child.name" />
        </KeepAlive>
      </RouterView>
    </main>

    <!-- Bottom bar — same look as the web's phone nav (AppHeader.vue): white
         bar, red active tab, raised red SOS button in the centre. -->
    <nav class="fixed bottom-0 left-0 z-40 w-full border-t border-slate-200 bg-white pb-[var(--safe-bottom)] shadow-[0_-4px_20px_rgba(0,0,0,0.08)] dark:border-white/10 dark:bg-[#050e1a]">
      <ul class="relative grid grid-cols-5 items-end px-1 pt-2 pb-1.5">
        <li v-for="tab in leftTabs" :key="tab.name"><TabLink :tab="tab" /></li>
        <li class="relative flex justify-center">
          <RouterLink to="/sos" aria-label="Request emergency assistance"
            class="tap absolute -top-9 flex h-16 w-16 flex-col items-center justify-center rounded-full border-4 border-white bg-red-600 text-white no-underline shadow-lg shadow-red-600/40 dark:border-[#050e1a]">
            <Icon icon="lucide:triangle-alert" width="22" />
            <span class="mt-0.5 text-[9px] font-black uppercase tracking-wide">SOS</span>
          </RouterLink>
          <span class="mt-7 text-[10px] font-semibold text-red-600 dark:text-red-400">Emergency</span>
        </li>
        <li v-for="tab in rightTabs" :key="tab.name"><TabLink :tab="tab" /></li>
      </ul>
    </nav>
  </div>
</template>

<script setup>
import { defineComponent, h } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { Icon } from '@iconify/vue'
import BrandLockup from '@/components/BrandLockup.vue'
import { useTheme } from '@/composables/useTheme'

const route = useRoute()
const { theme, toggleTheme } = useTheme()

const leftTabs = [
  { name: 'Home', tab: 'home', label: 'Home', icon: 'lucide:house' },
  { name: 'Requests', tab: 'requests', label: 'Requests', icon: 'lucide:clipboard-list' },
]
const rightTabs = [
  { name: 'Notifications', tab: 'notifications', label: 'Alerts', icon: 'lucide:bell' },
  { name: 'Profile', tab: 'profile', label: 'Profile', icon: 'lucide:circle-user-round' },
]

const TabLink = defineComponent({
  props: { tab: Object },
  setup(props) {
    return () => {
      const active = route.meta.tab === props.tab.tab
      return h(RouterLink, {
        to: { name: props.tab.name },
        replace: true,
        class: ['tap flex flex-col items-center gap-0.5 py-1 no-underline transition-colors', active ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-white/50'],
      }, () => [
        h(Icon, { icon: props.tab.icon, width: 22 }),
        h('span', { class: 'text-[10px] font-semibold' }, props.tab.label),
      ])
    }
  },
})
</script>
