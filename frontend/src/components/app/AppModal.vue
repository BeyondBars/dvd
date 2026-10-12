<script setup lang="ts">
/** A centred dialog over a dimmed page. Closes on the backdrop, the close button or Escape. */
import { onBeforeUnmount, onMounted } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

defineProps<{ title: string; wide?: boolean }>()
const emit = defineEmits<{ close: [] }>()

const onKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') emit('close')
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center" @click.self="emit('close')">
    <div class="card max-h-[90vh] w-full overflow-y-auto p-6"
      :class="wide ? 'max-w-4xl' : 'max-w-lg'" role="dialog" aria-modal="true" :aria-label="title">
      <div class="mb-4 flex items-center justify-between gap-4">
        <h2 class="text-lg font-semibold text-slate-900">{{ title }}</h2>
        <button type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100" aria-label="Close" @click="emit('close')">
          <FontAwesomeIcon icon="xmark" />
        </button>
      </div>
      <slot />
    </div>
  </div>
</template>
