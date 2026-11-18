<script setup>
import { formatDay as formatDate, yearOf } from '@/utils/dates'
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { http } from '@/api/http'
import { useShelfStore } from '@/stores/shelf'
import { useHaptics } from '@/composables/useHaptics'
import GlassCircle from '@/components/cine/GlassCircle.vue'
import StampPostmark from '@/components/cine/StampPostmark.vue'
import ReviewCard from '@/components/ReviewCard.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const shelf = useShelfStore()
const auth = useAuthStore()
const { tick } = useHaptics()

const mediaItem = ref(null)
const entries = ref([])
const reviews = ref([])
const community = ref({ average_rating: null, rating_count: 0 })
const episodes = ref([])
const episodeSeason = ref(null)
const loadingEpisodes = ref(false)

const TYPE_LABELS = { movie: 'Film', tv_show: 'TV', game: 'Game', book: 'Book' }
const STATUS_LABELS = {
  planned: 'Planned',
  in_progress: 'In Progress',
  done: 'Done',
  on_hold: 'On Hold',
  dropped: 'Dropped',
}

const shelfEntry = computed(() => shelf.entryForMediaItem(route.params.mediaId))
const isTv = computed(() => mediaItem.value?.type === 'tv_show')

const metaLine = computed(() => {
  if (!mediaItem.value) return ''
  const label = TYPE_LABELS[mediaItem.value.type] ?? mediaItem.value.type
  const year = yearOf(mediaItem.value.release_date)
  return year ? `${label} · ${year}` : label
})

const runtimeLabel = computed(() => {
  const minutes = Number(mediaItem.value?.metadata?.runtime)
  if (!minutes) return null
  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60
  if (hours === 0) return `${rest}m`
  return rest === 0 ? `${hours}h` : `${hours}h ${rest}m`
})

const ratingCountLabel = computed(() => {
  const count = community.value.rating_count
  return `${count} ${count === 1 ? 'rating' : 'ratings'}`
})

const seasons = computed(() => [...new Set(episodes.value.map((e) => e.season_number))])
const seasonEpisodes = computed(() =>
  episodes.value.filter((e) => e.season_number === episodeSeason.value),
)

onMounted(async () => {
  const [itemResponse, entriesResponse, reviewsResponse] = await Promise.all([
    http.get(`/api/media-items/${route.params.mediaId}`),
    http.get(`/api/media-items/${route.params.mediaId}/log-entries`),
    http.get(`/api/media-items/${route.params.mediaId}/reviews`),
    shelf.loaded ? Promise.resolve() : shelf.fetch(),
  ])

  mediaItem.value = itemResponse.data.data
  entries.value = entriesResponse.data.data
  reviews.value = reviewsResponse.data.data
  community.value = reviewsResponse.data.meta

  if (mediaItem.value.type === 'tv_show') loadEpisodes()
})

async function loadEpisodes() {
  loadingEpisodes.value = true

  try {
    const response = await http.get(`/api/media-items/${route.params.mediaId}/episodes`)
    episodes.value = response.data.data
    episodeSeason.value = seasons.value.find((s) => s !== 0) ?? seasons.value[0] ?? null
  } finally {
    loadingEpisodes.value = false
  }
}

function targetLabel(entry) {
  if (entry.episode_id != null) {
    const episode = episodes.value.find((e) => e.id === entry.episode_id)
    return episode ? `S${episode.season_number} · E${episode.episode_number}` : 'Episode'
  }
  if (entry.season_number != null) {
    return entry.season_number === 0 ? 'Specials' : `Season ${entry.season_number}`
  }
  return null
}


function openWizard() {
  tick('medium')
  router.push({ name: 'log-new', query: { mediaId: mediaItem.value.id } })
}

function editEntry(entry) {
  tick('light')
  router.push({
    name: 'log-edit',
    params: { entryId: entry.id },
    state: {
      entrySeed: {
        entry: JSON.parse(JSON.stringify(entry)),
        mediaItem: JSON.parse(JSON.stringify(mediaItem.value)),
        shelfStatus: shelfEntry.value?.status ?? null,
      },
    },
  })
}
</script>

