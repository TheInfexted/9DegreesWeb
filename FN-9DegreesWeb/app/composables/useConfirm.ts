import { ref } from 'vue'

export type ConfirmTone = 'default' | 'danger'

interface ConfirmOptions {
  tone?:         ConfirmTone
  confirmLabel?: string
}

interface ConfirmState {
  open:         boolean
  title:        string
  message:      string
  tone:         ConfirmTone
  confirmLabel: string | null
  resolve:      ((v: boolean) => void) | null
}

const blank: ConfirmState = {
  open: false, title: '', message: '', tone: 'default', confirmLabel: null, resolve: null,
}

const state = ref<ConfirmState>({ ...blank })

export function useConfirm() {
  const confirm = (title: string, message: string, opts: ConfirmOptions = {}): Promise<boolean> => {
    return new Promise((resolve) => {
      state.value = {
        open: true,
        title,
        message,
        tone: opts.tone ?? 'default',
        confirmLabel: opts.confirmLabel ?? null,
        resolve,
      }
    })
  }

  const respond = (value: boolean) => {
    state.value.resolve?.(value)
    state.value = { ...blank }
  }

  return { state, confirm, respond }
}
