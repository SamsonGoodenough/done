<script setup>
import { inject } from 'vue'
import { useHaptics } from '@/composables/useHaptics'

const wizard = inject('logWizard')
const { tick } = useHaptics()

function pick(episode) {
  tick('light')
  wizard.tv.episodeId = episode.id
  wizard.advanceAfter(420)
}
</script>

<template>
  <div class="step-panel">
    <div class="step-kicker">Episode</div>
    <h2 class="step-title">Which episode?</h2>
    <div class="opt-list episode-list">
      <button
        v-for="episode in wizard.seasonEpisodes"
        :key="episode.id"
        class="opt"
        :class="{ selected: wizard.tv.episodeId === episode.id }"
        type="button"
        @click="pick(episode)"
      >
        <span>
          <span class="opt-title">E{{ episode.episode_number }} · {{ episode.title }}</span>
          <span v-if="episode.air_date" class="opt-meta mono-meta">{{ String(episode.air_date).slice(0, 10) }}</span>
        </span>
        <span class="mark"><svg viewBox="0 0 20 20"><polyline points="4 10 8 14 16 5" /></svg></span>
      </button>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.episode-list {
  max-height: 46vh;
  overflow-y: auto;
}

.opt-title {
  display: block;
}

.opt-meta {
  display: block;
  margin-top: 0.3rem;
}
</style>
