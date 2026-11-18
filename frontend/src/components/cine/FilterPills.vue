<script setup>
import { useHaptics } from '@/composables/useHaptics'

defineProps({
  // [{ value, label }]
  options: { type: Array, required: true },
})

const model = defineModel({ type: [String, null], default: null })
const { tick } = useHaptics()

function pick(value) {
  if (model.value !== value) {
    tick('light')
    model.value = value
  }
}
</script>

<template>
  <div class="filterbar" role="tablist">
    <button
      v-for="option in options"
      :key="String(option.value)"
      class="pill"
      :class="{ active: model === option.value }"
      type="button"
      role="tab"
      :aria-selected="model === option.value"
      @click="pick(option.value)"
    >
      {{ option.label }}
    </button>
  </div>
</template>

<style lang="scss" scoped>
.filterbar {
  position: sticky;
  top: 0;
  z-index: 5;
  display: flex;
  gap: 0.4rem;
  padding: 0.8rem 1.1rem;
  overflow-x: auto;
  scrollbar-width: none;
  background: rgba(var(--v-theme-background), 0.55);
  backdrop-filter: blur(12px) saturate(160%);
  -webkit-backdrop-filter: blur(12px) saturate(160%);

  &::-webkit-scrollbar {
    display: none;
  }
}

.pill {
  flex: 0 0 auto;
  padding: 0.35rem 0.7rem;
  border-radius: 999px;
  border: 1px solid $hair-dark;
  color: rgb(var(--v-theme-ink-dim));
  background: transparent;
  cursor: pointer;
  white-space: nowrap;
  @include mono-label(0.62rem, 0.08em);

  &.active {
    color: rgb(var(--v-theme-background));
    background: rgb(var(--v-theme-accent));
    border-color: rgb(var(--v-theme-accent));
  }
}
</style>
