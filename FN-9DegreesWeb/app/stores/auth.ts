import { defineStore } from 'pinia'

interface AuthUser {
  id: number
  username: string
  role: 'owner' | 'admin' | 'leader' | 'ambassador'
  ambassador_id: number | null
}

interface AuthState {
  token: string | null
  expiresAt: number | null
  user: AuthUser | null
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    token: null,
    expiresAt: null,
    user: null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token && Date.now() < (state.expiresAt ?? 0) * 1000,
    isOwner: (state) => state.user?.role === 'owner',
    isAdmin: (state) => ['owner', 'admin'].includes(state.user?.role ?? ''),
  },

  actions: {
    setAuth(token: string, expiresAt: number, user: AuthUser) {
      this.token     = token
      this.expiresAt = expiresAt
      this.user      = user
      if (import.meta.client) {
        localStorage.setItem('auth', JSON.stringify({ token, expiresAt, user }))
      }
    },

    clear() {
      this.token     = null
      this.expiresAt = null
      this.user      = null
      if (import.meta.client) {
        localStorage.removeItem('auth')
      }
    },

    restoreFromStorage() {
      if (!import.meta.client) return
      const raw = localStorage.getItem('auth')
      if (!raw) return
      try {
        const { token, expiresAt, user } = JSON.parse(raw)
        if (Date.now() < expiresAt * 1000) {
          this.token     = token
          this.expiresAt = expiresAt
          this.user      = user
        } else {
          localStorage.removeItem('auth')
        }
      } catch {
        localStorage.removeItem('auth')
      }
    },
  },
})
