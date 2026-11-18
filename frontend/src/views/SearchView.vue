<script setup>
import { ref, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import { http } from '@/api/http'
import { useShelfStore } from '@/stores/shelf'
import { useHaptics } from '@/composables/useHaptics'
import FilterPills from '@/components/cine/FilterPills.vue'

const router = useRouter()
const shelf = useShelfStore()
const { tick } = useHaptics()

const TYPE_OPTIONS = [
  { value: 'movie', label: 'Film' },
  { value: 'tv_show', label: 'TV' },
  { value: 'game', label: 'Game' },
  { value: 'book', label: 'Book' },
]

const TYPE_LABELS = { movie: 'Film', tv_show: 'TV', game: 'Game', book: 'Book' }

const query = ref('')
const type = ref('movie')
const results = ref([])
const searching = ref(false)
const searched = ref(false)
const failed = ref(false)
// external_id -> 'adding' | 'added'
const addState = ref({})

let debounceTimer = null

function scheduleSearch() {
  clearTimeout(debounceTimer)

  if (query.value.trim().length < 2) {
    results.value = []
    searched.value = false
    return
  }

  debounceTimer = setTimeout(runSearch, 450)
}

async function runSearch() {
  searching.value = true
  failed.value = false

  try {
    const response = await http.get('/api/media-items/search', {
      params: { type: type.value, query: query.value.trim() },
    })
    results.value = response.data.data
    searched.value = true
  } catch {
    results.value = []
    searched.value = true
    failed.value = true
  } finally {
    searching.value = false
  }
}

watch(query, scheduleSearch)
watch(type, () => {
  if (query.value.trim().length >= 2) runSearch()
})

const shelfLoaded = computed(() => shelf.loaded)
if (!shelfLoaded.value) shelf.fetch()

function onShelf(result) {
  return (
    (result.media_item_id != null && shelf.entryForMediaItem(result.media_item_id) !== null) ||
    addState.value[result.external_id] === 'added'
  )
}

async function add(result) {
  if (onShelf(result) || addState.value[result.external_id] === 'adding') return

  addState.value = { ...addState.value, [result.external_id]: 'adding' }

  try {
    let mediaItemId = result.media_item_id

    if (mediaItemId == null) {
      const cataloged = await http.post('/api/media-items', {
        type: result.type,
        external_id: result.external_id,
      })
      mediaItemId = cataloged.data.data.id
    }

    const response = await http.post('/api/user-media', {
      media_item_id: mediaItemId,
      status: 'planned',
    })
    shelf.upsert(response.data.data)
    addState.value = { ...addState.value, [result.external_id]: 'added' }
    tick('success')
  } catch {
    addState.value = { ...addState.value, [result.external_id]: undefined }
  }
}

// Opens the item's page. A result nobody has cataloged yet is cataloged
// first (idempotent on the backend), so every result is viewable.
const opening = ref(null)

async function open(result) {
  if (opening.value) return
  opening.value = result.external_id
  tick('light')

  try {
    let mediaItemId = result.media_item_id

    if (mediaItemId == null) {
      const cataloged = await http.post('/api/media-items', {
        type: result.type,
        external_id: result.external_id,
      })
      mediaItemId = cataloged.data.data.id
    }

    router.push({ name: 'media-detail', params: { mediaId: mediaItemId } })
  } catch {
    failed.value = true
  } finally {
    opening.value = null
  }
}

function metaLine(result) {
  const label = TYPE_LABELS[result.type] ?? result.type
  const year = result.release_date ? String(result.release_date).slice(0, 4) : null
  return year ? `${label} · ${year}` : label
}
</script>

<template>
  <div class="search">
    <header class="topbar">
      <h1 class="title">Search</h1>
    </header>

    <div class="searchbox">
      <input
        v-model="query"
        type="search"
        placeholder="Search movies, shows, games, books…"
        autocomplete="off"
      >
    </div>

    <FilterPills v-model="type" :options="TYPE_OPTIONS" class="type-pills" />

    <div v-if="searching" class="state mono-meta">Searching…</div>

    <div v-else-if="failed" class="state">
      <div class="kicker">Search unavailable</div>
      <p class="state-line">That source didn't answer. Try again in a moment.</p>
    </div>

    <div v-else-if="searched && results.length === 0" class="state">
      <div class="kicker">No matches</div>
      <p class="state-line">Nothing in the catalog under that name.</p>
    </div>

    <ul v-else class="results">
      <li v-for="result in results" :key="result.external_id" class="row">
        <button
          class="open"
          :class="{ opening: opening === result.external_id }"
          type="button"
          :aria-label="`Open ${result.title}`"
          @click="open(result)"
        >
          <div class="thumb">
            <img v-if="result.poster_url" :src="result.poster_url" alt="" loading="lazy">
          </div>
          <div class="info">
            <div class="row-title">{{ result.title }}</div>
            <div class="mono-meta">{{ metaLine(result) }}</div>
          </div>
        </button>
        <button
          class="add"
          :class="{ done: onShelf(result) }"
          type="button"
          :aria-label="onShelf(result) ? 'On your shelf' : `Add ${result.title} to your shelf`"
          @click="add(result)"
        >
          <svg v-if="onShelf(result)" viewBox="0 0 20 20"><polyline points="4 10 8 14 16 5" /></svg>
          <svg v-else viewBox="0 0 20 20"><line x1="10" y1="4" x2="10" y2="16" /><line x1="4" y1="10" x2="16" y2="10" /></svg>
        </button>
      </li>
    </ul>
  </div>
</template>

<style lang="scss" scoped>
.search {
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

.searchbox {
  padding: 0 1.1rem 0.4rem;

  input {
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
}

.state {
  padding: 2rem 1.3rem;
}

.state-line {
  font-family: $font-serif;
  font-style: italic;
  font-size: 1.05rem;
  color: rgb(var(--v-theme-ink-dim));
  margin-top: 0.4rem;
}

.results {
  list-style: none;
  margin: 0;
  padding: 0;
}

.row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.8rem 1.1rem;

  & + .row {
    border-top: 1px dashed $hair-dark;
  }
}

.open {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex: 1;
  min-width: 0;
  padding: 0;
  background: none;
  border: none;
  color: inherit;
  text-align: left;
  cursor: pointer;
  transition: opacity 0.2s ease;

  &.opening {
    opacity: 0.5;
  }
}

.thumb {
  width: 46px;
  height: 46px;
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
}

.row-title {
  font-weight: 600;
  font-size: 0.88rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.add {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
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

  &.done {
    color: rgb(var(--v-theme-accent));
    border-color: rgb(var(--v-theme-accent));
  }
}
</style>
