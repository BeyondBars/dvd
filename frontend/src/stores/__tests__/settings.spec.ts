import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { DEFAULT_SETTINGS, isDark, useSettingsStore, type AppSettings } from '@/stores/settings'
import { api, ok } from '@/__tests__/helpers'

const css = (name: string) => document.documentElement.style.getPropertyValue(name)

const custom: AppSettings = {
  app_name: 'Acme',
  primary_color: '#16a34a',
  sidebar_background: '#ffffff',
  sidebar_text: '#111111',
  page_background: '#eeeeee',
  font_family: 'serif',
  border_radius: 'none',
}

describe('settings store', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('starts from the defaults when nothing is cached', () => {
    expect(useSettingsStore().settings).toEqual(DEFAULT_SETTINGS)
  })

  it('starts from the cached settings so reloads are themed straight away', () => {
    localStorage.setItem('app-settings', JSON.stringify({ primary_color: '#ff0000' }))

    expect(useSettingsStore().settings).toEqual({ ...DEFAULT_SETTINGS, primary_color: '#ff0000' })
  })

  it('ignores a corrupt cache', () => {
    localStorage.setItem('app-settings', '{not json')

    expect(useSettingsStore().settings).toEqual(DEFAULT_SETTINGS)
  })

  it('applies settings as CSS variables and the page title', () => {
    useSettingsStore().apply(custom)

    expect(css('--app-primary')).toBe('#16a34a')
    expect(css('--app-sidebar-bg')).toBe('#ffffff')
    expect(css('--app-sidebar-text')).toBe('#111111')
    expect(css('--app-page-bg')).toBe('#eeeeee')
    expect(css('--app-font')).toContain('Georgia')
    expect(css('--app-radius-scale')).toBe('0')
    expect(document.title).toBe('Acme')
  })

  it('falls back to Inter and medium corners for unknown values', () => {
    useSettingsStore().apply({ ...custom, font_family: 'comic' as never, border_radius: 'huge' as never })

    expect(css('--app-font')).toContain('Inter')
    expect(css('--app-radius-scale')).toBe('1')
  })

  it('applies the saved settings when called without arguments (drops a preview)', () => {
    const store = useSettingsStore()
    store.apply(custom)
    store.apply()

    expect(css('--app-primary')).toBe(DEFAULT_SETTINGS.primary_color)
    expect(store.applied).toEqual(store.settings)
  })

  it('init applies the cache, then loads, applies and caches the saved settings', async () => {
    api.get.mockReturnValue(ok(custom))
    const store = useSettingsStore()

    await store.init()

    expect(api.get).toHaveBeenCalledWith('settings')
    expect(store.settings).toEqual(custom)
    expect(css('--app-primary')).toBe('#16a34a')
    expect(JSON.parse(localStorage.getItem('app-settings')!)).toEqual(custom)
  })

  it('init keeps the current theme when the API is unreachable', async () => {
    api.get.mockRejectedValue(new Error('offline'))
    vi.spyOn(console, 'error').mockImplementation(() => {})
    const store = useSettingsStore()

    await expect(store.init()).resolves.toBeUndefined()
    expect(store.settings).toEqual(DEFAULT_SETTINGS)
    expect(css('--app-primary')).toBe(DEFAULT_SETTINGS.primary_color)
  })

  it('updateSettings saves through the API and applies the result', async () => {
    api.put.mockReturnValue(ok(custom))
    const store = useSettingsStore()

    await store.updateSettings({ primary_color: '#16a34a' })

    expect(api.put).toHaveBeenCalledWith('settings', { primary_color: '#16a34a' })
    expect(store.settings).toEqual(custom)
    expect(document.title).toBe('Acme')
  })

  it('resetSettings deletes the saved settings and applies the defaults', async () => {
    api.delete.mockReturnValue(ok(DEFAULT_SETTINGS))
    const store = useSettingsStore()
    store.save(custom)

    await store.resetSettings()

    expect(api.delete).toHaveBeenCalledWith('settings')
    expect(store.settings).toEqual(DEFAULT_SETTINGS)
    expect(css('--app-primary')).toBe(DEFAULT_SETTINGS.primary_color)
  })

  it('sidebarIsDark follows what is on screen, including previews', () => {
    const store = useSettingsStore()
    expect(store.sidebarIsDark).toBe(true)

    store.apply({ ...DEFAULT_SETTINGS, sidebar_background: '#f8fafc' })
    expect(store.sidebarIsDark).toBe(false)
  })
})

describe('isDark', () => {
  it.each([
    ['#000000', true],
    ['#0f172a', true],
    ['#4f46e5', true],
    ['#ffffff', false],
    ['#facc15', false],
  ])('%s is dark: %s', (hex, expected) => {
    expect(isDark(hex)).toBe(expected)
  })
})
