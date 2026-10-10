<template>
  <PageHeader :title="canEdit ? 'Edit user' : 'User details'">
    <RouterLink v-if="canManagePermissions" :to="{ name: 'users.permissions', params: { id: userId } }" class="btn btn-secondary">
      <FontAwesomeIcon icon="shield-halved" />
      Permissions
    </RouterLink>
  </PageHeader>

  <div class="card max-w-xl p-6">
    <p v-if="!canEdit" class="mb-4 flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-600">
      <FontAwesomeIcon icon="lock" />
      View only. You don't have permission to edit users.
    </p>

    <form novalidate @submit="onSubmit">
      <!-- A disabled fieldset disables every input inside it. -->
      <fieldset :disabled="!canEdit">
        <div class="grid gap-x-4 sm:grid-cols-2">
          <HTBFormField name="first_name" label="First name" type="text" />
          <HTBFormField name="last_name" label="Last name" type="text" />
        </div>
        <HTBPhoneInput name="mobile" label="Mobile" type="text" />

        <div class="mb-4">
          <label class="form-label" for="email">Email</label>
          <input id="email" v-model="email" class="form-input" type="email" disabled />
        </div>
      </fieldset>

      <div v-if="canEdit" class="flex justify-end">
        <button type="submit" class="btn btn-primary">Update details</button>
      </div>
    </form>
  </div>
</template>
<script lang="ts" setup>
import PageHeader from '@/components/app/PageHeader.vue'
import HTBFormField from '@/components/HTBFormField.vue'
import HTBPhoneInput from '@/components/HTBPhoneInput.vue'

import { computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toast-notification'
import { isAxiosError } from 'axios'

import { useUserStore } from '@/stores/users'
import { useLoggedInUser } from '@/composables/use-logged-in-user'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useForm, useField } from 'vee-validate'
import * as yup from 'yup'

// Toast instance
const toast = useToast()

// Router and Store
const route = useRoute()
const usersStore = useUserStore()
const { fetchUser, updateUser, resetUser } = usersStore

// Yup validation schema
const schema = yup.object({
  first_name: yup.string(),
  last_name: yup.string(),
  email: yup.string(),
  mobile: yup.string()
})

// Setup vee-validate form
const { handleSubmit, setErrors, setFieldValue } = useForm({
  validationSchema: schema,
  initialValues: {
    first_name: usersStore.user?.first_name ?? '',
    last_name: usersStore.user?.last_name ?? '',
    email: usersStore.user?.email ?? '',
    mobile: usersStore.user?.mobile ?? ''
  }
})

// Bind form fields (the others are bound by HTBFormField / HTBPhoneInput)
const { value: email } = useField<string>('email')

const userId = computed(() => String(route.params.id))

// Matches UserPolicy::update: your own account, or anyone's with "write users".
const { loggedInUser, can } = useLoggedInUser()
const canEdit = computed(() => String(loggedInUser.value.id) === userId.value || can('write users'))

// Super admins set per-user permissions, except their own.
const canManagePermissions = computed(
  () => loggedInUser.value.role?.name === 'super_admin' && String(loggedInUser.value.id) !== userId.value
)

// Load user details from store
const loadUser = async () => {
  await fetchUser(userId.value)
  const user = usersStore.user

  if (user) {
    setFieldValue('first_name', user.first_name ?? '')
    setFieldValue('last_name', user.last_name ?? '')
    setFieldValue('email', user.email)
    setFieldValue('mobile', user.mobile ?? '')
  }
}

// Run on component mount
onMounted(loadUser)

// Clean up when component unmounts
onUnmounted(() => {
  resetUser()
})

// Form submit handler
const onSubmit = handleSubmit(async (payload) => {
  if (!canEdit.value) return

  try {
    // The email can't be changed here (the field is read-only).
    await updateUser(userId.value, {
      first_name: payload.first_name,
      last_name: payload.last_name,
      mobile: payload.mobile || null
    })
    toast.success('Account details updated successfully!')
  } catch (error) {
    if (isAxiosError(error) && error.response?.data?.errors) {
      setErrors(error.response.data.errors)
      if (error.response.status === 429) return
    }

    throw error
  }
})
</script>
