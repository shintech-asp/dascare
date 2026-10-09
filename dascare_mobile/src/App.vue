<template>
  <div v-if="!ready" class="grid min-h-screen place-items-center bg-base-200 dark:bg-[#050e1a]">
    <span class="h-8 w-8 animate-spin rounded-full border-4 border-red-600 border-t-transparent"></span>
  </div>
  <RouterView v-else v-slot="{ Component, route }">
    <Transition :name="transitionName">
      <!-- The sign-up form stays alive so typed fields survive a trip to
           the Terms / Privacy screen and back. -->
      <KeepAlive :include="['RegisterView']">
        <component :is="Component" :key="route.meta.tab ? 'shell' : route.fullPath" />
      </KeepAlive>
    </Transition>
  </RouterView>
  <OfflineBanner />
  <AlertProvider />
  <ToastProvider />
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Capacitor } from '@capacitor/core'
import { App as CapApp } from '@capacitor/app'
import { StatusBar, Style } from '@capacitor/status-bar'
import AlertProvider from '@/components/modals/AlertProvider.vue'
import ToastProvider from '@/components/toasts/ToastProvider.vue'
import OfflineBanner from '@/components/OfflineBanner.vue'
import { useNetwork } from '@/composables/useNetwork'
import { useAlert } from '@/composables/useAlert'
import { useTheme } from '@/composables/useTheme'
import { useSession } from '@/composables/useSession'
import { useGuestKeys } from '@/composables/useGuestKeys'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const { theme } = useTheme()
const { ready, init } = useSession()
const toast = useToast()

// ------------------------------------------------------------------
// Screen transitions: forward = slide in from the right, back = slide
// out to the right (like native Android/iOS). Tab ↔ tab is handled
// inside AppShell (it stays mounted), so it doesn't slide here.
// ------------------------------------------------------------------
const transitionName = ref('none')
let lastPosition = window.history.state?.position ?? 0
router.afterEach((to, from) => {
  const position = window.history.state?.position ?? 0
  const bothTabs = to.meta.tab && from.meta.tab
  transitionName.value = !from.name || bothTabs ? 'none' : position < lastPosition ? 'slide-back' : 'slide-forward'
  lastPosition = position
})

// ------------------------------------------------------------------
// Status bar follows the theme (same colour as the screen background).
// ------------------------------------------------------------------
async function syncStatusBar() {
  if (!Capacitor.isNativePlatform()) return
  const dark = theme.value === 'dark'
  try {
    await StatusBar.setStyle({ style: dark ? Style.Dark : Style.Light })
    await StatusBar.setBackgroundColor({ color: dark ? '#050e1a' : '#f5efe1' })
  } catch { /* not supported everywhere */ }
}
watch(theme, syncStatusBar)

// ------------------------------------------------------------------
// Android back button: go back a screen; on a root screen (Home /
// Welcome) press twice to exit, like most Android apps.
// ------------------------------------------------------------------
let lastBackAt = 0
let backListener = null
let resumeListener = null
async function onBackButton() {
  const current = router.currentRoute.value
  // An open alert/confirm (the web's AlertModal) closes first, as "Cancel".
  const alert = useAlert()
  if (alert.visible.value) {
    if (alert.confirmAction.value) alert.confirmAction.value(false)
    else alert.close()
    return
  }
  if (document.querySelector('[data-modal-open]')) {
    window.dispatchEvent(new CustomEvent('dascare:close-modal'))
    return
  }
  if (!current.meta.root) {
    if (window.history.state?.back) router.back()
    else router.replace('/')
    return
  }
  if (Date.now() - lastBackAt < 2000) {
    CapApp.exitApp()
    return
  }
  lastBackAt = Date.now()
  toast.info('Press back again to exit.', 'DASCARE')
}

onMounted(async () => {
  await Promise.all([init(), useGuestKeys().load(), useNetwork().start()])
  syncStatusBar()
  if (Capacitor.isNativePlatform()) {
    backListener = await CapApp.addListener('backButton', onBackButton)
    // Back in the foreground: refresh the session (KYC may have been
    // approved on the web) and tell open screens to reload their data.
    resumeListener = await CapApp.addListener('resume', () => {
      useSession().fetchSession()
      window.dispatchEvent(new CustomEvent('dascare:resume'))
    })
  }
})
onBeforeUnmount(() => { backListener?.remove(); resumeListener?.remove() })
</script>

<style>
.slide-forward-enter-active,
.slide-forward-leave-active,
.slide-back-enter-active,
.slide-back-leave-active {
  transition: transform 0.26s cubic-bezier(0.2, 0.8, 0.2, 1), opacity 0.26s ease;
  position: absolute;
  inset: 0;
  width: 100%;
}
.slide-forward-enter-from { transform: translateX(100%); }
.slide-forward-leave-to { transform: translateX(-25%); opacity: 0.4; }
.slide-back-enter-from { transform: translateX(-25%); opacity: 0.4; }
.slide-back-leave-to { transform: translateX(100%); }
.slide-forward-leave-active,
.slide-back-enter-active { z-index: 0; }
.slide-forward-enter-active,
.slide-back-leave-active { z-index: 1; }
</style>
