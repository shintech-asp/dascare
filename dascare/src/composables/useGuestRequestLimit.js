// src/composables/useGuestRequestLimit.js
//
// Two distinct pieces of state, deliberately not conflated:
//
//   - `used` / `limit` — an INDICATIVE, IP-based count fetched from
//     citizen/guest_status.php before the guest has typed anything. It's
//     a reasonable heads-up, not the real threshold — there's no phone
//     number to key off yet at that point.
//   - `flagged` / `flagReason` — set only after an actual guest
//     submission, straight from create.php's authoritative (phone-keyed)
//     guestStatus response. This is what a dispatcher will actually see.
//
// Nothing here ever blocks a submission — the daily count is a soft,
// informational threshold; see reusables/guest_request_limit.php on the
// backend for why. Module-level refs (singleton), same pattern as
// useSession, so the hero badge and the modal always agree.
import { ref } from 'vue'
import axios from 'axios'

const API_BASE = import.meta.env.VITE_API_BASE_URL

const limit = ref(null)
const used = ref(null) // null = not fetched yet
const flagged = ref(false)
const flagReason = ref(null)
const loading = ref(false)

let hasFetched = false

async function fetchStatus({ force = false } = {}) {
  if (hasFetched && !force) return
  loading.value = true
  try {
    const res = await axios.get(`${API_BASE}/citizen/guest_status.php`, {
      withCredentials: true,
    })
    if (res.data?.success) {
      limit.value = res.data.limit
      used.value = res.data.used
      hasFetched = true
    }
  } catch (err) {
    console.error('Failed to fetch guest status', err)
    // Leave used as null — callers treat that as "unknown", not "zero",
    // and never gate submission on it either way.
  } finally {
    loading.value = false
  }
}

// create.php returns the authoritative, phone-keyed result as a side
// effect of a successful guest submission (`guestStatus`): apply it here
// so the badge/modal reflect it without a second request.
function applySubmissionResult(guestStatus) {
  if (!guestStatus) return
  limit.value = guestStatus.limit
  flagged.value = guestStatus.flagged
  flagReason.value = guestStatus.flagReason
  // ordinal is phone-keyed, so it's null when the guest left the phone blank —
  // fall back to re-reading the per-device count instead of blanking the badge.
  if (guestStatus.ordinal == null) {
    fetchStatus({ force: true })
    return
  }
  used.value = guestStatus.ordinal
  hasFetched = true
}

export function useGuestRequestLimit() {
  return { limit, used, flagged, flagReason, loading, fetchStatus, applySubmissionResult }
}
