<script setup>
// The reusable full-screen takeover shell: hero art with a slow Ken Burns
// drift, scrim, grain, progress segments, back/close chrome, crossfading
// steps, and the save flourish. Knows nothing about what the steps mean.
defineProps({
  stepCount: { type: Number, required: true },
  currentIndex: { type: Number, required: true },
  contextLine: { type: String, default: '' },
  heroUrl: { type: String, default: '' },
  // 'primary' | 'teal' | 'accent': the color grade over the art
  tint: { type: String, default: 'primary' },
  saving: { type: Boolean, default: false },
})

defineEmits(['back', 'close'])
</script>

<template>
  <div class="stepper" :class="[`tint-${tint}`, { saving }]">
    <div class="art">
      <img v-if="heroUrl" :src="heroUrl" alt="">
      <div class="grade" />
    </div>
    <div class="scrim" />
    <div class="grain" />

    <div class="chrome">
      <div class="progress">
        <span
          v-for="index in stepCount"
          :key="index"
          class="seg"
          :class="{ done: index - 1 < currentIndex, current: index - 1 === currentIndex }"
        ><i /></span>
      </div>
      <div class="chrome-row">
        <button
          class="chrome-btn"
          type="button"
          aria-label="Back"
          :style="{ visibility: currentIndex === 0 ? 'hidden' : 'visible' }"
          @click="$emit('back')"
        >
          <svg viewBox="0 0 20 20"><polyline points="12 4 6 10 12 16" /></svg>
        </button>
        <div class="context mono-meta">{{ contextLine }}</div>
        <button class="chrome-btn" type="button" aria-label="Close" @click="$emit('close')">
          <svg viewBox="0 0 20 20"><line x1="5" y1="5" x2="15" y2="15" /><line x1="15" y1="5" x2="5" y2="15" /></svg>
        </button>
      </div>
    </div>

    <div class="steps">
      <slot />
    </div>
  </div>
</template>

<style lang="scss" scoped>
.stepper {
  position: fixed;
  inset: 0;
  z-index: 20;
  overflow: hidden;
  background: rgb(var(--v-theme-background));
  transition: opacity 0.5s ease, transform 0.5s ease;

  &.saving {
    opacity: 0;
    transform: scale(0.96);
  }
}

.art {
  position: absolute;
  inset: 0;
  isolation: isolate;

  img {
    position: absolute;
    inset: -4%;
    width: 108%;
    height: 108%;
    object-fit: cover;
    filter: grayscale(0.35) contrast(1.2) brightness(0.5) sepia(0.18);
    animation: ken-burns 16s ease-in-out infinite alternate;
  }

  .grade {
    position: absolute;
    inset: 0;
    mix-blend-mode: multiply;
    background: linear-gradient(170deg, rgba(var(--v-theme-primary), 0.3), rgba(0, 0, 0, 0.95) 80%);
  }
}

.tint-teal .art .grade {
  background: linear-gradient(170deg, rgba(var(--v-theme-teal), 0.3), rgba(0, 0, 0, 0.95) 80%);
}

.tint-accent .art .grade {
  background: linear-gradient(170deg, rgba(var(--v-theme-accent), 0.3), rgba(0, 0, 0, 0.95) 80%);
}

@keyframes ken-burns {
  from {
    transform: scale(1);
  }
  to {
    transform: scale(1.1);
  }
}

.scrim {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    180deg,
    rgba(0, 0, 0, 0.72) 0%,
    rgba(0, 0, 0, 0.26) 36%,
    rgba(0, 0, 0, 0.32) 60%,
    rgba(0, 0, 0, 0.85) 100%
  );
}

.chrome {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 6;
  padding: calc(1rem + env(safe-area-inset-top)) 1.1rem 0;
}

.progress {
  display: flex;
  gap: 0.3rem;
  margin-bottom: 0.85rem;
}

