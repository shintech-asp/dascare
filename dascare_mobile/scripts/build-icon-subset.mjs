// Bundle only the line-md / mdi icons the app actually uses.
//
// The web loads icons from the Iconify API on demand; the app must work
// offline, so icons are bundled. Lucide is bundled whole (small), but the
// web also uses line-md (animated toasts/alerts/auth fields) and mdi, which
// are 1-3 MB each. This scans src/ for "line-md:name" / "mdi:name" strings
// and writes just those icons to src/assets/icon-subset.json.
//
// Runs automatically before `npm run dev` / `npm run build`.
// Icon names must appear as literal strings in src/ to be picked up.
import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { createRequire } from 'node:module'

const require = createRequire(import.meta.url)
const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const SETS = ['line-md', 'mdi']

function walk(dir, out = []) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name)
    if (statSync(p).isDirectory()) walk(p, out)
    else if (/\.(vue|js)$/.test(name)) out.push(p)
  }
  return out
}

const wanted = Object.fromEntries(SETS.map((s) => [s, new Set()]))
const pattern = new RegExp(`\\b(${SETS.join('|')}):([a-z0-9-]+)`, 'g')
for (const file of walk(join(root, 'src'))) {
  for (const [, set, name] of readFileSync(file, 'utf8').matchAll(pattern)) wanted[set].add(name)
}

const collections = []
const missing = []
for (const set of SETS) {
  const full = require(`@iconify-json/${set}/icons.json`)
  const icons = {}
  for (const name of [...wanted[set]].sort()) {
    const icon = full.icons[name] ?? (full.aliases?.[name] && { ...full.icons[full.aliases[name].parent], ...full.aliases[name] })
    if (icon) icons[name] = icon
    else missing.push(`${set}:${name}`)
  }
  collections.push({ prefix: set, icons, width: full.width, height: full.height })
}

writeFileSync(join(root, 'src/assets/icon-subset.json'), JSON.stringify(collections))
const count = collections.reduce((n, c) => n + Object.keys(c.icons).length, 0)
console.log(`icon-subset: ${count} icons (${SETS.join(', ')})${missing.length ? ` — not found: ${missing.join(', ')}` : ''}`)
