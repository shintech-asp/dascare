import { createApp } from 'vue'
import App from './App.vue'
import router from './router/index.js'
import { Icon } from '@iconify/vue'
import './assets/main.css'
import { useTheme } from '@/composables/useTheme'

useTheme()

const app = createApp(App)

app.component('Icon', Icon)


app.use(router)

app.mount('#app')