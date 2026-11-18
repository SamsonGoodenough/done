<script setup>
import { useRoute, useRouter } from 'vue-router'
import { useHaptics } from '@/composables/useHaptics'

const route = useRoute()
const router = useRouter()
const { tick } = useHaptics()

function go(name) {
  if (route.name !== name) {
    tick('light')
    router.push({ name })
  }
}

function openLog() {
  tick('medium')
  router.push({ name: 'log-new' })
}
</script>

<template>
  <nav class="bottomnav">
    <button class="navitem" :class="{ active: route.name === 'shelf' }" type="button" aria-label="Shelf" @click="go('shelf')">
      <svg class="icon" viewBox="0 0 20 20"><rect x="2" y="2" width="7" height="7" /><rect x="11" y="2" width="7" height="7" /><rect x="2" y="11" width="7" height="7" /><rect x="11" y="11" width="7" height="7" /></svg>
    </button>
    <button class="navitem" :class="{ active: route.name === 'search' }" type="button" aria-label="Search" @click="go('search')">
      <svg class="icon" viewBox="0 0 20 20"><circle cx="9" cy="9" r="6" /><line x1="17" y1="17" x2="13.5" y2="13.5" /></svg>
    </button>
    <button class="navitem add" type="button" aria-label="Log something" @click="openLog">
      <span class="addcircle">
        <svg class="icon" viewBox="0 0 20 20"><line x1="10" y1="4" x2="10" y2="16" /><line x1="4" y1="10" x2="16" y2="10" /></svg>
      </span>
    </button>
    <button class="navitem" :class="{ active: route.name === 'activity' }" type="button" aria-label="Activity" @click="go('activity')">
      <svg class="icon" viewBox="0 0 20 20"><path d="M2 10h3l2-6 4 12 2-6h5" /></svg>
    </button>
    <button class="navitem" :class="{ active: route.name === 'profile' }" type="button" aria-label="Profile" @click="go('profile')">
      <svg class="icon" viewBox="0 0 20 20"><circle cx="10" cy="6" r="3.2" /><path d="M3 17c1.5-4 4.5-6 7-6s5.5 2 7 6" /></svg>
    </button>
  </nav>
</template>

<style lang="scss" scoped>
.bottomnav {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: space-around;
  background: rgb(var(--v-theme-surface));
  border-top: 1px solid $hair-dark;
  padding: 0.5rem 0.4rem calc(0.5rem + env(safe-area-inset-bottom));
}

.navitem {
  display: flex;
  align-items: center;
  justify-content: center;
  color: rgb(var(--v-theme-ink-dim));
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.35rem;

  &.active {
    color: rgb(var(--v-theme-accent));
  }

  .icon {
    width: 21px;
    height: 21px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.6;
    stroke-linecap: round;
    stroke-linejoin: round;
  }
}

.navitem.add {
  position: relative;
  top: -10px;

  .addcircle {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
  }
}
</style>
