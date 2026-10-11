import { defineStore } from 'pinia'
import { http } from '@/utils/http'
import type { CategoryPayload, ItemPayload, MenuCategory, MenuItem } from '@/types/menu'

/**
 * The whole menu (every category with its items), kept in sync with the API after each change.
 */
export const useMenuStore = defineStore('menu', {
  state: () => ({
    categories: [] as MenuCategory[],
    isLoading: false,
  }),

  actions: {
    async fetchMenu() {
      this.isLoading = true

      try {
        const { data } = await http.get<{ data: MenuCategory[] }>('menu/categories')
        this.categories = data.data
      } finally {
        this.isLoading = false
      }
    },

    async createCategory(payload: CategoryPayload) {
      const { data } = await http.post<{ data: MenuCategory }>('menu/categories', payload)
      this.categories.push(data.data)
    },

    async updateCategory(id: number, payload: CategoryPayload) {
      const { data } = await http.put<{ data: MenuCategory }>(`menu/categories/${id}`, payload)
      this.replaceCategory(data.data)
    },

    async deleteCategory(id: number) {
      await http.delete(`menu/categories/${id}`)
      this.categories = this.categories.filter((category) => category.id !== id)
    },

    /** Move a category one place up (-1) or down (+1). */
    async moveCategory(id: number, offset: -1 | 1) {
      const ids = move(this.categories.map((category) => category.id), id, offset)
      if (!ids) return

      const { data } = await http.put<{ data: MenuCategory[] }>('menu/categories/sort', { ids })
      this.categories = data.data
    },

    async createItem(payload: ItemPayload) {
      const { data } = await http.post<{ data: MenuItem }>('menu/items', payload)
      this.findCategory(data.data.menu_category_id)?.items.push(data.data)
    },

    async updateItem(item: MenuItem, payload: ItemPayload) {
      const { data } = await http.put<{ data: MenuItem }>(`menu/items/${item.id}`, payload)
      const updated = data.data

      if (updated.menu_category_id === item.menu_category_id) {
        const items = this.findCategory(item.menu_category_id)?.items ?? []
        items.splice(items.findIndex((other) => other.id === item.id), 1, updated)
        return
      }

      // Moved to another category: it goes to the end there.
      this.removeItem(item)
      this.findCategory(updated.menu_category_id)?.items.push(updated)
    },

    async deleteItem(item: MenuItem) {
      await http.delete(`menu/items/${item.id}`)
      this.removeItem(item)
    },

    /** Move an item one place up (-1) or down (+1) within its category. */
    async moveItem(item: MenuItem, offset: -1 | 1) {
      const category = this.findCategory(item.menu_category_id)
      const ids = category && move(category.items.map((other) => other.id), item.id, offset)
      if (!category || !ids) return

      const { data } = await http.put<{ data: MenuItem[] }>(`menu/categories/${category.id}/items/sort`, { ids })
      category.items = data.data
    },

    findCategory(id: number) {
      return this.categories.find((category) => category.id === id)
    },

    replaceCategory(category: MenuCategory) {
      const index = this.categories.findIndex((other) => other.id === category.id)
      if (index !== -1) this.categories.splice(index, 1, category)
    },

    removeItem(item: MenuItem) {
      const category = this.findCategory(item.menu_category_id)
      if (category) category.items = category.items.filter((other) => other.id !== item.id)
    },
  },
})

/** The ids with `id` moved by `offset`, or null when it can't move that way. */
const move = (ids: number[], id: number, offset: -1 | 1): number[] | null => {
  const from = ids.indexOf(id)
  const to = from + offset
  if (from === -1 || to < 0 || to >= ids.length) return null

  const result = [...ids]
  ;[result[from], result[to]] = [result[to], result[from]]
  return result
}
