import { defineStore } from 'pinia'
import { http } from '@/utils/http'
import type { OrderingSettings } from '@/types/order'

/** Online ordering settings: on/off, opening hours, prep time and alert contacts. */
export const useOrderingStore = defineStore('ordering', {
  state: () => ({
    settings: null as OrderingSettings | null,
  }),

  actions: {
    async fetchSettings() {
      const { data } = await http.get<{ data: OrderingSettings }>('ordering-settings')
      this.settings = data.data
    },

    async updateSettings(payload: Partial<OrderingSettings>) {
      const { data } = await http.put<{ data: OrderingSettings }>('ordering-settings', payload)
      this.settings = data.data
    },
  },
})
