import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Settings from '@/views/settings/Settings.vue'
import confirm from '@/utils/alert'
import { DEFAULT_SETTINGS } from '@/stores/settings'
import type { OrderingSettings } from '@/types/order'
import { api, iconStub, makeRouter, ok, signIn } from '@/__tests__/helpers'

vi.mock('@/utils/alert', () => ({ default: vi.fn() }))

const orderingSettings = (): OrderingSettings => ({
  ordering_enabled: true,
  opening_hours: { mon: { open: '07:00', close: '15:00' }, tue: null, wed: null, thu: null, fri: null, sat: null, sun: null },
  prep_minutes: 15,
  busy_minutes: 0,
  alert_email: null,
  alert_phone: null,
})

const css = (name: string) => document.documentElement.style.getPropertyValue(name)

const mountPage = async (abilities: string[]) => {
  const pinia = signIn(abilities)
  const router = await makeRouter('/settings')
  api.get.mockImplementation((url: string) =>
    url === 'ordering-settings'
      ? ok(orderingSettings())
      : ok([{ id: 1, name: 'super_admin', title: 'Super Admin', description: 'Everything' }])
  )

  const wrapper = mount(Settings, { global: { plugins: [pinia, router], stubs: iconStub } })
  await flushPromises()

  return wrapper
}

type Wrapper = Awaited<ReturnType<typeof mountPage>>

const button = (wrapper: Wrapper, text: string) => wrapper.findAll('button').find((b) => b.text() === text)

