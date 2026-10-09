import { ref } from 'vue'
import { setGuestKeys } from '@/services/api'
import { getItem, setItem } from '@/services/storage'
import { refreshRealtimeAccess } from '@/services/realtime'

/**
 * Private keys for emergency requests sent as a GUEST from this phone
 * (returned by citizen/create.php as guestAccessToken). They let the guest
 * track the request and say "not my emergency" without an account. Every
 * API call sends them in X-Guest-Tokens (see services/api.js).
 */
const KEY = 'dascare.guestRequests'
const MAX = 20 // server reads at most 20

const requests = ref([]) // [{ id, reference_number, token, created_at }]
let loaded = false

async function load() {
  if (loaded) return
  requests.value = await getItem(KEY, [])
  setGuestKeys(requests.value.map((r) => r.token))
  loaded = true
}

async function add(entry) {
  await load()
  requests.value = [entry, ...requests.value.filter((r) => r.id !== entry.id)].slice(0, MAX)
  setGuestKeys(requests.value.map((r) => r.token))
  await setItem(KEY, requests.value)
  refreshRealtimeAccess() // the new request's live channel
}

export function useGuestKeys() {
  return { requests, load, add }
}
