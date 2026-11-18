<script setup>
import { ref, onMounted, computed } from 'vue'
import { useShelfStore } from '@/stores/shelf'
import { useNotificationsStore } from '@/stores/notifications'
import { useAuthStore } from '@/stores/auth'
import FilterPills from '@/components/cine/FilterPills.vue'
import ShelfCard from '@/components/ShelfCard.vue'
import GlassCircle from '@/components/cine/GlassCircle.vue'

const shelf = useShelfStore()
const notifications = useNotificationsStore()
const auth = useAuthStore()

const statusFilter = ref(null)

const STATUS_OPTIONS = [
  { value: null, label: 'All' },
  { value: 'done', label: 'Done' },
  { value: 'in_progress', label: 'In Progress' },
  { value: 'planned', label: 'Planned' },
  { value: 'on_hold', label: 'On Hold' },
  { value: 'dropped', label: 'Dropped' },
]

const initial = computed(() => (auth.user?.name ?? '?').charAt(0).toUpperCase())

onMounted(() => {
  shelf.fetch()
  notifications.fetchUnreadCount()
})

const visibleEntries = computed(() =>
  statusFilter.value
    ? shelf.entries.filter((entry) => entry.status === statusFilter.value)
    : shelf.entries,
)
</script>

<template>
  <div class="shelf">
    <header class="topbar">
      <h1 class="wordmark">Done.</h1>
      <div class="actions">
        <GlassCircle class="bell" aria-label="Notifications" title="Notifications, coming in Phase 9">
          <svg viewBox="0 0 20 20"><path d="M10 3a4.5 4.5 0 0 0-4.5 4.5c0 4-1.5 5.5-1.5 5.5h12s-1.5-1.5-1.5-5.5A4.5 4.5 0 0 0 10 3z" /><path d="M8.5 16a1.5 1.5 0 0 0 3 0" /></svg>
          <span v-if="notifications.unreadCount > 0" class="badge">{{ notifications.unreadCount }}</span>
        </GlassCircle>
        <span class="avatar" :title="auth.user?.name">{{ initial }}</span>
      </div>
    </header>

    <FilterPills v-model="statusFilter" :options="STATUS_OPTIONS" />

    <div v-if="shelf.loaded && visibleEntries.length === 0" class="empty">
      <div class="kicker">{{ statusFilter ? 'Nothing here' : 'An empty shelf' }}</div>
      <p class="empty-line">
        {{
          statusFilter
            ? 'Nothing on the shelf with this status yet.'
            : 'Find something you love and give it a place here.'
        }}
      </p>
      <RouterLink class="empty-link mono-meta" :to="{ name: 'search' }">Search the catalog →</RouterLink>
    </div>

    <div v-else class="grid">
      <ShelfCard v-for="entry in visibleEntries" :key="entry.id" :entry="entry" />
    </div>
  </div>
</template>

<style lang="scss" scoped>
.shelf {
  max-width: 480px;
  margin: 0 auto;
}

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.1rem 0.25rem;
}

.wordmark {
  font-size: 1.6rem;
  font-weight: 400;
  margin: 0;
}

.actions {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.bell {
  position: relative;
  overflow: visible;
}

.badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
  font-family: $font-mono;
  font-size: 0.58rem;
  line-height: 16px;
  text-align: center;
}

.avatar {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: rgb(var(--v-theme-surface));
  border: 1px solid $hair-dark;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: $font-mono;
  font-size: 0.68rem;
  color: rgb(var(--v-theme-on-surface));
}

.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.6rem;
  padding: 0.2rem 1.1rem 1rem;
}

.empty {
  padding: 3rem 1.3rem;
}

.empty-line {
  font-family: $font-serif;
  font-style: italic;
  font-size: 1.15rem;
  line-height: 1.4;
  margin: 0.5rem 0 1.2rem;
  color: rgb(var(--v-theme-ink-dim));
}

.empty-link {
  color: rgb(var(--v-theme-accent));
  text-decoration: none;
}
</style>
