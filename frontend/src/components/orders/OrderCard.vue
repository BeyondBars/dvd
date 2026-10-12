<script setup lang="ts">
/** One order on the board or in the history, with buttons to move it along. */
import { computed } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { formatPrice } from '@/utils/money'
import { formatTime } from '@/utils/time'
import type { Order, OrderStatus } from '@/types/order'

const props = defineProps<{ order: Order; canManage: boolean; highlight?: boolean; busy?: boolean }>()
const emit = defineEmits<{ move: [status: OrderStatus]; cancel: []; paid: [paid: boolean]; print: [] }>()

/** The usual next step for each status. */
const NEXT: Partial<Record<OrderStatus, { status: OrderStatus; label: string }>> = {
  new: { status: 'accepted', label: 'Accept' },
  accepted: { status: 'preparing', label: 'Start preparing' },
  preparing: { status: 'ready', label: 'Ready for pickup' },
  ready: { status: 'completed', label: 'Collected' },
}

const next = computed(() => NEXT[props.order.status])
const finished = computed(() => ['completed', 'cancelled'].includes(props.order.status))
const pickup = computed(() => (props.order.pickup_at ? formatTime(props.order.pickup_at) : 'ASAP'))
const atCounter = computed(() => props.order.payment_method === 'at_counter')
</script>

<template>
  <article
    class="card p-4 transition"
    :class="{ 'ring-2 ring-brand-500': highlight, 'opacity-70': finished }"
    :data-order="order.id"
  >
    <header class="flex items-start justify-between gap-3">
      <div>
        <p class="text-lg font-bold text-slate-900">{{ order.reference }}</p>
        <p class="flex items-center gap-1.5 text-sm text-slate-600">
          {{ order.customer_name }}
          <span v-if="atCounter" class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 uppercase" title="Taken at the truck">
            Counter
          </span>
          <span v-if="order.customer_id" class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 uppercase" title="Signed in to their account">
            Account
          </span>
        </p>
      </div>
      <div class="text-right">
        <p class="flex items-center justify-end gap-1.5 text-sm font-semibold text-slate-900">
          <FontAwesomeIcon icon="clock" class="text-slate-400" />
          {{ pickup }}
        </p>
        <p class="text-xs text-slate-500">Ordered {{ formatTime(order.created_at) }}</p>
      </div>
    </header>

    <ul class="my-3 space-y-1.5 border-y border-slate-100 py-3 text-sm">
      <li v-for="item in order.items" :key="item.id" class="flex justify-between gap-3">
        <div>
          <span class="font-semibold">{{ item.quantity }} ×</span> {{ item.name }}
          <p v-if="item.options.length" class="text-xs text-slate-500">{{ item.options.map((option) => option.name).join(', ') }}</p>
          <p v-if="item.notes" class="text-xs text-amber-700 italic">{{ item.notes }}</p>
        </div>
        <span class="whitespace-nowrap text-slate-600">{{ formatPrice(item.line_total_cents) }}</span>
      </li>
    </ul>

    <p v-if="order.notes" class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ order.notes }}</p>
    <p v-if="order.status === 'cancelled'" class="mb-3 text-sm text-red-700">
      Cancelled{{ order.cancel_reason ? `: ${order.cancel_reason}` : '' }}
    </p>

    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
      <span v-if="!order.customer_phone" />
      <a v-else :href="`tel:${order.customer_phone.replace(/[^\d+]/g, '')}`" class="link-muted flex items-center gap-1.5">
        <FontAwesomeIcon icon="phone" />
        {{ order.customer_phone }}
      </a>
      <div class="flex items-center gap-2">
        <button type="button" class="link-muted" :title="`Print receipt for ${order.reference}`" data-test="print" @click="emit('print')">
          <FontAwesomeIcon icon="print" />
          <span class="sr-only">Print receipt</span>
        </button>
        <span class="font-bold text-slate-900">{{ formatPrice(order.total_cents) }}</span>
        <button
          type="button"
          class="rounded-full px-2 py-0.5 text-xs font-semibold disabled:cursor-default"
          :class="order.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
          :disabled="!canManage || busy"
          :title="canManage ? 'Click to change' : undefined"
          data-test="payment"
          @click="emit('paid', order.payment_status !== 'paid')"
        >
          {{ order.payment_status === 'paid' ? 'Paid' : atCounter ? 'Not paid' : 'Pay at pickup' }}
        </button>
      </div>
    </div>

    <div v-if="canManage && !finished" class="mt-3 flex gap-2">
      <button v-if="next" type="button" class="btn btn-primary flex-1" :disabled="busy" @click="emit('move', next.status)">
        {{ next.label }}
      </button>
      <button type="button" class="btn btn-secondary" :disabled="busy" @click="emit('cancel')">Cancel</button>
    </div>
  </article>
</template>
