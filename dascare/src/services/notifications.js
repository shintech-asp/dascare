import api from '@/services/api'

export function normalizeNotification(item = {}) {
  const read = Boolean(item.read ?? item.is_read ?? item.read_at)
  return {
    ...item,
    id: Number(item.id || 0),
    related_id: item.related_id == null ? null : Number(item.related_id),
    read,
    is_read: read,
  }
}

export async function fetchNotifications() {
  const { data } = await api.get('/notifications/get.php')
  return Array.isArray(data) ? data.map(normalizeNotification) : []
}

export async function fetchUnreadNotificationCount() {
  const { data } = await api.get('/notifications/unread_count.php')
  return Number(data?.count || 0)
}

export async function markNotificationRead(id) {
  const { data } = await api.post('/notifications/mark_read.php', { id })
  if (!data?.success) throw new Error(data?.message || 'Could not mark notification as read.')
  return data
}

export async function markAllNotificationsRead() {
  const { data } = await api.post('/notifications/mark_all_read.php', {})
  if (!data?.success) throw new Error(data?.message || 'Could not mark notifications as read.')
  return data
}
