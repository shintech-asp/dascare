import { computed, onActivated, onBeforeUnmount, onDeactivated, onMounted, ref, watch } from 'vue'
import { connectRealtime, realtimeEpoch, realtimeState, subscribe } from '@/services/realtime'

/**
 * Keep a screen fresh: re-fetch the moment the server announces a change on
 * one of `channels`, and poll as a backup — every `pollMs` while live updates
 * are unavailable, every `livePollMs` while they're connected.
 *
 *   const { live } = useLiveUpdates(load, {
 *     channels: () => [realtimeChannels.value?.org, requestChannel(id)], // from services/realtime
 *     events: ['offer.created', 'offer.expired'],       // optional filter
 *     onEvent: (msg) => { ... return false to skip the refresh },
 *   })
 *
 * refresh(true) is called with `true` = background refresh (no spinners).
 * Pauses while the screen is hidden (KeepAlive) or the tab/app is in the
 * background, and refreshes when it comes back (`dascare:resume` in the app).
 *
 * Same file in dascare/src/composables/useLiveUpdates.js (web) — keep them in sync.
 */
export function useLiveUpdates(refresh, {
  channels = () => [],
  events = null,
  onEvent = null,
  pollMs = 15000,
  livePollMs = 60000,
  debounceMs = 250,
  enabled = () => true,
} = {}) {
  const wanted = ref(0)
  const joined = ref(0)
  // Live only when every channel this screen needs is joined.
  const live = computed(() => realtimeState.value === 'connected' && wanted.value > 0 && joined.value === wanted.value)

  let visible = false
  let pollTimer = null
  let debounceTimer = null
  let unsubscribers = []
  let wasConnected = false
  let subscribeRound = 0

  const run = () => { if (visible && !document.hidden && enabled()) refresh(true) }

  function onMessage(message) {
    if (events && !events.includes(message.name)) return
    if (onEvent && onEvent(message) === false) return
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(run, debounceMs)
  }

  function unsubscribeAll() {
    unsubscribers.forEach((off) => off())
    unsubscribers = []
    wanted.value = 0
    joined.value = 0
  }

  function subscribeAll() {
    unsubscribeAll()
    if (!visible) return
    const names = [...new Set((channels() || []).filter(Boolean))]
    const round = ++subscribeRound
    wanted.value = names.length
    unsubscribers = names.map((name) => subscribe(name, onMessage, (ok) => {
      if (ok && round === subscribeRound) joined.value += 1
    }))
  }

  function schedulePoll() {
    clearTimeout(pollTimer)
    if (!visible) return
    pollTimer = setTimeout(() => { run(); schedulePoll() }, live.value ? livePollMs : pollMs)
  }

  function start() {
    visible = true
    connectRealtime() // learns which channels exist (realtimeChannels) on first use
    subscribeAll()
    schedulePoll()
  }
  function stop() {
    visible = false
    clearTimeout(pollTimer)
    clearTimeout(debounceTimer)
    unsubscribeAll()
  }

  // Catch up after a reconnect (events sent while offline may be missed).
  watch(live, () => schedulePoll())
  watch(realtimeState, (state) => {
    if (state === 'connected') {
      if (wasConnected) run()
      wasConnected = true
    }
  })
  watch(() => (channels() || []).filter(Boolean).join('|'), () => { if (visible) subscribeAll() })
  watch(realtimeEpoch, () => { if (visible) subscribeAll() })

  const onResume = () => { if (visible) refresh(true) }
  const onVisibility = () => { if (!document.hidden) run() }

  onMounted(() => {
    start()
    window.addEventListener('dascare:resume', onResume)
    document.addEventListener('visibilitychange', onVisibility)
  })
  onActivated(() => { if (!visible) { start(); refresh(true) } })
  onDeactivated(stop)
  onBeforeUnmount(() => {
    stop()
    window.removeEventListener('dascare:resume', onResume)
    document.removeEventListener('visibilitychange', onVisibility)
  })

  return { live }
}
