// Emergency-request labels, icons, colours and dispatch stages — copied from
// the web's citizen pages (TrackRequest.vue, MyRequests.vue,
// CitizenDashboardHome.vue) so the app shows statuses identically.

export const STATUS_LABELS = {
  submitted: 'Submitted', validating: 'Validating', verified: 'Verified', assigned: 'Assigned',
  acknowledged: 'Acknowledged', responding: 'Responding', on_scene: 'On Scene', transporting: 'Transporting',
  completed: 'Completed', cancelled: 'Cancelled', rejected: 'Rejected', duplicate: 'Duplicate', false_alarm: 'False Alarm',
}
export const statusLabel = (status) => STATUS_LABELS[status] ?? status

export const STATUS_ICONS = {
  submitted: 'lucide:file-text', validating: 'lucide:search', verified: 'lucide:shield-check',
  assigned: 'lucide:truck', acknowledged: 'lucide:check', responding: 'lucide:navigation',
  on_scene: 'lucide:map-pin-check', transporting: 'lucide:activity', completed: 'lucide:check-circle-2',
  cancelled: 'lucide:x-circle', rejected: 'lucide:x-circle', duplicate: 'lucide:copy-x', false_alarm: 'lucide:shield-x',
}
export const statusIcon = (status) => STATUS_ICONS[status] ?? 'lucide:circle'

export const TERMINAL_ALT = ['cancelled', 'rejected', 'duplicate', 'false_alarm']
export const ACTIVE_STATUSES = ['submitted', 'validating', 'verified', 'assigned', 'acknowledged', 'responding', 'on_scene', 'transporting']
export const isActiveStatus = (status) => ACTIVE_STATUSES.includes(status)

export function statusBadgeClass(status) {
  if (status === 'completed') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
  if (TERMINAL_ALT.includes(status)) return 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-white/40'
  return 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'
}

export const STAGES = [
  { key: 'submitted', label: 'Submitted', icon: 'lucide:file-text', match: ['submitted', 'validating'] },
  { key: 'verified', label: 'Verified', icon: 'lucide:shield-check', match: ['verified'] },
  { key: 'assigned', label: 'Assigned', icon: 'lucide:truck', match: ['assigned', 'acknowledged'] },
  { key: 'responding', label: 'Responding', icon: 'lucide:navigation', match: ['responding'] },
  { key: 'on_scene', label: 'On Scene', icon: 'lucide:map-pin-check', match: ['on_scene'] },
  { key: 'transporting', label: 'Transporting', icon: 'lucide:activity', match: ['transporting'] },
  { key: 'completed', label: 'Completed', icon: 'lucide:check-circle-2', match: ['completed'] },
]
export function stageIndex(status) {
  const i = STAGES.findIndex((s) => s.match.includes(status))
  return i === -1 ? 0 : i
}

// Loose match against the category name, same table as the web.
const CATEGORY_ICONS = [
  [/medical/i, 'lucide:stethoscope'], [/road|accident|vehicle/i, 'lucide:car-front'], [/trauma|injury/i, 'lucide:bandage'],
  [/maternal|pregnan/i, 'lucide:baby'], [/cardiac|heart/i, 'lucide:heart-pulse'], [/fire/i, 'lucide:flame'], [/disaster/i, 'lucide:cloud-lightning'],
]
export const categoryIcon = (name = '') => CATEGORY_ICONS.find(([re]) => re.test(name))?.[1] ?? 'lucide:siren'

/** A report linked by duplicate detection follows the linked incident's status. */
export const followLinked = (r) => (r && r.linked_status ? { ...r, own_status: r.status, status: r.linked_status } : r)

export const formatDate = (s) => (s ? new Date(String(s).replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—')

export function relativeTime(s, now = Date.now()) {
  if (!s) return ''
  const mins = Math.round((now - new Date(String(s).replace(' ', 'T')).getTime()) / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins} min${mins === 1 ? '' : 's'} ago`
  const hrs = Math.round(mins / 60)
  if (hrs < 24) return `${hrs} hr${hrs === 1 ? '' : 's'} ago`
  const days = Math.round(hrs / 24)
  return `${days} day${days === 1 ? '' : 's'} ago`
}
