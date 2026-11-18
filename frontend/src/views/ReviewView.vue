<script setup>
import { formatDay as formatDate } from '@/utils/dates'
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { http } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import GlassCircle from '@/components/cine/GlassCircle.vue'
import StampPostmark from '@/components/cine/StampPostmark.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const entry = ref(null)
const episode = ref(null)
const revealed = ref(false)
const failed = ref(false)

const mediaItem = computed(() => entry.value?.media_item ?? null)
const isYou = computed(() => entry.value?.user?.id === auth.user?.id)

const JUMP_LABELS = {
  movie: 'Jump to movie',
  tv_show: 'Jump to show',
  game: 'Jump to game',
  book: 'Jump to book',
}

const targetLabel = computed(() => {
  if (!entry.value) return null
  if (entry.value.episode_id != null) {
    return episode.value
      ? `S${episode.value.season_number} · E${episode.value.episode_number} · ${episode.value.title}`
      : 'Episode'
  }
  if (entry.value.season_number != null) {
    return entry.value.season_number === 0 ? 'Specials' : `Season ${entry.value.season_number}`
  }
  return null
})

onMounted(async () => {
  try {
    const response = await http.get(`/api/log-entries/${route.params.entryId}`)
    entry.value = response.data.data
  } catch {
    failed.value = true
    return
  }

  if (entry.value.episode_id != null && mediaItem.value?.type === 'tv_show') {
    try {
      const episodes = await http.get(`/api/media-items/${mediaItem.value.id}/episodes`)
      episode.value = episodes.data.data.find((candidate) => candidate.id === entry.value.episode_id)
    } catch {
      // The label falls back to "Episode".
    }
  }
})


function goBack() {
  // A deep link has no history to go back to.
  if (window.history.state?.back) router.back()
  else router.push({ name: 'media-detail', params: { mediaId: mediaItem.value?.id } })
}
</script>

<template>
  <div v-if="entry && mediaItem" class="review-page">
    <div class="hero" :class="`tint-${mediaItem.type}`">
      <div class="art">
        <img v-if="mediaItem.poster_url" :src="mediaItem.poster_url" alt="">
        <div class="grade" />
      </div>
      <div class="fade" />
      <div class="grain" />
      <GlassCircle class="back" aria-label="Back" @click="goBack">
        <svg viewBox="0 0 20 20"><polyline points="12 4 6 10 12 16" /></svg>
      </GlassCircle>
      <div class="title-block">
        <h1 class="hero-title">{{ mediaItem.title }}</h1>
        <RouterLink class="jump" :to="{ name: 'media-detail', params: { mediaId: mediaItem.id } }">
          {{ JUMP_LABELS[mediaItem.type] ?? 'Jump to page' }}
          <svg viewBox="0 0 20 20"><polyline points="8 4 14 10 8 16" /></svg>
        </RouterLink>
      </div>
    </div>

    <section class="paper">
      <header class="byline">
        <div>
          <div class="who">{{ isYou ? 'You' : entry.user?.name }}</div>
          <div class="mono-paper-meta">
            {{ formatDate(entry.logged_date) }}
            <template v-if="entry.is_rewatch"> · Rewatch</template>
          </div>
          <div v-if="targetLabel" class="mono-paper-meta target">{{ targetLabel }}</div>
        </div>
        <StampPostmark v-if="entry.rating != null" :rating="entry.rating" :size="56" />
      </header>

      <template v-if="entry.review">
        <div class="quote-mark">&ldquo;</div>
        <div v-if="entry.contains_spoilers && !revealed" class="spoiler">
          <p class="quote blurred" aria-hidden="true">{{ entry.review }}</p>
          <button class="reveal mono-paper-meta" type="button" @click="revealed = true">
            Contains spoilers · tap to reveal
          </button>
        </div>
        <p v-else class="quote">{{ entry.review }}</p>
      </template>
      <p v-else class="mono-paper-meta no-review">Logged without a review.</p>
    </section>
  </div>

  <div v-else-if="failed" class="state">
    <div class="kicker">Not available</div>
    <p class="state-line">This review doesn't exist, or it isn't shared with you.</p>
    <button class="state-back mono-meta" type="button" @click="router.push({ name: 'shelf' })">Back to shelf</button>
  </div>
