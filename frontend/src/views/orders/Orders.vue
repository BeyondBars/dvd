<template>
  <PageHeader title="Orders" description="Online orders from the website. The board refreshes by itself.">
    <div class="flex items-center gap-2">
      <button type="button" class="btn btn-secondary" :aria-pressed="sound.enabled.value" @click="sound.toggle">
        <FontAwesomeIcon :icon="sound.enabled.value ? 'volume-high' : 'volume-xmark'" />
        {{ sound.enabled.value ? 'Sound on' : 'Sound off' }}
      </button>
      <div class="flex rounded-lg bg-slate-200/60 p-1" role="tablist">
        <button
          v-for="(label, value) in TABS"
          :key="value"
          type="button"
          role="tab"
          class="rounded-md px-3 py-1.5 text-sm font-medium"
          :class="tab === value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          :aria-selected="tab === value"
          @click="tab = value"
        >
          {{ label }}
        </button>
      </div>
    </div>
  </PageHeader>

  <p v-if="!canManage" class="mb-6 flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-600">
    <FontAwesomeIcon icon="lock" />
    View only. You don't have permission to update orders.
  </p>

  <template v-if="tab === 'board'">
    <p v-if="!orderStore.boardLoaded" class="text-sm text-slate-500">Loading orders...</p>
    <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <section v-for="column in COLUMNS" :key="column.status" :data-column="column.status">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold tracking-wide text-slate-500 uppercase">
          {{ column.title }}
          <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700">{{ byStatus(column.status).length }}</span>
        </h2>
        <div class="space-y-3">
          <OrderCard
            v-for="order in byStatus(column.status)"
            :key="order.id"
            :order="order"
            :can-manage="canManage"
            :highlight="fresh.has(order.id)"
            :busy="busy === order.id"
            @move="(status) => move(order, status)"
            @cancel="cancel(order)"
            @paid="(paid) => setPaid(order, paid)"
          />
          <p v-if="!byStatus(column.status).length" class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-sm text-slate-400">
            None
          </p>
        </div>
      </section>
    </div>

    <section v-if="finishedToday.length" class="mt-10">
      <h2 class="mb-3 text-sm font-semibold tracking-wide text-slate-500 uppercase">Finished today ({{ finishedToday.length }})</h2>
      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <OrderCard v-for="order in finishedToday" :key="order.id" :order="order" :can-manage="canManage" @paid="(paid) => setPaid(order, paid)" />
      </div>
    </section>
  </template>

  <div v-else class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
          <tr>
            <th class="px-4 py-3">Order</th>
            <th class="px-4 py-3">Placed</th>
            <th class="px-4 py-3">Customer</th>
            <th class="px-4 py-3">Items</th>
            <th class="px-4 py-3 text-right">Total</th>
            <th class="px-4 py-3">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="order in orderStore.history" :key="order.id">
            <td class="px-4 py-3 font-semibold whitespace-nowrap text-slate-900">{{ order.reference }}</td>
            <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ formatDateTime(order.created_at) }}</td>
            <td class="px-4 py-3 text-slate-600">{{ order.customer_name }}<br /><span class="text-xs">{{ order.customer_phone }}</span></td>
            <td class="px-4 py-3 text-slate-600">{{ order.items.map((item) => `${item.quantity} × ${item.name}`).join(', ') }}</td>
            <td class="px-4 py-3 text-right font-semibold whitespace-nowrap">{{ formatPrice(order.total_cents) }}</td>
            <td class="px-4 py-3 whitespace-nowrap">
              <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="STATUS_BADGE[order.status]">{{ order.status_label }}</span>
            </td>
          </tr>
          <tr v-if="!orderStore.history.length && !loadingHistory">
            <td colspan="6" class="px-4 py-6 text-center text-slate-500">No orders yet.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="orderStore.historyHasMore" class="border-t border-slate-100 p-3 text-center">
      <button type="button" class="btn btn-secondary" :disabled="loadingHistory" @click="loadHistory()">Load more</button>
    </div>
  </div>
</template>