<template>
  <div v-if="mediaItem" class="detail">
    <div class="hero" :class="`tint-${mediaItem.type}`">
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
        <StampPostmark
          v-if="community.average_rating != null"
          :rating="community.average_rating"
          label="avg"
          :size="46"
        />
        <div class="mono-meta chip-meta">
          {{ metaLine }}<template v-if="runtimeLabel"> · {{ runtimeLabel }}</template><br>
          <template v-if="shelfEntry">{{ STATUS_LABELS[shelfEntry.status] }}</template>
        </div>
      </div>
      <h1 class="hero-title">{{ mediaItem.title }}</h1>
    </div>

    <section class="about">
      <div class="kicker">
        <template v-if="community.average_rating != null">
          ★ {{ community.average_rating.toFixed(1) }} average · {{ ratingCountLabel }}
        </template>
        <template v-else>No ratings yet</template>
      </div>
      <p v-if="mediaItem.overview" class="synopsis">{{ mediaItem.overview }}</p>
      <p v-else class="synopsis dim">No synopsis available.</p>
    </section>

    <section class="paper">
      <div class="kicker paper-kicker">Your diary</div>
      <template v-if="entries.length">
        <article v-for="(entry, index) in entries" :key="entry.id" class="entry">
          <hr v-if="index > 0" class="tear">
          <header class="entry-head">
            <div class="mono-paper-meta">
              {{ formatDate(entry.logged_date) }}
              <template v-if="targetLabel(entry)"> · {{ targetLabel(entry) }}</template>
              <template v-if="entry.is_rewatch"> · Rewatch</template>
            </div>
            <div class="entry-tools">
              <span v-if="entry.rating != null" class="entry-rating mono-paper-meta">★ {{ entry.rating.toFixed(1) }}</span>
              <button class="edit-btn" type="button" :aria-label="`Edit entry from ${formatDate(entry.logged_date)}`" @click="editEntry(entry)">
                <svg viewBox="0 0 20 20"><path d="M13.5 3.5l3 3L7 16H4v-3z" /></svg>
              </button>
            </div>
          </header>
          <template v-if="entry.review">
            <div class="quote-mark">&ldquo;</div>
            <p class="quote">{{ entry.review }}</p>
          </template>
          <p v-else class="no-review mono-paper-meta">Logged without a review.</p>
        </article>
      </template>
      <template v-else>
        <div class="kicker">No entries yet</div>
        <p class="quote empty-quote">Your history with this one starts when you log it.</p>
      </template>
    </section>

    <section v-if="isTv" class="episodes">
      <h2 class="section-title">Episodes</h2>
      <p v-if="loadingEpisodes" class="mono-meta pad">Syncing episodes…</p>
      <template v-else-if="episodes.length">
        <div class="season-pills">
          <button
            v-for="season in seasons"
            :key="season"
            class="pill"
            :class="{ active: episodeSeason === season }"
            type="button"
            @click="tick('light'); episodeSeason = season"
          >
            {{ season === 0 ? 'Specials' : `Season ${season}` }}
          </button>
        </div>
        <RouterLink
          v-for="episode in seasonEpisodes"
          :key="episode.id"
          class="episode-row"
          :to="{ name: 'episode-detail', params: { mediaId: mediaItem.id, episodeId: episode.id } }"
        >
          <span class="episode-title">E{{ episode.episode_number }} · {{ episode.title }}</span>
          <svg viewBox="0 0 20 20"><polyline points="8 4 14 10 8 16" /></svg>
        </RouterLink>
      </template>
    </section>

    <section class="reviews">
      <h2 class="section-title">Reviews</h2>
      <div v-if="reviews.length" class="review-list">
        <ReviewCard
          v-for="review in reviews"
          :key="review.id"
          :entry="review"
          :target-label="targetLabel(review)"
          :is-you="review.user?.id === auth.user?.id"
        />
      </div>
      <p v-else class="empty-reviews">Nobody has written about this one yet.</p>
    </section>

    <button class="fab" type="button" aria-label="Log this" @click="openWizard">
      <svg viewBox="0 0 20 20"><line x1="10" y1="4" x2="10" y2="16" /><line x1="4" y1="10" x2="16" y2="10" /></svg>
    </button>
  </div>
