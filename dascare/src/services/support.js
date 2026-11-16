// Voluntary support: donations (PayMongo checkout), fundraising goals, sponsors.
// Same public wrappers in dascare_mobile/src/services/support.js — keep in sync.
import api from '@/services/api'

// ---- public
export const fetchSupportPublic = async () => (await api.get('/support/public.php')).data
export const createDonation = async (payload) => (await api.post('/support/donate.php', payload)).data
export const fetchDonationStatus = async (ref) => (await api.get('/support/status.php', { params: { ref } })).data
export const sendSponsorInquiry = async (payload) => (await api.post('/support/sponsor_inquiry.php', payload)).data
export const sponsorLogoUrl = (id) => `${String(api.defaults.baseURL || '').replace(/\/$/, '')}/support/sponsor_logo.php?id=${id}`

// ---- platform (Platform Executive)
export const fetchDonations = async (params = {}) => (await api.get('/platform/support/donations.php', { params })).data
export const recordDonation = async (payload) => (await api.post('/platform/support/donations.php', { action: 'record', ...payload })).data
export const updateDonation = async (payload) => (await api.post('/platform/support/donations.php', { action: 'update', ...payload })).data
export const fetchCampaigns = async () => (await api.get('/platform/support/campaigns.php')).data
export const saveCampaign = async (payload) => (await api.post('/platform/support/campaigns.php', { action: 'save_campaign', ...payload })).data
export const saveSupportSettings = async (settings) => (await api.post('/platform/support/campaigns.php', { action: 'save_settings', settings })).data
export const fetchSponsors = async () => (await api.get('/platform/support/sponsors.php')).data
export const saveSponsor = async (formData) => (await api.post('/platform/support/sponsors.php', formData, { headers: { 'Content-Type': 'multipart/form-data' } })).data
export const setSponsorStatus = async (payload) => (await api.post('/platform/support/sponsors.php', { action: 'status', ...payload })).data
export const deleteSponsor = async (id) => (await api.post('/platform/support/sponsors.php', { action: 'delete', id })).data
export const fetchSponsorLogoBlob = async (id) => (await api.get('/platform/support/sponsor_logo.php', { params: { id }, responseType: 'blob' })).data
export const fetchInquiries = async () => (await api.get('/platform/support/inquiries.php')).data
export const updateInquiry = async (payload) => (await api.post('/platform/support/inquiries.php', payload)).data

export const formatPeso = (centavos, decimals = 0) => `₱${(Number(centavos || 0) / 100).toLocaleString('en-PH', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}`