<script lang="ts" setup>
import PageHeader from '@/components/app/PageHeader.vue'
import OrderCard from '@/components/orders/OrderCard.vue'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { isAxiosError } from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import Swal from 'sweetalert2'
import { useOrderStore } from '@/stores/orders'
import { useLoggedInUser } from '@/composables/use-logged-in-user'
import { useOrderSound } from '@/composables/use-order-sound'
import { formatPrice } from '@/utils/money'
import { formatDateTime } from '@/utils/time'
import type { Order, OrderStatus } from '@/types/order'

/** How often the board checks for new orders. */
const REFRESH_MS = 10_000

const TABS = { board: 'Board', history: 'History' } as const

const COLUMNS: { status: OrderStatus; title: string }[] = [
  { status: 'new', title: 'New' },
  { status: 'accepted', title: 'Accepted' },
  { status: 'preparing', title: 'Preparing' },
  { status: 'ready', title: 'Ready' },
]

const STATUS_BADGE: Record<OrderStatus, string> = {
  new: 'bg-brand-100 text-brand-700',
  accepted: 'bg-sky-100 text-sky-700',
  preparing: 'bg-violet-100 text-violet-700',
  ready: 'bg-emerald-100 text-emerald-700',
  completed: 'bg-slate-200 text-slate-700',
  cancelled: 'bg-red-100 text-red-700',
}

const orderStore = useOrderStore()
const { can } = useLoggedInUser()
const toast = useToast()
const sound = useOrderSound()

const canManage = computed(() => can('manage orders'))
const tab = ref<keyof typeof TABS>('board')
const busy = ref<number | null>(null)
const loadingHistory = ref(false)
/** Orders that arrived since the page opened, highlighted until they're accepted. */
const fresh = ref(new Set<number>())

const byStatus = (status: OrderStatus) => orderStore.board.filter((order) => order.status === status)
const finishedToday = computed(() =>
  orderStore.board.filter((order) => order.status === 'completed' || order.status === 'cancelled').reverse()
)

const refresh = async () => {
  try {
    const arrived = await orderStore.fetchBoard()

    if (arrived.length) {
      arrived.forEach((order) => fresh.value.add(order.id))
      sound.play()
      toast.info(arrived.length === 1 ? `New order ${arrived[0].reference}` : `${arrived.length} new orders`)
    }
  } catch (error) {
    console.error('Error refreshing orders:', error)
  }
}

let timer: ReturnType<typeof setInterval> | undefined

onMounted(() => {
  refresh()
  timer = setInterval(refresh, REFRESH_MS)
})

onBeforeUnmount(() => clearInterval(timer))

const loadHistory = async (reset = false) => {
  loadingHistory.value = true

  try {
    await orderStore.fetchHistory(reset)
  } catch {
    toast.error("Couldn't load the order history.")
  } finally {
    loadingHistory.value = false
  }
}

watch(tab, (value) => {
  if (value === 'history') loadHistory(true)
})

const run = async (order: Order, change: () => Promise<unknown>) => {
  busy.value = order.id

  try {
    await change()
    fresh.value.delete(order.id)
  } catch (error) {
    const data = isAxiosError(error) ? error.response?.data : null
    toast.error(data?.errors?.status?.[0] ?? data?.message ?? 'Something went wrong. Please try again.')
    // Someone else may have moved it: show the latest.
    refresh()
  } finally {
    busy.value = null
  }
}

const move = (order: Order, status: OrderStatus) => run(order, () => orderStore.updateStatus(order, status))

const setPaid = (order: Order, paid: boolean) => run(order, () => orderStore.updatePayment(order, paid ? 'paid' : 'unpaid'))

const cancel = async (order: Order) => {
  const result = await Swal.fire({
    title: `Cancel ${order.reference}?`,
    text: 'The customer will see it as cancelled, with your reason if you give one. Call them if they might already be on the way.',
    input: 'text',
    inputPlaceholder: 'Reason (optional), e.g. Sold out of paneer',
    showCancelButton: true,
    confirmButtonText: 'Cancel order',
    cancelButtonText: 'Keep order',
    buttonsStyling: false,
    customClass: { confirmButton: 'btn btn-danger ml-2', cancelButton: 'btn btn-secondary', input: 'form-input' },
  })

  if (result.isConfirmed) {
    run(order, () => orderStore.updateStatus(order, 'cancelled', result.value || undefined))
  }
}
</script>