.seg {
  flex: 1;
  height: 3px;
  border-radius: 2px;
  background: rgba(var(--v-theme-on-background), 0.22);
  overflow: hidden;

  i {
    display: block;
    height: 100%;
    width: 0%;
    background: rgb(var(--v-theme-accent));
    transition: width 0.4s ease;
  }

  &.done i,
  &.current i {
    width: 100%;
  }
}

.chrome-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.chrome-btn {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: rgb(var(--v-theme-on-surface));
  cursor: pointer;
  @include glass(0.45, 10px);

  svg {
    width: 15px;
    height: 15px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.6;
    stroke-linecap: round;
    stroke-linejoin: round;
  }
}

.context {
  letter-spacing: 0.07em;
}

.steps {
  position: absolute;
  inset: 0;
  z-index: 4;
}

// Step panels crossfade with a slight vertical drift, like the mockup.
:deep(.step-panel) {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 0 1.3rem calc(1.7rem + env(safe-area-inset-bottom));
  max-width: 480px;
  margin: 0 auto;
}

:deep(.step-enter-active),
:deep(.step-leave-active) {
  transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.2, 0.8, 0.2, 1);
}

:deep(.step-enter-from) {
  opacity: 0;
  transform: translateY(18px);
}

:deep(.step-leave-to) {
  opacity: 0;
  transform: translateY(-18px);
}

:deep(.step-kicker) {
  @include mono-label(0.62rem, 0.14em);
  color: rgb(var(--v-theme-accent));
  margin-bottom: 0.5rem;
}

:deep(.step-title) {
  font-family: $font-serif;
  font-size: 1.9rem;
  font-weight: 400;
  line-height: 1.05;
  margin: 0 0 1.2rem;
  color: rgb(var(--v-theme-on-background));
  text-shadow: 0 4px 18px rgba(0, 0, 0, 0.55);
}

// Big serif single-pick option rows, shared by several steps.
:deep(.opt-list) {
  display: flex;
  flex-direction: column;
}

:deep(.opt) {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.8rem;
  width: 100%;
  background: none;
  border: none;
  border-bottom: 1px solid rgba(var(--v-theme-on-background), 0.14);
  cursor: pointer;
  text-align: left;
  padding: 0.8rem 0;
  color: rgb(var(--v-theme-ink-dim));
  font-family: $font-serif;
  font-size: 1.5rem;
  transition: color 0.2s ease, padding-left 0.2s ease;

  &:last-child {
    border-bottom: none;
  }

  .mark {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 1.5px solid rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-primary));
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transform: scale(0.5);
    transition: opacity 0.25s ease, transform 0.25s ease, background 0.25s ease;
    flex: 0 0 auto;

    svg {
      width: 13px;
      height: 13px;
      stroke: currentColor;
      fill: none;
      stroke-width: 1.6;
      stroke-linecap: round;
      stroke-linejoin: round;
    }
  }

  &.selected {
    color: rgb(var(--v-theme-on-background));
    padding-left: 0.3rem;

    .mark {
      opacity: 1;
      transform: scale(1);
      background: rgb(var(--v-theme-primary));
      color: rgb(var(--v-theme-on-primary));
    }
  }
}

// The floating primary continue/save button steps can opt into.
:deep(.next-btn) {
  position: absolute;
  right: 1.2rem;
  bottom: calc(1.6rem + env(safe-area-inset-bottom));
  z-index: 7;
  width: 54px;
  height: 54px;
  border-radius: 50%;
  background: rgb(var(--v-theme-primary));
  border: none;
  color: rgb(var(--v-theme-on-primary));
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 10px 28px rgba(var(--v-theme-primary), 0.45);
  transition: transform 0.15s ease;

  &:active {
    transform: scale(0.9);
  }

  svg {
    width: 22px;
    height: 22px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.6;
    stroke-linecap: round;
    stroke-linejoin: round;
  }
}
</style>
