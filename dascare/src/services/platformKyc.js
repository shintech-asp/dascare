import api from '@/services/api'

export async function fetchKycQueue(params = {}) {
  const { data } = await api.get('/platform/kyc/list.php', { params })
  return data
}

export async function fetchKycDetail(userId) {
  const { data } = await api.get('/platform/kyc/detail.php', {
    params: { user_id: userId },
  })
  return data
}

export async function fetchKycImage(userId) {
  const { data } = await api.get('/platform/kyc/media.php', {
    params: { user_id: userId },
    responseType: 'blob',
  })
  return data
}

export async function submitKycReview(payload) {
  const { data } = await api.post('/platform/kyc/review.php', payload)
  return data
}
