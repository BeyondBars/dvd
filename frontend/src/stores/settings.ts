import { defineStore } from 'pinia'
import { http } from '@/utils/http'

export type FontFamily = 'outfit' | 'inter' | 'system' | 'serif' | 'mono'
export type BorderRadius = 'none' | 'small' | 'medium' | 'large'

/** Shape of GET /settings (App\Models\Setting::DEFAULTS). */
export type AppSettings = {
  app_name: string
  primary_color: string
  sidebar_background: string
  sidebar_text: string
  page_background: string
  font_family: FontFamily
  border_radius: BorderRadius
}

/** Mirrors the API defaults; used until the saved settings have loaded. */
export const DEFAULT_SETTINGS: AppSettings = {
  app_name: 'Desi Videshi Diner',
  primary_color: '#f87417',
  sidebar_background: '#15110d',
  sidebar_text: '#fbf7e9',
  page_background: '#fbf7e9',
  font_family: 'outfit',
  border_radius: 'medium',
}

export const FONTS: Record<FontFamily, { label: string; stack: string }> = {
  outfit: { label: 'Outfit', stack: "'Outfit', ui-sans-serif, system-ui, sans-serif" },
  inter: { label: 'Inter', stack: "'Inter', ui-sans-serif, system-ui, sans-serif" },
  system: { label: 'System', stack: 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif' },
  serif: { label: 'Serif', stack: 'ui-serif, Georgia, Cambria, "Times New Roman", serif' },
  mono: { label: 'Monospace', stack: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace' },
}

export const RADII: Record<BorderRadius, { label: string; scale: number }> = {
  none: { label: 'None', scale: 0 },
  small: { label: 'Small', scale: 0.5 },
  medium: { label: 'Medium', scale: 1 },
  large: { label: 'Large', scale: 1.6 },
}

// The last loaded settings are cached so the next page load is themed before the API answers.
const CACHE_KEY = 'app-settings'

const readCache = (): Partial<AppSettings> => {
  try {
    return JSON.parse(localStorage.getItem(CACHE_KEY) ?? '{}')
  } catch {
    return {}
  }
}

const writeCache = (settings: AppSettings) => {
  try {
    localStorage.setItem(CACHE_KEY, JSON.stringify(settings))
  } catch {
    // Storage unavailable (private mode etc.): the theme just loads a moment later.
  }
}

/** True when light text reads better than dark text on the given hex colour. */
export const isDark = (hex: string) => {
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
  return 0.2126 * r + 0.7152 * g + 0.0722 * b < 0.5
}

/**
 * App-wide UI settings saved in the database. `settings` is what is saved; `apply()` can also
 * preview unsaved values (the settings page does this while editing).
 */
export const useSettingsStore = defineStore('settings', {
  state: () => {
    const settings: AppSettings = { ...DEFAULT_SETTINGS, ...readCache() }

    // `applied` is what's on screen: the saved settings, or a preview while editing.
    return { settings, applied: settings }
  },

  getters: {
    sidebarIsDark: (state) => isDark(state.applied.sidebar_background),
  },

  actions: {
    /** Apply the cached theme straight away, then load the saved one. */
    async init() {
      this.apply()

      try {
        await this.fetchSettings()
      } catch (error) {
        console.error('Error fetching settings:', error)
      }
    },

    async fetchSettings() {
      const { data } = await http.get<{ data: AppSettings }>('settings')
      this.save(data.data)
    },

    async updateSettings(payload: Partial<AppSettings>) {
      const { data } = await http.put<{ data: AppSettings }>('settings', payload)
      this.save(data.data)
    },

    async resetSettings() {
      const { data } = await http.delete<{ data: AppSettings }>('settings')
      this.save(data.data)
    },

    save(settings: AppSettings) {
      this.settings = settings
      writeCache(settings)
      this.apply()
    },

    /** Push settings into the CSS variables used by app.css (defaults to the saved settings). */
    apply(settings?: AppSettings) {
      settings ??= this.settings
      const style = document.documentElement.style
      this.applied = settings

      style.setProperty('--app-primary', settings.primary_color)
      style.setProperty('--app-sidebar-bg', settings.sidebar_background)
      style.setProperty('--app-sidebar-text', settings.sidebar_text)
      style.setProperty('--app-page-bg', settings.page_background)
      style.setProperty('--app-font', (FONTS[settings.font_family] ?? FONTS.outfit).stack)
      style.setProperty('--app-radius-scale', String((RADII[settings.border_radius] ?? RADII.medium).scale))

      document.title = settings.app_name
    },
  },
})
