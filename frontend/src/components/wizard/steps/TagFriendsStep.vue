<script setup>
import { onMounted, inject } from 'vue'
import { useFriendsStore } from '@/stores/friends'
import { useHaptics } from '@/composables/useHaptics'

const wizard = inject('logWizard')
const emit = defineEmits(['save'])

const friendsStore = useFriendsStore()
const { tick } = useHaptics()

onMounted(() => {
  if (!friendsStore.loaded) friendsStore.fetch()
})

function toggle(friend) {
  tick('light')
  const tagged = wizard.fields.taggedUserIds

  if (tagged.includes(friend.id)) {
    wizard.fields.taggedUserIds = tagged.filter((id) => id !== friend.id)
  } else {
    wizard.fields.taggedUserIds = [...tagged, friend.id]
  }
}
</script>

<template>
  <div class="step-panel">
    <div class="step-kicker">Tag Friends</div>
    <h2 class="step-title">Who was there?</h2>

    <p v-if="friendsStore.loaded && friendsStore.friends.length === 0" class="mono-meta">
      No friends yet. You can tag people once you've added some.
    </p>

    <div v-else class="tag-grid">
      <button
        v-for="friend in friendsStore.friends"
        :key="friend.id"
        class="tag-avatar"
        :class="{ selected: wizard.fields.taggedUserIds.includes(friend.id) }"
        type="button"
        @click="toggle(friend)"
      >
        <span class="face">
          <img v-if="friend.avatar_path" :src="friend.avatar_path" alt="">
          <span v-else class="initial">{{ friend.name.charAt(0).toUpperCase() }}</span>
        </span>
        <span class="name mono-meta">{{ friend.name }}</span>
      </button>
    </div>

    <p class="hint mono-meta">Optional. Tap to tag, then save.</p>

    <button class="next-btn" type="button" aria-label="Save entry" @click="emit('save')">
      <svg viewBox="0 0 20 20"><polyline points="4 10 8 14 16 5" /></svg>
    </button>
  </div>
</template>

<style lang="scss" scoped>
.tag-grid {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.tag-avatar {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
  width: 60px;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}

.face {
  width: 58px;
  height: 58px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgb(var(--v-theme-surface));
  transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: grayscale(0.3) contrast(1.1) brightness(0.82);
    transition: filter 0.2s ease;
  }

  .initial {
    font-family: $font-serif;
    font-size: 1.4rem;
    color: rgb(var(--v-theme-ink-dim));
  }
}

.name {
  font-size: 0.58rem;
  letter-spacing: 0.04em;
  max-width: 60px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tag-avatar.selected {
  .face {
    border-color: rgb(var(--v-theme-primary));
    transform: scale(1.08);
    box-shadow: 0 0 0 4px rgba(var(--v-theme-primary), 0.22);

    img {
      filter: none;
    }
  }

  .name {
    color: rgb(var(--v-theme-on-background));
  }
}

.hint {
  margin-top: 1rem;
  opacity: 0.8;
}
</style>
