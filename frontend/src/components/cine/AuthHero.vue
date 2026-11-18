<script setup>
defineProps({
  height: { type: Number, default: 300 },
})
</script>

<template>
  <div class="auth-hero" :style="{ height: `${height}px` }">
    <div class="grade" />
    <div class="fade" />
    <div class="grain" />
    <div class="content">
      <slot>
        <span class="wordmark hero-wordmark">Done.</span>
      </slot>
    </div>
    <div class="corner">
      <slot name="corner" />
    </div>
  </div>
</template>

<style lang="scss" scoped>
// A photographic hero belongs here eventually; until there's a real brand
// asset this layers graded gradients + grain for the same cinematic depth
// without hotlinking stock imagery.
.auth-hero {
  position: relative;
  overflow: hidden;
  background:
    radial-gradient(120% 90% at 85% 0%, rgba(var(--v-theme-accent), 0.18), transparent 60%),
    radial-gradient(130% 100% at 10% 20%, rgba(var(--v-theme-primary), 0.3), transparent 65%),
    rgb(var(--v-theme-background));
}

.grade {
  position: absolute;
  inset: 0;
  background: linear-gradient(170deg, rgba(var(--v-theme-primary), 0.16), rgba(0, 0, 0, 0.55) 80%);
  mix-blend-mode: multiply;
}

.fade {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgb(var(--v-theme-background)) 0%, transparent 45%);
}

.content {
  position: absolute;
  left: 1.2rem;
  bottom: 3.4rem;
  z-index: 2;
}

.hero-wordmark {
  font-size: 2.4rem;
}

.corner {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 2;
}
</style>
