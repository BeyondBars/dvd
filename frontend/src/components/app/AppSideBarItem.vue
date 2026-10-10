<template>
  <RouterLink v-if="to" :to="to" :class="[base, isActive ? active : idle]">
    <FontAwesomeIcon :icon="icon" class="w-4" />
    <span><slot /></span>
  </RouterLink>

  <button v-else type="button" :class="[base, idle, 'w-full']" @click="emit('click')">
    <FontAwesomeIcon :icon="icon" class="w-4" />
    <span><slot /></span>
  </button>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { useRoute, useRouter, type RouteLocationRaw } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

const props = defineProps<{
  icon: string
  to?: RouteLocationRaw
}>()
const emit = defineEmits<{ click: [] }>()

const base = 'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition'
const active = 'bg-brand-600 text-white'
const idle = 'hover:bg-sidebar-text/10'

const currentRoute = useRoute()
const router = useRouter()

// Active for the item's own page and any page beneath it (e.g. /users/12).
const isActive = computed(() => {
  if (!props.to) return false
  const path = router.resolve(props.to).path
  return currentRoute.path === path || currentRoute.path.startsWith(`${path}/`)
})
</script>
