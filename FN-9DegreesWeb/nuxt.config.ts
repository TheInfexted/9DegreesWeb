// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: { enabled: true },

  ssr: false, // SPA mode

  modules: ['@nuxtjs/tailwindcss', '@pinia/nuxt'],

  css: ['~/assets/css/globals.css'],

  app: {
    head: {
      title: '9 Degrees',
      meta: [{ name: 'viewport', content: 'width=device-width, initial-scale=1' }],
      link: [{ rel: 'icon', type: 'image/png', href: '/favicon.png' }],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.API_BASE || 'http://localhost:8080/api/v1',
    },
  },

  // Subfolders (ui/, layout/) were registering as UiAppCard / LayoutAppSidebar;
  // templates use AppCard / AppSidebar. Filename-based names fix resolution.
  components: [{ path: '~/components', pathPrefix: false }],
})
