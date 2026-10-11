import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Menu from '@/views/menu/Menu.vue'
import confirm from '@/utils/alert'
import { useMenuStore } from '@/stores/menu'
import { api, iconStub, makeRouter, ok, signIn } from '@/__tests__/helpers'
import type { MenuCategory, MenuItem } from '@/types/menu'

vi.mock('@/utils/alert', () => ({ default: vi.fn() }))

const item = (overrides: Partial<MenuItem> = {}): MenuItem => ({
  id: 11,
  menu_category_id: 1,
  name: 'Samosa (2 pcs)',
  description: 'Crispy pastry',
  price_cents: 799,
  sort_order: 0,
  is_available: true,
  option_groups: [],
  ...overrides,
})

const menu = (): MenuCategory[] => [
  {
    id: 1,
    name: 'Snacks',
    description: null,
    sort_order: 0,
    is_visible: true,
    items: [item(), item({ id: 12, name: 'Poha', price_cents: 1299, sort_order: 1, is_available: false })],
  },
  { id: 2, name: 'Secret', description: 'Staff only', sort_order: 1, is_visible: false, items: [] },
]

const mountPage = async (abilities: string[]) => {
  const pinia = signIn(abilities)
  const router = await makeRouter('/menu')
  api.get.mockReturnValue(ok(menu()))

  const wrapper = mount(Menu, { global: { plugins: [pinia, router], stubs: iconStub } })
  await flushPromises()

  return wrapper
}

type Wrapper = Awaited<ReturnType<typeof mountPage>>

const button = (wrapper: Wrapper, label: string) =>
  wrapper.findAll('button').find((b) => b.text() === label || b.attributes('aria-label') === label)

