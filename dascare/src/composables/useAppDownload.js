import { computed, ref } from 'vue'

/**
 * Published Android app (downloads/app.json + DASCARE.apk, written by
 * `npm run apk:web` / `npm run apk -- live` in dascare_mobile/, not in git).
 * Shared state: app.json is fetched once per page load. Used by the
 * "Download app" link in AppHeader.
 */
const APK_PATH = 'downloads/DASCARE.apk'
const app = ref(null)
const loading = ref(true)
let started = false

// ?v=<version>: each release gets a fresh URL, so the hosting CDN never serves an older cached APK.
const apkUrl = computed(() => `${import.meta.env.BASE_URL}${APK_PATH}${app.value?.version ? `?v=${encodeURIComponent(app.value.version)}` : ''}`)
const sizeLabel = computed(() => (app.value?.size_bytes ? `${(app.value.size_bytes / 1e6).toFixed(1)} MB` : ''))

async function load() {
  try {
    const res = await fetch(`${import.meta.env.BASE_URL}downloads/app.json`, { cache: 'no-store' })
    if (res.ok) app.value = await res.json()
  } catch { /* not published — the link stays hidden */ } finally {
    loading.value = false
  }
}

export function useAppDownload() {
  if (!started) { started = true; load() }
  return { app, loading, apkUrl, sizeLabel }
}
