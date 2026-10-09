import api from '@/services/api'

export async function fetchPlatformCitizens(params = {}) {
  const { data } = await api.get('/platform/citizens/list.php', { params })
  return data
}

export async function updatePlatformCitizenStatus(payload) {
  const { data } = await api.post('/platform/citizens/status.php', payload)
  return data
}
