import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import vuetify from 'vite-plugin-vuetify'
import { VitePWA } from 'vite-plugin-pwa'
import { palette } from './src/theme/palette.js'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    {
      // index.html's <meta name="theme-color"> reads the palette too, so the
      // browser chrome color can't drift from the canonical file.
      name: 'done-theme-color',
      transformIndexHtml: (html) => html.replace('__THEME_COLOR__', palette.bg),
    },
    vueDevTools(),
    vuetify({ autoImport: true }),
    VitePWA({
      registerType: 'autoUpdate',
      manifest: {
        name: 'Done',
        short_name: 'Done',
        start_url: '/',
        display: 'standalone',
        theme_color: palette.bg,
        background_color: palette.bg,
      },
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  css: {
    preprocessorOptions: {
      scss: {
        // Token aliases + mixins available in every <style lang="scss">
        // without a per-file @use. The partial emits no CSS of its own.
        additionalData: `@use "@/styles/tokens" as *;\n`,
      },
    },
  },
  server: {
    host: true,
    strictPort: true,
    port: 5173,
    // Lets a cloudflared quick tunnel reach the dev server for phone testing.
    allowedHosts: ['.trycloudflare.com'],
    watch: {
      usePolling: true,
    },
  },
})
