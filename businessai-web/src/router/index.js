import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

import LoginView from '@/views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'
import DashboardView from '@/views/DashboardView.vue'
import DocumentsView from '@/views/DocumentsView.vue'
import AskAIView from '@/views/AskAIView.vue'

import AppLayout from '@/layouts/AppLayout.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),

  routes: [
    // =========================
    // ROOT ROUTE
    // =========================

    {
      path: '/',
      name: 'home',
      redirect: () => {
        const auth = useAuthStore()

        return auth.isAuthenticated
          ? { name: 'dashboard' }
          : { name: 'login' }
      },
    },

    // =========================
    // GUEST ROUTES
    // =========================

    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: {
        guest: true,
      },
    },

    {
      path: '/register',
      name: 'register',
      component: RegisterView,
      meta: {
        guest: true,
      },
    },

    // =========================
    // AUTHENTICATED APPLICATION
    // =========================

    {
      path: '/',
      component: AppLayout,
      meta: {
        requiresAuth: true,
      },

      children: [
        {
          path: 'dashboard',
          name: 'dashboard',
          component: DashboardView,
        },

        {
          path: 'documents',
          name: 'documents',
          component: DocumentsView,
        },

        {
          path: 'ask-ai',
          name: 'ask-ai',
          component: AskAIView,
        },
      ],
    },
  ],
})

// =========================
// AUTHENTICATION GUARD
// =========================

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  /*
   * If a token exists but user information
   * hasn't been loaded, verify the session
   * with the Laravel backend.
   */
  if (auth.token && !auth.user) {
    await auth.fetchUser()
  }

  /*
   * Check whether the route requires
   * authentication.
   */
  const requiresAuth = to.matched.some(
    (record) => record.meta.requiresAuth,
  )

  /*
   * Redirect unauthenticated users
   * to the login page.
   */
  if (requiresAuth && !auth.isAuthenticated) {
    return {
      name: 'login',
      query: {
        redirect: to.fullPath,
      },
    }
  }

  /*
   * Prevent authenticated users from
   * accessing login and register pages.
   */
  if (to.meta.guest && auth.isAuthenticated) {
    return {
      name: 'dashboard',
    }
  }
})

export default router