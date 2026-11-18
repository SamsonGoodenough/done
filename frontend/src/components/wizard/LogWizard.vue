<script setup>
import { computed, provide } from 'vue'
import { yearOf } from '@/utils/dates'
import CineStepper from '@/components/wizard/CineStepper.vue'
import PickTargetStep from '@/components/wizard/steps/PickTargetStep.vue'
import TvLevelStep from '@/components/wizard/steps/TvLevelStep.vue'
import TvSeasonStep from '@/components/wizard/steps/TvSeasonStep.vue'
import TvEpisodeStep from '@/components/wizard/steps/TvEpisodeStep.vue'
import StatusStep from '@/components/wizard/steps/StatusStep.vue'
import RatingStep from '@/components/wizard/steps/RatingStep.vue'
import ReviewStep from '@/components/wizard/steps/ReviewStep.vue'
import TagFriendsStep from '@/components/wizard/steps/TagFriendsStep.vue'

const props = defineProps({
  wizard: { type: Object, required: true },
  saving: { type: Boolean, default: false },
})

defineEmits(['close', 'save'])

// Steps are compound children of this flow; they share the wizard state by
// injection rather than prop mutation.
provide('logWizard', props.wizard)

const STEP_COMPONENTS = {
  pick: PickTargetStep,
  level: TvLevelStep,
  season: TvSeasonStep,
  episode: TvEpisodeStep,
  status: StatusStep,
  rating: RatingStep,
  review: ReviewStep,
  tag: TagFriendsStep,
}

const stepComponent = computed(() => STEP_COMPONENTS[props.wizard.currentStep])

const TYPE_LABELS = { movie: 'Film', tv_show: 'TV', game: 'Game', book: 'Book' }

const contextLine = computed(() => {
  const item = props.wizard.mediaItem
  if (!item) return 'New Entry'

  const label = TYPE_LABELS[item.type] ?? item.type
  const year = yearOf(item.release_date)
  return [item.title, label, year].filter(Boolean).join(' · ')
})

const tint = computed(() => {
  switch (props.wizard.mediaItem?.type) {
    case 'tv_show':
      return 'teal'
    case 'game':
      return 'accent'
    default:
      return 'primary'
  }
})
</script>

<template>
  <CineStepper
    :step-count="wizard.path.length"
    :current-index="wizard.currentIndex"
    :context-line="contextLine"
    :hero-url="wizard.mediaItem?.poster_url ?? ''"
    :tint="tint"
    :saving="saving"
    @back="wizard.back()"
    @close="$emit('close')"
  >
    <Transition name="step">
      <component :is="stepComponent" :key="wizard.currentStep" @save="$emit('save')" />
    </Transition>
  </CineStepper>
</template>
