import { ref } from 'vue'
import { defineStore } from 'pinia'
import { http } from '@/api/http'

export const useNotificationsStore = defineStore('notifications', () => {
  const unreadCount = ref(0)

  async function fetchUnreadCount() {
    try {
      const response = await http.get('/api/notifications/unread-count')
      unreadCount.value = response.data.count ?? 0
    } catch {
      // The badge is decorative; a failed poll shouldn't surface anywhere.
    }
  }

  function reset() {
    unreadCount.value = 0
  }

  return { unreadCount, fetchUnreadCount, reset }
})
