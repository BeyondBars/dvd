<script setup lang="ts">
/**
 * Take a walk-up order at the truck: tap items off the menu, then place it. Payment is taken on
 * EFTPOS Air for the total shown here (no need to enter the items there too).
 */
import { computed, onMounted, ref } from 'vue'
import { isAxiosError } from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { http } from '@/utils/http'
import { useOrderStore } from '@/stores/orders'
import { formatPrice } from '@/utils/money'
import type { MenuCategory, MenuItem, MenuOption } from '@/types/menu'
import type { Order } from '@/types/order'

type Line = { key: string; item: MenuItem; options: MenuOption[]; quantity: number }

const emit = defineEmits<{ placed: [order: Order]; cancel: [] }>()

const orderStore = useOrderStore()

const menu = ref<MenuCategory[]>([])
const loading = ref(true)
const lines = ref<Line[]>([])
/** The item whose choices are being picked, with the option ids chosen so far per group. */
const picking = ref<{ item: MenuItem; chosen: number[][] } | null>(null)
const customerName = ref('')
const notes = ref('')
const paid = ref(false)
const saving = ref(false)
const error = ref('')

const total = computed(() => lines.value.reduce((sum, line) => sum + unitPrice(line) * line.quantity, 0))
const count = computed(() => lines.value.reduce((sum, line) => sum + line.quantity, 0))

const unitPrice = (line: Pick<Line, 'item' | 'options'>) =>
  line.item.price_cents + line.options.reduce((sum, option) => sum + option.price_cents, 0)

onMounted(async () => {
  try {
    // The same menu customers order from online.
    const { data } = await http.get<{ data: MenuCategory[] }>('public/menu')
    menu.value = data.data.filter((category) => category.items.length)
  } catch {
    error.value = "Couldn't load the menu. Close this and try again."
  } finally {
    loading.value = false
  }
})

const addLine = (item: MenuItem, options: MenuOption[]) => {
  const key = [item.id, ...options.map((option) => option.id).sort()].join('-')
  const existing = lines.value.find((line) => line.key === key)

  if (existing) existing.quantity++
  else lines.value.push({ key, item, options, quantity: 1 })
}

const choose = (item: MenuItem) => {
  if (!item.is_available) return

  if (item.option_groups.length) picking.value = { item, chosen: item.option_groups.map(() => []) }
  else addLine(item, [])
}

const toggleOption = (groupIndex: number, optionId: number) => {
  if (!picking.value) return

  const group = picking.value.item.option_groups[groupIndex]
  const chosen = picking.value.chosen[groupIndex]

  if (chosen.includes(optionId)) {
    picking.value.chosen[groupIndex] = chosen.filter((id) => id !== optionId)
  } else if (group.max_choices === 1) {
    picking.value.chosen[groupIndex] = [optionId]
  } else if (chosen.length < group.max_choices) {
    chosen.push(optionId)
  }
}

const pickingReady = computed(
  () => picking.value?.item.option_groups.every((group, index) => picking.value!.chosen[index].length >= group.min_choices) ?? false
)

const addPicked = () => {
  if (!picking.value || !pickingReady.value) return

  const { item, chosen } = picking.value
  const ids = chosen.flat()
  addLine(item, item.option_groups.flatMap((group) => group.options.filter((option) => ids.includes(option.id!))))
  picking.value = null
}

const changeQuantity = (line: Line, offset: 1 | -1) => {
  line.quantity += offset
  if (line.quantity < 1) lines.value = lines.value.filter((other) => other !== line)
}

