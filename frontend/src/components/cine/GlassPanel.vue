<template>
  <div class="glass-panel">
    <slot />
  </div>
</template>

<style lang="scss" scoped>
.glass-panel {
  position: relative;
  z-index: 3;
  border-radius: 16px;
  padding: 1.5rem 1.3rem;
  @include glass(0.5, 18px);
}
</style>
