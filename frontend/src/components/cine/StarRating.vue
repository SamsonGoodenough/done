<script setup>
import { computed } from 'vue'
import { useHaptics } from '@/composables/useHaptics'

const props = defineProps({
  // null = unrated
  size: { type: String, default: 'md' }, // 'md' | 'xl'
  readonly: { type: Boolean, default: false },
})

const model = defineModel({ type: [Number, null], default: null })
const { tick } = useHaptics()

const fillWidth = computed(() => (model.value == null ? 0 : (model.value / 5) * 100))

function applyFromEvent(event) {
  const rect = event.currentTarget.getBoundingClientRect()
  const fraction = (event.clientX - rect.left) / rect.width
  const value = Math.max(0, Math.min(5, Math.round(fraction * 10) / 2))

  if (value !== model.value) {
    // The click of a picker wheel: one light tap per half-star crossed.
    tick('selection')
    model.value = value
  }
}

function onPointerDown(event) {
  if (props.readonly) return
  event.currentTarget.setPointerCapture(event.pointerId)
  applyFromEvent(event)
}

function onPointerMove(event) {
  if (props.readonly) return
  if (!event.currentTarget.hasPointerCapture(event.pointerId)) return
  applyFromEvent(event)
}
</script>

<template>
  <span
    class="stars"
    :class="[`size-${size}`, { readonly }]"
    role="slider"
    :aria-valuemin="0"
    :aria-valuemax="5"
    :aria-valuenow="model ?? 0"
    aria-label="Rating"
    @pointerdown="onPointerDown"
    @pointermove="onPointerMove"
  >
    <span class="base">★★★★★</span>
    <span class="fill" :style="{ width: `${fillWidth}%` }">★★★★★</span>
  </span>
</template>

<style lang="scss" scoped>
.stars {
  position: relative;
  display: inline-block;
  font-size: 2rem;
  line-height: 1;
  letter-spacing: 0.1rem;
  cursor: pointer;
  user-select: none;
  // The whole widget is a horizontal scrub surface; never let the page
  // steal the drag as a scroll gesture.
  touch-action: none;

  &.size-xl {
    font-size: 3.1rem;
  }

  &.readonly {
    cursor: default;
    touch-action: auto;
  }
}

.base {
  color: $hair-dark;
}

.fill {
  position: absolute;
  top: 0;
  left: 0;
  overflow: hidden;
  white-space: nowrap;
  color: rgb(var(--v-theme-primary));
  pointer-events: none;
}
</style>
