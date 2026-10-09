import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [vue(), tailwindcss()],

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
