/**
 * False-alarm sanctions (dascare_api/reusables/false_alarm_sanctions.php).
 * Citizen side works with the session, or with the short-lived key a
 * suspended citizen gets at login (X-Sanction-Token).
 * Same citizen functions in dascare_mobile/src/services/sanctions.js — keep in sync.
 */
import api from '@/services/api'

const tokenHeaders = (token) => (token ? { headers: { 'X-Sanction-Token': token } } : {})

export const fetchMySanctions = async (token = null) => (await api.get('/citizen/sanctions.php', tokenHeaders(token))).data
export const appealStrike = async (strikeId, message, token = null) => (await api.post('/citizen/sanction_appeal.php', { strike_id: strikeId, message }, tokenHeaders(token))).data

// Platform → False Alarm Sanctions
export const fetchSanctionsOverview = async () => (await api.get('/platform/sanctions/list.php')).data
export const saveSanctionSettings = async (settings) => (await api.post('/platform/sanctions/settings.php', settings)).data
export const reviewSanctionAppeal = async (appealId, decision, note) => (await api.post('/platform/sanctions/review_appeal.php', { appeal_id: appealId, decision, note })).data
export const removeSanctionStrike = async (strikeId, reason) => (await api.post('/platform/sanctions/remove_strike.php', { strike_id: strikeId, reason })).data
export const liftSanctionSuspension = async (userId, note) => (await api.post('/platform/sanctions/lift.php', { user_id: userId, note })).data
