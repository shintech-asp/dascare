import api from '@/services/api'

export async function fetchOrganizationProfile() {
  const { data } = await api.get('/organizations/profile/get.php')
  return data
}

export async function updateOrganizationProfile(payload) {
  const { data } = await api.post('/organizations/profile/update.php', payload)
  return data
}

export async function fetchOrganizationRoles() {
  const { data } = await api.get('/organizations/roles/list.php')
  return data
}

export async function fetchOrganizationMembers() {
  const { data } = await api.get('/organizations/members/list.php')
  return data
}

export async function inviteOrganizationMember(payload) {
  const { data } = await api.post('/organizations/members/invite.php', payload)
  return data
}

export async function cancelOrganizationInvitation(invitationId) {
  const { data } = await api.post('/organizations/invitations/cancel.php', { invitation_id: invitationId })
  return data
}

export async function previewOrganizationInvitation(token) {
  const { data } = await api.get('/organizations/invitations/preview.php', { params: { token } })
  return data
}

export async function acceptOrganizationInvitation(payload) {
  const { data } = await api.post('/organizations/invitations/accept.php', payload)
  return data
}

export async function fetchOrganizationAccessCatalog() {
  const { data } = await api.get('/organizations/access/catalog.php')
  return data
}

export async function createOrganizationRole(payload) {
  const { data } = await api.post('/organizations/roles/create.php', payload)
  return data
}

export async function updateOrganizationRole(payload) {
  const { data } = await api.post('/organizations/roles/update.php', payload)
  return data
}

export async function deleteOrganizationRole(roleId) {
  const { data } = await api.post('/organizations/roles/delete.php', { role_id: roleId })
  return data
}

export async function updateOrganizationMemberRoles(organizationMemberId, roleIds) {
  const { data } = await api.post('/organizations/members/roles/update.php', {
    organization_member_id: organizationMemberId,
    role_ids: roleIds,
  })
  return data
}

export async function updateOrganizationMemberStatus(organizationMemberId, membershipStatus, note = '') {
  const { data } = await api.post('/organizations/members/status/update.php', {
    organization_member_id: organizationMemberId,
    membership_status: membershipStatus,
    note,
  })
  return data
}

export async function updateOrganizationMemberAvailability(organizationMemberId, availabilityStatus, note = '') {
  const { data } = await api.post('/organizations/members/availability/update.php', {
    organization_member_id: organizationMemberId,
    availability_status: availabilityStatus,
    note,
  })
  return data
}
