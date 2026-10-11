<template>
  <PageHeader :title="`Welcome back${loggedInUser.first_name ? `, ${loggedInUser.first_name}` : ''}`" />

  <!-- Placeholder: apps built on this starter replace this page with their own dashboard. -->
  <div class="card p-6">
    <h2 class="text-base font-semibold text-slate-900">You're signed in</h2>
    <p class="mt-1 text-sm text-slate-600">
      This is the starter dashboard. Replace it with your app's own content.
    </p>
    <div class="mt-4 flex flex-wrap gap-3">
      <RouterLink :to="{ name: 'account' }" class="btn btn-secondary">My account</RouterLink>
      <RouterLink v-if="can('read users')" :to="{ name: 'users' }" class="btn btn-secondary">Manage users</RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import PageHeader from '@/components/app/PageHeader.vue'
import { useLoggedInUser } from '@/composables/use-logged-in-user'

const { loggedInUser, can } = useLoggedInUser()
</script>
