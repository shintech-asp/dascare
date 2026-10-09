import api from '@/services/api'

export const fetchPlatformOrganizations = async (params={}) => (await api.get('/platform/management/organizations.php',{params})).data
export const updatePlatformOrganizationStatus = async (payload) => (await api.post('/platform/management/organizations.php',payload)).data
export const fetchPlatformCompliance = async () => (await api.get('/platform/compliance/list.php')).data
export const fetchPlatformFleet = async () => (await api.get('/platform/fleet/list.php')).data
export const fetchPlatformAnalytics = async () => (await api.get('/platform/analytics/summary.php')).data
export const fetchPlatformDssSettings = async () => (await api.get('/platform/settings/dss.php')).data
export const savePlatformDssSettings = async (payload) => (await api.post('/platform/settings/dss.php',payload)).data
export const fetchPlatformAudit = async (params={}) => (await api.get('/platform/audit/list.php',{params})).data
export const fetchPlatformEscalations = async () => (await api.get('/platform/escalations/list.php')).data

export const fetchTechnicalSummary = async () => (await api.get('/technical/summary.php')).data
export const fetchTechnicalAccounts = async (params={}) => (await api.get('/technical/accounts/list.php',{params})).data
export const updateTechnicalAccountStatus = async (payload) => (await api.post('/technical/accounts/status.php',payload)).data
export const fetchTechnicalAudit = async (params={}) => (await api.get('/technical/audit/list.php',{params})).data
export const fetchTechnicalConfiguration = async () => (await api.get('/technical/configuration/index.php')).data
export const saveTechnicalConfiguration = async (key,value) => (await api.post('/technical/configuration/index.php',{key,value})).data
export const saveTechnicalConfigurationBatch = async (items) => (await api.post('/technical/configuration/index.php',{items})).data