describe('Settings page', () => {
  it('lists the roles', async () => {
    const wrapper = await mountPage(['read settings'])

    expect(api.get).toHaveBeenCalledWith('roles')
    expect(wrapper.find('tbody').text()).toContain('Super Admin')
  })

  describe('for a user who can only view settings', () => {
    it('shows the appearance locked, with no save or reset buttons', async () => {
      const wrapper = await mountPage(['read settings'])

      expect(wrapper.find('fieldset').attributes('disabled')).toBeDefined()
      expect(wrapper.text()).toContain("View only. You don't have permission to change the appearance.")
      expect(button(wrapper, 'Save changes')).toBeUndefined()
      expect(button(wrapper, 'Discard')).toBeUndefined()
      expect(button(wrapper, 'Reset to defaults')).toBeUndefined()
    })

    it('shows the current values', async () => {
      const wrapper = await mountPage(['read settings'])

      expect((wrapper.find('#app_name').element as HTMLInputElement).value).toBe(DEFAULT_SETTINGS.app_name)
      expect((wrapper.find('#primary_color').element as HTMLInputElement).value).toBe(DEFAULT_SETTINGS.primary_color)
    })
  })

  describe('for a user who can manage settings', () => {
    it('can edit, with Save and Discard enabled only after a change', async () => {
      const wrapper = await mountPage(['manage settings'])

      expect(wrapper.find('fieldset').attributes('disabled')).toBeUndefined()
      expect(button(wrapper, 'Save changes')?.attributes('disabled')).toBeDefined()

      await wrapper.find('#app_name').setValue('Acme')

      expect(button(wrapper, 'Save changes')?.attributes('disabled')).toBeUndefined()
      expect(button(wrapper, 'Discard')?.attributes('disabled')).toBeUndefined()
    })

    it('previews a colour as soon as it is a complete hex code', async () => {
      const wrapper = await mountPage(['manage settings'])

      await wrapper.find('#primary_color').setValue('#16a')
      expect(css('--app-primary')).toBe(DEFAULT_SETTINGS.primary_color)

      await wrapper.find('#primary_color').setValue('#16a34a')
      expect(css('--app-primary')).toBe('#16a34a')
    })

    it('previews the corner radius and font', async () => {
      const wrapper = await mountPage(['manage settings'])

      await button(wrapper, 'None')!.trigger('click')
      await wrapper.find('#font_family').setValue('mono')

      expect(css('--app-radius-scale')).toBe('0')
      expect(css('--app-font')).toContain('monospace')
    })

    it('Discard drops the preview', async () => {
      const wrapper = await mountPage(['manage settings'])
      await wrapper.find('#primary_color').setValue('#16a34a')

      await button(wrapper, 'Discard')!.trigger('click')

      expect(css('--app-primary')).toBe(DEFAULT_SETTINGS.primary_color)
      expect((wrapper.find('#primary_color').element as HTMLInputElement).value).toBe(DEFAULT_SETTINGS.primary_color)
    })

    it('Save sends the settings to the API', async () => {
      const wrapper = await mountPage(['manage settings'])
      api.put.mockReturnValue(ok({ ...DEFAULT_SETTINGS, primary_color: '#16a34a' }))

      await wrapper.find('#primary_color').setValue('#16a34a')
      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(api.put).toHaveBeenCalledWith('settings', { ...DEFAULT_SETTINGS, primary_color: '#16a34a' })
    })

    it('shows validation errors from the API', async () => {
      const wrapper = await mountPage(['manage settings'])
      api.put.mockRejectedValue(
        Object.assign(new Error('Unprocessable'), {
          isAxiosError: true,
          response: { status: 422, data: { errors: { primary_color: ['The primary color must be a hex colour.'] } } },
        })
      )

      await wrapper.find('#primary_color').setValue('#zzzzzz')
      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(wrapper.text()).toContain('The primary color must be a hex colour.')
      expect(wrapper.find('#primary_color').classes()).toContain('is-invalid')
    })

    it('Reset to defaults asks first, then resets for everyone', async () => {
      const wrapper = await mountPage(['manage settings'])
      api.delete.mockReturnValue(ok(DEFAULT_SETTINGS))

      vi.mocked(confirm).mockResolvedValue(false)
      await button(wrapper, 'Reset to defaults')!.trigger('click')
      await flushPromises()
      expect(api.delete).not.toHaveBeenCalled()

      vi.mocked(confirm).mockResolvedValue(true)
      await button(wrapper, 'Reset to defaults')!.trigger('click')
      await flushPromises()
      expect(api.delete).toHaveBeenCalledWith('settings')
    })
  })

  it('drops an unsaved preview when leaving the page', async () => {
    const wrapper = await mountPage(['manage settings'])
    await wrapper.find('#primary_color').setValue('#16a34a')

    wrapper.unmount()

    expect(css('--app-primary')).toBe(DEFAULT_SETTINGS.primary_color)
  })

  describe('online ordering', () => {
    const card = (wrapper: Wrapper) => wrapper.find('[data-test="ordering-settings"]')

    it('shows the saved hours, locked for a viewer', async () => {
      const wrapper = await mountPage(['read settings'])

      expect(api.get).toHaveBeenCalledWith('ordering-settings')
      expect((card(wrapper).find('[aria-label="Monday opens"]').element as HTMLInputElement).value).toBe('07:00')
      expect(card(wrapper).find('[data-day="tue"]').text()).toContain('Closed')
      expect(card(wrapper).find('fieldset').attributes('disabled')).toBeDefined()
      expect(card(wrapper).text()).not.toContain('Save ordering settings')
    })

    it('only enables saving after a change', async () => {
      const wrapper = await mountPage(['read settings', 'manage settings'])
      const save = () => card(wrapper).findAll('button').find((b) => b.text() === 'Save ordering settings')!

      expect(save().attributes('disabled')).toBeDefined()
      await card(wrapper).find('#prep_minutes').setValue('25')
      expect(save().attributes('disabled')).toBeUndefined()
    })

    it('saves hours, prep and rush time and alert contacts', async () => {
      const wrapper = await mountPage(['read settings', 'manage settings'])
      api.put.mockReturnValue(ok({ ...orderingSettings(), prep_minutes: 20 }))

      await card(wrapper).find('[data-day="sat"] input[type="checkbox"]').setValue(true)
      await card(wrapper).find('[aria-label="Saturday opens"]').setValue('09:00')
      await card(wrapper).find('[aria-label="Saturday closes"]').setValue('13:00')
      await card(wrapper).find('#prep_minutes').setValue('20')
      await card(wrapper).find('#busy_minutes').setValue(10)
      await card(wrapper).find('#alert_phone').setValue(' 0435 331 122 ')
      await card(wrapper).find('form').trigger('submit')
      await flushPromises()

      expect(api.put).toHaveBeenCalledWith('ordering-settings', {
        ordering_enabled: true,
        prep_minutes: 20,
        busy_minutes: 10,
        alert_email: null,
        alert_phone: '0435 331 122',
        opening_hours: {
          mon: { open: '07:00', close: '15:00' },
          tue: null,
          wed: null,
          thu: null,
          fri: null,
          sat: { open: '09:00', close: '13:00' },
          sun: null,
        },
      })
    })

    it('shows errors for a day', async () => {
      const wrapper = await mountPage(['read settings', 'manage settings'])
      api.put.mockRejectedValue(
        Object.assign(new Error('Unprocessable'), {
          isAxiosError: true,
          response: { status: 422, data: { errors: { 'opening_hours.mon.close': ['Closing time must be after opening time.'] } } },
        })
      )

      await card(wrapper).find('[aria-label="Monday closes"]').setValue('06:00')
      await card(wrapper).find('form').trigger('submit')
      await flushPromises()

      expect(card(wrapper).find('[data-day="mon"]').text()).toContain('Closing time must be after opening time.')
    })
  })
})
