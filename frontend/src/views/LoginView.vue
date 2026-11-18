<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AuthHero from '@/components/cine/AuthHero.vue'
import GlassPanel from '@/components/cine/GlassPanel.vue'
import CineField from '@/components/cine/CineField.vue'
import CineButton from '@/components/cine/CineButton.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const loading = ref(false)
const errors = ref({})

async function submit() {
  loading.value = true
  errors.value = {}

  try {
    await auth.login({ email: email.value, password: password.value, remember: true })
    router.replace(typeof route.query.redirect === 'string' ? route.query.redirect : { name: 'shelf' })
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors ?? { email: [error.response.data.message] }
    } else {
      errors.value = { email: ['Something went wrong. Try again.'] }
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login">
    <AuthHero />
    <GlassPanel class="panel">
      <div class="kicker">Sign In</div>
      <h1 class="title">Welcome back.</h1>
      <form novalidate @submit.prevent="submit">
        <CineField
          v-model="email"
          label="Email"
          type="email"
          placeholder="you@example.com"
          autocomplete="email"
          :error="errors.email?.[0] ?? ''"
        />
        <CineField
          v-model="password"
          label="Password"
          type="password"
          placeholder="••••••••"
          autocomplete="current-password"
          :error="errors.password?.[0] ?? ''"
          class="last-field"
        />
        <CineButton :loading="loading">Sign In</CineButton>
      </form>
    </GlassPanel>
    <p class="footnote mono-meta">Invite-only. Ask an admin for an invite.</p>
  </div>
</template>

<style lang="scss" scoped>
.login {
  max-width: 480px;
  margin: 0 auto;
  min-height: 100vh;
}

.panel {
  margin: -3.2rem 1.1rem 1.3rem;
}

.title {
  font-family: $font-serif;
  font-size: 1.4rem;
  font-weight: 400;
  margin: 0.3rem 0 1.2rem;
}

.last-field {
  margin-bottom: 1.4rem;
}

.footnote {
  text-align: center;
  padding: 0.4rem 1.5rem 1.5rem;
}
</style>
