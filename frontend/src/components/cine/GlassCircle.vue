<template>
  <button class="glass-circle" type="button">
    <slot />
  </button>
</template>

<style lang="scss" scoped>
.glass-circle {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  color: rgb(var(--v-theme-on-surface));
  cursor: pointer;
  @include glass(0.45, 10px);

  :deep(svg) {
    width: 16px;
    height: 16px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.6;
    stroke-linecap: round;
    stroke-linejoin: round;
  }
}
</style>
