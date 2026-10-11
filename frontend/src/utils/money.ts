/** 1299 -> "$12.99" */
export const formatPrice = (cents: number) => `$${(cents / 100).toFixed(2)}`

/** "12.99" or "$12.99" -> 1299. Null when it isn't a price. */
export const parsePrice = (value: string): number | null => {
  const cleaned = value.trim().replace(/^\$/, '')

  if (!/^\d+(\.\d{1,2})?$/.test(cleaned)) return null

  return Math.round(parseFloat(cleaned) * 100)
}
