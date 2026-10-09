import api from '@/services/api'

export async function submitOrganizationApplication(formData) {
  const { data } = await api.post('/organizations/applications/create.php', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function fetchOrganizationApplicationStatus() {
  const { data } = await api.get('/organizations/applications/status.php')
  return data
}

export async function verifyOrganizationApplicantEmail(email, otp) {
  const { data } = await api.post('/auth/verify_otp.php', {
    email,
    otp,
    context: 'register',
  })
  return data
}

export async function resendOrganizationApplicantOtp(email) {
  const { data } = await api.post('/auth/resend_otp.php', { email })
  return data
}
