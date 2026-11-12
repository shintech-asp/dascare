// Countdown shown when the server refuses an emergency request because the
// sender reached the request limit (HTTP 429, code REQUEST_LIMIT,
// retry_after_seconds). Same file in dascare_mobile/src/composables/ — keep in sync.
import { computed, onBeforeUnmount, ref } from 'vue'

export function useRequestLimitCountdown() {
  const until = ref(0)
  const now = ref(Date.now())
  let timer = null

  const remaining = computed(() => Math.max(0, Math.ceil((until.value - now.value) / 1000)))
  const active = computed(() => remaining.value > 0)
  const label = computed(() => {
    const s = remaining.value
    const h = Math.floor(s / 3600)
    const m = Math.floor((s % 3600) / 60)
    const sec = String(s % 60).padStart(2, '0')
    return h ? `${h}:${String(m).padStart(2, '0')}:${sec}` : `${m}:${sec}`
  })

  function stop() { if (timer) { clearInterval(timer); timer = null } }
  function start(seconds) {
    const s = Math.max(1, Number(seconds) || 60)
    until.value = Date.now() + s * 1000
    now.value = Date.now()
    stop()
    timer = setInterval(() => {
      now.value = Date.now()
      if (until.value <= now.value) stop()
    }, 1000)
  }
  /** True (and starts the countdown) when `data` is the server's request-limit refusal. */
  function handle(data) {
    if (data?.code !== 'REQUEST_LIMIT') return false
    start(data.retry_after_seconds)
    return true
  }
  onBeforeUnmount(stop)
  return { active, label, remaining, start, handle }
}
