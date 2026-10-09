import { createApp } from 'vue'
import { Icon, addCollection } from '@iconify/vue'
import lucide from '@iconify-json/lucide/icons.json'
import iconSubset from '@/assets/icon-subset.json'
import 'leaflet/dist/leaflet.css'
import App from './App.vue'
import router from './router'
import './assets/main.css'
import { useTheme } from '@/composables/useTheme'

// Same <Icon icon="..."> usage as the web, but bundled instead of fetched
// from the Iconify API, so icons render with no signal: all of Lucide, plus
// only the line-md / mdi icons the app uses (scripts/build-icon-subset.mjs).
addCollection(lucide)
iconSubset.forEach((collection) => addCollection(collection))

useTheme()

const app = createApp(App)
app.component('Icon', Icon)
app.use(router)
app.mount('#app')
