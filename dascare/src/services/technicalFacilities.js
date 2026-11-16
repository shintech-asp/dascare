import api from '@/services/api'

export const fetchTechnicalFacilities = async () => (await api.get('/technical/facilities/list.php')).data
export const saveTechnicalFacility = async (payload) => (await api.post('/technical/facilities/save.php', payload)).data
export const setTechnicalFacilityStatus = async (id, status) => (await api.post('/technical/facilities/save.php', { id, status })).data
