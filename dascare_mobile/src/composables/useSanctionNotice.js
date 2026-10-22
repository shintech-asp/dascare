// Login refused for false alarms (mobile/auth/login.php FALSE_ALARM_SUSPENDED):
// LoginView keeps { message, record, token } here for FalseAlarmView.
import { ref } from 'vue'

const notice = ref(null)
export function useSanctionNotice() {
  return { notice }
}
