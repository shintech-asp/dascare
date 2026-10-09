// Shared Tailwind class strings, copied from the web's auth/KYC screens
// (dascare/src/views/auth/AuthPage.vue, views/pages/Verification.vue) so the
// app's forms look the same. Only change: py-3.5 / text-base inputs, which
// stop Android from zooming in on focus and give bigger tap targets.

export const label = 'mb-1.5 block text-xs font-semibold text-slate-500 dark:text-white/50'

export const input =
  'w-full rounded-xl border border-slate-200 bg-[#f8f3e8] px-4 py-3.5 text-base text-slate-800 placeholder:text-slate-400 transition-all focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20 disabled:opacity-60 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-white/30'

export const inputWithIcon = input.replace('px-4', 'pl-11 pr-4')

export const primaryButton =
  'tap flex w-full items-center justify-center gap-2 rounded-2xl bg-red-600 py-4 font-bold text-white shadow-lg shadow-red-600/25 transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60 dark:shadow-red-950/40'

export const secondaryButton =
  'tap flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-slate-200 py-3.5 text-sm font-semibold text-slate-600 transition-colors hover:bg-[#f3ecdd] dark:border-white/10 dark:text-white/60 dark:hover:bg-white/5'

export const card =
  'rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#071829]'

export const eyebrow =
  'inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-red-700 dark:bg-red-500/10 dark:text-red-300'

export const PH_MOBILE = /^(\+639\d{9}|09\d{9})$/

// Mirrors the web (and dascare_api/reusables/password_helpers.php): 10+ chars,
// upper + lower case, a number and a special character.
export function passwordScore(pwd) {
  if (!pwd) return 0
  let s = 0
  if (pwd.length >= 10) s++
  if (pwd.length >= 14) s++
  if (/[a-z]/.test(pwd) && /[A-Z]/.test(pwd)) s++
  if (/\d/.test(pwd)) s++
  if (/[^A-Za-z0-9]/.test(pwd)) s++
  return Math.min(s, 4)
}
export function passwordText(score) {
  if (score <= 1) return 'Weak password'
  if (score === 2) return 'Fair password'
  if (score === 3) return 'Good password'
  return 'Strong password'
}
export function meetsPasswordRules(pwd) {
  return pwd.length >= 10 && pwd.length <= 128 &&
    /[a-z]/.test(pwd) && /[A-Z]/.test(pwd) && /\d/.test(pwd) && /[^A-Za-z0-9]/.test(pwd)
}
