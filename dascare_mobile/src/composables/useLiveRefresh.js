import { onActivated, onBeforeUnmount, onDeactivated, onMounted } from 'vue'

/**
 * Keep a screen's data fresh like the web does (polling every ~15 s — there's
 * no push transport yet), plus refresh when the app comes back to the
 * foreground (App.vue fires `dascare:resume`). Pauses while the screen is
 * hidden (KeepAlive tab) or the phone is locked.
 *
 *   useLiveRefresh(load, { intervalMs: 15000, enabled: () => isActive.value })
 */
export function useLiveRefresh(refresh, { intervalMs = 15000, enabled = () => true } = {}) {
  let timer = null
  let visible = false

  const tick = () => { if (visible && !document.hidden && enabled()) refresh(true) }
  const onResume = () => { if (visible) refresh(true) }

  function start() {
    visible = true
    clearInterval(timer)
    timer = setInterval(tick, intervalMs)
  }
  function stop() {
    visible = false
    clearInterval(timer)
  }

  onMounted(() => { start(); window.addEventListener('dascare:resume', onResume) })
  onActivated(() => { if (!visible) { start(); refresh(true) } })
  onDeactivated(stop)
  onBeforeUnmount(() => { stop(); window.removeEventListener('dascare:resume', onResume) })
}
