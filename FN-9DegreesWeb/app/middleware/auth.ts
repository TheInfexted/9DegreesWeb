export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore()
  auth.restoreFromStorage()

  if (to.path === '/login') return

  if (!auth.isAuthenticated) {
    return navigateTo('/login')
  }
})
