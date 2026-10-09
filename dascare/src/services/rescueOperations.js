import api from '@/services/api'

export async function fetchLiveTracking() {
  const { data } = await api.get('/organizations/tracking/list.php')
  if (!data?.success) throw new Error(data?.message || 'Unable to load live tracking.')
  return data
}

export async function pushAmbulanceLocation(payload) {
  const { data } = await api.post('/organizations/tracking/update.php', payload)
  if (!data?.success) throw new Error(data?.message || 'Unable to update ambulance location.')
  return data
}

export async function fetchAssessments(requestId) {
  const { data } = await api.get('/organizations/care/assessments_list.php', { params: { request_id: requestId } })
  if (!data?.success) throw new Error(data?.message || 'Unable to load assessments.')
  return data
}

export async function createAssessment(payload) {
  const { data } = await api.post('/organizations/care/assessment_create.php', payload)
  if (!data?.success) throw new Error(data?.message || 'Unable to save assessment.')
  return data
}

export async function fetchReceivingFacilities() {
  const { data } = await api.get('/organizations/care/facilities.php')
  if (!data?.success) throw new Error(data?.message || 'Unable to load facilities.')
  return data
}

export async function fetchHandoffs(assignmentId) {
  const { data } = await api.get('/organizations/care/handoffs_list.php', { params: { assignment_id: assignmentId } })
  if (!data?.success) throw new Error(data?.message || 'Unable to load handoffs.')
  return data
}

export async function createHandoff(payload) {
  const { data } = await api.post('/organizations/care/handoff_create.php', payload)
  if (!data?.success) throw new Error(data?.message || 'Unable to create handoff.')
  return data
}

export async function updateHandoffStatus(handoffId, status, note = '') {
  const { data } = await api.post('/organizations/care/handoff_status.php', { handoff_id: handoffId, status, note })
  if (!data?.success) throw new Error(data?.message || 'Unable to update handoff.')
  return data
}

export async function fetchFieldReport(requestId) {
  const { data } = await api.get('/organizations/care/report_get.php', { params: { request_id: requestId } })
  if (!data?.success) throw new Error(data?.message || 'Unable to load field report.')
  return data
}

export async function saveFieldReport(payload) {
  const { data } = await api.post('/organizations/care/report_save.php', payload)
  if (!data?.success) throw new Error(data?.message || 'Unable to save field report.')
  return data
}
