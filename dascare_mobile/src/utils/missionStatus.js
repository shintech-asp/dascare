// Mission (dispatch assignment) statuses for the staff app — same flow and
// rules as the web's Active Missions page and missions/status.php.
export const ACTIVE_MISSION = ['assigned', 'acknowledged', 'responding', 'on_scene', 'transporting']

const LABELS = {
  assigned: 'Assigned', acknowledged: 'Acknowledged', responding: 'Responding', on_scene: 'On scene',
  transporting: 'Transporting', completed: 'Completed', cancelled: 'Cancelled', reassigned: 'Reassigned', declined: 'Declined',
}
export const missionLabel = (s) => LABELS[s] || s

export function missionBadge(s) {
  if (s === 'assigned') return 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'
  if (s === 'acknowledged' || s === 'responding') return 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300'
  if (s === 'on_scene') return 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300'
  if (s === 'transporting') return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
  return 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-white/45'
}

// Allowed next steps (missions/status.php $trans).
export const NEXT_STEPS = {
  assigned: [{ status: 'acknowledged', label: 'Acknowledge mission', icon: 'lucide:check-circle-2' }],
  acknowledged: [{ status: 'responding', label: 'Depart — en route', icon: 'lucide:siren' }],
  responding: [{ status: 'on_scene', label: 'Arrived on scene', icon: 'lucide:map-pin-check' }],
  on_scene: [
    { status: 'transporting', label: 'Start patient transport', icon: 'lucide:ambulance' },
    { status: 'completed', label: 'Complete — no transport', icon: 'lucide:flag', secondary: true },
  ],
  transporting: [{ status: 'completed', label: 'Complete mission', icon: 'lucide:flag' }],
}

export const STEPPER = ['assigned', 'acknowledged', 'responding', 'on_scene', 'transporting', 'completed']
