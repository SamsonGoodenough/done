<script setup>
import { inject } from 'vue'
import StarRating from '@/components/cine/StarRating.vue'

const wizard = inject('logWizard')
</script>

<template>
  <div class="step-panel">
    <div class="step-kicker">Rating</div>
    <h2 class="step-title">Rate it.</h2>
    <div class="rate-wrap">
      <StarRating v-model="wizard.fields.rating" size="xl" />
      <span class="readout">{{ wizard.fields.rating == null ? '–' : wizard.fields.rating.toFixed(1) }}</span>
    </div>
    <button class="next-btn" type="button" aria-label="Continue" @click="wizard.next()">
      <svg viewBox="0 0 20 20"><polyline points="8 4 14 10 8 16" /></svg>
    </button>
  </div>
</template>

<style lang="scss" scoped>
.rate-wrap {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.7rem;
  padding-bottom: 0.6rem;
}

.readout {
  font-family: $font-serif;
  font-size: 3rem;
  color: rgb(var(--v-theme-on-background));
  text-shadow: 0 4px 18px rgba(0, 0, 0, 0.5);
}
</style>
