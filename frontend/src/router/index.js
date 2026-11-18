import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

// meta.layout: 'app' (bottom-nav chrome) or 'blank' (full-screen takeover:
// auth, the log wizard, drill-in detail pages). meta.public marks the only
// routes reachable without a session.
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: { name: 'shelf' } },
    {
      path: '/login',
      name: 'login',
      meta: { layout: 'blank', public: true },
      component: () => import('@/views/LoginView.vue'),
    },
    {
      path: '/invite/:token',
      name: 'invite',
      meta: { layout: 'blank', public: true },
      component: () => import('@/views/InviteView.vue'),
    },
    {
      path: '/shelf',
      name: 'shelf',
      meta: { layout: 'app' },
      component: () => import('@/views/ShelfView.vue'),
    },
    {
      path: '/search',
      name: 'search',
      meta: { layout: 'app' },
      component: () => import('@/views/SearchView.vue'),
    },
    {
      path: '/activity',
      name: 'activity',
      meta: { layout: 'app' },
      component: () => import('@/views/ActivityView.vue'),
    },
    {
      path: '/profile',
      name: 'profile',
      meta: { layout: 'app' },
      component: () => import('@/views/ProfileView.vue'),
    },
    {
      path: '/log/new',
      name: 'log-new',
      meta: { layout: 'blank' },
      component: () => import('@/views/LogWizardView.vue'),
    },
    {
      path: '/log/:entryId/edit',
      name: 'log-edit',
      meta: { layout: 'blank' },
      component: () => import('@/views/LogWizardView.vue'),
    },
    {
      path: '/media/:mediaId',
      name: 'media-detail',
      meta: { layout: 'blank' },
      component: () => import('@/views/MediaDetailView.vue'),
    },
    {
      path: '/review/:entryId',
      name: 'review',
      meta: { layout: 'blank' },
      component: () => import('@/views/ReviewView.vue'),
    },
    {
      path: '/media/:mediaId/episode/:episodeId',
      name: 'episode-detail',
      meta: { layout: 'blank' },
      component: () => import('@/views/EpisodeDetailView.vue'),
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.checked) {
    await auth.fetchUser()
  }

  if (to.meta.public) {
    // A signed-in member has no business on login/invite; send them home.
    return auth.isAuthenticated ? { name: 'shelf' } : true
  }

  if (!auth.isAuthenticated) {
    return { name: 'login', query: to.fullPath === '/shelf' ? {} : { redirect: to.fullPath } }
  }

  return true
})

export default router
