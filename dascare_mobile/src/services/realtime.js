import { ref } from 'vue'
import api from '@/services/api'

/**
 * Live updates over Ably (singleton). The server decides which channels this
 * app may listen to (realtime/auth.php); events are hints like
 * "request 12 changed", and screens re-fetch their usual endpoint. Screens
 * use the useLiveUpdates composable rather than this file directly.
 *
 * Inert when the server has no Ably key or live updates are switched off
 * (Technical → Configuration): state stays 'disabled' and screens keep
 * polling as before. The Ably library is only downloaded once enabled.
 *
 * Same file in dascare/src/services/realtime.js (web) — keep them in sync.
 */

// idle | disabled | initialized | connecting | connected | disconnected | suspended | closing | closed | failed
export const realtimeState = ref('idle')
export const realtimeChannels = ref(null) // { user, org, platform, requests[] } from realtime/auth.php
export const realtimeEpoch = ref(0) // bumps on reset; subscribers re-subscribe

let client = null
let starting = null
let generation = 0 // bumps on reset, so a stale start can't install its client
let disabledUntil = 0
const listeners = new Map() // channel name -> Set(handler)

const RETRY_DISABLED_MS = 5 * 60 * 1000
const CAPABILITY_ERRORS = [40160, 40161] // channel not in this token's list

async function fetchAuth() {
  const { data } = await api.get('/realtime/auth.php')
  return data
}

/** Channel name for one emergency request (null until the server sent the prefix). */
export function requestChannel(id) {
  const prefix = realtimeChannels.value?.prefix
  return prefix && id ? `${prefix}:request:${id}` : null
}

export function isRealtimeLive() {
  return realtimeState.value === 'connected'
}

async function ensureClient() {
  if (client) return client
  if (Date.now() < disabledUntil) return null
  if (starting) return starting

  const myGeneration = generation
  starting = (async () => {
    let first
    try {
      first = await fetchAuth()
    } catch {
      disabledUntil = Date.now() + 30 * 1000 // API unreachable: try again shortly
      return null
    }
    if (!first?.enabled) {
      realtimeState.value = 'disabled'
      disabledUntil = Date.now() + RETRY_DISABLED_MS
      return null
    }
    realtimeChannels.value = first.channels ? { ...first.channels, prefix: first.prefix } : null
    if (!first.tokenRequest) {
      realtimeState.value = 'idle' // nothing to listen to yet (e.g. guest without requests)
      return null
    }

    const { Realtime } = await import('ably')
    if (myGeneration !== generation) return null

    let pending = first.tokenRequest
    const created = new Realtime({
      authCallback: async (_params, callback) => {
        try {
          let tokenRequest = pending
          pending = null
          if (!tokenRequest) {
            const data = await fetchAuth()
            if (!data?.enabled || !data.tokenRequest) return callback('Live updates are off.', null)
            realtimeChannels.value = data.channels ? { ...data.channels, prefix: data.prefix } : null
            tokenRequest = data.tokenRequest
          }
          callback(null, tokenRequest)
        } catch (err) {
          callback(err?.message || 'Live updates sign-in failed.', null)
        }
      },
      echoMessages: false,
      logLevel: 0,
    })
    created.connection.on((change) => {
      if (client === created) realtimeState.value = change.current
    })
    client = created
    realtimeState.value = created.connection.state
    return created
  })()

  try {
    return await starting
  } finally {
    starting = null
  }
}

async function attach(name) {
  const channel = client.channels.get(name)
  try {
    await channel.attach()
  } catch (err) {
    if (!CAPABILITY_ERRORS.includes(err?.code)) throw err
    // A request made after this token was issued: ask for a fresh channel list once.
    await client.auth.authorize()
    await channel.attach()
  }
  return channel
}

/**
 * Listen to a channel. Returns an unsubscribe function (safe to call before
 * the subscription finished). handler(message) gets { name, data };
 * onStatus(true|false) reports whether the channel could be joined.
 */
export function subscribe(name, handler, onStatus = () => {}) {
  let cancelled = false
  let channel = null
  ;(async () => {
    const c = await ensureClient()
    if (!c || cancelled || !name) { onStatus(false); return }
    if (!listeners.has(name)) listeners.set(name, new Set())
    listeners.get(name).add(handler)
    try {
      channel = await attach(name)
      if (cancelled) return
      channel.subscribe(handler)
      onStatus(true)
    } catch {
      listeners.get(name)?.delete(handler) // not allowed / failed: the screen keeps polling
      onStatus(false)
    }
  })()

  return () => {
    cancelled = true
    const set = listeners.get(name)
    if (!set) return
    set.delete(handler)
    if (channel) channel.unsubscribe(handler)
    if (!set.size) {
      listeners.delete(name)
      if (client) client.channels.get(name).detach().catch(() => {})
    }
  }
}

/** Ask the server for a fresh channel list (after sending a new request, etc.). */
export async function refreshRealtimeAccess() {
  disabledUntil = 0
  if (!client) return ensureClient()
  try {
    await client.auth.authorize()
  } catch { /* keeps the old token; screens still poll */ }
  return client
}

/** Drop the connection (sign-in / sign-out changes which channels are allowed). */
export function resetRealtime() {
  generation += 1
  disabledUntil = 0
  starting = null
  listeners.clear()
  realtimeChannels.value = null
  if (client) {
    try { client.close() } catch { /* ignore */ }
  }
  client = null
  realtimeState.value = 'idle'
  realtimeEpoch.value += 1
}

/** Connect without subscribing (diagnostics screens). */
export function connectRealtime() {
  return ensureClient()
}

/**
 * Round-trip check for diagnostics screens (signed-in users): join your own
 * user channel, ask the server to publish a test event (realtime/test.php),
 * and time how long it takes to arrive. Resolves { ok, ms?, message }.
 */
export async function runRealtimeTest({ timeoutMs = 10000 } = {}) {
  const c = await ensureClient()
  if (!c) return { ok: false, message: realtimeState.value === 'disabled' ? 'Live updates are turned off or not set up on the server.' : 'Could not start live updates.' }
  const name = realtimeChannels.value?.user
  if (!name) return { ok: false, message: 'Sign in to run this test.' }

  const nonce = Math.random().toString(36).slice(2, 12)
  let off = () => {}
  try {
    return await new Promise((resolve) => {
      let started = 0
      const timer = setTimeout(() => resolve({ ok: false, message: 'No test event arrived within 10 seconds.' }), timeoutMs)
      const finish = (result) => { clearTimeout(timer); resolve(result) }
      off = subscribe(name, (message) => {
        if (message.name === 'test' && message.data?.nonce === nonce) {
          finish({ ok: true, ms: Math.round(performance.now() - started), message: 'Live updates are working.' })
        }
      }, async (joined) => {
        if (!joined) return finish({ ok: false, message: 'Could not join the live updates channel.' })
        started = performance.now()
        try {
          await api.post('/realtime/test.php', { nonce })
        } catch (err) {
          finish({ ok: false, message: err?.response?.data?.message || 'The server could not publish the test event.' })
        }
      })
    })
  } finally {
    off()
  }
}
