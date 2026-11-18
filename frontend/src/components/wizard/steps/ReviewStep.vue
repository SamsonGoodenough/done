<script setup>
import { ref, onMounted, inject } from 'vue'
import { useHaptics } from '@/composables/useHaptics'

const wizard = inject('logWizard')
const { tick } = useHaptics()

const quoteEl = ref(null)

// contenteditable is intentionally uncontrolled: the DOM owns the text and
// we mirror it into wizard state on input, which keeps the caret stable.
onMounted(() => {
  if (wizard.fields.review) {
    quoteEl.value.textContent = wizard.fields.review
  }
})

function onInput() {
  // innerText, not textContent: it turns the <div>/<br> the browser inserts on
  // Enter back into newlines, so paragraphs survive.
  wizard.fields.review = quoteEl.value.innerText.replace(/\n+$/, '')
}

// Plain text only; rich paste would drop foreign markup into the quote.
function onPaste(event) {
  const text = event.clipboardData?.getData('text/plain') ?? ''
  document.execCommand('insertText', false, text)
}

function toggleSpoilers() {
  tick('rigid')
  wizard.fields.containsSpoilers = !wizard.fields.containsSpoilers
}
</script>

<template>
  <div class="step-panel review-panel">
    <div class="step-kicker">Review</div>
    <div class="quote-wrap">
      <div class="quote-mark">&ldquo;</div>
      <div
        ref="quoteEl"
        class="live-quote"
        contenteditable="true"
        data-placeholder="Write about it. The words show up right here."
        @input="onInput"
        @paste.prevent="onPaste"
      />
    </div>
    <div class="panel-foot">
      <button class="spoiler-toggle" :class="{ on: wizard.fields.containsSpoilers }" type="button" @click="toggleSpoilers">
        {{ wizard.fields.containsSpoilers ? 'Spoilers' : 'No Spoilers' }}
      </button>
    </div>
    <button class="next-btn" type="button" aria-label="Continue" @click="wizard.next()">
      <svg viewBox="0 0 20 20"><polyline points="8 4 14 10 8 16" /></svg>
    </button>
  </div>
</template>

<style lang="scss" scoped>
.review-panel {
  justify-content: flex-start;
  padding-top: calc(6.2rem + env(safe-area-inset-top));
}

.quote-wrap {
  max-height: 46vh;
  overflow-y: auto;
  padding-right: 0.3rem;
}

.quote-mark {
  font-family: $font-serif;
  font-size: 3.6rem;
  line-height: 0.5;
  color: rgba(var(--v-theme-on-background), 0.35);
  margin-bottom: 0.5rem;
}

.live-quote {
  font-family: $font-serif;
  font-style: italic;
  font-size: clamp(1.3rem, 5.4vw, 1.8rem);
  line-height: 1.32;
  color: rgb(var(--v-theme-on-background));
  text-shadow: 0 3px 16px rgba(0, 0, 0, 0.55);
  text-align: justify;
  text-align-last: left;
  outline: none;
  white-space: pre-wrap;
  min-height: 2.6em;

  &:empty::before {
    content: attr(data-placeholder);
    color: rgb(var(--v-theme-ink-dim));
    opacity: 0.55;
  }
}

.panel-foot {
  margin-top: auto;
  padding-top: 1rem;
}

.spoiler-toggle {
  padding: 0.32rem 0.7rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-primary));
  background: transparent;
  cursor: pointer;
  transition: transform 0.15s ease, background 0.15s ease, color 0.15s ease;
  @include mono-label(0.62rem, 0.08em);

  &.on {
    background: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
    transform: rotate(-3deg);
  }
}
</style>