</template>

<style lang="scss" scoped>
.review-page {
  max-width: 480px;
  margin: 0 auto;
  min-height: 100vh;
  padding-bottom: 3rem;
}

.hero {
  position: relative;
  height: 320px;
  overflow: hidden;
  clip-path: polygon(
    0% 0%, 100% 0%, 100% 92%, 92% 100%, 84% 92%, 76% 100%, 68% 92%, 60% 100%,
    52% 92%, 44% 100%, 36% 92%, 28% 100%, 20% 92%, 12% 100%, 4% 92%, 0% 100%
  );
}

.art {
  position: absolute;
  inset: 0;
  isolation: isolate;

  img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: grayscale(0.35) contrast(1.2) brightness(0.6) sepia(0.2);
  }

  .grade {
    position: absolute;
    inset: 0;
    mix-blend-mode: multiply;
    background: linear-gradient(170deg, rgba(var(--v-theme-primary), 0.3), rgba(0, 0, 0, 0.95) 80%);
  }
}

.tint-tv_show .art .grade {
  background: linear-gradient(170deg, rgba(var(--v-theme-teal), 0.3), rgba(0, 0, 0, 0.95) 80%);
}

.tint-game .art .grade {
  background: linear-gradient(170deg, rgba(var(--v-theme-accent), 0.3), rgba(0, 0, 0, 0.95) 80%);
}

.fade {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgb(var(--v-theme-background)) 0%, transparent 50%);
}

.back {
  position: absolute;
  top: calc(14px + env(safe-area-inset-top));
  left: 14px;
  z-index: 3;
}

.title-block {
  position: absolute;
  left: 1.2rem;
  right: 1.2rem;
  bottom: 2.6rem;
  z-index: 2;
}

.hero-title {
  margin: 0;
  font-family: $font-serif;
  font-weight: 400;
  font-size: clamp(1.8rem, 9vw, 2.5rem);
  line-height: 1;
  color: rgb(var(--v-theme-on-background));
  text-shadow: 0 6px 20px rgba(0, 0, 0, 0.65);
}

.jump {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  margin-top: 0.7rem;
  padding: 0.4rem 0.8rem;
  border-radius: 999px;
  text-decoration: none;
  color: rgb(var(--v-theme-on-background));
  @include mono-label(0.64rem, 0.1em);
  @include glass(0.42, 14px);

  svg {
    width: 12px;
    height: 12px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
  }
}

.paper {
  position: relative;
  margin-top: -0.4rem;
  background: rgb(var(--v-theme-paper));
  color: rgb(var(--v-theme-on-paper));
  border-radius: 10px;
  padding: 1.8rem 1.3rem 1.8rem;
}

.byline {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.who {
  font-weight: 600;
  font-size: 1.05rem;
  margin-bottom: 0.25rem;
}

.mono-paper-meta {
  @include mono-label(0.62rem, 0.08em);
  color: rgb(var(--v-theme-paper-dim));
}

.target {
  margin-top: 0.2rem;
}

.quote-mark {
  font-family: $font-serif;
  font-size: 4.5rem;
  line-height: 0.5;
  color: rgba(var(--v-theme-primary), 0.35);
  margin: 1.4rem 0 0.7rem;
}

.quote {
  font-family: $font-serif;
  font-style: italic;
  font-size: 1.45rem;
  line-height: 1.4;
  margin: 0;
  white-space: pre-line;
}

.no-review {
  margin: 1.2rem 0 0;
}

.spoiler {
  position: relative;
}

.blurred {
  filter: blur(9px);
  user-select: none;
}

.reveal {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: none;
  border: none;
  cursor: pointer;
}

.state {
  max-width: 480px;
  margin: 0 auto;
  padding: 4rem 1.3rem;
}

.state-line {
  font-family: $font-serif;
  font-style: italic;
  font-size: 1.1rem;
  color: rgb(var(--v-theme-ink-dim));
  margin: 0.5rem 0 1.4rem;
}

.state-back {
  background: none;
  border: 1px solid $hair-dark;
  border-radius: 999px;
  padding: 0.5rem 1rem;
  color: inherit;
  cursor: pointer;
}
</style>
