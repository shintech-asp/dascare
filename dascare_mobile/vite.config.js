import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'
import { existsSync, readFileSync } from 'node:fs'

// Push notifications are compiled in only when Firebase is configured for
// Android (see PUSH_SETUP.md). Override with DASCARE_PUSH=true/false.
const pushEnabled = process.env.DASCARE_PUSH
  ? process.env.DASCARE_PUSH === 'true'
  : existsSync(fileURLToPath(new URL('./android/app/google-services.json', import.meta.url)))

// App version shown in the app (Profile footer) — from package.json, so it
// always matches the APK (bump it together with android/app/build.gradle).
const appVersion = JSON.parse(readFileSync(new URL('./package.json', import.meta.url), 'utf8')).version

export default defineConfig({
  plugins: [vue(), tailwindcss()],

  define: {
    __DASCARE_PUSH__: JSON.stringify(pushEnabled),
    __APP_VERSION__: JSON.stringify(appVersion),
  },

  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },

  // Relative asset paths so the built app loads from Capacitor's WebView.
  base: './',

  // Browser preview of the app during development (npm run dev). Kept off
  // 5173/5174 so it never collides with the web frontend's dev server.
  server: {
    port: 5180,
  },
})
