<script setup>
import { yearOf } from '@/utils/dates'
import { computed } from 'vue'
import StampPostmark from '@/components/cine/StampPostmark.vue'

const props = defineProps({
  entry: { type: Object, required: true },
})

const item = computed(() => props.entry.media_item ?? {})

const TYPE_LABELS = { movie: 'Film', tv_show: 'TV', game: 'Game', book: 'Book' }

const meta = computed(() => {
  const label = TYPE_LABELS[item.value.type] ?? item.value.type
  const year = yearOf(item.value.release_date)
  return year ? `${label} · ${year}` : label
})
</script>

<template>
  <RouterLink class="card" :class="`type-${item.type}`" :to="{ name: 'media-detail', params: { mediaId: item.id } }">
    <div class="art">
      <img v-if="item.poster_url" :src="item.poster_url" alt="" loading="lazy">
      <div class="grade" />
    </div>
    <div class="fade" />
    <StampPostmark v-if="entry.latest_rating != null" class="stamp" :rating="entry.latest_rating" :size="36" />
    <div class="text">
      <div class="title">{{ item.title }}</div>
      <div class="meta mono-meta">{{ meta }}</div>
    </div>
  </RouterLink>
</template>

<style lang="scss" scoped>
.card {
  position: relative;
  display: block;
  aspect-ratio: 2 / 3;
  border-radius: 10px;
  overflow: hidden;
  text-decoration: none;
  background: rgb(var(--v-theme-surface));

  // Ambient per-type glow, matching the mockup's shelf.
  &.type-movie {
    box-shadow: 0 0 50px 6px rgba(var(--v-theme-primary), 0.28);
  }
  &.type-tv_show {
    box-shadow: 0 0 50px 6px rgba(var(--v-theme-teal), 0.26);
  }
  &.type-game {
    box-shadow: 0 0 50px 6px rgba(var(--v-theme-accent), 0.26);
  }
  &.type-book {
    box-shadow: 0 0 50px 6px rgba(var(--v-theme-primary), 0.18);
  }
}

// Scoped wrapper so the filter/positioning can never leak onto other
// imgs nested in the card (hard-won lesson from the mockup pass).
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
    filter: grayscale(0.3) contrast(1.15) brightness(0.68) sepia(0.15);
  }

  .grade {
    position: absolute;
    inset: 0;
    mix-blend-mode: multiply;
    background: linear-gradient(170deg, rgba(var(--v-theme-primary), 0.3), rgba(0, 0, 0, 0.9) 80%);
  }
}

.type-tv_show .art .grade {
  background: linear-gradient(170deg, rgba(var(--v-theme-teal), 0.3), rgba(0, 0, 0, 0.9) 80%);
}

.type-game .art .grade {
  background: linear-gradient(170deg, rgba(var(--v-theme-accent), 0.3), rgba(0, 0, 0, 0.9) 80%);
}

.fade {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.92) 0%, transparent 55%);
}

.stamp {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  z-index: 2;
}

.text {
  position: absolute;
  left: 0.7rem;
  right: 0.7rem;
  bottom: 0.6rem;
  z-index: 2;
}

.title {
  font-family: $font-sans;
  font-weight: 600;
  font-size: 0.85rem;
  color: rgb(var(--v-theme-on-background));
}

.meta {
  font-size: 0.58rem;
}
</style>
