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

// --- Employee government ID (same rules as reusables/input_validation.php) ---
export const EMPLOYEE_ID_TYPES = [
  { value: 'drivers_license', label: "Driver's license (LTO)", hint: 'N01-12-345678' },
  { value: 'philsys', label: 'PhilSys National ID', hint: '1234-5678-9012-3456' },
  { value: 'umid', label: 'UMID', hint: 'CRN-0123-4567890-1' },
  { value: 'prc', label: 'PRC ID', hint: 'PRC license number' },
  { value: 'passport', label: 'Passport', hint: 'P1234567A' },
  { value: 'sss', label: 'SSS ID', hint: 'SS number' },
  { value: 'voters', label: "Voter's ID", hint: 'Voter ID number' },
  { value: 'postal', label: 'Postal ID', hint: 'Postal ID number' },
  { value: 'other_government_id', label: 'Other government ID', hint: 'ID number' },
]
export const isDriverRole = (roleName) => String(roleName || '').trim().toLowerCase() === 'driver'

/** Dashed uppercase ID number for its type, or null when invalid. */
export function normalizeIdNumber(type, raw) {
  const v = String(raw ?? '').trim().toUpperCase().replace(/\s+/g, '')
  if (type === 'drivers_license') {
    const m = v.replace(/-/g, '').match(/^([A-Z])(\d{2})(\d{2})(\d{6})$/)
    return m ? `${m[1]}${m[2]}-${m[3]}-${m[4]}` : null
  }
  if (!/^[A-Z0-9][A-Z0-9-]{4,24}$/.test(v) || (v.match(/\d/g) || []).length < 4 || /(.)\1{5,}/.test(v)) return null
  return v
}

/** First problem with an ID type + number, or ''. */
export function idNumberError(type, number, driver = false) {
  const t = driver ? 'drivers_license' : type
  if (!EMPLOYEE_ID_TYPES.some((x) => x.value === t)) return 'Choose which government ID the employee is giving.'
  if (!String(number || '').trim()) return driver ? "Enter the driver's license number (e.g. N01-12-345678)." : "Enter the employee's government ID number."
  if (normalizeIdNumber(t, number) === null) {
    return t === 'drivers_license' ? 'Enter a valid license number like N01-12-345678 (letter, 2 digits, 2 digits, 6 digits).' : `Enter a valid ${EMPLOYEE_ID_TYPES.find((x) => x.value === t).label} number (letters/digits, at least 4 digits).`
  }
  return ''
}
