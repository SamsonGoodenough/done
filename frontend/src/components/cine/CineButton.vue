<script setup>
defineProps({
  loading: { type: Boolean, default: false },
  block: { type: Boolean, default: true },
})
</script>

<template>
  <button class="cine-btn" :class="{ block }" type="submit" :disabled="loading">
    <span v-if="loading" class="dots" aria-hidden="true">· · ·</span>
    <slot v-else />
  </button>
</template>

<style lang="scss" scoped>
.cine-btn {
  display: inline-block;
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
  border: none;
  border-radius: 8px;
  padding: 0.85rem 1.4rem;
  cursor: pointer;
  transition: transform 0.15s ease, opacity 0.15s ease;
  @include mono-label(0.72rem);

  &.block {
    display: block;
    width: 100%;
  }

  &:active {
    transform: scale(0.98);
  }

  &:disabled {
    opacity: 0.65;
    cursor: default;
  }
}

.dots {
  letter-spacing: 0.3em;
}
</style>
