import { ref } from 'vue'
import { useAuthStore } from '~/stores/auth'

type Method = 'POST' | 'PUT' | 'DELETE' | 'PATCH'

interface UseMutationResult<T> {
  mutate: (body?: unknown) => Promise<T | null>
  loading: Ref<boolean>
  error: Ref<string | null>
}

export function useAPIMutation<T = unknown>(
  method: Method,
  endpoint: string
): UseMutationResult<T> {
  const config  = useRuntimeConfig()
  const auth    = useAuthStore()
  const loading = ref(false)
  const error   = ref<string | null>(null)

  const mutate = async (body?: unknown): Promise<T | null> => {
    loading.value = true
    error.value   = null
    try {
      const url = `${config.public.apiBase}/${endpoint}`
      const res = await globalThis.fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          Authorization:  `Bearer ${auth.token}`,
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
      })
      if (res.status === 401) {
        auth.clear()
        navigateTo('/login')
        return null
      }
      if (res.status === 204) return null
      const json = await res.json()
      if (!res.ok) throw new Error(json.message ?? 'Request failed.')
      return (json.data ?? json) as T
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Unknown error'
      throw e
    } finally {
      loading.value = false
    }
  }

  return { mutate, loading, error }
}
