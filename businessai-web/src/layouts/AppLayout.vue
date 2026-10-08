<script setup>
import { RouterView } from 'vue-router'
import AppSidebar from '@/components/AppSidebar.vue'
</script>

<template>
  <div class="app-layout">
    <AppSidebar />

    <main class="app-content">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.app-layout {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 260px minmax(0, 1fr);
  background: #f8fafc;
}

.app-content {
  min-width: 0;
  min-height: 100vh;
  overflow-x: hidden;
}

@media (max-width: 1000px) {
  .app-layout {
    grid-template-columns: 210px minmax(0, 1fr);
  }
}

@media (max-width: 760px) {
  .app-layout {
    display: block;
  }

  .app-content {
    min-height: auto;
  }
}
</style>