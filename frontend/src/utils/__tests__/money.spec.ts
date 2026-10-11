import { describe, expect, it } from 'vitest'
import { formatPrice, parsePrice } from '@/utils/money'

describe('money', () => {
  it.each([
    [1299, '$12.99'],
    [300, '$3.00'],
    [99, '$0.99'],
    [0, '$0.00'],
  ])('formats %i cents as %s', (cents, text) => {
    expect(formatPrice(cents)).toBe(text)
  })

  it.each([
    ['12.99', 1299],
    ['$12.99', 1299],
    [' 3 ', 300],
    ['3.5', 350],
    ['0.29', 29],
    ['4.35', 435],
  ])('parses %s as %i cents', (text, cents) => {
    expect(parsePrice(text)).toBe(cents)
  })

  it.each([[''], ['abc'], ['1.999'], ['-2'], ['1,50']])('rejects %s', (text) => {
    expect(parsePrice(text)).toBeNull()
  })
})
