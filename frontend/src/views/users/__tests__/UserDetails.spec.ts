import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import UserDetails from '@/views/users/UserDetails.vue'
import { api, iconStub, makeRouter, makeUser, ok, signIn } from '@/__tests__/helpers'

const ADMIN_ID = 1

/** Open user ADMIN_ID's details page as a user with the given abilities. */
const mountPage = async (abilities: string[], { role = 'user', id = 10, viewing = ADMIN_ID } = {}) => {
  const pinia = signIn(abilities, { role, id })
  const router = await makeRouter(`/users/${viewing}`)
  api.get.mockReturnValue(ok(makeUser({ id: viewing, first_name: 'John', last_name: 'Admin', email: 'admin@example.com' })))

  const wrapper = mount(UserDetails, { global: { plugins: [pinia, router], stubs: iconStub } })
  await flushPromises()

  return wrapper
}

type Wrapper = Awaited<ReturnType<typeof mountPage>>

// The form validates asynchronously before its submit handler runs.
const submit = async (wrapper: Wrapper) => {
  await wrapper.find('form').trigger('submit')
  await new Promise((resolve) => setTimeout(resolve, 50))
  await flushPromises()
}

const fieldset = (wrapper: Wrapper) => wrapper.find('fieldset')
const updateButton = (wrapper: Wrapper) => wrapper.findAll('button').find((b) => b.text() === 'Update details')
const permissionsLink = (wrapper: Wrapper) => wrapper.findAll('a').find((a) => a.text() === 'Permissions')

describe('UserDetails page', () => {
  describe('for a user who can only view users', () => {
    it('locks every field and hides the Update button', async () => {
      const wrapper = await mountPage(['read users'])

      expect(fieldset(wrapper).attributes('disabled')).toBeDefined()
      expect(updateButton(wrapper)).toBeUndefined()
      expect(wrapper.find('h1').text()).toBe('User details')
      expect(wrapper.text()).toContain("View only. You don't have permission to edit users.")
    })

    it('still shows the user details', async () => {
      const wrapper = await mountPage(['read users'])

      expect((wrapper.find('#first_name').element as HTMLInputElement).value).toBe('John')
      expect((wrapper.find('#email').element as HTMLInputElement).value).toBe('admin@example.com')
    })

    it('never sends an update, even if the form is submitted', async () => {
      const wrapper = await mountPage(['read users'])

      await submit(wrapper)

      expect(api.put).not.toHaveBeenCalled()
    })
  })

  it('lets users with "write users" edit other users', async () => {
    const wrapper = await mountPage(['read users', 'write users'])

    expect(fieldset(wrapper).attributes('disabled')).toBeUndefined()
    expect(updateButton(wrapper)).toBeDefined()
    expect(wrapper.find('h1').text()).toBe('Edit user')
    expect(wrapper.text()).not.toContain('View only')
  })

  it('lets users edit their own details without "write users"', async () => {
    const wrapper = await mountPage(['read users'], { id: 10, viewing: 10 })

    expect(fieldset(wrapper).attributes('disabled')).toBeUndefined()
    expect(updateButton(wrapper)).toBeDefined()
  })

  it('sends the edited details when saved', async () => {
    const wrapper = await mountPage(['read users', 'write users'])
    api.put.mockReturnValue(ok(makeUser({ id: ADMIN_ID })))

    await wrapper.find('#first_name').setValue('Johnny')
    await submit(wrapper)

    await vi.waitFor(() =>
      expect(api.put).toHaveBeenCalledWith(`users/${ADMIN_ID}`, expect.objectContaining({ first_name: 'Johnny' }))
    )
  })

  describe('Permissions link', () => {
    it('shows for super admins viewing someone else', async () => {
      const wrapper = await mountPage([], { role: 'super_admin', id: 2 })

      expect(permissionsLink(wrapper)?.attributes('href')).toBe(`/users/${ADMIN_ID}/permissions`)
    })

    it('is hidden from super admins on their own account', async () => {
      const wrapper = await mountPage([], { role: 'super_admin', id: ADMIN_ID })

      expect(permissionsLink(wrapper)).toBeUndefined()
    })

    it('is hidden from everyone else, even with "write users"', async () => {
      const wrapper = await mountPage(['read users', 'write users'])

      expect(permissionsLink(wrapper)).toBeUndefined()
    })
  })
})
