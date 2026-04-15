import { ref, watch, type Ref } from 'vue'
import { useAuthStore } from '~/stores/auth'

export interface ListMeta {
  page: number
  per_page: number
  total: number
  last_page: number
}

interface UseAPIResult<T> {
  data: Ref<T | null>
  loading: Ref<boolean>
  error: Ref<string | null>
  /** Present when the API returns a paginated payload (e.g. ambassadors?page=1). */
  meta: Ref<ListMeta | null>
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
  const meta     = ref<ListMeta | null>(null)

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

  const execute = async (): Promise<void> => {
    loading.value = true
    error.value   = null
    try {
      const res = await fetch(buildUrl(), {
        headers: { Authorization: `Bearer ${auth.token}` },
      })
      if (res.status === 401) {
        meta.value = null
        auth.clear()
        navigateTo('/login')
        return
      }
      let json: any = null
      try { json = await res.json() } catch { json = null }

      if (!res.ok) {
        if (res.status >= 500) {
          error.value = 'Server error, please try again.'
        } else if (res.status >= 400) {
          error.value = (json && typeof json.message === 'string') ? json.message : 'Request failed.'
        } else {
          error.value = 'Unexpected response.'
        }
        meta.value = null
        return
      }

      data.value = json?.data ?? json
      meta.value = json?.meta ?? null
    } catch (e: unknown) {
      error.value = 'Network error, please check your connection.'
      meta.value  = null
    } finally {
      loading.value = false
    }
  }

  if (params && 'value' in (params as object)) {
    watch(params as Ref, execute, { deep: true, immediate: true })
  } else {
    execute()
  }

  return { data, loading, error, meta, refresh: execute }
}
