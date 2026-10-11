<template>
  <!-- Fixed-height shell: the main area scrolls (the users list's infinite scroll listens to it). -->
  <div class="flex h-dvh">
    <AppSidebar :open="sidebarOpen" @close="sidebarOpen = false" />

    <div class="flex min-w-0 flex-1 flex-col">
      <!-- Mobile top bar -->
      <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 lg:hidden">
        <button type="button" class="rounded-md p-2 text-slate-600 hover:bg-slate-100" aria-label="Open menu" @click="sidebarOpen = true">
          <FontAwesomeIcon icon="bars" />
        </button>
        <img class="h-10 w-auto" src="@/assets/img/logo/logo.png" alt="Home" />
      </header>

      <main id="page-content-wrapper" class="flex-1 overflow-y-auto px-4 py-6 sm:px-6 lg:px-8">
        <Suspense>
          <RouterView />
        </Suspense>
      </main>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import AppSidebar from '@/components/app/AppSideBar.vue'

const sidebarOpen = ref(false)
const route = useRoute()

// Close the mobile menu after navigating.
watch(() => route.fullPath, () => (sidebarOpen.value = false))
</script>
