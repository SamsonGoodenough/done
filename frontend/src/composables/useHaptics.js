import { WebHaptics } from 'web-haptics'

// One app-wide instance: the library owns hidden DOM/audio resources, so a
// singleton avoids leaking them per component. On Android this drives the
// Vibration API; on iOS 17.4+ it falls back to the native switch-control
// haptic. In dev, debug mode plays an audible click so the feedback is
// testable on a laptop.
let instance = null

function haptics() {
  instance ??= new WebHaptics({ debug: import.meta.env.DEV })
  return instance
}

/**
 * tick('selection'): scrubbing across half-star steps (lightest)
 * tick('light'):     picking an option (status, tag, season, episode)
 * tick('medium'):    opening the wizard from a FAB
 * tick('rigid'):     the spoiler toggle snapping on
 * tick('success'):   saving an entry, finishing a flow
 */
export function useHaptics() {
  function tick(preset = 'selection') {
    try {
      haptics().trigger(preset)
    } catch {
      // Haptics are a flourish, so never let them break an interaction.
    }
  }

  return { tick }
}
