import { ref, watch, type Ref } from 'vue'
import { useAuthStore } from '~/stores/auth'

interface UseAPIResult<T> {
  data: Ref<T | null>
  loading: Ref<boolean>
  error: Ref<string | null>
  refresh: () => Promise<void>
}

export function useAPI<T = unknown>(
  endpoint: string,
  params?: Ref<Record<string, unknown>> | Record<string, unknown>
): UseAPIResult<T> {
  const config   = useRuntimeConfig()
  const auth     = useAuthStore()
  const data     = ref<T | null>(null) as Ref<T | null>
  const loading  = ref(false)
  const error    = ref<string | null>(null)

  const buildUrl = (): string => {
    const p = (params && 'value' in (params as object)) ? (params as Ref).value : params
    if (!p || Object.keys(p).length === 0) return `${config.public.apiBase}/${endpoint}`
    const qs = new URLSearchParams(
      Object.entries(p)
        .filter(([, v]) => v !== null && v !== undefined && v !== '')
        .flatMap(([k, v]) => Array.isArray(v) ? v.map((i) => [`${k}[]`, String(i)]) : [[k, String(v)]])
    ).toString()
    return `${config.public.apiBase}/${endpoint}${qs ? '?' + qs : ''}`
  }

  const fetch = async (): Promise<void> => {
    loading.value = true
    error.value   = null
    try {
      const res = await globalThis.fetch(buildUrl(), {
        headers: { Authorization: `Bearer ${auth.token}` },
      })
      if (res.status === 401) {
        auth.clear()
        navigateTo('/login')
        return
      }
      const json = await res.json()
      if (!res.ok) throw new Error(json.message ?? 'Request failed.')
      data.value = json.data ?? json
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Unknown error'
    } finally {
      loading.value = false
    }
  }

  if (params && 'value' in (params as object)) {
    watch(params as Ref, fetch, { deep: true, immediate: true })
  } else {
    fetch()
  }

  return { data, loading, error, refresh: fetch }
}
