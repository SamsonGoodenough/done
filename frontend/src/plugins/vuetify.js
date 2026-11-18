import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import { doneDark } from '@/theme/palette'

export default createVuetify({
  theme: {
    // Vuetify 4 defaults to 'system'; this app is dark-only by design.
    defaultTheme: 'dark',
    variations: false,
    themes: { dark: doneDark },
  },
})
