<script setup lang="ts">
/**
 * The centred card every sign-in, sign-up and verification screen sits in.
 * Put the form in the default slot and the bottom row (secondary link + main button) in `footer`.
 */
defineProps<{ title?: string }>()
</script>

<template>
  <div class="card w-full max-w-md p-6 sm:p-8">
    <RouterLink :to="{ name: 'login' }" class="mb-6 inline-block">
      <img class="h-20 w-auto" src="@/assets/img/logo/logo.png" alt="Home" />
    </RouterLink>

    <h1 v-if="title" class="mb-4 text-xl font-bold text-slate-900">{{ title }}</h1>

    <slot />

    <div v-if="$slots.footer" class="mt-6 flex items-center justify-between gap-4">
      <slot name="footer" />
    </div>
  </div>
</template>
