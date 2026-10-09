import { ref } from 'vue'
import api from '@/services/api'

/** Unread notification count for the Alerts tab badge (shared state). */
const count = ref(0)

async function refresh() {
  try {
    const { data } = await api.get('/notifications/unread_count.php')
    count.value = Number(data?.count || 0)
  } catch { /* keep the last value */ }
}

export function useUnreadCount() {
  return { count, refresh }
}
