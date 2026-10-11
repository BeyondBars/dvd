<template>
  <PageHeader title="Menu" description="What customers see and can order on the website.">
    <button v-if="canManage" type="button" class="btn btn-primary" @click="openCategory()">
      <FontAwesomeIcon icon="plus" />
      Add category
    </button>
  </PageHeader>

  <p v-if="!canManage" class="mb-6 flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-600">
    <FontAwesomeIcon icon="lock" />
    View only. You don't have permission to change the menu.
  </p>

  <p v-if="menuStore.isLoading && !menuStore.categories.length" class="text-sm text-slate-500">Loading the menu...</p>
  <div v-else-if="!menuStore.categories.length" class="card p-6 text-sm text-slate-500">
    The menu is empty. {{ canManage ? 'Add a category to get started.' : '' }}
  </div>

  <section v-for="(category, index) in menuStore.categories" :key="category.id" class="card mb-6 overflow-hidden" :data-category="category.id">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-4 py-3">
      <div class="min-w-0">
        <h2 class="flex items-center gap-2 text-base font-semibold text-slate-900">
          {{ category.name }}
          <span v-if="!category.is_visible" class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600">
            Hidden from website
          </span>
        </h2>
        <p v-if="category.description" class="mt-0.5 text-sm text-slate-500">{{ category.description }}</p>
      </div>
      <div v-if="canManage" class="flex shrink-0 items-center gap-1">
        <button type="button" class="icon-btn" :disabled="index === 0" aria-label="Move category up" @click="run(() => menuStore.moveCategory(category.id, -1))">
          <FontAwesomeIcon icon="arrow-up" />
        </button>
        <button
          type="button"
          class="icon-btn"
          :disabled="index === menuStore.categories.length - 1"
          aria-label="Move category down"
          @click="run(() => menuStore.moveCategory(category.id, 1))"
        >
          <FontAwesomeIcon icon="arrow-down" />
        </button>
        <button type="button" class="icon-btn" aria-label="Edit category" @click="openCategory(category)">
          <FontAwesomeIcon icon="pen" />
        </button>
        <button type="button" class="icon-btn hover:text-red-600" aria-label="Delete category" @click="removeCategory(category)">
          <FontAwesomeIcon icon="trash" />
        </button>
        <button type="button" class="btn btn-secondary ml-2 px-3 py-1.5" @click="openItem(category)">
          <FontAwesomeIcon icon="plus" />
          Item
        </button>
      </div>
    </header>

    <p v-if="!category.items.length" class="px-4 py-3 text-sm text-slate-500">No items yet.</p>
    <ul v-else class="divide-y divide-slate-100">
      <li v-for="(item, itemIndex) in category.items" :key="item.id" class="flex flex-wrap items-center gap-3 px-4 py-3" :data-item="item.id">
        <div class="min-w-0 flex-1">
          <p class="font-medium text-slate-900" :class="{ 'text-slate-400 line-through': !item.is_available }">{{ item.name }}</p>
          <p v-if="item.description" class="text-sm text-slate-500">{{ item.description }}</p>
        </div>
        <span class="w-20 text-right font-semibold whitespace-nowrap text-slate-900">{{ formatPrice(item.price_cents) }}</span>

        <button
          type="button"
          class="w-24 rounded-full px-2.5 py-1 text-xs font-semibold transition disabled:cursor-default"
          :class="item.is_available ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
          :disabled="!canManage"
          :title="canManage ? 'Click to change' : undefined"
          data-test="availability"
          @click="run(() => menuStore.updateItem(item, { is_available: !item.is_available }))"
        >
          {{ item.is_available ? 'Available' : 'Sold out' }}
        </button>

        <div v-if="canManage" class="flex items-center gap-1">
          <button type="button" class="icon-btn" :disabled="itemIndex === 0" aria-label="Move item up" @click="run(() => menuStore.moveItem(item, -1))">
            <FontAwesomeIcon icon="arrow-up" />
          </button>
          <button
            type="button"
            class="icon-btn"
            :disabled="itemIndex === category.items.length - 1"
            aria-label="Move item down"
            @click="run(() => menuStore.moveItem(item, 1))"
          >
            <FontAwesomeIcon icon="arrow-down" />
          </button>
          <button type="button" class="icon-btn" aria-label="Edit item" @click="openItem(category, item)">
            <FontAwesomeIcon icon="pen" />
          </button>
          <button type="button" class="icon-btn hover:text-red-600" aria-label="Delete item" @click="removeItem(item)">
            <FontAwesomeIcon icon="trash" />
          </button>
        </div>
      </li>
    </ul>
  </section>

  <!-- Add / edit a category -->
  <AppModal v-if="categoryForm" :title="categoryForm.id ? 'Edit category' : 'Add category'" @close="categoryForm = null">
    <form novalidate @submit.prevent="saveCategory">
      <div class="mb-4">
        <label class="form-label" for="category_name">Name</label>
        <input id="category_name" v-model="categoryForm.name" class="form-input" :class="{ 'is-invalid': errors.name }" type="text" maxlength="100" />
        <span v-if="errors.name" class="form-error">{{ errors.name[0] }}</span>
      </div>
      <div class="mb-4">
        <label class="form-label" for="category_description">Description (optional)</label>
        <textarea id="category_description" v-model="categoryForm.description" class="form-input" rows="2" maxlength="500" />
        <span v-if="errors.description" class="form-error">{{ errors.description[0] }}</span>
      </div>
      <label class="mb-6 flex items-center gap-2 text-sm text-slate-700">
        <input v-model="categoryForm.is_visible" type="checkbox" class="form-check" />
        Show on the website
      </label>
      <div class="flex justify-end gap-3">
        <button type="button" class="btn btn-secondary" @click="categoryForm = null">Cancel</button>
        <button type="submit" class="btn btn-primary" :disabled="saving">Save</button>
      </div>
    </form>
  </AppModal>

  <!-- Add / edit an item -->
  <AppModal v-if="itemForm" :title="itemForm.item ? 'Edit item' : 'Add item'" @close="itemForm = null">
    <form novalidate @submit.prevent="saveItem">
      <div class="mb-4">
        <label class="form-label" for="item_name">Name</label>
        <input id="item_name" v-model="itemForm.name" class="form-input" :class="{ 'is-invalid': errors.name }" type="text" maxlength="100" />
        <span v-if="errors.name" class="form-error">{{ errors.name[0] }}</span>
      </div>
      <div class="mb-4">
        <label class="form-label" for="item_description">Description (optional)</label>
        <textarea id="item_description" v-model="itemForm.description" class="form-input" rows="2" maxlength="500" />
        <span v-if="errors.description" class="form-error">{{ errors.description[0] }}</span>
      </div>
      <div class="mb-4 grid gap-4 sm:grid-cols-2">
        <div>
          <label class="form-label" for="item_price">Price ($)</label>
          <input
            id="item_price"
            v-model="itemForm.price"
            class="form-input"
            :class="{ 'is-invalid': errors.price_cents }"
            type="text"
            inputmode="decimal"
            placeholder="12.99"
          />
          <span v-if="errors.price_cents" class="form-error">{{ errors.price_cents[0] }}</span>
        </div>
        <div>
          <label class="form-label" for="item_category">Category</label>
          <select id="item_category" v-model="itemForm.menu_category_id" class="form-input" :class="{ 'is-invalid': errors.menu_category_id }">
            <option v-for="category in menuStore.categories" :key="category.id" :value="category.id">{{ category.name }}</option>
          </select>
          <span v-if="errors.menu_category_id" class="form-error">{{ errors.menu_category_id[0] }}</span>
        </div>
      </div>
      <label class="mb-6 flex items-center gap-2 text-sm text-slate-700">
        <input v-model="itemForm.is_available" type="checkbox" class="form-check" />
        Available to order (untick when sold out)
      </label>
      <div class="flex justify-end gap-3">
        <button type="button" class="btn btn-secondary" @click="itemForm = null">Cancel</button>
        <button type="submit" class="btn btn-primary" :disabled="saving">Save</button>
      </div>
    </form>
  </AppModal>
