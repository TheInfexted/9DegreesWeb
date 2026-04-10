export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore()

  if (to.path === '/login') return

  if (!auth.isAuthenticated) {
    return navigateTo('/login')
  }
})
