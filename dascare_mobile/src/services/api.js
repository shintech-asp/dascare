import axios from 'axios'

/**
 * Single API client for the DASCARE app — same role as the web's
 * dascare/src/services/api.js.
 *
 * Difference from the web: no cookies. The app will authenticate with a
 * bearer token (added in Phase 1), because WebView cookies to the XAMPP host
 * aren't reliable. Base URL comes from .env (see .env.example).
 */
export const API_BASE_URL = String(import.meta.env.VITE_API_BASE_URL ?? '')
  .trim()
  .replace(/\/$/, '')

if (!API_BASE_URL) {
  throw new Error('VITE_API_BASE_URL is missing. Copy .env.example to .env.')
}

const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 15000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

export default api