</template>

<script lang="ts" setup>
import PageHeader from '@/components/app/PageHeader.vue'
import AppModal from '@/components/app/AppModal.vue'
import { computed, onMounted, ref } from 'vue'
import { isAxiosError } from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import { useMenuStore } from '@/stores/menu'
import { useLoggedInUser } from '@/composables/use-logged-in-user'
import { formatPrice, parsePrice } from '@/utils/money'
import confirm from '@/utils/alert'
import type { MenuCategory, MenuItem } from '@/types/menu'

type CategoryForm = { id: number | null; name: string; description: string; is_visible: boolean }
type ItemForm = {
  item: MenuItem | null
  menu_category_id: number
  name: string
  description: string
  price: string
  is_available: boolean
}

const menuStore = useMenuStore()
const { can } = useLoggedInUser()
const toast = useToast()

const canManage = computed(() => can('manage menu'))

const categoryForm = ref<CategoryForm | null>(null)
const itemForm = ref<ItemForm | null>(null)
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)

onMounted(() => {
  menuStore.fetchMenu().catch(() => toast.error("Couldn't load the menu."))
})

/** Run a change, showing a toast when it fails. Returns false on validation errors. */
const run = async (change: () => Promise<unknown>) => {
  saving.value = true
  errors.value = {}

  try {
    await change()
    return true
  } catch (error) {
    if (isAxiosError(error) && error.response?.status === 422) {
      errors.value = error.response.data.errors ?? {}
      return false
    }

    toast.error(isAxiosError(error) && error.response?.data?.message ? error.response.data.message : 'Something went wrong. Please try again.')
    return false
  } finally {
    saving.value = false
  }
}

