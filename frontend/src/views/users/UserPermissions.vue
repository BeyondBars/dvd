<template>
  <RouterLink :to="{ name: 'users.details', params: { id } }" class="link-muted mb-3 inline-flex items-center gap-1.5">
    <FontAwesomeIcon icon="chevron-left" class="w-3" />
    Back to user
  </RouterLink>

  <PageHeader title="Permissions">
    <template #description>
      <template v-if="usersStore.user">{{ usersStore.user.name || usersStore.user.email }} · </template>
      Role: {{ permissions?.role ?? 'None' }}. The role sets the defaults; changes here apply to this user only.
    </template>
  </PageHeader>

  <div v-if="permissions" class="space-y-4">
    <div v-for="module in permissions.modules" :key="module.key" class="card p-6">
      <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-base font-semibold text-slate-900">{{ module.title }}</h2>
          <p class="mt-0.5 text-sm text-slate-500">{{ module.description }}</p>
        </div>
        <span
          class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
          :class="draft[module.view] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
        >
          <FontAwesomeIcon :icon="draft[module.view] ? 'eye' : 'eye-slash'" />
          {{ draft[module.view] ? 'Shown in sidebar' : 'Hidden from sidebar' }}
        </span>
      </div>

      <ul class="divide-y divide-slate-100">
        <li v-for="ability in module.abilities" :key="ability.name" class="flex items-center justify-between gap-4 py-3">
          <div class="min-w-0">
            <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-slate-900">
              {{ ability.label }}
              <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="sourceBadge(ability).class">
                {{ sourceBadge(ability).label }}
              </span>
            </p>
            <p class="text-sm text-slate-500">{{ ability.description }}</p>
          </div>

          <button
            type="button"
            role="switch"
            :aria-checked="draft[ability.name]"
            :aria-label="`${module.title}: ${ability.label}`"
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
            :class="draft[ability.name] ? 'bg-brand-600' : 'bg-slate-300'"
            @click="toggle(ability.name)"
          >
            <span
              class="absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow-sm transition"
              :class="{ 'translate-x-5': draft[ability.name] }"
            />
          </button>
        </li>
      </ul>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <button type="button" class="btn btn-secondary" :disabled="saving || !hasOverrides" @click="resetToRole">
        Reset to role defaults
      </button>
      <div class="flex gap-3">
        <button type="button" class="btn btn-secondary" :disabled="!dirty || saving" @click="discard">Discard</button>
        <button type="button" class="btn btn-primary" :disabled="!dirty || saving" @click="save">Save permissions</button>
      </div>
    </div>

    <p class="text-xs text-slate-500">The user sees the changes the next time they load the app.</p>
  </div>
</template>

<script lang="ts" setup>
import PageHeader from '@/components/app/PageHeader.vue'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import { useUserStore } from '@/stores/users'
import confirm from '@/utils/alert'
import type { PermissionAbility, UserPermissions } from '@/types/permission'

const props = defineProps<{ id: string }>()

const usersStore = useUserStore()
const toast = useToast()

const permissions = ref<UserPermissions | null>(null)
const draft = ref<Record<string, boolean>>({})
const saving = ref(false)

const abilities = computed(() => permissions.value?.modules.flatMap((module) => module.abilities) ?? [])
const dirty = computed(() => abilities.value.some((ability) => draft.value[ability.name] !== ability.granted))
const hasOverrides = computed(() => abilities.value.some((ability) => ability.override !== null))

/** Where the draft value comes from compared with the role. */
const sourceBadge = (ability: PermissionAbility) => {
  const granted = draft.value[ability.name]

  if (granted === ability.role) {
    return { label: 'Role default', class: 'bg-slate-100 text-slate-600' }
  }

  return granted
    ? { label: 'Added for this user', class: 'bg-brand-100 text-brand-700' }
    : { label: 'Removed for this user', class: 'bg-amber-100 text-amber-700' }
}

const load = (data: UserPermissions) => {
  permissions.value = data
  draft.value = Object.fromEntries(data.modules.flatMap((module) => module.abilities).map((a) => [a.name, a.granted]))
}

// Managing needs viewing: turning "Manage" on turns "View" on, turning "View" off turns "Manage" off.
const toggle = (name: string) => {
  const granted = !draft.value[name]
  draft.value[name] = granted

  for (const ability of abilities.value) {
    if (granted && ability.name === name && ability.requires) draft.value[ability.requires] = true
    if (!granted && ability.requires === name) draft.value[ability.name] = false
  }
}

const discard = () => permissions.value && load(permissions.value)

const save = async () => {
  saving.value = true

  try {
    load(await usersStore.updatePermissions(props.id, { ...draft.value }))
    toast.success('Permissions saved.')
  } finally {
    saving.value = false
  }
}

const resetToRole = async () => {
  const confirmed = await confirm({
    title: 'Reset permissions?',
    text: "This removes every change made for this user, leaving just their role's permissions.",
    confirmButtonText: 'Reset',
  })
  if (!confirmed) return

  saving.value = true

  try {
    load(await usersStore.resetPermissions(props.id))
    toast.success('Permissions reset to the role defaults.')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  const [data] = await Promise.all([usersStore.fetchPermissions(props.id), usersStore.fetchUser(props.id)])
  load(data)
})

onUnmounted(() => usersStore.resetUser())
</script>
