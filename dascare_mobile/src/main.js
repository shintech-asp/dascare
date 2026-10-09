import { createApp } from 'vue'
import { Icon, addCollection } from '@iconify/vue'
import lucide from '@iconify-json/lucide/icons.json'
import App from './App.vue'
import './assets/main.css'
import { useTheme } from '@/composables/useTheme'

// Same <Icon icon="lucide:..."> usage as the web, but the Lucide set is
// bundled into the app instead of fetched from the Iconify API, so icons
// still render with no signal.
addCollection(lucide)

useTheme()

const app = createApp(App)
app.component('Icon', Icon)
app.mount('#app')
