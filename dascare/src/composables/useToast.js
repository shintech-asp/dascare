import { ref } from 'vue'

const toasts = ref([])
let id = 0

const show = (message, type = 'info', titleOrOptions = {}) => {
  // Accepts either a plain title string — show(msg, 'success', 'Saved') —
  // or an options object — show(msg, 'success', { title: 'Saved', duration: 4000 }).
  const options = typeof titleOrOptions === 'string'
    ? { title: titleOrOptions }
    : titleOrOptions

  const toastId = ++id
  const duration = options.duration ?? 2500

  toasts.value.push({
    id: toastId,
    message,
    type,
    title: options.title ?? '',
    duration
  })

  // No auto-dismiss timer here on purpose: Toast.vue runs its own
  // pausable requestAnimationFrame loop (paused on hover) and calls
  // onClose -> remove() itself when it finishes. A setTimeout here
  // would run in parallel, ignore hover-pause, and remove the toast
  // out from under the component while it thinks it's still paused.
}

const remove = (toastId) => {
  toasts.value = toasts.value.filter(t => t.id !== toastId)
}

export function useToast() {
  return {
    toasts,
    success: (msg, opts) => show(msg, 'success', opts),
    error: (msg, opts) => show(msg, 'error', opts),
    warning: (msg, opts) => show(msg, 'warning', opts),
    info: (msg, opts) => show(msg, 'info', opts),
    remove
  }
}