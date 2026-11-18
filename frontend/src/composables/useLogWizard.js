import { ref, computed, reactive, onScopeDispose } from 'vue'
import { http } from '@/api/http'
import { useShelfStore } from '@/stores/shelf'

import { todayIso } from '@/utils/dates'

/**
 * All wizard state and the save orchestration. Ephemeral by design, owned
 * by LogWizardView's lifetime, reset by navigation, never a Pinia store.
 *
 * mode 'create': target can arrive preresolved (contextual entry from a
 * detail page) or be chosen in-wizard (pick step, then TV granularity).
 * mode 'edit': target is fixed by the existing entry; only content changes.
 */
export function useLogWizard() {
  const mode = ref('create')
  const mediaItem = ref(null)
  const entryId = ref(null)

  // TV granularity. level: null | 'show' | 'season' | 'episode'
  const tv = reactive({
    level: null,
    resolved: false, // true when the logging target needs no granularity steps
    seasonNumber: null,
    episodeId: null,
    episodes: [],
    episodesLoaded: false,
  })

  const fields = reactive({
    status: null,
    loggedDate: todayIso(),
    rating: null,
    review: '',
    containsSpoilers: false,
    taggedUserIds: [],
  })

  const original = reactive({
    shelfStatus: null,
    taggedUserIds: [],
  })

  const currentIndex = ref(0)
  const saving = ref(false)

  const isTv = computed(() => mediaItem.value?.type === 'tv_show')

  const path = computed(() => {
    const steps = []

    if (mode.value === 'create') {
      if (!mediaItem.value) steps.push('pick')

      if (mediaItem.value && isTv.value && !tv.resolved) {
        steps.push('level')
        if (tv.level === 'season' || tv.level === 'episode') steps.push('season')
        if (tv.level === 'episode') steps.push('episode')
      }
    }

    steps.push('status', 'rating', 'review', 'tag')
    return steps
  })

  const currentStep = computed(() => path.value[currentIndex.value])

  // Option steps advance a beat after a pick so the selection can register.
  // One pending advance at a time (a second tap just changes the selection),
  // and going back or leaving the wizard cancels it, so a stale timer can
  // never skip a step or fire against an unmounted step.
  let advanceTimer = null

  function cancelAdvance() {
    clearTimeout(advanceTimer)
    advanceTimer = null
  }

  function advanceAfter(ms = 420) {
    if (advanceTimer !== null) return

    advanceTimer = setTimeout(() => {
      advanceTimer = null
      next()
    }, ms)
  }

  onScopeDispose(cancelAdvance)

  function next() {
    cancelAdvance()
    if (currentIndex.value < path.value.length - 1) currentIndex.value += 1
  }

  function back() {
    cancelAdvance()
    if (currentIndex.value > 0) currentIndex.value -= 1
  }

  async function ensureEpisodes() {
    if (tv.episodesLoaded || !mediaItem.value) return

    const response = await http.get(`/api/media-items/${mediaItem.value.id}/episodes`)
    tv.episodes = response.data.data
    tv.episodesLoaded = true
  }

  const seasons = computed(() => [...new Set(tv.episodes.map((episode) => episode.season_number))])

  const seasonEpisodes = computed(() =>
    tv.episodes.filter((episode) => episode.season_number === tv.seasonNumber),
  )

  /** The create payload's granularity: whole item unless a season/episode target. */
  function granularity() {
    if (!isTv.value) return { episode_id: null, season_number: null }
    if (tv.level === 'episode' && tv.episodeId != null) {
      return { episode_id: tv.episodeId, season_number: null }
    }
    if (tv.level === 'season' && tv.seasonNumber != null) {
      return { episode_id: null, season_number: tv.seasonNumber }
    }
    return { episode_id: null, season_number: null }
  }

  async function save() {
    saving.value = true

    try {
      // Once the entry exists (created here or opened for edit) every save is
      // an update. That makes a retry after a failed later step (participant
      // sync, shelf status) safe: it can never create a second entry.
      if (entryId.value == null) {
        const created = await http.post('/api/log-entries', {
          media_item_id: mediaItem.value.id,
          ...granularity(),
          logged_date: fields.loggedDate,
          rating: fields.rating,
          review: fields.review.trim() || null,
          contains_spoilers: fields.containsSpoilers,
          tagged_user_ids: fields.taggedUserIds,
        })

        entryId.value = created.data.data.id
        original.taggedUserIds = [...fields.taggedUserIds]
      } else {
        await http.patch(`/api/log-entries/${entryId.value}`, {
          logged_date: fields.loggedDate,
          rating: fields.rating,
          review: fields.review.trim() || null,
          contains_spoilers: fields.containsSpoilers,
        })

        const before = new Set(original.taggedUserIds)
        const after = new Set(fields.taggedUserIds)
        const added = [...after].filter((id) => !before.has(id))
        const removed = [...before].filter((id) => !after.has(id))

        await Promise.all([
          ...added.map((id) =>
            http.post(`/api/log-entries/${entryId.value}/participants`, { user_id: id }),
          ),
          ...removed.map((id) =>
            http.delete(`/api/log-entries/${entryId.value}/participants/${id}`),
          ),
        ])

        original.taggedUserIds = [...fields.taggedUserIds]
      }

      // The shelf status is its own explicit action (ADR 0003): sync it when
      // the chosen status differs from where the shelf entry actually is.
      const shelf = useShelfStore()
      await shelf.fetch()
      const shelfEntry = shelf.entryForMediaItem(mediaItem.value.id)

      if (shelfEntry && fields.status && shelfEntry.status !== fields.status) {
        const response = await http.patch(`/api/user-media/${shelfEntry.id}`, {
          status: fields.status,
        })
        shelf.upsert(response.data.data)
      }

      return true
    } finally {
      saving.value = false
    }
  }

  return {
    mode,
    mediaItem,
    entryId,
    tv,
    fields,
    original,
    currentIndex,
    saving,
    isTv,
    path,
    currentStep,
    seasons,
    seasonEpisodes,
    next,
    back,
    advanceAfter,
    ensureEpisodes,
    save,
  }
}
