import api from './api'

export async function fetchSidebarBadges() {
  const { data } = await api.get('/navigation/sidebar_badges.php')
  return data?.success && data?.badges && typeof data.badges === 'object'
    ? data.badges
    : {}
}
