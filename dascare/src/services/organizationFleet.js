import api from '@/services/api'

export async function fetchFleetUnits() {
  const { data } = await api.get('/organizations/fleet/list.php')
  return data
}

export async function fetchFleetUnit(id) {
  const { data } = await api.get('/organizations/fleet/get.php', { params: { id } })
  return data
}

export async function createFleetUnit(payload) {
  const { data } = await api.post('/organizations/fleet/create.php', payload)
  return data
}

export async function updateFleetUnit(payload) {
  const { data } = await api.post('/organizations/fleet/update.php', payload)
  return data
}

export async function updateFleetUnitStatus(id, status, reason = '') {
  const { data } = await api.post('/organizations/fleet/status.php', { id, status, reason })
  return data
}

export async function archiveFleetUnit(id, reason) {
  const { data } = await api.post('/organizations/fleet/archive.php', { id, reason })
  return data
}

export async function saveReadinessCheck(payload) {
  const { data } = await api.post('/organizations/fleet/readiness/save.php', payload)
  return data
}

export async function createMaintenanceRecord(payload) {
  const { data } = await api.post('/organizations/fleet/maintenance/create.php', payload)
  return data
}

export async function updateMaintenanceRecord(payload) {
  const { data } = await api.post('/organizations/fleet/maintenance/update.php', payload)
  return data
}