</template>

<style lang="scss" scoped>
.detail {
  max-width: 480px;
  margin: 0 auto;
  min-height: 100vh;
  padding-bottom: 6rem;
}

.hero {
  position: relative;
  height: 440px;
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
  background: linear-gradient(to top, rgb(var(--v-theme-background)) 0%, transparent 45%);
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
  bottom: 3.4rem;
  max-width: 80%;
  margin: 0;
  z-index: 2;
  font-family: $font-serif;
  font-weight: 400;
  font-size: clamp(2rem, 10vw, 2.8rem);
  line-height: 0.98;
  color: rgb(var(--v-theme-on-background));
  text-shadow: 0 6px 20px rgba(0, 0, 0, 0.65);
}

.about {
  padding: 0.4rem 1.3rem 1.6rem;
}

.synopsis {
  font-family: $font-serif;
  font-size: 1.1rem;
  line-height: 1.5;
  margin: 0.7rem 0 0;

  &.dim {
    color: rgb(var(--v-theme-ink-dim));
    font-style: italic;
  }
}

.paper-kicker {
  color: rgb(var(--v-theme-paper-dim));
  margin-bottom: 0.8rem;
}

.reviews {
  padding: 1.6rem 1.1rem 0.4rem;

  .section-title {
    padding-left: 0.2rem;
  }
}

.review-list {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.empty-reviews {
  font-family: $font-serif;
  font-style: italic;
  color: rgb(var(--v-theme-ink-dim));
  padding: 0 0.2rem;
}

.paper {
  position: relative;
  margin-top: 0;
  background: rgb(var(--v-theme-paper));
  color: rgb(var(--v-theme-on-paper));
  border-radius: 10px;
  padding: 2.4rem 1.3rem 1.4rem;
  margin-left: 0;
  margin-right: 0;
}

.entry-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.mono-paper-meta {
  @include mono-label(0.62rem, 0.08em);
  color: rgb(var(--v-theme-paper-dim));
}

.entry-tools {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.entry-rating {
  color: rgb(var(--v-theme-primary));
}

.edit-btn {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 1px solid rgba(var(--v-theme-on-paper), 0.2);
  background: none;
  color: rgb(var(--v-theme-paper-dim));
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;

  svg {
    width: 13px;
    height: 13px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-linejoin: round;
  }
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

.no-review {
  margin: 0.4rem 0 0;
  opacity: 0.8;
}

.tear {
  @include tear($paper-hair);
}

.episodes {
  padding: 1.4rem 0 0.4rem;
}

.section-title {
  font-family: $font-serif;
  font-weight: 400;
  font-size: 1.2rem;
  margin: 0;
  padding: 0 1.3rem 0.6rem;
}

.pad {
  padding: 0 1.3rem;
}

.season-pills {
  display: flex;
  gap: 0.4rem;
  overflow-x: auto;
  padding: 0.2rem 1.3rem 0.8rem;
  scrollbar-width: none;

  &::-webkit-scrollbar {
    display: none;
  }
}

.pill {
  flex: 0 0 auto;
  padding: 0.35rem 0.7rem;
  border-radius: 999px;
  border: 1px solid $hair-dark;
  color: rgb(var(--v-theme-ink-dim));
  background: transparent;
  cursor: pointer;
  white-space: nowrap;
  @include mono-label(0.62rem, 0.08em);

  &.active {
    color: rgb(var(--v-theme-background));
    background: rgb(var(--v-theme-accent));
    border-color: rgb(var(--v-theme-accent));
  }
}

.episode-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.8rem;
  padding: 0.75rem 1.3rem;
  text-decoration: none;
  color: rgb(var(--v-theme-on-background));
  border-bottom: 1px dashed $hair-dark;

  &:last-child {
    border-bottom: none;
  }

  .episode-title {
    font-family: $font-serif;
    font-size: 1.05rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  svg {
    width: 14px;
    height: 14px;
    flex: 0 0 auto;
    stroke: rgb(var(--v-theme-ink-dim));
    fill: none;
    stroke-width: 1.6;
    stroke-linecap: round;
    stroke-linejoin: round;
  }
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
