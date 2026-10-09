import api from '@/services/api'

export async function fetchPlatformIncidents(params = {}) {
  const { data } = await api.get('/platform/incidents/list.php', { params })
  if (!data?.success) throw new Error(data?.message || 'Unable to load incidents.')
  return data
}

export async function fetchPlatformIncident(id) {
  const { data } = await api.get('/platform/incidents/detail.php', { params: { id } })
  if (!data?.success) throw new Error(data?.message || 'Unable to load incident.')
  return data
}

export async function runIncidentDss(requestId) {
  const { data } = await api.post('/platform/incidents/run_dss.php', { request_id: requestId })
  if (!data?.success) throw new Error(data?.message || 'Unable to run DSS.')
  return data
}

export async function fetchOrganizationOffers() {
  const { data } = await api.get('/organizations/dispatch/offers/list.php')
  if (!data?.success) throw new Error(data?.message || 'Unable to load incident offers.')
  return data
}

export async function respondToIncidentOffer(offerId, decision, note = '') {
  const { data } = await api.post('/organizations/dispatch/offers/respond.php', {
    offer_id: offerId,
    decision,
    note,
  })
  if (!data?.success) throw new Error(data?.message || 'Unable to respond to incident offer.')
  return data
}

export async function fetchResourceAssignments() {
  const { data } = await api.get('/organizations/dispatch/assignments/list.php')
  if (!data?.success) throw new Error(data?.message || 'Unable to load resource assignments.')
  return data
}

export async function createResourceAssignment(payload) {
  const { data } = await api.post('/organizations/dispatch/assignments/create.php', payload)
  if (!data?.success) throw new Error(data?.message || 'Unable to assign resources.')
  return data
}

export async function fetchOrganizationMissions() {
  const { data } = await api.get('/organizations/dispatch/missions/list.php')
  if (!data?.success) throw new Error(data?.message || 'Unable to load missions.')
  return data
}

export async function updateMissionStatus(assignmentId, status, note = '') {
  const { data } = await api.post('/organizations/dispatch/missions/status.php', {
    assignment_id: assignmentId,
    status,
    note,
  })
  if (!data?.success) throw new Error(data?.message || 'Unable to update mission.')
  return data
}

// --- Duplicate-report dedup (reusables/dispatch_dedup.php) ---

export async function unmergePlatformReport(requestId, reason = '') {
  const { data } = await api.post('/platform/incidents/unmerge.php', { request_id: requestId, reason })
  if (!data?.success) throw new Error(data?.message || 'Unable to unlink this report.')
  return data
}

export async function fetchOrganizationLinkedReports(requestId) {
  const { data } = await api.get('/organizations/incidents/linked.php', { params: { id: requestId } })
  if (!data?.success) throw new Error(data?.message || 'Unable to load linked reports.')
  return data.reports || []
}

export async function unmergeOrganizationReport(requestId, reason = '') {
  const { data } = await api.post('/organizations/incidents/unmerge.php', { request_id: requestId, reason })
  if (!data?.success) throw new Error(data?.message || 'Unable to unlink this report.')
  return data
}

// Requester's own "not the same emergency" — citizen or same-session guest.
export async function unmergeOwnReport(requestId) {
  const { data } = await api.post('/citizen/unmerge.php', { id: requestId })
  if (!data?.success) throw new Error(data?.message || 'Unable to request separately.')
  return data
}
