import { ref } from 'vue'

export type ToastTone = 'info' | 'success' | 'error'

export interface Toast {
  id:      number
  tone:    ToastTone
  title:   string
  message?: string
}

const toasts = ref<Toast[]>([])
let nextId = 1

function push(tone: ToastTone, title: string, message?: string, durationMs = 4500): number {
  const id = nextId++
  toasts.value.push({ id, tone, title, message })
  if (durationMs > 0) {
    setTimeout(() => dismiss(id), durationMs)
  }
  return id
}

function dismiss(id: number) {
  const i = toasts.value.findIndex(t => t.id === id)
  if (i !== -1) toasts.value.splice(i, 1)
}

export function useToast() {
  return {
    toasts,
    dismiss,
    info:    (title: string, message?: string, durationMs?: number) => push('info',    title, message, durationMs),
    success: (title: string, message?: string, durationMs?: number) => push('success', title, message, durationMs),
    error:   (title: string, message?: string, durationMs?: number) => push('error',   title, message, durationMs ?? 7000),
  }
}
