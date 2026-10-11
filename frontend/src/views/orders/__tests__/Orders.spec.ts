import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Orders from '@/views/orders/Orders.vue'
import { api, iconStub, makeRouter, ok, signIn } from '@/__tests__/helpers'
import type { Order } from '@/types/order'

const swal = vi.hoisted(() => ({ fire: vi.fn() }))
vi.mock('sweetalert2', () => ({ default: swal }))

const order = (overrides: Partial<Order> = {}): Order => ({
  id: 1,
  number: 1001,
  reference: 'DVD-1001',
  status: 'new',
  status_label: 'New',
  customer_name: 'Priya',
  customer_phone: '0412 345 678',
  customer_email: null,
  pickup_at: null,
  notes: 'Extra napkins',
  subtotal_cents: 1497,
  total_cents: 1497,
  payment_method: 'pay_at_pickup',
  payment_status: 'unpaid',
  items: [
    {
      id: 1,
      menu_item_id: 5,
      name: 'Desi Patty',
      quantity: 2,
      unit_price_cents: 599,
      line_total_cents: 1198,
      options: [{ group: 'Filling', name: 'Paneer', price_cents: 0 }],
      notes: 'Extra hot',
    },
  ],
  created_at: '2026-10-12T10:00:00+10:30',
  accepted_at: null,
  ready_at: null,
  completed_at: null,
  cancelled_at: null,
  cancel_reason: null,
  ...overrides,
})

const mountPage = async (abilities: string[], board: Order[] = [order()]) => {
  const pinia = signIn(abilities)
  const router = await makeRouter('/orders')
  api.get.mockReturnValue(ok(board))

  const wrapper = mount(Orders, { global: { plugins: [pinia, router], stubs: iconStub } })
  await flushPromises()

  return wrapper
}

type Wrapper = Awaited<ReturnType<typeof mountPage>>

const column = (wrapper: Wrapper, status: string) => wrapper.find(`[data-column="${status}"]`)
const button = (wrapper: Wrapper, text: string) => wrapper.findAll('button').find((b) => b.text() === text)

describe('Orders page', () => {
  beforeEach(() => vi.useFakeTimers({ shouldAdvanceTime: true }))
  afterEach(() => vi.useRealTimers())

  it('shows each order in its column with items, choices and notes', async () => {
    const wrapper = await mountPage(['read orders'], [order(), order({ id: 2, reference: 'DVD-1002', status: 'ready', status_label: 'Ready' })])

    expect(api.get).toHaveBeenCalledWith('orders')
    const card = column(wrapper, 'new').find('[data-order="1"]')
    expect(card.text()).toContain('DVD-1001')
    expect(card.text()).toContain('2 × Desi Patty')
    expect(card.text()).toContain('Paneer')
    expect(card.text()).toContain('Extra hot')
    expect(card.text()).toContain('Extra napkins')
    expect(card.text()).toContain('ASAP')
    expect(card.text()).toContain('$14.97')
    expect(card.find('a[href="tel:0412345678"]').exists()).toBe(true)
    expect(column(wrapper, 'ready').text()).toContain('DVD-1002')
  })

  it('is read only without "manage orders"', async () => {
    const wrapper = await mountPage(['read orders'])

    expect(wrapper.text()).toContain("View only. You don't have permission to update orders.")
    expect(button(wrapper, 'Accept')).toBeUndefined()
    expect(wrapper.find('[data-test="payment"]').attributes('disabled')).toBeDefined()
  })

  it('moves an order to the next step', async () => {
    const wrapper = await mountPage(['read orders', 'manage orders'])
    api.put.mockReturnValue(ok(order({ status: 'accepted', status_label: 'Accepted' })))

    await button(wrapper, 'Accept')!.trigger('click')
    await flushPromises()

    expect(api.put).toHaveBeenCalledWith('orders/1/status', { status: 'accepted', reason: undefined })
    expect(column(wrapper, 'accepted').text()).toContain('DVD-1001')
    expect(button(wrapper, 'Start preparing')).toBeDefined()
  })

  it('cancels an order with a reason after confirming', async () => {
    const wrapper = await mountPage(['read orders', 'manage orders'])
    api.put.mockReturnValue(ok(order({ status: 'cancelled', status_label: 'Cancelled', cancel_reason: 'Sold out' })))
    swal.fire.mockResolvedValueOnce({ isConfirmed: false }).mockResolvedValueOnce({ isConfirmed: true, value: 'Sold out' })

    await button(wrapper, 'Cancel')!.trigger('click')
    await flushPromises()
    expect(api.put).not.toHaveBeenCalled()

    await button(wrapper, 'Cancel')!.trigger('click')
    await flushPromises()
    expect(api.put).toHaveBeenCalledWith('orders/1/status', { status: 'cancelled', reason: 'Sold out' })
    expect(wrapper.text()).toContain('Finished today (1)')
    expect(wrapper.text()).toContain('Cancelled: Sold out')
  })

  it('marks an order paid', async () => {
    const wrapper = await mountPage(['read orders', 'manage orders'])
    api.put.mockReturnValue(ok(order({ payment_status: 'paid' })))

    await wrapper.find('[data-test="payment"]').trigger('click')
    await flushPromises()

    expect(api.put).toHaveBeenCalledWith('orders/1/payment', { payment_status: 'paid' })
    expect(wrapper.find('[data-test="payment"]').text()).toBe('Paid')
  })

  it('checks for new orders every 10 seconds and highlights them', async () => {
    const wrapper = await mountPage(['read orders'])
    api.get.mockReturnValue(ok([order(), order({ id: 2, reference: 'DVD-1002' })]))

    await vi.advanceTimersByTimeAsync(10_000)
    await flushPromises()

    expect(api.get).toHaveBeenCalledTimes(2)
    expect(column(wrapper, 'new').find('[data-order="2"]').classes()).toContain('ring-2')
    expect(column(wrapper, 'new').find('[data-order="1"]').classes()).not.toContain('ring-2')
  })

  it('lists past orders on the History tab', async () => {
    const wrapper = await mountPage(['read orders'])
    api.get.mockReturnValue(
      Promise.resolve({ data: { data: [order({ status: 'completed', status_label: 'Collected' })], meta: { current_page: 1, last_page: 1 } } })
    )

    await button(wrapper, 'History')!.trigger('click')
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('orders', { params: { view: 'history', page: 1 } })
    expect(wrapper.find('tbody').text()).toContain('DVD-1001')
    expect(wrapper.find('tbody').text()).toContain('Collected')
    expect(button(wrapper, 'Load more')).toBeUndefined()
  })
})
