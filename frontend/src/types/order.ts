export type OrderStatus = 'new' | 'accepted' | 'preparing' | 'ready' | 'completed' | 'cancelled'

export type OrderLine = {
  id: number
  menu_item_id: number | null
  name: string
  quantity: number
  unit_price_cents: number
  line_total_cents: number
  options: { group: string; name: string; price_cents: number }[]
  notes: string | null
}

/** App\Http\Resources\OrderResource */
export type Order = {
  id: number
  number: number
  /** DVD-1001 */
  reference: string
  status: OrderStatus
  status_label: string
  /** Set when the customer was signed in to their website account. */
  customer_id: number | null
  customer_name: string
  customer_phone: string
  customer_email: string | null
  /** Null means as soon as possible. */
  pickup_at: string | null
  notes: string | null
  subtotal_cents: number
  total_cents: number
  /** at_counter: taken at the truck by staff. */
  payment_method: 'pay_at_pickup' | 'at_counter'
  payment_status: 'paid' | 'unpaid'
  items: OrderLine[]
  created_at: string
  accepted_at: string | null
  ready_at: string | null
  completed_at: string | null
  cancelled_at: string | null
  cancel_reason: string | null
}

/** POST /orders: a walk-up order staff take at the truck. */
export type CounterOrderPayload = {
  customer_name?: string
  notes?: string
  /** Already paid on EFTPOS Air. */
  paid: boolean
  items: { menu_item_id: number; quantity: number; option_ids: number[] }[]
}

export type Day = 'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun'

/** Shape of GET /ordering-settings (App\Services\Ordering::DEFAULTS). */
export type OrderingSettings = {
  ordering_enabled: boolean
  opening_hours: Record<Day, { open: string; close: string } | null>
  prep_minutes: number
  alert_email: string | null
  alert_phone: string | null
}