describe('Menu page', () => {
  it('lists the categories and items with prices and availability', async () => {
    const wrapper = await mountPage(['read menu'])

    expect(api.get).toHaveBeenCalledWith('menu/categories')
    expect(wrapper.text()).toContain('Samosa (2 pcs)')
    expect(wrapper.text()).toContain('$7.99')
    expect(wrapper.text()).toContain('$12.99')
    expect(wrapper.find('[data-item="11"] [data-test="availability"]').text()).toBe('Available')
    expect(wrapper.find('[data-item="12"] [data-test="availability"]').text()).toBe('Sold out')
    expect(wrapper.find('[data-category="2"]').text()).toContain('Hidden from website')
  })

  it('is read only without "manage menu"', async () => {
    const wrapper = await mountPage(['read menu'])

    expect(wrapper.text()).toContain("View only. You don't have permission to change the menu.")
    expect(button(wrapper, 'Add category')).toBeUndefined()
    expect(button(wrapper, 'Edit item')).toBeUndefined()
    expect(wrapper.find('[data-test="availability"]').attributes('disabled')).toBeDefined()
  })

  it('marks an item sold out with one click', async () => {
    const wrapper = await mountPage(['read menu', 'manage menu'])
    api.put.mockReturnValue(ok(item({ is_available: false })))

    await wrapper.find('[data-item="11"] [data-test="availability"]').trigger('click')
    await flushPromises()

    expect(api.put).toHaveBeenCalledWith('menu/items/11', { is_available: false })
    expect(wrapper.find('[data-item="11"] [data-test="availability"]').text()).toBe('Sold out')
  })

  it('adds an item, converting the price to cents', async () => {
    const wrapper = await mountPage(['read menu', 'manage menu'])
    api.post.mockReturnValue(ok(item({ id: 13, name: 'Kheer', price_cents: 699, sort_order: 2 })))

    await wrapper.find('[data-category="1"]').findAll('button').find((b) => b.text() === 'Item')!.trigger('click')
    await wrapper.find('#item_name').setValue('Kheer')
    await wrapper.find('#item_price').setValue('$6.99')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('menu/items', {
      menu_category_id: 1,
      name: 'Kheer',
      description: null,
      price_cents: 699,
      is_available: true,
      option_groups: [],
    })
    expect(wrapper.find('[data-item="13"]').text()).toContain('$6.99')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('rejects a price that is not a price without calling the API', async () => {
    const wrapper = await mountPage(['read menu', 'manage menu'])

    await button(wrapper, 'Edit item')!.trigger('click')
    await wrapper.find('#item_price').setValue('cheap')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(api.put).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Enter a price like 12.99.')
  })

  it('shows validation errors from the API', async () => {
    const wrapper = await mountPage(['read menu', 'manage menu'])
    api.post.mockRejectedValue(
      Object.assign(new Error('Unprocessable'), {
        isAxiosError: true,
        response: { status: 422, data: { errors: { name: ['The name field is required.'] } } },
      })
    )

    await button(wrapper, 'Add category')!.trigger('click')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('The name field is required.')
    expect(wrapper.find('form').exists()).toBe(true)
  })

  it('moves a category down', async () => {
    const wrapper = await mountPage(['read menu', 'manage menu'])
    api.put.mockReturnValue(ok([...menu()].reverse()))

    await wrapper.find('[data-category="1"]').find('[aria-label="Move category down"]').trigger('click')
    await flushPromises()

    expect(api.put).toHaveBeenCalledWith('menu/categories/sort', { ids: [2, 1] })
    expect(useMenuStore().categories.map((c) => c.id)).toEqual([2, 1])
  })

  it('deletes an item only after confirming', async () => {
    const wrapper = await mountPage(['read menu', 'manage menu'])
    vi.mocked(confirm).mockResolvedValueOnce(false).mockResolvedValueOnce(true)
    api.delete.mockReturnValue(Promise.resolve({ data: null }))
    const deleteButton = () => wrapper.find('[data-item="11"]').find('[aria-label="Delete item"]')

    await deleteButton().trigger('click')
    await flushPromises()
    expect(api.delete).not.toHaveBeenCalled()

    await deleteButton().trigger('click')
    await flushPromises()
    expect(api.delete).toHaveBeenCalledWith('menu/items/11')
    expect(wrapper.find('[data-item="11"]').exists()).toBe(false)
  })

  it('lists the choices under an item', async () => {
    const pinia = signIn(['read menu'])
    const router = await makeRouter('/menu')
    const categories = menu()
    categories[0].items[0].option_groups = [
      { id: 1, name: 'Size', min_choices: 0, max_choices: 1, options: [{ id: 2, name: 'Large Combo', price_cents: 299 }] },
    ]
    api.get.mockReturnValue(ok(categories))

    const wrapper = mount(Menu, { global: { plugins: [pinia, router], stubs: iconStub } })
    await flushPromises()

    expect(wrapper.find('[data-item="11"] [data-test="choices"]').text()).toBe('Size (optional): Large Combo +$2.99')
  })

  it('saves choices with extra prices in cents and drops empty options', async () => {
    const wrapper = await mountPage(['read menu', 'manage menu'])
    api.put.mockReturnValue(ok(item()))

    await wrapper.find('[data-item="11"]').find('[aria-label="Edit item"]').trigger('click')
    await button(wrapper, 'Add a choice')!.trigger('click')
    const group = wrapper.find('[data-test="choice-group"]')
    await group.find('[aria-label="Choice 1 name"]').setValue('Size')
    await group.find('input[type="checkbox"]').setValue(false)
    await group.find('[aria-label="Option name"]').setValue('Large Combo')
    await group.find('[aria-label="Extra price"]').setValue('2.99')
    await group.findAll('button').find((b) => b.text() === '+ Add option')!.trigger('click')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(api.put).toHaveBeenCalledWith('menu/items/11', expect.objectContaining({
      option_groups: [{ name: 'Size', min_choices: 0, max_choices: 1, options: [{ name: 'Large Combo', price_cents: 299 }] }],
    }))
  })

  it('rejects an extra price that is not a price', async () => {
    const wrapper = await mountPage(['read menu', 'manage menu'])

    await wrapper.find('[data-item="11"]').find('[aria-label="Edit item"]').trigger('click')
    await button(wrapper, 'Add a choice')!.trigger('click')
    await wrapper.find('[aria-label="Choice 1 name"]').setValue('Size')
    await wrapper.find('[aria-label="Option name"]').setValue('Large')
    await wrapper.find('[aria-label="Extra price"]').setValue('lots')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(api.put).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Enter extra prices like 2.99, or leave them empty.')
  })
})
