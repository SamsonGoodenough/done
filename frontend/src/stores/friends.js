import { ref } from 'vue'
import { defineStore } from 'pinia'
import { http } from '@/api/http'
import { useAuthStore } from '@/stores/auth'

export const useFriendsStore = defineStore('friends', () => {
  const friends = ref([]) // accepted friends as member objects {id, name, avatar_path}
  const loaded = ref(false)

  async function fetch() {
    const auth = useAuthStore()
    const response = await http.get('/api/friendships', { params: { status: 'accepted' } })

    friends.value = response.data.data.map((friendship) =>
      friendship.requester?.id === auth.user?.id ? friendship.addressee : friendship.requester,
    )
    loaded.value = true
  }

  function reset() {
    friends.value = []
    loaded.value = false
  }

  return { friends, loaded, fetch, reset }
})
