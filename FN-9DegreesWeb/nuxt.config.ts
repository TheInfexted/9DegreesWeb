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
      htmlAttrs: { lang: 'en-GB' },
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'theme-color', content: '#0E0F10' },
        { name: 'description', content: '9 Degrees — nightlife ambassador sales & commission tracking.' },
      ],
      link: [
        { rel: 'icon', type: 'image/png', href: '/favicon.png' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap',
        },
      ],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.API_BASE || 'https://api.ninedsales.com/api/v1',
    },
  },

  // Use rootDir so this works whether Nuxt's srcDir is `.` or `app/`.
  // Otherwise `~/components` can miss `app/components` and you get UiAppCard / LayoutAppSidebar.
  components: [{ path: '~~/app/components', pathPrefix: false }],
})
