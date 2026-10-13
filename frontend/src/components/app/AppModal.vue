<script setup lang="ts">
/**
 * A centred dialog over a dimmed page (a bottom sheet on phones), with a title bar, a scrolling body
 * and an optional footer slot. Closes on the backdrop, the close button or Escape.
 */
import { onBeforeUnmount, onMounted } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

withDefaults(defineProps<{ title: string; description?: string; size?: 'md' | 'lg' | 'xl' }>(), { size: 'lg' })
const emit = defineEmits<{ close: [] }>()

const widths = { md: 'max-w-md', lg: 'max-w-lg', xl: 'max-w-3xl' }

const onKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') emit('close')
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center" @click.self="emit('close')">
      <div
        role="dialog"
        aria-modal="true"
        :aria-label="title"
        class="card flex max-h-[90vh] w-full flex-col overflow-hidden"
        :class="widths[size]"
      >
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
          <div class="min-w-0">
            <h2 class="text-lg font-semibold text-slate-900">{{ title }}</h2>
            <p v-if="description" class="mt-0.5 text-sm text-slate-500">{{ description }}</p>
          </div>
          <button type="button" class="-mr-1 rounded-md p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Close" @click="emit('close')">
            <FontAwesomeIcon icon="xmark" />
          </button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto p-5">
          <slot />
        </div>
        <div v-if="$slots.footer" class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>
