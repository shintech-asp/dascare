import { ref } from 'vue'

const THEME_KEY = 'dascare-theme'
const VALID_PREFERENCES = ['light', 'dark', 'system']
const theme = ref('light')
const themePreference = ref('light')
let initialized = false
let mediaQuery = null

function resolveTheme(preference) {
  if (preference === 'system') {
    return mediaQuery?.matches ? 'dark' : 'light'
  }
  return preference === 'dark' ? 'dark' : 'light'
}

function renderTheme() {
  const resolved = resolveTheme(themePreference.value)
  theme.value = resolved
  document.documentElement.setAttribute('data-theme', resolved)
}

function persistPreference(value) {
  try { localStorage.setItem(THEME_KEY, value) } catch { /* storage is optional */ }
}

function applyTheme(value) {
  const next = VALID_PREFERENCES.includes(value) ? value : 'light'
  themePreference.value = next
  persistPreference(next)
  renderTheme()
}

function onSystemThemeChange() {
  if (themePreference.value === 'system') renderTheme()
}

function initializeTheme() {
  if (initialized) return
  initialized = true

  mediaQuery = window.matchMedia?.('(prefers-color-scheme: dark)') || null
  mediaQuery?.addEventListener?.('change', onSystemThemeChange)

  let saved = null
  try { saved = localStorage.getItem(THEME_KEY) } catch { /* storage is optional */ }

  // Light is intentionally the DASCARE default. System preference is only
  // used after the user explicitly selects "System" inside Settings.
  themePreference.value = VALID_PREFERENCES.includes(saved) ? saved : 'light'
  renderTheme()
}

export function useTheme() {
  initializeTheme()

  // Quick header toggles stay intentionally binary. If the user previously
  // selected System, pressing the quick toggle switches to an explicit theme.
  const toggleTheme = () => applyTheme(theme.value === 'dark' ? 'light' : 'dark')
  return { theme, themePreference, applyTheme, toggleTheme }
}
