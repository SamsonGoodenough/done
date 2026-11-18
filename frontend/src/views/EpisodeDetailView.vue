<script setup>
import { formatDay as formatDate } from '@/utils/dates'
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { http } from '@/api/http'
import { useHaptics } from '@/composables/useHaptics'
import GlassCircle from '@/components/cine/GlassCircle.vue'
import StampPostmark from '@/components/cine/StampPostmark.vue'

const route = useRoute()
const router = useRouter()
const { tick } = useHaptics()

const mediaItem = ref(null)
const episode = ref(null)
const entries = ref([])

// Friends' reviews and the circle-wide average land with Phase 9's social
// surfaces; until then this page shows your own history with the episode.
const episodeEntries = computed(() =>
  entries.value.filter((entry) => entry.episode_id === Number(route.params.episodeId)),
)

const ownRating = computed(() => {
  const rated = episodeEntries.value.find((entry) => entry.rating != null)
  return rated ? rated.rating : null
})

onMounted(async () => {
  const [itemResponse, episodesResponse, entriesResponse] = await Promise.all([
    http.get(`/api/media-items/${route.params.mediaId}`),
    http.get(`/api/media-items/${route.params.mediaId}/episodes`),
    http.get(`/api/media-items/${route.params.mediaId}/log-entries`),
  ])

  mediaItem.value = itemResponse.data.data
  episode.value = episodesResponse.data.data.find(
    (candidate) => candidate.id === Number(route.params.episodeId),
  )
  entries.value = entriesResponse.data.data
})


function logThisEpisode() {
  tick('medium')
  router.push({
    name: 'log-new',
    query: { mediaId: mediaItem.value.id, episodeId: episode.value.id },
  })
}
</script>

<template>
  <div v-if="mediaItem && episode" class="episode-detail">
    <div class="hero">
      <div class="art">
        <img v-if="mediaItem.poster_url" :src="mediaItem.poster_url" alt="">
        <div class="grade" />
      </div>
      <div class="fade" />
      <div class="grain" />
      <GlassCircle class="back" aria-label="Back" @click="router.back()">
        <svg viewBox="0 0 20 20"><polyline points="12 4 6 10 12 16" /></svg>
      </GlassCircle>
      <div class="meta-chip">
        <StampPostmark v-if="ownRating != null" :rating="ownRating" label="you" :size="46" />
        <div class="mono-meta chip-meta">
          S{{ episode.season_number }} · E{{ episode.episode_number }}
          <template v-if="episode.air_date"><br>{{ String(episode.air_date).slice(0, 10) }}</template>
        </div>
      </div>
      <h1 class="hero-title">{{ episode.title }}</h1>
    </div>

    <section class="paper">
      <div class="mono-paper-meta show-line">
        {{ mediaItem.title }} · Season {{ episode.season_number }} · Episode {{ episode.episode_number }}
      </div>

      <template v-if="episodeEntries.length">
        <article v-for="(entry, index) in episodeEntries" :key="entry.id" class="entry">
          <hr v-if="index > 0" class="tear">
          <div class="mono-paper-meta">
            {{ formatDate(entry.logged_date) }}
            <template v-if="entry.is_rewatch"> · Rewatch</template>
            <template v-if="entry.rating != null"> · ★ {{ entry.rating.toFixed(1) }}</template>
          </div>
          <template v-if="entry.review">
            <div class="quote-mark">&ldquo;</div>
            <p class="quote">{{ entry.review }}</p>
          </template>
        </article>
      </template>
      <template v-else>
        <div class="kicker">Unlogged</div>
        <p class="quote empty-quote">You haven't logged this episode yet.</p>
      </template>
    </section>

    <button class="fab" type="button" aria-label="Log this episode" @click="logThisEpisode">
      <svg viewBox="0 0 20 20"><line x1="10" y1="4" x2="10" y2="16" /><line x1="4" y1="10" x2="16" y2="10" /></svg>
    </button>
  </div>
</template>

<style lang="scss" scoped>
.episode-detail {
  max-width: 480px;
  margin: 0 auto;
  min-height: 100vh;
  padding-bottom: 6rem;
}

.hero {
  position: relative;
  height: 380px;
  overflow: hidden;
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
    filter: grayscale(0.35) contrast(1.2) brightness(0.55) sepia(0.2);
  }

  .grade {
    position: absolute;
    inset: 0;
    mix-blend-mode: multiply;
    background: linear-gradient(170deg, rgba(var(--v-theme-teal), 0.3), rgba(0, 0, 0, 0.95) 80%);
  }
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

.meta-chip {
  position: absolute;
  top: calc(56px + env(safe-area-inset-top));
  right: 14px;
  z-index: 3;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.5rem 0.8rem;
  border-radius: 14px;
  @include glass(0.42, 14px);
}

.chip-meta {
  text-align: left;
  line-height: 1.5;
}

.hero-title {
  position: absolute;
  left: 1.2rem;
  bottom: 2.2rem;
  max-width: 82%;
  margin: 0;
  z-index: 2;
  font-family: $font-serif;
  font-weight: 400;
  font-size: clamp(1.8rem, 9vw, 2.6rem);
  line-height: 1;
  color: rgb(var(--v-theme-on-background));
  text-shadow: 0 6px 20px rgba(0, 0, 0, 0.65);
}

.paper {
  position: relative;
  margin-top: -1rem;
  background: rgb(var(--v-theme-paper));
  color: rgb(var(--v-theme-on-paper));
  border-radius: 10px;
  padding: 1.6rem 1.3rem 1.4rem;
}

.show-line {
  margin-bottom: 0.9rem;
}

.mono-paper-meta {
  @include mono-label(0.62rem, 0.08em);
  color: rgb(var(--v-theme-paper-dim));
}

.quote-mark {
  font-family: $font-serif;
  font-size: 4rem;
  line-height: 0.5;
  color: rgba(var(--v-theme-primary), 0.35);
  margin: 0.6rem 0 0.5rem;
}

.quote {
  font-family: $font-serif;
  font-style: italic;
  font-size: 1.25rem;
  line-height: 1.4;
  margin: 0;
}

.empty-quote {
  color: rgb(var(--v-theme-paper-dim));
  margin-top: 0.5rem;
}

.tear {
  @include tear($paper-hair);
}

.fab {
  position: fixed;
  right: 1.2rem;
  bottom: calc(1.4rem + env(safe-area-inset-bottom));
  z-index: 15;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: rgb(var(--v-theme-primary));
  border: none;
  color: rgb(var(--v-theme-on-primary));
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 10px 28px rgba(var(--v-theme-primary), 0.5);
  transition: transform 0.15s ease;

  &:active {
    transform: scale(0.92);
  }

  svg {
    width: 22px;
    height: 22px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.6;
    stroke-linecap: round;
  }
}
</style>
