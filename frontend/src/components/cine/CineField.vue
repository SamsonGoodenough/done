<script setup>
defineProps({
  label: { type: String, required: true },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  autocomplete: { type: String, default: 'off' },
  error: { type: String, default: '' },
})

const model = defineModel({ type: String, default: '' })
</script>

<template>
  <label class="cine-field">
    <span class="field-label">{{ label }}</span>
    <input
      v-model="model"
      :type="type"
      :placeholder="placeholder"
      :autocomplete="autocomplete"
      :aria-invalid="Boolean(error)"
    >
    <span v-if="error" class="field-error">{{ error }}</span>
  </label>
</template>

<style lang="scss" scoped>
.cine-field {
  display: block;
  margin-bottom: 1.15rem;
}

.field-label {
  display: block;
  margin-bottom: 0.3rem;
  color: rgb(var(--v-theme-ink-dim));
  @include mono-label(0.6rem);
}

input {
  width: 100%;
  background: transparent;
  border: none;
  border-bottom: 1px solid $hair-dark;
  border-radius: 0;
  color: rgb(var(--v-theme-on-background));
  padding: 0.5rem 0.05rem;
  font-family: $font-sans;
  font-size: 0.92rem;

  &:focus {
    outline: none;
    border-bottom-color: rgb(var(--v-theme-accent));
  }

  &::placeholder {
    color: rgb(var(--v-theme-ink-dim));
    opacity: 0.6;
  }
}

.cine-field:has(.field-error) input {
  border-bottom-color: rgb(var(--v-theme-primary));
}

.field-error {
  display: block;
  margin-top: 0.35rem;
  color: rgb(var(--v-theme-primary));
  @include mono-label(0.58rem, 0.06em);
  text-transform: none;
}
</style>
