<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import CineButton from '@/components/cine/CineButton.vue'

const auth = useAuthStore()
const router = useRouter()

async function signOut() {
  await auth.logout()
  router.replace({ name: 'login' })
}
</script>

<template>
  <div class="profile">
    <header class="topbar">
      <h1 class="title">Profile</h1>
    </header>
    <div class="body">
      <div class="kicker">Signed in as</div>
      <p class="name">{{ auth.user?.name }}</p>
      <p class="mono-meta email">{{ auth.user?.email }}</p>
      <p class="mono-meta note">Full profile pages arrive in Phase 9.</p>
      <CineButton :block="false" @click.prevent="signOut">Sign Out</CineButton>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.profile {
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

.body {
  padding: 1.5rem 1.3rem;
}

.name {
  font-family: $font-serif;
  font-size: 1.7rem;
  margin: 0.4rem 0 0.2rem;
}

.email {
  margin-bottom: 1.6rem;
}

.note {
  margin-bottom: 2rem;
  opacity: 0.8;
}
</style>
