import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Settings from '@/views/settings/Settings.vue'
import confirm from '@/utils/alert'
import { DEFAULT_SETTINGS } from '@/stores/settings'
import { api, iconStub, makeRouter, ok, signIn } from '@/__tests__/helpers'

vi.mock('@/utils/alert', () => ({ default: vi.fn() }))

const css = (name: string) => document.documentElement.style.getPropertyValue(name)

const mountPage = async (abilities: string[]) => {
  const pinia = signIn(abilities)
  const router = await makeRouter('/settings')
  api.get.mockReturnValue(ok([{ id: 1, name: 'super_admin', title: 'Super Admin', description: 'Everything' }]))

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
})
