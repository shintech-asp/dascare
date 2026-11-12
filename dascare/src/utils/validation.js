// Form checks shared by the web and the app — same rules as
// dascare_api/reusables/input_validation.php (the server has the final say).
// Same file in dascare_mobile/src/utils/validation.js — keep in sync.

/** Reads like real text: enough letters, no key-mashing ("aaaaa", "zxcvbn"). */
export function looksLikeText(s, minLetters = 3) {
  const t = String(s ?? '').trim()
  if ((t.match(/\p{L}/gu) || []).length < minLetters) return false
  if (/(.)\1{3,}/u.test(t)) return false
  return !t.toLowerCase().split(/[^\p{L}]+/u).some((w) => w.length >= 5 && !/[aeiouyáéíóúñ]/u.test(w))
}

export const isPersonName = (s) => /^[\p{L}][\p{L} .'\-]+$/u.test(String(s ?? '').trim())

const VITALS = {
  pulse_bpm: ['Pulse', 20, 250, 'bpm', true],
  respiratory_rate: ['Respiratory rate', 4, 80, 'breaths/min', true],
  spo2_percent: ['SpO2', 50, 100, '%', true],
  temperature_c: ['Temperature', 30, 45, '°C', false],
}

/** First problem in a vital-signs object, or '' when fine. Blank values are allowed. */
export function vitalsError(v = {}) {
  for (const [key, [label, min, max, unit, whole]] of Object.entries(VITALS)) {
    const raw = String(v[key] ?? '').trim()
    if (!raw) continue
    const n = Number(raw)
    if (!Number.isFinite(n) || n < min || n > max || (whole && !Number.isInteger(n))) {
      return `${label} must be a ${whole ? 'whole ' : ''}number between ${min} and ${max} ${unit}.`
    }
  }
  const bp = String(v.blood_pressure ?? '').trim()
  if (bp) {
    const m = bp.match(/^(\d{2,3})\s*\/\s*(\d{2,3})$/)
    if (!m || +m[1] < 50 || +m[1] > 260 || +m[2] < 30 || +m[2] > 160 || +m[1] <= +m[2]) return 'Blood pressure must look like 120/80.'
  }
  return ''
}

/** Assessment form → first error message, or ''. */
export function assessmentError(f) {
  if (f.patient_name && !isPersonName(f.patient_name)) return 'Patient name can only contain letters, spaces, apostrophes, periods and hyphens.'
  if (f.approximate_age !== '' && f.approximate_age != null && !(Number.isInteger(Number(f.approximate_age)) && f.approximate_age >= 0 && f.approximate_age <= 120)) return 'Approximate age must be between 0 and 120.'
  if (f.breathing_status && !looksLikeText(f.breathing_status, 3)) return 'Describe the breathing status in words (e.g. normal, labored).'
  if (!looksLikeText(f.condition_summary, 8)) return "Describe the patient's condition in words (at least a short sentence)."
  if (f.injuries && !looksLikeText(f.injuries, 3)) return 'Injuries/notes must be readable text.'
  return vitalsError(f.vital_signs)
}

/** Field-report form → first error message, or ''. */
export function fieldReportError(f, submit) {
  if (!looksLikeText(f.incident_summary, 10) || !looksLikeText(f.actions_taken, 10)) return 'Write the incident summary and actions taken as real sentences (at least 10 letters each).'
  if (submit && f.outcome === 'transported' && !f.destination_facility_id) return 'Choose the destination facility for a transported patient.'
  return ''
}

/** Incident-details form (organization side) → first error message, or ''. */
export function incidentDetailsError(f) {
  if (f.requester_name && !isPersonName(f.requester_name)) return 'Requester name can only contain letters, spaces, apostrophes, periods and hyphens.'
  if (f.landmark && !looksLikeText(f.landmark, 3)) return 'Landmark must be readable text.'
  if (f.address_text && !looksLikeText(f.address_text, 3)) return 'Address must be readable text.'
  if (f.description && !looksLikeText(f.description, 8)) return 'Describe the situation in words (at least a short sentence).'
  return ''
}
