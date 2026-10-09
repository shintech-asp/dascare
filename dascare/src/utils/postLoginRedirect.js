// Remembers "where the user was" when they hit Login/Sign Up, so we can
// send them back there after a successful login/registration — including
// after the Google OAuth round-trip, which leaves the SPA entirely and
// comes back on a full page load (so in-memory/router state doesn't survive,
// but sessionStorage does).

const KEY = 'postLoginRedirect'

// Paths we should never store as a redirect target (avoids redirect loops
// back into the auth flow itself, and open-redirect-style tricks).
function isSafeTarget(path) {
  if (!path || typeof path !== 'string') return false
  if (!path.startsWith('/') || path.startsWith('//')) return false // internal paths only
  if (/^\/(login|register)(\/|\?|$)/i.test(path)) return false
  return true
}

export function setPostLoginRedirect(path) {
  if (!isSafeTarget(path)) return
  try {
    sessionStorage.setItem(KEY, path)
  } catch {
    /* sessionStorage unavailable (e.g. private mode) — fail silently, we just fall back to '/' */
  }
}

// Reads AND clears — call this once you're ready to actually redirect.
export function consumePostLoginRedirect() {
  try {
    const value = sessionStorage.getItem(KEY)
    sessionStorage.removeItem(KEY)
    return isSafeTarget(value) ? value : null
  } catch {
    return null
  }
}

// Read-only peek, used by the router guard to decide whether it's worth
// even checking the session on a given navigation.
export function peekPostLoginRedirect() {
  try {
    return sessionStorage.getItem(KEY)
  } catch {
    return null
  }
}

export function clearPostLoginRedirect() {
  try {
    sessionStorage.removeItem(KEY)
  } catch {
    /* noop */
  }
}