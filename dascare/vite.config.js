import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import VueDevTools from 'vite-plugin-vue-devtools'
import { fileURLToPath, URL } from 'node:url'
import { createReadStream, existsSync, statSync } from 'node:fs'

// Serve the Android app download (public/downloads/*.apk, published by
// `npm run apk:web` in dascare_mobile/) with the APK content type. Without it
// Vite sends no type and Android saves the file as ".apk.zip", which can't be
// tapped to install. Applies to `vite` (dev) and `vite preview`.
function apkContentType() {
  const publicDir = fileURLToPath(new URL('./public', import.meta.url))
  const serveApk = (req, res, next) => {
    const path = decodeURIComponent((req.url || '').split('?')[0])
    if (!/^\/downloads\/[\w.-]+\.apk$/.test(path)) return next()
    const file = publicDir + path
    if (!existsSync(file)) return next()
    res.setHeader('Content-Type', 'application/vnd.android.package-archive')
    res.setHeader('Content-Length', statSync(file).size)
    res.setHeader('Content-Disposition', 'attachment; filename="' + path.split('/').pop() + '"')
    createReadStream(file).pipe(res)
  }
  return {
    name: 'dascare-apk-content-type',
    configureServer: (server) => { server.middlewares.use(serveApk) },
    configurePreviewServer: (server) => { server.middlewares.use(serveApk) },
  }
}

export default defineConfig({
  plugins: [
    VueDevTools(),
    vue(),
    tailwindcss(),
    apkContentType()
  ],

  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },

  server: {
    port: 5173
  }
})