<script setup>
import { inject } from 'vue'
import { useHaptics } from '@/composables/useHaptics'

const wizard = inject('logWizard')
const { tick } = useHaptics()

function pick(season) {
  tick('light')

  // An episode belongs to one season; a new season invalidates it.
  if (wizard.tv.seasonNumber !== season) wizard.tv.episodeId = null
  wizard.tv.seasonNumber = season

  wizard.advanceAfter(380)
}
</script>

<template>
  <div class="step-panel">
    <div class="step-kicker">Season</div>
    <h2 class="step-title">Which season?</h2>
    <p v-if="!wizard.tv.episodesLoaded" class="mono-meta">Fetching seasons…</p>
    <div v-else class="season-grid">
      <button
        v-for="season in wizard.seasons"
        :key="season"
        class="season-opt"
        :class="{ selected: wizard.tv.seasonNumber === season }"
        type="button"
        @click="pick(season)"
      >
        {{ season === 0 ? 'Sp' : `S${season}` }}
      </button>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.season-grid {
  display: flex;
  gap: 0.9rem;
  flex-wrap: wrap;
}

.season-opt {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  border: 1.5px solid rgba(var(--v-theme-on-background), 0.25);
  background: none;
  color: rgb(var(--v-theme-ink-dim));
  font-family: $font-serif;
  font-size: 1.3rem;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: border-color 0.2s ease, color 0.2s ease, background 0.2s ease, transform 0.2s ease;

  &.selected {
    border-color: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-background));
    background: rgba(var(--v-theme-primary), 0.2);
    transform: scale(1.06);
  }
}
</style>
