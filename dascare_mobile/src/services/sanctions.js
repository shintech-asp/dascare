/**
 * False-alarm sanctions (dascare_api/reusables/false_alarm_sanctions.php).
 * Citizen side works with the session, or with the short-lived key a
 * suspended citizen gets at login (X-Sanction-Token).
 * Citizen functions copied from dascare/src/services/sanctions.js (web) — keep in sync.
 */
import api from '@/services/api'

const tokenHeaders = (token) => (token ? { headers: { 'X-Sanction-Token': token } } : {})

export const fetchMySanctions = async (token = null) => (await api.get('/citizen/sanctions.php', tokenHeaders(token))).data
export const appealStrike = async (strikeId, message, token = null) => (await api.post('/citizen/sanction_appeal.php', { strike_id: strikeId, message }, tokenHeaders(token))).data

