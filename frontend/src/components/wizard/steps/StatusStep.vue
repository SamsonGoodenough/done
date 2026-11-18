<script setup>
import { formatDay, todayIso } from '@/utils/dates'
import { computed, inject } from 'vue'
import { useHaptics } from '@/composables/useHaptics'

const wizard = inject('logWizard')
const { tick } = useHaptics()

const OPTIONS = [
  { value: 'planned', label: 'Planned' },
  { value: 'in_progress', label: 'In Progress' },
  { value: 'done', label: 'Done' },
  { value: 'on_hold', label: 'On Hold' },
  { value: 'dropped', label: 'Dropped' },
]

function pick(status) {
  tick('light')
  wizard.fields.status = status
  wizard.advanceAfter(420)
}

const dateLabel = computed(() => {
  const picked = wizard.fields.loggedDate

  return picked === todayIso() ? 'Today' : formatDay(picked)
})

const maxDate = computed(() => todayIso())
</script>

<template>
  <div class="step-panel">
    <div class="step-kicker">Status</div>
    <h2 class="step-title">How did it go?</h2>

    <label class="date-chip" @pointerdown="tick('selection')">
      <svg viewBox="0 0 20 20"><rect x="3" y="4" width="14" height="13" rx="2" /><line x1="3" y1="8" x2="17" y2="8" /><line x1="7" y1="2" x2="7" y2="5" /><line x1="13" y1="2" x2="13" y2="5" /></svg>
      <span class="mono-meta chip-label">Logged · {{ dateLabel }}</span>
      <input v-model="wizard.fields.loggedDate" type="date" :max="maxDate">
    </label>

    <div class="opt-list">
      <button
        v-for="option in OPTIONS"
        :key="option.value"
        class="opt"
        :class="{ selected: wizard.fields.status === option.value }"
        type="button"
        @click="pick(option.value)"
      >
        {{ option.label }}
        <span class="mark"><svg viewBox="0 0 20 20"><polyline points="4 10 8 14 16 5" /></svg></span>
      </button>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.date-chip {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  align-self: flex-start;
  padding: 0.4rem 0.85rem;
  border-radius: 999px;
  margin-bottom: 1.1rem;
  cursor: pointer;
  @include glass(0.45, 10px);

  svg {
    width: 14px;
    height: 14px;
    stroke: rgb(var(--v-theme-ink-dim));
    fill: none;
    stroke-width: 1.6;
    stroke-linecap: round;
  }

  .chip-label {
    color: rgb(var(--v-theme-on-surface));
    letter-spacing: 0.06em;
  }

  // The native date input sits invisibly on top so a tap opens the
  // platform's own picker.
  input {
    position: absolute;
    inset: 0;
    opacity: 0;
    width: 100%;
    cursor: pointer;
  }
}
</style>
