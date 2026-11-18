import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { http, ensureCsrf } from '@/api/http'
import { useShelfStore } from '@/stores/shelf'
import { useFriendsStore } from '@/stores/friends'
import { useNotificationsStore } from '@/stores/notifications'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  // True once the initial session check has resolved either way, so the
  // router guard only blocks on the very first navigation.
  const checked = ref(false)

  const isAuthenticated = computed(() => user.value !== null)

  async function fetchUser() {
    try {
      const response = await http.get('/api/user')
      user.value = response.data.data
    } catch {
      user.value = null
    } finally {
      checked.value = true
    }

    return user.value
  }

  async function login({ email, password, remember = false }) {
    await ensureCsrf()
    const response = await http.post('/api/login', { email, password, remember })
    user.value = response.data.data
    checked.value = true
  }

  async function acceptInvite(token, { name, password, passwordConfirmation }) {
    await ensureCsrf()
    const response = await http.post(`/api/invitations/${token}/accept`, {
      name,
      password,
      password_confirmation: passwordConfirmation,
    })
    // Accepting an invite creates the account and logs the session in.
    user.value = response.data.data
    checked.value = true
  }

  async function logout() {
    try {
      await http.post('/api/logout')
    } finally {
      forgetSession()
    }
  }

  // Everything user-scoped goes with the session, so the next member to sign
  // in on this device never sees the previous member's shelf, friends or
  // unread count.
  function forgetSession() {
    user.value = null
    useShelfStore().reset()
    useFriendsStore().reset()
    useNotificationsStore().reset()
  }

  return { user, checked, isAuthenticated, fetchUser, login, acceptInvite, logout, forgetSession }
})
