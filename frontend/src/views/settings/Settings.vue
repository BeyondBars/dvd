<template>
  <PageHeader title="Settings" description="App-wide appearance and the roles users can hold." />

  <div class="card mb-6 p-6">
    <div class="mb-4 flex items-center gap-2">
      <FontAwesomeIcon icon="palette" class="text-brand-600" />
      <h2 class="text-base font-semibold text-slate-900">Appearance</h2>
    </div>
    <p v-if="canManage" class="mb-6 text-sm text-slate-500">
      Changes preview straight away and apply to everyone once saved, including the sign-in screens.
    </p>
    <p v-else class="mb-6 flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-600">
      <FontAwesomeIcon icon="lock" />
      View only. You don't have permission to change the appearance.
    </p>

    <form novalidate @submit.prevent="save">
      <!-- A disabled fieldset disables every input and button inside it. -->
      <fieldset :disabled="!canManage">
      <div class="mb-4 max-w-md">
        <label class="form-label" for="app_name">App name</label>
        <input id="app_name" v-model="form.app_name" class="form-input" :class="{ 'is-invalid': errors.app_name }" type="text" maxlength="60" />
        <span v-if="errors.app_name" class="form-error">{{ errors.app_name[0] }}</span>
      </div>

      <div class="grid gap-x-6 sm:grid-cols-2 xl:grid-cols-4">
        <div v-for="field in colorFields" :key="field.key" class="mb-4">
          <label class="form-label" :for="field.key">{{ field.label }}</label>
          <div class="flex items-center gap-2">
            <input
              v-model="form[field.key]"
              type="color"
              class="h-10 w-12 shrink-0 cursor-pointer rounded-lg border border-slate-300 bg-white p-1 disabled:cursor-not-allowed disabled:opacity-60"
              :aria-label="`${field.label} picker`"
            />
            <input
              :id="field.key"
              v-model="form[field.key]"
              class="form-input font-mono uppercase"
              :class="{ 'is-invalid': errors[field.key] }"
              type="text"
              maxlength="7"
            />
          </div>
          <span v-if="errors[field.key]" class="form-error">{{ errors[field.key]?.[0] }}</span>
        </div>
      </div>

      <div class="grid gap-x-6 sm:grid-cols-2">
        <div class="mb-4">
          <label class="form-label" for="font_family">Font</label>
          <select id="font_family" v-model="form.font_family" class="form-input">
            <option v-for="(font, value) in FONTS" :key="value" :value="value">{{ font.label }}</option>
          </select>
        </div>

        <fieldset class="mb-4">
          <legend class="form-label">Corner radius</legend>
          <div class="flex gap-1 rounded-lg bg-slate-200/60 p-1">
            <button
              v-for="(radius, value) in RADII"
              :key="value"
              type="button"
              class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition disabled:cursor-not-allowed"
              :class="form.border_radius === value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
              @click="form.border_radius = value"
            >
              {{ radius.label }}
            </button>
          </div>
        </fieldset>
      </div>

      </fieldset>

      <!-- Preview of the shared building blocks with the current values -->
      <div class="mb-6 flex flex-wrap items-center gap-3 rounded-xl border border-dashed border-slate-300 p-4">
        <button type="button" class="btn btn-primary">Primary button</button>
        <button type="button" class="btn btn-secondary">Secondary</button>
        <a href="#" class="link text-sm" @click.prevent>A link</a>
        <span class="rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-medium text-brand-700">Badge</span>
      </div>

      <div v-if="canManage" class="flex flex-wrap items-center justify-between gap-3">
        <button type="button" class="btn btn-secondary" :disabled="saving" @click="resetToDefaults">Reset to defaults</button>
        <div class="flex gap-3">
          <button type="button" class="btn btn-secondary" :disabled="!dirty || saving" @click="discard">Discard</button>
          <button type="submit" class="btn btn-primary" :disabled="!dirty || saving">Save changes</button>
        </div>
      </div>
    </form>
  </div>

  <div class="card overflow-hidden">
    <h2 class="px-4 pt-4 pb-2 text-base font-semibold text-slate-900">Roles</h2>
    <p class="px-4 pb-3 text-sm text-slate-500">Roles are defined in code and synced with php artisan abilities:refresh.</p>
    <table class="min-w-full divide-y divide-slate-200 text-sm">
      <thead class="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
        <tr>
          <th class="px-4 py-3">Role</th>
          <th class="px-4 py-3">Description</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <tr v-for="role in rolesStore.roles" :key="role.id">
          <td class="px-4 py-3 font-medium whitespace-nowrap text-slate-900">{{ role.title }}</td>
          <td class="px-4 py-3 text-slate-600">{{ role.description }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script lang="ts" setup>
import PageHeader from '@/components/app/PageHeader.vue'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { isAxiosError } from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import { useRoleStore } from '@/stores/roles'
import { FONTS, RADII, useSettingsStore, type AppSettings } from '@/stores/settings'
import { useLoggedInUser } from '@/composables/use-logged-in-user'
import confirm from '@/utils/alert'

type ColorKey = 'primary_color' | 'sidebar_background' | 'sidebar_text' | 'page_background'

const colorFields: { key: ColorKey; label: string }[] = [
  { key: 'primary_color', label: 'Primary colour' },
  { key: 'page_background', label: 'Page background' },
  { key: 'sidebar_background', label: 'Sidebar background' },
  { key: 'sidebar_text', label: 'Sidebar text' },
]

const rolesStore = useRoleStore()
const settingsStore = useSettingsStore()
const { can } = useLoggedInUser()
const toast = useToast()

const canManage = computed(() => can('manage settings'))

const form = reactive<AppSettings>({ ...settingsStore.settings })
const errors = ref<Partial<Record<keyof AppSettings, string[]>>>({})
const saving = ref(false)

const dirty = computed(() =>
  (Object.keys(form) as (keyof AppSettings)[]).some((key) => form[key] !== settingsStore.settings[key])
)

const isHex = (value: string) => /^#[0-9a-fA-F]{6}$/.test(value)

// Live preview: only push complete colours, so typing a hex code doesn't flicker.
watch(form, () => {
  const preview = { ...form }
  for (const { key } of colorFields) {
    if (!isHex(preview[key])) preview[key] = settingsStore.settings[key]
  }
  settingsStore.apply(preview)
})

// Pick up the saved settings if they arrive after the page has opened.
watch(
  () => settingsStore.settings,
  (settings) => {
    if (!dirty.value) Object.assign(form, settings)
  }
)

const discard = () => {
  Object.assign(form, settingsStore.settings)
  errors.value = {}
}

const handleError = (error: unknown) => {
  if (isAxiosError(error) && error.response?.data?.errors) {
    errors.value = error.response.data.errors
    return
  }

  throw error
}

const save = async () => {
  if (!canManage.value) return
  saving.value = true
  errors.value = {}

  try {
    await settingsStore.updateSettings({ ...form })
    Object.assign(form, settingsStore.settings)
    toast.success('Settings saved.')
  } catch (error) {
    handleError(error)
  } finally {
    saving.value = false
  }
}

const resetToDefaults = async () => {
  const confirmed = await confirm({
    title: 'Reset appearance?',
    text: 'This restores the default colours, font and app name for everyone.',
    confirmButtonText: 'Reset',
  })
  if (!confirmed) return

  saving.value = true

  try {
    await settingsStore.resetSettings()
    discard()
    toast.success('Settings reset to defaults.')
  } catch (error) {
    handleError(error)
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  rolesStore.fetchRoles()
})

// Leaving with unsaved changes drops the preview.
onBeforeUnmount(() => settingsStore.apply())
</script>
