import axios from 'axios'

/**
 * Single API client for the DASCARE app — same role as the web's
 * dascare/src/services/api.js.
 *
 * Difference from the web: no cookies. Every request carries
 *   X-Dascare-Client: mobile           (tells cors.php this is the app)
 *   Authorization: Bearer <token>      (when a citizen is logged in)
 *   X-Guest-Tokens: <key>,<key>        (guest SOS requests sent from this phone)
 * See dascare_api/reusables/mobile_auth.php. Base URL comes from .env.
 */
export const API_BASE_URL = String(import.meta.env.VITE_API_BASE_URL ?? '')
  .trim()
  .replace(/\/$/, '')

if (!API_BASE_URL) {
  throw new Error('VITE_API_BASE_URL is missing. Copy .env.example to .env.')
}

const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 20000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Dascare-Client': 'mobile',
  },
})

// Set by useSession / useGuestKeys (kept here as plain values to avoid an
// import cycle between this file and the composables).
let authToken = null
let guestKeys = []
let onInvalidToken = null

export function setAuthToken(token) { authToken = token || null }
export function setGuestKeys(keys) { guestKeys = Array.isArray(keys) ? keys : [] }
export function onTokenInvalid(callback) { onInvalidToken = callback }

api.interceptors.request.use((config) => {
  if (authToken) config.headers.Authorization = `Bearer ${authToken}`
  if (guestKeys.length) config.headers['X-Guest-Tokens'] = guestKeys.join(',')
  return config
})

// The server never blocks a request over a bad token (an SOS must still go
// through) — it answers as if signed out and flags it in this header.
function checkTokenHeader(response) {
  if (authToken && response?.headers?.['x-dascare-token-status'] === 'invalid') onInvalidToken?.()
}

api.interceptors.response.use(
  (response) => { checkTokenHeader(response); return response },
  (error) => { checkTokenHeader(error.response); return Promise.reject(error) },
)

/** Best user-facing message from an axios error or API response. */
export function apiMessage(err, fallback = 'Something went wrong. Please try again.') {
  if (!err?.response) return 'Can’t reach DASCARE. Check your internet connection.'
  return err.response.data?.message || fallback
}

export default api
