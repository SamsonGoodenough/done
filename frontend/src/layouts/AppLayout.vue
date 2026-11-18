<script setup>
import AppBottomNav from '@/components/AppBottomNav.vue'
</script>

<template>
  <v-main class="app-main">
    <slot />
  </v-main>
  <AppBottomNav />
</template>

<style lang="scss" scoped>
.app-main {
  // Keep scrolling content clear of the fixed bottom nav + safe area.
  padding-bottom: calc(72px + env(safe-area-inset-bottom)) !important;
  min-height: 100vh;
}
</style>
