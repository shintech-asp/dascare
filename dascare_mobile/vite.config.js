import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'
import { existsSync } from 'node:fs'

// Push notifications are compiled in only when Firebase is configured for
// Android (see PUSH_SETUP.md). Override with DASCARE_PUSH=true/false.
const pushEnabled = process.env.DASCARE_PUSH
  ? process.env.DASCARE_PUSH === 'true'
  : existsSync(fileURLToPath(new URL('./android/app/google-services.json', import.meta.url)))

export default defineConfig({
  plugins: [vue(), tailwindcss()],

  define: {
    __DASCARE_PUSH__: JSON.stringify(pushEnabled),
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
