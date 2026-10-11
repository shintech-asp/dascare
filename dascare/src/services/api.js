import axios from 'axios'
import { guestTokensHeader } from '@/services/guestRequests'

/**
 * Single API client for the entire DASCARE frontend.
 *
 * Development example:
 *   VITE_API_BASE_URL=http://localhost/dascare-starter/api
 *
 * Production example:
 *   VITE_API_BASE_URL=https://dascare.example.com/api
 *
 * All requests should import this client instead of creating a new Axios
 * instance or hardcoding an API URL inside a component.
 */
export const API_BASE_URL = String(import.meta.env.VITE_API_BASE_URL ?? '')
  .trim()
  .replace(/\/$/, '')

if (!API_BASE_URL) {
  throw new Error('VITE_API_BASE_URL is missing. Add it to frontend/.env.')
}

const api = axios.create({
  baseURL: API_BASE_URL,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json'
  }
})

// Guest SOS keys saved in this browser (services/guestRequests.js) unlock
// tracking of those requests without an account (dascare_api cors.php).
api.interceptors.request.use((config) => {
  const tokens = guestTokensHeader()
  if (tokens) config.headers['X-Guest-Tokens'] = tokens
  return config
})

export default api
