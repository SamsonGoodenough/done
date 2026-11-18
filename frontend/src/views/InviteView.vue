<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { http } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import AuthHero from '@/components/cine/AuthHero.vue'
import GlassPanel from '@/components/cine/GlassPanel.vue'
import CineField from '@/components/cine/CineField.vue'
import CineButton from '@/components/cine/CineButton.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const state = ref('loading') // loading | ready | invalid | gone
const invitation = ref(null)

const name = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const submitting = ref(false)
const errors = ref({})

onMounted(async () => {
  try {
    const response = await http.get(`/api/invitations/${route.params.token}`)
    invitation.value = response.data.data
    state.value = 'ready'
  } catch (error) {
    state.value = error.response?.status === 410 ? 'gone' : 'invalid'
  }
})

async function submit() {
  submitting.value = true
  errors.value = {}

  try {
    await auth.acceptInvite(route.params.token, {
      name: name.value,
      password: password.value,
      passwordConfirmation: passwordConfirmation.value,
    })
    router.replace({ name: 'shelf' })
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors ?? {}
    } else if (error.response?.status === 410) {
      state.value = 'gone'
    } else {
      errors.value = { name: ['Something went wrong. Try again.'] }
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="invite">
    <AuthHero>
      <template #corner>
        <span class="status-tag">Invitation</span>
      </template>
    </AuthHero>

    <GlassPanel class="panel">
      <template v-if="state === 'loading'">
        <div class="kicker">One moment</div>
        <h1 class="title">Checking your invite…</h1>
      </template>

      <template v-else-if="state === 'ready'">
        <div class="kicker">You're Invited</div>
        <h1 class="title">
          {{ invitation.invited_by ? `${invitation.invited_by} invited you to Done.` : 'Welcome to Done.' }}
        </h1>
        <p class="invited-email mono-meta">Joining as {{ invitation.email }}</p>
        <form novalidate @submit.prevent="submit">
          <CineField
            v-model="name"
            label="Your Name"
            placeholder="Jules Whitfield"
            autocomplete="name"
            :error="errors.name?.[0] ?? ''"
          />
          <CineField
            v-model="password"
            label="Password"
            type="password"
            placeholder="••••••••"
            autocomplete="new-password"
            :error="errors.password?.[0] ?? ''"
          />
          <CineField
            v-model="passwordConfirmation"
            label="Confirm Password"
            type="password"
            placeholder="••••••••"
            autocomplete="new-password"
            class="last-field"
          />
          <CineButton :loading="submitting">Join</CineButton>
        </form>
      </template>

      <template v-else>
        <div class="kicker">{{ state === 'gone' ? 'Invite Expired' : 'Invite Not Found' }}</div>
        <h1 class="title">
          {{
            state === 'gone'
              ? 'This invitation was already used or has expired.'
              : "This invitation link doesn't exist."
          }}
        </h1>
        <p class="mono-meta">Ask an admin to send you a fresh one.</p>
      </template>
    </GlassPanel>
  </div>
</template>

<style lang="scss" scoped>
.invite {
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

.invited-email {
  margin: -0.6rem 0 1.1rem;
}

.last-field {
  margin-bottom: 1.4rem;
}

.status-tag {
  display: inline-block;
  padding: 0.18rem 0.55rem;
  border: 1px solid currentColor;
  color: rgb(var(--v-theme-accent));
  transform: rotate(-3deg);
  @include mono-label(0.62rem);
}
</style>
