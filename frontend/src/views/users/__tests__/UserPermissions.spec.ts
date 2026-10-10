import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import UserPermissions from '@/views/users/UserPermissions.vue'
import confirm from '@/utils/alert'
import { api, iconStub, makeRouter, makeUser, ok, signIn } from '@/__tests__/helpers'
import type { PermissionAbility, UserPermissions as Permissions } from '@/types/permission'

vi.mock('@/utils/alert', () => ({ default: vi.fn() }))

const ability = (name: string, label: string, overrides: Partial<PermissionAbility> = {}): PermissionAbility => ({
  name,
  label,
  description: `${label} description`,
  requires: null,
  role: false,
  override: null,
  granted: false,
  ...overrides,
})

// A Staff user: can view users through their role, nothing else.
const staff = (): Permissions => ({
  role: 'Staff',
  modules: [
    {
      key: 'users',
      title: 'User management',
      description: 'Users',
      view: 'read users',
      abilities: [
        ability('read users', 'View', { role: true, granted: true }),
        ability('write users', 'Manage', { requires: 'read users' }),
      ],
    },
    {
      key: 'settings',
      title: 'Settings',
      description: 'Settings',
      view: 'read settings',
      abilities: [
        ability('read settings', 'View'),
        ability('manage settings', 'Manage', { requires: 'read settings' }),
      ],
    },
  ],
})

const mountPage = async (permissions = staff()) => {
  const pinia = signIn([], { role: 'super_admin', id: 1 })
  const router = await makeRouter('/users/7/permissions')
  api.get.mockImplementation((url: string) => (url.endsWith('/permissions') ? ok(permissions) : ok(makeUser({ id: 7 }))))

  const wrapper = mount(UserPermissions, {
    props: { id: '7' },
    global: { plugins: [pinia, router], stubs: iconStub },
  })
  await flushPromises()

  return wrapper
}

type Wrapper = Awaited<ReturnType<typeof mountPage>>

const toggle = (wrapper: Wrapper, label: string) => wrapper.find(`[aria-label="${label}"]`)
const isOn = (wrapper: Wrapper, label: string) => toggle(wrapper, label).attributes('aria-checked') === 'true'
const button = (wrapper: Wrapper, text: string) => wrapper.findAll('button').find((b) => b.text() === text)!
const moduleCard = (wrapper: Wrapper, title: string) => wrapper.findAll('.card').find((card) => card.find('h2').text() === title)!

describe('UserPermissions page', () => {
  it('loads the user permissions and shows the role', async () => {
    const wrapper = await mountPage()

    expect(api.get).toHaveBeenCalledWith('users/7/permissions')
    expect(wrapper.text()).toContain('Role: Staff')
    expect(wrapper.text()).toContain('Jane Doe')
    expect(isOn(wrapper, 'User management: View')).toBe(true)
    expect(isOn(wrapper, 'User management: Manage')).toBe(false)
  })

  it('shows which modules appear in the sidebar from their View permission', async () => {
    const wrapper = await mountPage()

    expect(moduleCard(wrapper, 'User management').text()).toContain('Shown in sidebar')
    expect(moduleCard(wrapper, 'Settings').text()).toContain('Hidden from sidebar')

    await toggle(wrapper, 'Settings: View').trigger('click')

    expect(moduleCard(wrapper, 'Settings').text()).toContain('Shown in sidebar')
  })

  it('turning Manage on also turns View on', async () => {
    const wrapper = await mountPage()

    await toggle(wrapper, 'Settings: Manage').trigger('click')

    expect(isOn(wrapper, 'Settings: Manage')).toBe(true)
    expect(isOn(wrapper, 'Settings: View')).toBe(true)
  })

  it('turning View off also turns Manage off', async () => {
    const wrapper = await mountPage()
    await toggle(wrapper, 'User management: Manage').trigger('click')

    await toggle(wrapper, 'User management: View').trigger('click')

    expect(isOn(wrapper, 'User management: View')).toBe(false)
    expect(isOn(wrapper, 'User management: Manage')).toBe(false)
  })

  it('labels each permission against the role', async () => {
    const wrapper = await mountPage()
    const usersCard = () => moduleCard(wrapper, 'User management').text()

    expect(usersCard()).toContain('Role default')

    await toggle(wrapper, 'User management: View').trigger('click')
    expect(usersCard()).toContain('Removed for this user')

    await toggle(wrapper, 'User management: Manage').trigger('click')
    expect(usersCard()).toContain('Added for this user')
  })

  it('only enables Save and Discard once something changes', async () => {
    const wrapper = await mountPage()
    expect(button(wrapper, 'Save permissions').attributes('disabled')).toBeDefined()
    expect(button(wrapper, 'Discard').attributes('disabled')).toBeDefined()

    await toggle(wrapper, 'Settings: View').trigger('click')

    expect(button(wrapper, 'Save permissions').attributes('disabled')).toBeUndefined()
    expect(button(wrapper, 'Discard').attributes('disabled')).toBeUndefined()
  })

  it('Discard puts the switches back', async () => {
    const wrapper = await mountPage()
    await toggle(wrapper, 'Settings: Manage').trigger('click')

    await button(wrapper, 'Discard').trigger('click')

    expect(isOn(wrapper, 'Settings: Manage')).toBe(false)
    expect(isOn(wrapper, 'Settings: View')).toBe(false)
  })

  it('Save sends every permission and shows what the API returns', async () => {
    const wrapper = await mountPage()
    const saved = staff()
    saved.modules[1].abilities[0] = { ...saved.modules[1].abilities[0], granted: true, override: 'allow' }
    api.put.mockReturnValue(ok(saved))

    await toggle(wrapper, 'Settings: View').trigger('click')
    await button(wrapper, 'Save permissions').trigger('click')
    await flushPromises()

    expect(api.put).toHaveBeenCalledWith('users/7/permissions', {
      permissions: {
        'read users': true,
        'write users': false,
        'read settings': true,
        'manage settings': false,
      },
    })
    expect(button(wrapper, 'Save permissions').attributes('disabled')).toBeDefined()
  })

  it('Reset to role defaults is disabled when there is nothing to reset', async () => {
    const wrapper = await mountPage()

    expect(button(wrapper, 'Reset to role defaults').attributes('disabled')).toBeDefined()
  })

  it('Reset to role defaults asks first and does nothing when cancelled', async () => {
    const withOverride = staff()
    withOverride.modules[0].abilities[0] = { ...withOverride.modules[0].abilities[0], granted: false, override: 'forbid' }
    const wrapper = await mountPage(withOverride)
    vi.mocked(confirm).mockResolvedValue(false)

    await button(wrapper, 'Reset to role defaults').trigger('click')
    await flushPromises()

    expect(confirm).toHaveBeenCalled()
    expect(api.delete).not.toHaveBeenCalled()
  })

  it('Reset to role defaults clears the overrides once confirmed', async () => {
    const withOverride = staff()
    withOverride.modules[0].abilities[0] = { ...withOverride.modules[0].abilities[0], granted: false, override: 'forbid' }
    const wrapper = await mountPage(withOverride)
    vi.mocked(confirm).mockResolvedValue(true)
    api.delete.mockReturnValue(ok(staff()))

    await button(wrapper, 'Reset to role defaults').trigger('click')
    await flushPromises()

    expect(api.delete).toHaveBeenCalledWith('users/7/permissions')
    expect(isOn(wrapper, 'User management: View')).toBe(true)
  })
})