const openCategory = (category?: MenuCategory) => {
  errors.value = {}
  categoryForm.value = {
    id: category?.id ?? null,
    name: category?.name ?? '',
    description: category?.description ?? '',
    is_visible: category?.is_visible ?? true,
  }
}

const saveCategory = async () => {
  const form = categoryForm.value
  if (!form) return

  const payload = { name: form.name, description: form.description || null, is_visible: form.is_visible }
  const saved = await run(() => (form.id ? menuStore.updateCategory(form.id, payload) : menuStore.createCategory(payload)))

  if (saved) {
    categoryForm.value = null
    toast.success('Category saved.')
  }
}

const removeCategory = async (category: MenuCategory) => {
  const confirmed = await confirm({
    title: `Delete ${category.name}?`,
    text: category.items.length
      ? `Its ${category.items.length} item(s) will be deleted too. This can't be undone.`
      : "This can't be undone.",
    confirmButtonText: 'Delete',
  })

  if (confirmed && (await run(() => menuStore.deleteCategory(category.id)))) {
    toast.success('Category deleted.')
  }
}

const openItem = (category: MenuCategory, item?: MenuItem) => {
  errors.value = {}
  itemForm.value = {
    item: item ?? null,
    menu_category_id: category.id,
    name: item?.name ?? '',
    description: item?.description ?? '',
    price: item ? (item.price_cents / 100).toFixed(2) : '',
    is_available: item?.is_available ?? true,
  }
}

const saveItem = async () => {
  const form = itemForm.value
  if (!form) return

  const price = parsePrice(form.price)
  if (price === null) {
    errors.value = { price_cents: ['Enter a price like 12.99.'] }
    return
  }

  const payload = {
    menu_category_id: form.menu_category_id,
    name: form.name,
    description: form.description || null,
    price_cents: price,
    is_available: form.is_available,
  }
  const saved = await run(() => (form.item ? menuStore.updateItem(form.item, payload) : menuStore.createItem(payload)))

  if (saved) {
    itemForm.value = null
    toast.success('Item saved.')
  }
}

const removeItem = async (item: MenuItem) => {
  const confirmed = await confirm({ title: `Delete ${item.name}?`, text: "This can't be undone.", confirmButtonText: 'Delete' })

  if (confirmed && (await run(() => menuStore.deleteItem(item)))) {
    toast.success('Item deleted.')
  }
}
</script>
