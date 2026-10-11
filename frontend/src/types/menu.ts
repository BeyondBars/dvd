export type MenuOption = { id?: number; name: string; price_cents: number }

/** A choice for an item: min 1 / max 1 is "pick one" (required), min 0 is optional. */
export type MenuOptionGroup = {
  id?: number
  name: string
  min_choices: number
  max_choices: number
  options: MenuOption[]
}

/** App\Http\Resources\MenuItemResource. Prices are in cents. */
export type MenuItem = {
  id: number
  menu_category_id: number
  name: string
  description: string | null
  price_cents: number
  sort_order: number
  /** False when sold out: still shown on the website, but can't be ordered. */
  is_available: boolean
  option_groups: MenuOptionGroup[]
}

/** App\Http\Resources\MenuCategoryResource. Hidden categories are left off the website. */
export type MenuCategory = {
  id: number
  name: string
  description: string | null
  sort_order: number
  is_visible: boolean
  items: MenuItem[]
}

export type CategoryPayload = Partial<Pick<MenuCategory, 'name' | 'description' | 'is_visible'>>

export type ItemPayload = Partial<
  Pick<MenuItem, 'menu_category_id' | 'name' | 'description' | 'price_cents' | 'is_available' | 'option_groups'>
>
