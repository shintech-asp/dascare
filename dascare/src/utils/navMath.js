/**
 * Geometry for in-app driving navigation (components/maps/NavigationMode.vue).
 * Small-area math (Dasmariñas): an equirectangular projection around the
 * route is accurate to centimetres over a few km.
 * Same file in dascare_mobile/src/utils/navMath.js — keep in sync.
 */
const R = 6371000

export function haversine(a, b) {
  const toRad = (d) => (d * Math.PI) / 180
  const dLat = toRad(b[1] - a[1]), dLng = toRad(b[0] - a[0])
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a[1])) * Math.cos(toRad(b[1])) * Math.sin(dLng / 2) ** 2
  return 2 * R * Math.asin(Math.sqrt(h))
}

export function bearing(a, b) {
  const toRad = (d) => (d * Math.PI) / 180
  const y = Math.sin(toRad(b[0] - a[0])) * Math.cos(toRad(b[1]))
  const x = Math.cos(toRad(a[1])) * Math.sin(toRad(b[1])) - Math.sin(toRad(a[1])) * Math.cos(toRad(b[1])) * Math.cos(toRad(b[0] - a[0]))
  return ((Math.atan2(y, x) * 180) / Math.PI + 360) % 360
}

/**
 * Prepares a Mapbox Directions route (geometries=geojson, steps=true) for
 * fast progress lookups: the full line with cumulative distances, and each
 * step's start/end distance along it.
 */
export function prepareRoute(route) {
  const coords = route.geometry.coordinates
  const cum = [0]
  for (let i = 1; i < coords.length; i++) cum.push(cum[i - 1] + haversine(coords[i - 1], coords[i]))
  const steps = []
  let along = 0
  for (const s of route.legs[0].steps) {
    steps.push({ ...s, startAlong: along, endAlong: along + s.distance })
    along += s.distance
  }
  // Step distances are rounded; scale so they end exactly at the line length.
  const scale = along > 0 ? cum[cum.length - 1] / along : 1
  for (const s of steps) { s.startAlong *= scale; s.endAlong *= scale }
  return { coords, cum, length: cum[cum.length - 1], steps, duration: route.duration, distance: route.distance }
}

/**
 * Snap a position to the route: distance from the line (metres), distance
 * travelled along it, and the snapped point. Searches near the previous hit
 * first so a route that doubles back doesn't jump.
 */
export function snapToRoute(prepared, pos, hintIndex = 0) {
  const { coords, cum } = prepared
  const lat0 = (pos[1] * Math.PI) / 180
  const kx = (Math.PI / 180) * R * Math.cos(lat0), ky = (Math.PI / 180) * R
  const px = pos[0] * kx, py = pos[1] * ky
  let best = { dist: Infinity, along: 0, index: 0, point: coords[0] }
  const from = Math.max(0, hintIndex - 30), to = coords.length - 1
  for (let i = from; i < to; i++) {
    const ax = coords[i][0] * kx, ay = coords[i][1] * ky, bx = coords[i + 1][0] * kx, by = coords[i + 1][1] * ky
    const dx = bx - ax, dy = by - ay, len2 = dx * dx + dy * dy
    const t = len2 ? Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / len2)) : 0
    const sx = ax + t * dx, sy = ay + t * dy
    const d = Math.hypot(px - sx, py - sy)
    if (d < best.dist) best = { dist: d, along: cum[i] + t * (cum[i + 1] - cum[i]), index: i, point: [sx / kx, sy / ky] }
  }
  return best
}

/** Index of the step the vehicle is on, given the distance travelled along the route. */
export function stepIndexAt(prepared, along) {
  const { steps } = prepared
  for (let i = 0; i < steps.length; i++) if (along < steps[i].endAlong) return i
  return steps.length - 1
}

/** Bearing of the route a short distance ahead of `along` (camera heading when GPS heading is unreliable). */
export function routeBearingAt(prepared, along, ahead = 30) {
  const { coords, cum } = prepared
  const target = Math.min(prepared.length, along + ahead)
  let i = 0
  while (i < cum.length - 2 && cum[i + 1] < along) i++
  let j = i
  while (j < cum.length - 1 && cum[j] < target) j++
  return bearing(coords[i], coords[Math.max(j, i + 1)])
}

/** "200 m", "1.4 km" — rounded the way drivers read it. */
export function formatDistance(m) {
  if (m >= 1000) return `${(m / 1000).toFixed(m >= 10000 ? 0 : 1)} km`
  if (m >= 100) return `${Math.round(m / 50) * 50} m`
  return `${Math.max(0, Math.round(m / 10) * 10)} m`
}

/** Lucide icon for a Mapbox maneuver. */
export function maneuverIcon(type, modifier) {
  if (type === 'arrive') return 'lucide:flag'
  if (type === 'depart') return 'lucide:navigation'
  if (type === 'roundabout' || type === 'rotary' || type === 'roundabout turn') return 'lucide:rotate-cw'
  if (modifier === 'uturn') return 'lucide:undo-2'
  if (modifier === 'sharp left' || modifier === 'left') return 'lucide:corner-up-left'
  if (modifier === 'sharp right' || modifier === 'right') return 'lucide:corner-up-right'
  if (modifier === 'slight left') return 'lucide:arrow-up-left'
  if (modifier === 'slight right') return 'lucide:arrow-up-right'
  if (type === 'merge') return 'lucide:merge'
  return 'lucide:arrow-up'
}
