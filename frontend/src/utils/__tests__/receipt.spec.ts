import { describe, expect, it } from 'vitest'
import { receiptHtml } from '@/utils/receipt'
import type { Order } from '@/types/order'

const order: Order = {
  id: 1,
  number: 1001,
  reference: 'DVD-1001',
  status: 'new',
  status_label: 'New',
  customer_id: null,
  customer_name: 'Priya <b>',
  customer_phone: '0412 345 678',
  customer_email: null,
  pickup_at: '2026-10-12T12:15:00+10:30',
  notes: 'Extra napkins',
  subtotal_cents: 1198,
  total_cents: 1198,
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
      notes: 'Extra hot'
    }
  ],
  created_at: '2026-10-12T11:50:00+10:30',
  accepted_at: null,
  ready_at: null,
  completed_at: null,
  cancelled_at: null,
  cancel_reason: null
}

describe('receiptHtml', () => {
  it('lists the order for an 80mm printer', () => {
    const html = receiptHtml(order, 'Desi Videshi Diner')

    expect(html).toContain('size: 80mm auto')
    expect(html).toContain('Desi Videshi Diner')
    expect(html).toContain('DVD-1001')
    expect(html).toContain('Pickup: 12:15 pm')
    expect(html).toContain('<b>2 x</b> Desi Patty')
    expect(html).toContain('Paneer')
    expect(html).toContain('* Extra hot')
    expect(html).toContain('Note: Extra napkins')
    expect(html).toContain('$11.98')
    expect(html).toContain('PAY AT PICKUP')
  })

  it('escapes what customers typed', () => {
    expect(receiptHtml(order, 'DVD')).toContain('Priya &lt;b&gt;')
  })
})
