/**
 * Emergency (accident) types — mirrors the emergency_categories seed rows
 * (ids are stable). The requester may pick one when sending SOS (optional,
 * default "Other"); the handling organization can relabel it later
 * (organizations/incidents/update_details.php).
 * Same file in dascare/src/config/emergencyTypes.js (web) — keep in sync.
 */
export const OTHER_EMERGENCY_TYPE = 8

export const EMERGENCY_TYPES = [
  { id: 1, label: 'Medical', icon: 'lucide:stethoscope' },
  { id: 2, label: 'Road accident', icon: 'lucide:car-front' },
  { id: 3, label: 'Injury / trauma', icon: 'lucide:bandage' },
  { id: 4, label: 'Pregnancy / birth', icon: 'lucide:baby' },
  { id: 5, label: 'Heart / chest pain', icon: 'lucide:heart-pulse' },
  { id: 6, label: 'Fire', icon: 'lucide:flame' },
  { id: 7, label: 'Disaster', icon: 'lucide:waves' },
  { id: 8, label: 'Other', icon: 'lucide:circle-help' },
]
