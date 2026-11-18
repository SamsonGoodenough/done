<script setup>
import { computed } from 'vue'

const props = defineProps({
  rating: { type: [Number, String], default: null },
  label: { type: String, default: '' },
  size: { type: Number, default: 36 },
})

const value = computed(() =>
  props.rating === null || props.rating === '' ? null : Number(props.rating).toFixed(1),
)
</script>

<template>
  <span
    class="stamp"
    :style="{
      width: `${size}px`,
      height: `${size}px`,
      fontSize: `${size * 0.019}rem`,
    }"
  >
    <span class="value">{{ value }}</span>
    <span v-if="label" class="label">{{ label }}</span>
  </span>
</template>

<style lang="scss" scoped>
.stamp {
  position: relative;
  border-radius: 50%;
  background: rgb(var(--v-theme-paper));
  border: 1.5px solid rgb(var(--v-theme-primary));
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  transform: rotate(-9deg);
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.45);

  &::before {
    content: '';
    position: absolute;
    inset: 4px;
    border: 1px dashed rgba(var(--v-theme-primary), 0.55);
    border-radius: 50%;
  }
}

.value {
  font-family: $font-mono;
  font-weight: 600;
  font-size: 1em;
  line-height: 1.1;
  color: rgb(var(--v-theme-primary));
}

.label {
  font-family: $font-mono;
  font-size: 0.55em;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: rgb(var(--v-theme-primary));
}
</style>
