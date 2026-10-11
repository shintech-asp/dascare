import { ref } from 'vue'

/**
 * Emergency requests sent as a GUEST from this browser, with their private
 * keys (citizen/create.php → guestAccessToken). The keys go out on every API
 * call as X-Guest-Tokens (see services/api.js), so the guest can open the
 * public tracking page (/track/:id) and say "not my emergency" without an
 * account — same mechanism as the Android app (dascare_mobile/useGuestKeys).
 *
 * A tracking link (/track/:id#key=…) carries the key, so the request can be
 * followed from another phone too; opening it stores the key here.
 */
const STORAGE_KEY = 'dascare.guestRequests'
const MAX = 20 // the server reads at most 20 keys

function read() {
  try {
    const list = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')
    return Array.isArray(list) ? list.filter((r) => r && r.id && r.token) : []
  } catch {
    return []
  }
}

export const guestRequests = ref(read())

function save() {
  try { localStorage.setItem(STORAGE_KEY, JSON.stringify(guestRequests.value)) } catch { /* private mode */ }
}

/** Remember a guest request ({ id, token, reference_number?, created_at? }); newest first. */
export function rememberGuestRequest(entry) {
  if (!entry?.id || !entry?.token) return
  const id = Number(entry.id)
  const previous = guestRequests.value.find((r) => r.id === id) || {}
  guestRequests.value = [
    { ...previous, ...entry, id, created_at: entry.created_at || previous.created_at || new Date().toISOString() },
    ...guestRequests.value.filter((r) => r.id !== id),
  ].slice(0, MAX)
  save()
}

export function guestTokenFor(id) {
  return guestRequests.value.find((r) => r.id === Number(id))?.token || null
}

/** Value for the X-Guest-Tokens header (comma-separated), or '' when none. */
export function guestTokensHeader() {
  return guestRequests.value.slice(0, MAX).map((r) => r.token).join(',')
}

/** Shareable tracking link for a guest request (the key travels in the #fragment, never to the server log). */
export function guestTrackingUrl(id) {
  const token = guestTokenFor(id)
  return token ? `${window.location.origin}/track/${id}#key=${encodeURIComponent(token)}` : null
}
