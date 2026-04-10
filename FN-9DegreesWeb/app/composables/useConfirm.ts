import { ref } from 'vue'

interface ConfirmState {
  open: boolean
  title: string
  message: string
  resolve: ((v: boolean) => void) | null
}

const state = ref<ConfirmState>({ open: false, title: '', message: '', resolve: null })

export function useConfirm() {
  const confirm = (title: string, message: string): Promise<boolean> => {
    return new Promise((resolve) => {
      state.value = { open: true, title, message, resolve }
    })
  }

  const respond = (value: boolean) => {
    state.value.resolve?.(value)
    state.value = { open: false, title: '', message: '', resolve: null }
  }

  return { state, confirm, respond }
}
