import { ref } from 'vue'
import { Network } from '@capacitor/network'

/**
 * Phone connectivity (Capacitor Network), shared app-wide. Drives the
 * no-signal banner; when the connection comes back, open screens refresh
 * (same `dascare:resume` event App.vue sends on app resume).
 */
const online = ref(true)
const connectionType = ref('unknown')
let started = false

async function start() {
  if (started) return
  started = true
  try {
    const status = await Network.getStatus()
    online.value = status.connected
    connectionType.value = status.connectionType
  } catch { /* assume online */ }
  Network.addListener('networkStatusChange', (status) => {
    const wasOffline = !online.value
    online.value = status.connected
    connectionType.value = status.connectionType
    if (wasOffline && status.connected) window.dispatchEvent(new CustomEvent('dascare:resume'))
  })
}

export function useNetwork() {
  return { online, connectionType, start }
}
