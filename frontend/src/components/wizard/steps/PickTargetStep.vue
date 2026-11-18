<script setup>
import { ref, watch, inject } from 'vue'
import { http } from '@/api/http'
import { useHaptics } from '@/composables/useHaptics'
import FilterPills from '@/components/cine/FilterPills.vue'

const wizard = inject('logWizard')
const { tick } = useHaptics()

const TYPE_OPTIONS = [
  { value: 'movie', label: 'Film' },
  { value: 'tv_show', label: 'TV' },
  { value: 'game', label: 'Game' },
  { value: 'book', label: 'Book' },
]

const query = ref('')
const type = ref('movie')
const results = ref([])
const searching = ref(false)
const picking = ref(null)

let debounceTimer = null

watch([query, type], () => {
  clearTimeout(debounceTimer)

  if (query.value.trim().length < 2) {
    results.value = []
    return
  }

  debounceTimer = setTimeout(async () => {
    searching.value = true

    try {
      const response = await http.get('/api/media-items/search', {
        params: { type: type.value, query: query.value.trim() },
      })
      results.value = response.data.data
    } catch {
      results.value = []
    } finally {
      searching.value = false
    }
  }, 450)
})

async function pick(result) {
  if (picking.value) return
  picking.value = result.external_id
  tick('light')

  try {
    let mediaItemId = result.media_item_id

    if (mediaItemId == null) {
      const cataloged = await http.post('/api/media-items', {
        type: result.type,
        external_id: result.external_id,
      })
      wizard.mediaItem = cataloged.data.data
    } else {
      const existing = await http.get(`/api/media-items/${mediaItemId}`)
      wizard.mediaItem = existing.data.data
    }

    // No next() here: setting the media item removes 'pick' from the path,
    // so index 0 now already points at the next step (level or status).
  } finally {
    picking.value = null
  }
}
</script>

<template>
  <div class="step-panel pick-panel">
    <div class="step-kicker">New Entry</div>
    <h2 class="step-title">What did you finish?</h2>

    <input
      v-model="query"
      class="pick-search"
      type="search"
      placeholder="Search movies, shows, games, books…"
      autocomplete="off"
    >

    <FilterPills v-model="type" :options="TYPE_OPTIONS" class="pick-pills" />

    <div class="pick-results">
      <p v-if="searching" class="mono-meta">Searching…</p>
      <button
        v-for="result in results"
        :key="result.external_id"
        class="pick-row"
        type="button"
        :disabled="picking !== null"
        @click="pick(result)"
      >
        <span class="thumb">
          <img v-if="result.poster_url" :src="result.poster_url" alt="" loading="lazy">
        </span>
        <span class="info">
          <span class="row-title">{{ result.title }}</span>
          <span class="mono-meta">{{ result.release_date ? String(result.release_date).slice(0, 4) : '' }}</span>
        </span>
        <span class="go">
          <svg viewBox="0 0 20 20"><polyline points="8 4 14 10 8 16" /></svg>
        </span>
      </button>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.pick-panel {
  justify-content: flex-start;
  padding-top: calc(6.2rem + env(safe-area-inset-top));
}

.pick-search {
  width: 100%;
  border-radius: 999px;
  padding: 0.65rem 1rem;
  color: rgb(var(--v-theme-on-surface));
  font-family: $font-sans;
  font-size: 0.92rem;
  @include glass(0.5, 10px);

  &:focus {
    outline: none;
    border-color: rgb(var(--v-theme-accent));
  }

  &::placeholder {
    color: rgb(var(--v-theme-ink-dim));
    opacity: 0.7;
  }
}

.pick-pills {
  position: static;
  background: none;
  backdrop-filter: none;
  -webkit-backdrop-filter: none;
  padding-left: 0;
  padding-right: 0;
}

.pick-results {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
}

.pick-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding: 0.7rem 0;
  background: none;
  border: none;
  border-bottom: 1px dashed rgba(var(--v-theme-on-background), 0.14);
  cursor: pointer;
  text-align: left;

  &:last-child {
    border-bottom: none;
  }
}

.thumb {
  width: 42px;
  height: 42px;
  border-radius: 8px;
  overflow: hidden;
  flex: 0 0 auto;
  background: rgb(var(--v-theme-surface));

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: grayscale(0.2) contrast(1.15) brightness(0.8);
  }
}

.info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.row-title {
  font-weight: 600;
  font-size: 0.88rem;
  color: rgb(var(--v-theme-on-background));
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.go {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  color: rgb(var(--v-theme-ink-dim));

  svg {
    width: 14px;
    height: 14px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.6;
    stroke-linecap: round;
    stroke-linejoin: round;
  }
}
</style>
