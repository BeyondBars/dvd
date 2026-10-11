<template>
  <!-- Backdrop (mobile only) -->
  <div v-if="open" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden" @click="emit('close')" />

  <aside
    class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-sidebar text-sidebar-text transition-transform lg:static lg:translate-x-0"
    :class="open ? 'translate-x-0' : '-translate-x-full'"
  >
    <div class="flex items-center justify-between px-5 py-5">
      <img class="h-16 w-auto rounded-xl bg-page p-1" :src="settingsStore.sidebarIsDark ? whiteLogo : logo" :alt="settingsStore.applied.app_name" />
      <button type="button" class="rounded-md p-2 hover:bg-sidebar-text/10 lg:hidden" aria-label="Close menu" @click="emit('close')">
        <FontAwesomeIcon icon="xmark" />
      </button>
    </div>

    <nav class="flex flex-1 flex-col gap-1 px-3">
      <SidebarItem :to="{ name: 'dashboard' }" icon="house">Dashboard</SidebarItem>
      <!-- Modules only show for users with their "view" permission (set per user under Users > Permissions). -->
      <SidebarItem v-if="can('read orders') || can('manage orders')" :to="{ name: 'orders' }" icon="receipt">Orders</SidebarItem>
      <SidebarItem v-if="can('read menu') || can('manage menu')" :to="{ name: 'menu' }" icon="utensils">Menu</SidebarItem>
      <SidebarItem v-if="can('read users')" :to="{ name: 'users' }" icon="users">User Management</SidebarItem>
      <SidebarItem v-if="can('read settings') || can('manage settings')" :to="{ name: 'settings' }" icon="gear">
        Settings
      </SidebarItem>
      <SidebarItem :to="{ name: 'account' }" icon="circle-user">My Account</SidebarItem>

      <div class="mt-auto mb-4">
        <SidebarItem icon="right-from-bracket" @click="logoutUser">Logout</SidebarItem>
      </div>
    </nav>
  </aside>
</template>

<script lang="ts" setup>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import { isAxiosError } from 'axios'
import SidebarItem from './AppSideBarItem.vue'
import { useLogout } from '@/composables/use-logout'
import { useSettingsStore } from '@/stores/settings'
import { useLoggedInUser } from '@/composables/use-logged-in-user'
import logo from '@/assets/img/logo/logo.png'
import whiteLogo from '@/assets/img/logo/white-logo.png'

defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: [] }>()

const toast = useToast()
const { logout } = useLogout()
const settingsStore = useSettingsStore()
const { can } = useLoggedInUser()

const logoutUser = async () => {
  try {
    await logout()
  } catch (error) {
    if (isAxiosError(error) && error.response?.data?.message) {
      toast.error(error.response.data.message)
      return
    }

    throw error
  }
}
</script>
