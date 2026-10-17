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
// Since 2026-10-15 the count is a HARD limit (2 per 24 h, per device / IP /
// phone — reusables/guest_request_limit.php): `blocked` is true once it's
// used up, and create.php refuses further guest requests with GUEST_LIMIT. Module-level refs (singleton), same pattern as
// useSession, so the hero badge and the modal always agree.
import { computed, ref } from 'vue'
import api from '@/services/api'

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
    // api.js sends this browser's guest keys, so the count covers this device.
    const res = await api.get('/citizen/guest_status.php')
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
  if (guestStatus.used == null) {
    fetchStatus({ force: true })
    return
  }
  used.value = guestStatus.used
  hasFetched = true
}

const blocked = computed(() => used.value != null && limit.value != null && used.value >= limit.value)

// create.php refused the request (GUEST_LIMIT): show the blocked state.
function markBlocked(data) {
  limit.value = data?.limit ?? limit.value ?? 2
  used.value = Math.max(data?.used ?? 0, limit.value)
  hasFetched = true
}

export function useGuestRequestLimit() {
  return { limit, used, blocked, flagged, flagReason, loading, fetchStatus, applySubmissionResult, markBlocked }
}
