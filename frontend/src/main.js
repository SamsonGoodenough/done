import '@/styles/global.scss'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import vuetify from './plugins/vuetify'
import { installUnauthorizedHandler } from '@/api/http'
import { useAuthStore } from '@/stores/auth'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(vuetify)

installUnauthorizedHandler(() => {
  const auth = useAuthStore(pinia)

  // Before the first session check resolves, the 401 is just that check
  // answering "signed out". The router guard is mid-navigation and will
  // redirect with the return path itself; pushing here would override it.
  if (!auth.checked) return

  auth.forgetSession()

  const current = router.currentRoute.value

  if (current.meta.public !== true) {
    router.push({ name: 'login', query: { redirect: current.fullPath } })
  }
})

app.mount('#app')
