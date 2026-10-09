import { ref } from 'vue'

const visible = ref(false)
const type = ref('info')
const title = ref('')
const message = ref('')
const confirmAction = ref(null)

/* ================= CORE OPEN ================= */
const open = ({ t, ti, m, onConfirm }) => {
  type.value = t
  title.value = ti
  message.value = m
  confirmAction.value = onConfirm || null
  visible.value = true
}

/* ================= CLOSE ================= */
const close = () => {
  visible.value = false
  confirmAction.value = null
}

export function useAlert() {
  return {
    visible,
    type,
    title,
    message,
    confirmAction,
    close,

    /* ================= BASIC ALERTS ================= */
    success: (msg, titleText = 'Success') =>
      open({ t: 'success', ti: titleText, m: msg }),

    error: (msg, titleText = 'Error') =>
      open({ t: 'error', ti: titleText, m: msg }),

    warning: (msg, titleText = 'Warning') =>
      open({ t: 'warning', ti: titleText, m: msg }),

    info: (msg, titleText = 'Info') =>
      open({ t: 'info', ti: titleText, m: msg }),

    /* ================= CONFIRM (DUAL MODE SAFE) ================= */
    confirm: (msg, onConfirmOrTitle, maybeTitle) => {
      // If second parameter is a function → callback style (old usage)
      if (typeof onConfirmOrTitle === 'function') {
        open({
          t: 'confirm',
          ti: maybeTitle || 'Confirm',
          m: msg,
          onConfirm: (result) => {
            onConfirmOrTitle(result)
            close()
          }
        })
        return
      }

      // Otherwise → Promise style (recommended)
      return new Promise((resolve) => {
        open({
          t: 'confirm',
          ti: onConfirmOrTitle || 'Confirm',
          m: msg,
          onConfirm: (result) => {
            resolve(result)
            close()
          }
        })
      })
    }
  }
}
