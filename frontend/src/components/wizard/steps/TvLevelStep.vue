<script setup>
import { inject } from 'vue'
import { useHaptics } from '@/composables/useHaptics'

const wizard = inject('logWizard')
const { tick } = useHaptics()

const OPTIONS = [
  { value: 'show', label: 'Whole Show' },
  { value: 'season', label: 'A Season' },
  { value: 'episode', label: 'One Episode' },
]

function pick(level) {
  tick('light')
  wizard.tv.level = level

  // A different level invalidates whatever was chosen downstream of it.
  if (level === 'show') wizard.tv.seasonNumber = null
  if (level !== 'episode') wizard.tv.episodeId = null

  if (level !== 'show') wizard.ensureEpisodes()

  wizard.advanceAfter(420)
}
</script>

<template>
  <div class="step-panel">
    <div class="step-kicker">Log Level</div>
    <h2 class="step-title">How much are you logging?</h2>
    <div class="opt-list">
      <button
        v-for="option in OPTIONS"
        :key="option.value"
        class="opt"
        :class="{ selected: wizard.tv.level === option.value }"
        type="button"
        @click="pick(option.value)"
      >
        {{ option.label }}
        <span class="mark"><svg viewBox="0 0 20 20"><polyline points="4 10 8 14 16 5" /></svg></span>
      </button>
    </div>
  </div>
</template>
