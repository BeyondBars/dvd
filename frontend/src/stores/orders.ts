import { defineStore } from 'pinia'
import { http } from '@/utils/http'
import type { Order, OrderStatus } from '@/types/order'

type Page = { data: Order[]; meta: { current_page: number; last_page: number } }

/**
 * The orders board (open orders plus those finished today) and the order history.
 */
export const useOrderStore = defineStore('orders', {
  state: () => ({
    board: [] as Order[],
    boardLoaded: false,
    history: [] as Order[],
    historyPage: 0,
    historyHasMore: true,
  }),

  actions: {
    /** Refresh the board. Resolves to the orders that weren't on it before (none on the first load). */
    async fetchBoard(): Promise<Order[]> {
      const { data } = await http.get<{ data: Order[] }>('orders')
      const known = new Set(this.board.map((order) => order.id))
      const arrived = this.boardLoaded ? data.data.filter((order) => !known.has(order.id)) : []

      this.board = data.data
      this.boardLoaded = true

      return arrived
    },

    async fetchHistory(reset = false) {
      if (reset) {
        this.history = []
        this.historyPage = 0
        this.historyHasMore = true
      }

      if (!this.historyHasMore) return

      const { data } = await http.get<Page>('orders', { params: { view: 'history', page: this.historyPage + 1 } })
      this.history.push(...data.data)
      this.historyPage = data.meta.current_page
      this.historyHasMore = data.meta.current_page < data.meta.last_page
    },

    async updateStatus(order: Order, status: OrderStatus, reason?: string) {
      const { data } = await http.put<{ data: Order }>(`orders/${order.id}/status`, { status, reason })
      this.replace(data.data)
    },

    async updatePayment(order: Order, paymentStatus: Order['payment_status']) {
      const { data } = await http.put<{ data: Order }>(`orders/${order.id}/payment`, { payment_status: paymentStatus })
      this.replace(data.data)
    },

    replace(order: Order) {
      for (const list of [this.board, this.history]) {
        const index = list.findIndex((other) => other.id === order.id)
        if (index !== -1) list.splice(index, 1, order)
      }
    },
  },
})
