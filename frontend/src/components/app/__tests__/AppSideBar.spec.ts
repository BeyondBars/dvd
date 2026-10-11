import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AppSideBar from '@/components/app/AppSideBar.vue'
import { useSettingsStore, DEFAULT_SETTINGS } from '@/stores/settings'
import { iconStub, makeRouter, signIn } from '@/__tests__/helpers'

const mountSidebar = async (abilities: string[]) => {
  const pinia = signIn(abilities)
  const router = await makeRouter()

  return mount(AppSideBar, {
    props: { open: false },
    global: { plugins: [pinia, router], stubs: iconStub },
  })
}

const items = (wrapper: Awaited<ReturnType<typeof mountSidebar>>) =>
  wrapper.findAll('nav a, nav button').map((item) => item.text())

describe('AppSideBar', () => {
  it('shows only the always-available items to a user without module permissions', async () => {
    expect(items(await mountSidebar([]))).toEqual(['Dashboard', 'My Account', 'Logout'])
  })

  it('shows User Management with "read users"', async () => {
    expect(items(await mountSidebar(['read users']))).toEqual(['Dashboard', 'User Management', 'My Account', 'Logout'])
  })

  it('does not show User Management for "write users" alone', async () => {
    expect(items(await mountSidebar(['write users']))).not.toContain('User Management')
  })

  it.each([['read settings'], ['manage settings']])('shows Settings with "%s"', async (ability) => {
    expect(items(await mountSidebar([ability]))).toContain('Settings')
  })

  it.each([['read orders'], ['manage orders']])('shows Orders with "%s"', async (ability) => {
    expect(items(await mountSidebar([ability]))).toContain('Orders')
  })

  it.each([['read menu'], ['manage menu']])('shows Menu with "%s"', async (ability) => {
    expect(items(await mountSidebar([ability]))).toContain('Menu')
  })

  it('shows every module to a user with all permissions', async () => {
    const wrapper = await mountSidebar([
      'read orders',
      'manage orders',
      'read menu',
      'manage menu',
      'read users',
      'write users',
      'read settings',
      'manage settings',
    ])

    expect(items(wrapper)).toEqual(['Dashboard', 'Orders', 'Menu', 'User Management', 'Settings', 'My Account', 'Logout'])
  })

  it('links each item to its page', async () => {
    const wrapper = await mountSidebar(['read users', 'read settings'])
    const hrefs = wrapper.findAll('nav a').map((link) => link.attributes('href'))

    expect(hrefs).toEqual(['/dashboard', '/users', '/settings', '/account'])
  })

  it('uses the white logo on a dark sidebar and the dark logo on a light one', async () => {
    const wrapper = await mountSidebar([])
    const logo = () => wrapper.find('img').attributes('src')

    expect(logo()).toContain('white-logo')

    useSettingsStore().apply({ ...DEFAULT_SETTINGS, sidebar_background: '#ffffff' })
    await wrapper.vm.$nextTick()

    expect(logo()).not.toContain('white-logo')
  })
})
