import { describe, expect, it } from 'vitest'
import { formatDateTime, formatTime } from '@/utils/time'

describe('time', () => {
  it('shows times in the truck time zone, whatever the browser is set to', () => {
    // Midnight UTC is 10:30 am in Adelaide (daylight saving).
    expect(formatTime('2026-10-12T00:00:00Z')).toBe('10:30 am')
    expect(formatDateTime('2026-10-12T00:00:00Z')).toMatch(/^Mon,? 12 Oct, 10:30 am$/)
  })
})
