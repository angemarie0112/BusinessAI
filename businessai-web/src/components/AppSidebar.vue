<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

// =========================
// USER
// =========================

const userInitial = computed(() => {
  return auth.user?.name?.charAt(0).toUpperCase() || 'U'
})

// =========================
// ACTIVE NAVIGATION
// =========================

const isActive = (path) => {
  return route.path === path
}

// =========================
// LOGOUT
// =========================

const handleLogout = async () => {
  await auth.logout()

  router.push('/login')
}
</script>

<template>
  <aside class="sidebar">

    <div>

      <!-- =========================
           BRAND
      ========================== -->

      <div class="brand">
        <div class="brand-mark">
          B
        </div>

        <span>
          BusinessAI
        </span>
      </div>

      <!-- =========================
           NAVIGATION
      ========================== -->

      <nav class="navigation">

        <!-- Dashboard -->

        <RouterLink
          class="nav-item"
          :class="{ active: isActive('/dashboard') }"
          to="/dashboard"
        >
          <span class="nav-icon">
            <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
            >
              <rect
                x="3"
                y="3"
                width="7"
                height="7"
                rx="1"
              />

              <rect
                x="14"
                y="3"
                width="7"
                height="7"
                rx="1"
              />

              <rect
                x="3"
                y="14"
                width="7"
                height="7"
                rx="1"
              />

              <rect
                x="14"
                y="14"
                width="7"
                height="7"
                rx="1"
              />
            </svg>
          </span>

          Dashboard
        </RouterLink>

        <!-- Documents -->

        <RouterLink
          class="nav-item"
          :class="{ active: isActive('/documents') }"
          to="/documents"
        >
          <span class="nav-icon">
            <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
            >
              <path d="M6 2h8l4 4v16H6z" />
              <path d="M14 2v5h5" />
              <path d="M9 13h6" />
              <path d="M9 17h6" />
            </svg>
          </span>

          Documents
        </RouterLink>

        <!-- Ask AI -->

        <RouterLink
          class="nav-item"
          :class="{ active: isActive('/ask-ai') }"
          to="/ask-ai"
        >
          <span class="nav-icon">
            <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
            >
              <path
                d="M12 3a7 7 0 0 0-4 12.74V21l4-2 4 2v-5.26A7 7 0 0 0 12 3Z"
              />

              <path d="M9 10h.01" />
              <path d="M15 10h.01" />

              <path
                d="M9.5 13a4 4 0 0 0 5 0"
              />
            </svg>
          </span>

          Ask AI
        </RouterLink>

      </nav>

    </div>

    <!-- =========================
         USER
    ========================== -->

    <div class="sidebar-user">

      <div class="user-info">

        <div class="user-avatar">
          {{ userInitial }}
        </div>

        <div class="user-details">

          <strong>
            {{ auth.user?.name }}
          </strong>

          <span>
            {{ auth.user?.email }}
          </span>

        </div>

      </div>

      <button
        type="button"
        class="logout-button"
        @click="handleLogout"
      >
        Log out
      </button>

    </div>

  </aside>
</template>

<style scoped>

/* =========================
   SIDEBAR
========================= */

.sidebar {
  position: sticky;
  top: 0;
  width: 100%;
  height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-sizing: border-box;
  padding: 28px 20px;
  border-right: 1px solid #e2e8f0;
  background: #ffffff;
}

/* =========================
   BRAND
========================= */

.brand {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 0 10px;
  font-size: 19px;
  font-weight: 750;
  letter-spacing: -0.03em;
}

.brand-mark {
  width: 34px;
  height: 34px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border-radius: 9px;
  background: #2563eb;
  color: white;
  font-size: 16px;
}

/* =========================
   NAVIGATION
========================= */

.navigation {
  display: grid;
  gap: 6px;
  margin-top: 44px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 12px;
  border-radius: 9px;
  color: #64748b;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  transition:
    background 0.2s,
    color 0.2s;
}

.nav-item:hover {
  background: #f8fafc;
  color: #0f172a;
}

.nav-item.active {
  background: #eff6ff;
  color: #2563eb;
}

.nav-icon {
  width: 19px;
  height: 19px;
  flex-shrink: 0;
}

.nav-icon svg {
  width: 100%;
  height: 100%;
}

/* =========================
   USER
========================= */

.sidebar-user {
  padding-top: 20px;
  border-top: 1px solid #e2e8f0;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 11px;
}

.user-avatar {
  width: 38px;
  height: 38px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #dbeafe;
  color: #2563eb;
  font-size: 14px;
  font-weight: 700;
}

.user-details {
  min-width: 0;
  display: grid;
  gap: 2px;
}

.user-details strong {
  overflow: hidden;
  font-size: 13px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-details span {
  overflow: hidden;
  color: #94a3b8;
  font-size: 11px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* =========================
   LOGOUT
========================= */

.logout-button {
  width: 100%;
  margin-top: 14px;
  padding: 9px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  color: #64748b;
  font: inherit;
  font-size: 12px;
  cursor: pointer;
  transition:
    border-color 0.2s,
    color 0.2s,
    background 0.2s;
}

.logout-button:hover {
  border-color: #cbd5e1;
  background: #f8fafc;
  color: #0f172a;
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 760px) {
  .sidebar {
    position: static;
    width: 100%;
    height: auto;
  }

  .navigation,
  .sidebar-user {
    display: none;
  }
}
</style>