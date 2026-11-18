<script setup>
import { formatDay as formatDate } from '@/utils/dates'
import { ref } from 'vue'

// One member's review as a paper stub in a list. The whole card links to the
// review page; a spoiler-flagged quote stays blurred until tapped, and that
// tap does not navigate.
defineProps({
  entry: { type: Object, required: true },
  targetLabel: { type: String, default: null },
  isYou: { type: Boolean, default: false },
})

const revealed = ref(false)

</script>

<template>
  <RouterLink class="card" :to="{ name: 'review', params: { entryId: entry.id } }">
    <header class="head">
      <span class="who">{{ isYou ? 'You' : entry.user?.name }}</span>
      <span v-if="entry.rating != null" class="rating mono-paper-meta">★ {{ entry.rating.toFixed(1) }}</span>
    </header>
    <div class="mono-paper-meta sub">
      {{ formatDate(entry.logged_date) }}
      <template v-if="targetLabel"> · {{ targetLabel }}</template>
      <template v-if="entry.is_rewatch"> · Rewatch</template>
    </div>
    <div v-if="entry.contains_spoilers && !revealed" class="spoiler">
      <p class="quote blurred" aria-hidden="true">{{ entry.review }}</p>
      <button class="reveal mono-paper-meta" type="button" @click.prevent.stop="revealed = true">
        Spoilers · tap to reveal
      </button>
    </div>
    <p v-else class="quote">{{ entry.review }}</p>
  </RouterLink>
</template>

<style lang="scss" scoped>
.card {
  display: block;
  position: relative;
  padding: 1rem 1.1rem 1.1rem;
  border-radius: 8px;
  text-decoration: none;
  background: rgb(var(--v-theme-paper));
  color: rgb(var(--v-theme-on-paper));
  box-shadow: 0 8px 22px rgba(0, 0, 0, 0.35);
}

.head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.6rem;
}

.who {
  font-weight: 600;
  font-size: 0.92rem;
}

.mono-paper-meta {
  @include mono-label(0.62rem, 0.08em);
  color: rgb(var(--v-theme-paper-dim));
}

.rating {
  color: rgb(var(--v-theme-primary));
}

.sub {
  margin: 0.2rem 0 0.6rem;
}

.quote {
  font-family: $font-serif;
  font-style: italic;
  font-size: 1.1rem;
  line-height: 1.4;
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 6;
  line-clamp: 6;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.spoiler {
  position: relative;
}

.blurred {
  filter: blur(7px);
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
</style>