const place = async () => {
  if (!lines.value.length || saving.value) return

  saving.value = true
  error.value = ''

  try {
    const order = await orderStore.placeCounterOrder({
      customer_name: customerName.value.trim() || undefined,
      notes: notes.value.trim() || undefined,
      paid: paid.value,
      items: lines.value.map((line) => ({
        menu_item_id: line.item.id,
        quantity: line.quantity,
        option_ids: line.options.map((option) => option.id!),
      })),
    })
    emit('placed', order)
  } catch (exception) {
    const data = isAxiosError(exception) ? exception.response?.data : null
    const first = data?.errors ? (Object.values(data.errors)[0] as string[])[0] : null
    error.value = first ?? data?.message ?? 'Something went wrong. Please try again.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <p v-if="loading" class="text-sm text-slate-500">Loading the menu...</p>

  <div v-else class="grid gap-6 md:grid-cols-[1fr_18rem]">
    <!-- Menu -->
    <div>
      <div v-if="picking" class="rounded-xl border border-brand-200 bg-brand-50/50 p-4" data-test="choices">
        <p class="mb-3 font-semibold text-slate-900">{{ picking.item.name }}</p>
        <div v-for="(group, groupIndex) in picking.item.option_groups" :key="group.id" class="mb-3">
          <p class="mb-1.5 text-sm font-medium text-slate-700">
            {{ group.name }}
            <span class="font-normal text-slate-500">
              {{ group.min_choices ? (group.max_choices === 1 ? '(pick one)' : `(pick ${group.min_choices}-${group.max_choices})`) : '(optional)' }}
            </span>
          </p>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="option in group.options"
              :key="option.id"
              type="button"
              class="rounded-lg border px-3 py-2 text-sm"
              :class="
                picking.chosen[groupIndex].includes(option.id!)
                  ? 'border-brand-500 bg-brand-500 text-white'
                  : 'border-slate-300 bg-white text-slate-700 hover:border-brand-400'
              "
              :aria-pressed="picking.chosen[groupIndex].includes(option.id!)"
              @click="toggleOption(groupIndex, option.id!)"
            >
              {{ option.name }}<span v-if="option.price_cents"> +{{ formatPrice(option.price_cents) }}</span>
            </button>
          </div>
        </div>
        <div class="flex gap-2">
          <button type="button" class="btn btn-primary" :disabled="!pickingReady" @click="addPicked">Add to order</button>
          <button type="button" class="btn btn-secondary" @click="picking = null">Back</button>
        </div>
      </div>

      <template v-else>
        <section v-for="category in menu" :key="category.id" class="mb-4">
          <h3 class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ category.name }}</h3>
          <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            <button
              v-for="item in category.items"
              :key="item.id"
              type="button"
              class="rounded-lg border border-slate-200 bg-white p-3 text-left text-sm hover:border-brand-400 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="!item.is_available"
              :data-item="item.id"
              @click="choose(item)"
            >
              <span class="block font-medium text-slate-900">{{ item.name }}</span>
              <span class="text-slate-500">{{ item.is_available ? formatPrice(item.price_cents) : 'Sold out' }}</span>
            </button>
          </div>
        </section>
        <p v-if="!menu.length && !error" class="text-sm text-slate-500">The menu is empty.</p>
      </template>
    </div>

    <!-- The order so far -->
    <form class="flex flex-col" @submit.prevent="place">
      <ul class="mb-3 space-y-2 text-sm" data-test="lines">
        <li v-for="line in lines" :key="line.key" class="flex items-start justify-between gap-2">
          <div>
            <p class="font-medium text-slate-900">{{ line.item.name }}</p>
            <p v-if="line.options.length" class="text-xs text-slate-500">{{ line.options.map((option) => option.name).join(', ') }}</p>
            <div class="mt-1 flex items-center gap-2">
              <button type="button" class="rounded border border-slate-300 px-2 text-slate-600" :aria-label="`One less ${line.item.name}`" @click="changeQuantity(line, -1)">
                <FontAwesomeIcon icon="minus" />
              </button>
              <span class="w-5 text-center font-semibold">{{ line.quantity }}</span>
              <button type="button" class="rounded border border-slate-300 px-2 text-slate-600" :aria-label="`One more ${line.item.name}`" @click="changeQuantity(line, 1)">
                <FontAwesomeIcon icon="plus" />
              </button>
            </div>
          </div>
          <span class="whitespace-nowrap text-slate-600">{{ formatPrice(unitPrice(line) * line.quantity) }}</span>
        </li>
        <li v-if="!lines.length" class="rounded-lg border border-dashed border-slate-300 p-4 text-center text-slate-400">Tap items to add them</li>
      </ul>

      <label class="form-label" for="counter-name">Name (optional)</label>
      <input id="counter-name" v-model="customerName" type="text" maxlength="80" class="form-input mb-3" placeholder="Walk-in" />

      <label class="form-label" for="counter-notes">Note (optional)</label>
      <input id="counter-notes" v-model="notes" type="text" maxlength="500" class="form-input mb-3" placeholder="e.g. No onion" />

      <label class="mb-4 flex items-center gap-2 text-sm text-slate-700">
        <input v-model="paid" type="checkbox" class="form-check" />
        Already paid on EFTPOS Air
      </label>

      <p v-if="error" class="mb-3 text-sm text-red-600" role="alert">{{ error }}</p>

      <div class="mt-auto">
        <p class="mb-2 flex items-baseline justify-between">
          <span class="text-sm text-slate-500">{{ count }} {{ count === 1 ? 'item' : 'items' }}</span>
          <span class="text-2xl font-bold text-slate-900" data-test="total">{{ formatPrice(total) }}</span>
        </p>
        <button type="submit" class="btn btn-primary w-full" :disabled="!lines.length || saving">Place order</button>
        <button type="button" class="btn btn-secondary mt-2 w-full" @click="emit('cancel')">Cancel</button>
      </div>
    </form>
  </div>
</template>
