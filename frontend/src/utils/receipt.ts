import { formatPrice } from '@/utils/money'
import { formatDateTime, formatTime } from '@/utils/time'
import type { Order } from '@/types/order'

const escape = (value: string) =>
  value.replace(
    /[&<>"']/g,
    (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]!
  )

/** Styles for an 80mm thermal roll (about 72mm printable). Also fine on A4: it just prints narrow. */
const STYLES = `
  @page { size: 80mm auto; margin: 0; }
  * { box-sizing: border-box; }
  body { width: 72mm; margin: 0 auto; padding: 4mm 0; font: 12px/1.35 ui-monospace, Menlo, Consolas, monospace; color: #000; }
  h1 { margin: 0; font-size: 16px; text-align: center; }
  .center { text-align: center; }
  .big { font-size: 22px; font-weight: bold; }
  .rule { border-top: 1px dashed #000; margin: 6px 0; }
  .row { display: flex; justify-content: space-between; gap: 8px; }
  .row > :last-child { white-space: nowrap; }
  .sub { padding-left: 14px; font-size: 11px; }
  .note { margin: 4px 0; padding: 4px; border: 1px solid #000; font-weight: bold; }
  ul { list-style: none; margin: 0; padding: 0; }
  li { margin-bottom: 4px; }
`

/** A complete HTML page for one order's receipt, ready to print. */
export function receiptHtml(order: Order, businessName: string): string {
  const items = order.items
    .map((item) => {
      const options = item.options.length
        ? `<div class="sub">${escape(item.options.map((option) => option.name).join(', '))}</div>`
        : ''
      const notes = item.notes ? `<div class="sub"><b>* ${escape(item.notes)}</b></div>` : ''

      return `<li><div class="row"><span><b>${item.quantity} x</b> ${escape(item.name)}</span><span>${formatPrice(item.line_total_cents)}</span></div>${options}${notes}</li>`
    })
    .join('')

  const paid = order.payment_status === 'paid' ? 'PAID' : 'PAY AT PICKUP'

  return `<!doctype html>
<html><head><meta charset="utf-8"><title>${escape(order.reference)}</title><style>${STYLES}</style></head>
<body>
  <h1>${escape(businessName)}</h1>
  <div class="center">${formatDateTime(order.created_at)}</div>
  <div class="rule"></div>
  <div class="center big">${escape(order.reference)}</div>
  <div class="center"><b>Pickup: ${order.pickup_at ? formatTime(order.pickup_at) : 'ASAP'}</b></div>
  <div class="rule"></div>
  <div>${escape(order.customer_name)}</div>
  <div>${escape(order.customer_phone)}</div>
  <div class="rule"></div>
  <ul>${items}</ul>
  ${order.notes ? `<div class="note">Note: ${escape(order.notes)}</div>` : ''}
  <div class="rule"></div>
  <div class="row big"><span>Total</span><span>${formatPrice(order.total_cents)}</span></div>
  <div class="center"><b>${paid}</b></div>
  ${order.status === 'cancelled' ? `<div class="center big">CANCELLED</div>` : ''}
  <div class="rule"></div>
  <div class="center">Thank you!</div>
</body></html>`
}
