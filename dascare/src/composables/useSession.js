import { computed, ref } from 'vue'
import api from '@/services/api'

const user = ref(null)
const organization = ref(null)
const platform = ref(null)
const csrfToken = ref(null)
const ready = ref(false)
let inFlight = null

const dashboardByRole = {
  technical_super_admin: '/system',
  platform_executive_admin: '/platform',
  organization_admin: '/organization',
  organization_operational_user: '/organization',
  citizen: '/citizen',
}

export function clearSessionCache() {
  user.value = null
  organization.value = null
  platform.value = null
  csrfToken.value = null
  ready.value = false
  inFlight = null
}

export function getSession() {
  return {
    loggedIn: Boolean(user.value),
    ready: ready.value,
    user: user.value,
    organization: organization.value,
    platform: platform.value,
  }
}

export function useSession() {
  const isAuthenticated = computed(() => Boolean(user.value))
  const activePermissions = computed(() => {
    if (['technical_super_admin', 'platform_executive_admin'].includes(user.value?.level)) {
      return platform.value?.permissions ?? []
    }
    if (['organization_admin', 'organization_operational_user'].includes(user.value?.level)) {
      return organization.value?.permissions ?? []
    }
    return []
  })

  const fetchSession = async (force = false) => {
    if (ready.value && !force) return user.value
    if (inFlight && !force) return inFlight

    inFlight = api.get('/session.php')
      .then(({ data }) => {
        user.value = data.loggedIn ? data.user : null
        organization.value = data.loggedIn ? (data.organization ?? null) : null
        platform.value = data.loggedIn ? (data.platform ?? null) : null
        csrfToken.value = data.csrf_token ?? null
        ready.value = true
        return user.value
      })
      .catch(() => {
        user.value = null
        organization.value = null
        platform.value = null
        csrfToken.value = null
        ready.value = true
        return null
      })
      .finally(() => { inFlight = null })

    return inFlight
  }

  const login = async (credentials) => {
    const { data } = await api.post('/auth/login.php', credentials)
    if (data.success && !data.requires_2fa) {
      ready.value = false
      await fetchSession(true)
    }
    return data
  }

  const register = async (payload) => {
    const { data } = await api.post('/auth/register.php', payload)
    return data
  }

  const logout = async () => {
    await api.post('/auth/logout.php', {}, {
      headers: csrfToken.value ? { 'X-CSRF-Token': csrfToken.value } : {},
    })
    clearSessionCache()
    ready.value = true
  }

  const hasRole = (...roles) => Boolean(user.value && roles.includes(user.value.level))
  const dashboardPath = (role = user.value?.level) => dashboardByRole[role] ?? '/'
  const hasPermission = (permission) => {
    if (!permission) return true
    if (['technical_super_admin', 'organization_admin'].includes(user.value?.level)) return true
    const permissions = activePermissions.value
    return permissions.includes('*') || permissions.includes(permission)
  }
  const hasAnyPermission = (...permissions) => {
    if (!permissions.length) return true
    return permissions.some(hasPermission)
  }
  const hasAllPermissions = (...permissions) => permissions.every(hasPermission)

  return {
    user,
    organization,
    platform,
    csrfToken,
    ready,
    isAuthenticated,
    activePermissions,
    fetchSession,
    login,
    register,
    logout,
    hasRole,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    dashboardPath,
    api,
  }
}
