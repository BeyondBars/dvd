import { ref } from 'vue'
import { useSettingsStore } from '@/stores/settings'
import { printHtml } from '@/utils/print'
import { receiptHtml } from '@/utils/receipt'
import type { Order } from '@/types/order'

const AUTO_KEY = 'orders-auto-print'
const PRINTED_KEY = 'orders-printed'
/** How many printed order ids to remember, so a reload doesn't print them again. */
const REMEMBER = 200

const read = <T>(key: string, fallback: T): T => {
  try {
    const value = localStorage.getItem(key)
    return value === null ? fallback : (JSON.parse(value) as T)
  } catch {
    return fallback
  }
}

const write = (key: string, value: unknown) => {
  try {
    localStorage.setItem(key, JSON.stringify(value))
  } catch {
    // Not remembered; it still works for this visit.
  }
}

/**
 * Prints order receipts, sized for an 80mm receipt printer. With auto-print on, every new order
 * prints once as it arrives (remembered on this device, so reloading the board doesn't reprint).
 */
export function useReceiptPrinter() {
  const settings = useSettingsStore()
  const autoPrint = ref(read(AUTO_KEY, false))
  const printed = new Set<number>(read<number[]>(PRINTED_KEY, []))

  const remember = (orders: Order[]) => {
    orders.forEach((order) => printed.add(order.id))
    write(PRINTED_KEY, [...printed].slice(-REMEMBER))
  }

  const print = (order: Order) => {
    remember([order])
    return printHtml(receiptHtml(order, settings.settings.app_name))
  }

  /** Print the new orders on the board that haven't printed yet (when auto-print is on). */
  const printNew = (board: Order[]) => {
    if (!autoPrint.value) return

    board.filter((order) => order.status === 'new' && !printed.has(order.id)).forEach(print)
  }

  /** Switch auto-print on or off. Orders already waiting don't print; only ones that arrive after. */
  const toggle = (board: Order[]) => {
    autoPrint.value = !autoPrint.value
    write(AUTO_KEY, autoPrint.value)

    if (autoPrint.value) remember(board)
  }

  return { autoPrint, print, printNew, toggle }
}
