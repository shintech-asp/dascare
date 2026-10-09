import api from '@/services/api'

export async function fetchOrganizationApplications(params = {}) {
  const { data } = await api.get('/platform/organizations/list.php', { params })
  return data
}

export async function fetchOrganizationApplicationDetail(id) {
  const { data } = await api.get('/platform/organizations/detail.php', { params: { id } })
  return data
}

export async function fetchOrganizationDocument(documentId) {
  const { data } = await api.get('/platform/organizations/media.php', {
    params: { document_id: documentId },
    responseType: 'blob',
  })
  return data
}

export async function submitOrganizationReview(payload) {
  const { data } = await api.post('/platform/organizations/review.php', payload)
  return data
}
