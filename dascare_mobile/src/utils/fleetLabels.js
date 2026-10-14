// Fleet labels/pill colours for the staff app — same wording as the web's
// Fleet and Readiness pages.
export const typeLabel = (v) => ({ basic_life_support: 'Basic Life Support', advanced_life_support: 'Advanced Life Support', patient_transport: 'Patient Transport', rescue_unit: 'Rescue Unit' }[v] || v)
export const statusLabel = (v) => ({ available: 'Available', reserved: 'Reserved', dispatched: 'Dispatched', on_scene: 'On scene', transporting: 'Transporting', returning: 'Returning', maintenance: 'Maintenance', offline: 'Offline' }[v] || v || 'Created')
export const readinessLabel = (v) => ({ ready: 'Ready', needs_attention: 'Needs attention', out_of_service: 'Out of service' }[v] || 'Not checked')
export function statusPill(v) {
  if (v === 'available') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
  if (v === 'maintenance') return 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
  if (v === 'offline') return 'bg-slate-200 text-slate-600 dark:bg-white/10 dark:text-white/45'
  return 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300'
}
export function readinessPill(v) {
  if (v === 'ready') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
  if (v === 'out_of_service') return 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'
  if (v === 'needs_attention') return 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
  return 'bg-base-200 text-slate-500 dark:bg-white/5 dark:text-white/40'
}
export const maintenanceTypeLabel = (v) => ({ preventive: 'Preventive maintenance', repair: 'Repair', inspection: 'Inspection', other: 'Other' }[v] || v)
export const maintenanceStatusLabel = (v) => ({ scheduled: 'Scheduled', in_progress: 'In progress', completed: 'Completed', cancelled: 'Cancelled' }[v] || v)
export const formatDay = (v) => (v ? new Date(`${String(v).slice(0, 10)}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Not recorded')
export const formatWhen = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—')
