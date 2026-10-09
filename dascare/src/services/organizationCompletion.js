import api from '@/services/api'

export const fetchOrganizationDocuments = async () => (await api.get('/organizations/management/documents.php')).data
export const fetchOrganizationReports = async () => (await api.get('/organizations/reports/summary.php')).data
export const fetchOrganizationAudit = async (params={}) => (await api.get('/organizations/audit/list.php',{params})).data
export const fetchOrganizationIncidents = async () => (await api.get('/organizations/incidents/list.php')).data
export const updateIncidentDetails = async (payload) => (await api.post('/organizations/incidents/update_details.php', payload)).data
