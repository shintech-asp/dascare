import { reactive } from 'vue'

/**
 * In-memory hand-off between the account screens: which email a code was
 * sent to, why (register / forgot / login_2fa — same three contexts as the
 * web's AuthPage OTP modal), and the 2FA challenge token.
 */
const flow = reactive({ context: '', email: '', challenge: '' })

export function useAuthFlow() {
  function start(context, email, challenge = '') {
    flow.context = context
    flow.email = email
    flow.challenge = challenge
  }
  function clear() {
    flow.context = ''
    flow.email = ''
    flow.challenge = ''
  }
  return { flow, start, clear }
}
