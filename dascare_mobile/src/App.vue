<template>
  <!-- Phase 0: single setup-check screen. Router, app shell (top bar +
       bottom tab bar with SOS) and the real screens arrive in Phase 2. -->
  <ConnectionCheck />
</template>

<script setup>
import { onMounted, watch } from 'vue'
import { Capacitor } from '@capacitor/core'
import { StatusBar, Style } from '@capacitor/status-bar'
import ConnectionCheck from '@/views/ConnectionCheck.vue'
import { useTheme } from '@/composables/useTheme'

const { theme } = useTheme()

// Status bar follows the app theme (same background as the web's base-200).
async function syncStatusBar() {
  if (!Capacitor.isNativePlatform()) return
  const dark = theme.value === 'dark'
  try {
    await StatusBar.setStyle({ style: dark ? Style.Dark : Style.Light })
    await StatusBar.setBackgroundColor({ color: dark ? '#050e1a' : '#f5efe1' })
  } catch { /* not fatal on devices that don't support it */ }
}

onMounted(syncStatusBar)
watch(theme, syncStatusBar)
</script>
