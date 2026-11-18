<template>
  <div class="activity">
    <header class="topbar">
      <h1 class="title">Activity</h1>
    </header>
    <div class="placeholder">
      <div class="kicker">Coming in Phase 9</div>
      <p class="line">The reel from your circle, what everyone's been watching, playing, and reading, screens here soon.</p>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.activity {
  max-width: 480px;
  margin: 0 auto;
}

.topbar {
  padding: 1rem 1.1rem 0.75rem;
}

.title {
  font-family: $font-serif;
  font-size: 1.3rem;
  font-weight: 400;
  margin: 0;
}

.placeholder {
  padding: 3rem 1.3rem;
}

.line {
  font-family: $font-serif;
  font-style: italic;
  font-size: 1.15rem;
  line-height: 1.4;
  margin-top: 0.5rem;
  color: rgb(var(--v-theme-ink-dim));
}
</style>
