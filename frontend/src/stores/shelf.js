import { ref } from 'vue'
import { defineStore } from 'pinia'
import { http } from '@/api/http'

export const useShelfStore = defineStore('shelf', () => {
  const entries = ref([])
  const loading = ref(false)
  const loaded = ref(false)

  // Always the whole shelf (no server-side filters): other screens look
  // entries up by media item, so a filtered snapshot would hide items that
  // are really on the shelf. Views filter client-side.
  async function fetch() {
    loading.value = true

    try {
      const response = await http.get('/api/user-media')
      entries.value = response.data.data
      loaded.value = true
    } finally {
      loading.value = false
    }
  }

  function entryForMediaItem(mediaItemId) {
    return entries.value.find((entry) => entry.media_item_id === Number(mediaItemId)) ?? null
  }

  /** Merge a fresh shelf entry (from a store/update response) into the list. */
  function upsert(entry) {
    const index = entries.value.findIndex((existing) => existing.id === entry.id)

    if (index === -1) {
      entries.value.unshift(entry)
    } else {
      entries.value[index] = { ...entries.value[index], ...entry }
    }
  }

  function reset() {
    entries.value = []
    loaded.value = false
  }

  return { entries, loading, loaded, fetch, entryForMediaItem, upsert, reset }
})
