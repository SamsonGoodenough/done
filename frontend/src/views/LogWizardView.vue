<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { http } from '@/api/http'
import { useLogWizard } from '@/composables/useLogWizard'
import { useHaptics } from '@/composables/useHaptics'
import LogWizard from '@/components/wizard/LogWizard.vue'

const route = useRoute()
const router = useRouter()
const { tick } = useHaptics()

// reactive() unwraps the composable's refs so steps and templates read
// plain properties.
const wizard = reactive(useLogWizard())

const ready = ref(false)
const flourish = ref(false)

onMounted(async () => {
  if (route.name === 'log-edit') {
    // Edit mode is seeded from navigation state (there's no fetch-one-entry
    // endpoint, by design), so a cold deep-link just goes home.
    const seed = history.state?.entrySeed

    if (!seed) {
      router.replace({ name: 'shelf' })
      return
    }

    wizard.mode = 'edit'
    wizard.entryId = seed.entry.id
    wizard.mediaItem = seed.mediaItem
    wizard.tv.resolved = true
    wizard.fields.status = seed.shelfStatus ?? null
    wizard.fields.loggedDate = String(seed.entry.logged_date).slice(0, 10)
    wizard.fields.rating = seed.entry.rating
    wizard.fields.review = seed.entry.review ?? ''
    wizard.fields.containsSpoilers = Boolean(seed.entry.contains_spoilers)
    wizard.fields.taggedUserIds = [...(seed.entry.tagged_user_ids ?? [])]
    wizard.original.taggedUserIds = [...(seed.entry.tagged_user_ids ?? [])]
    wizard.original.shelfStatus = seed.shelfStatus ?? null
  } else {
    const { mediaId, episodeId, seasonNumber } = route.query

    if (mediaId) {
      const response = await http.get(`/api/media-items/${mediaId}`)
      wizard.mediaItem = response.data.data
    }

    if (episodeId) {
      // Contextual entry from an episode page: granularity already resolved.
      wizard.tv.episodeId = Number(episodeId)
      wizard.tv.level = 'episode'
      wizard.tv.resolved = true
    } else if (seasonNumber) {
      wizard.tv.seasonNumber = Number(seasonNumber)
      wizard.tv.level = 'season'
      wizard.tv.resolved = true
    }
  }

  ready.value = true
})

function close() {
  if (history.state?.back) {
    router.back()
  } else {
    router.replace({ name: 'shelf' })
  }
}

async function save() {
  if (flourish.value) return
  flourish.value = true

  try {
    await wizard.save()
    tick('success')
    setTimeout(() => {
      if (wizard.mode === 'edit') {
        close()
      } else {
        router.replace({ name: 'media-detail', params: { mediaId: wizard.mediaItem.id } })
      }
    }, 480)
  } catch {
    flourish.value = false
  }
}
</script>

<template>
  <LogWizard v-if="ready" :wizard="wizard" :saving="flourish" @close="close" @save="save" />
</template>
