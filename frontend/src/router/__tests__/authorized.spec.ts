import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter, type RouteRecordRaw } from 'vue-router'
import { useToast } from 'vue-toast-notification'
import authorized from '@/router/guards/authorized'
import appRoutes from '@/router/routes'
import { signIn } from '@/__tests__/helpers'

const Page = { template: '<div />' }

// Routes with the same `meta` shapes the app uses.
const routes: RouteRecordRaw[] = [
  { path: '/dashboard', name: 'dashboard', component: Page },
  { path: '/open', name: 'open', component: Page },
  { path: '/users', name: 'users', component: Page, meta: { authorize: 'read users' } },
  { path: '/settings', name: 'settings', component: Page, meta: { authorize: ['read settings', 'manage settings'] } },
  { path: '/permissions', name: 'permissions', component: Page, meta: { roles: ['super_admin'] } },
  {
    path: '/custom',
    name: 'custom',
    component: Page,
    meta: { authorize: ({ can }) => can('read users') && can('write users') },
  },
]

const makeRouter = () => {
  const router = createRouter({ history: createMemoryHistory(), routes })
  authorized(router)
  return router
}

/** Navigate from the dashboard (an in-app click) or, with `fresh`, as the first page loaded. */
const visit = async (path: string, { fresh = false } = {}) => {
  const router = makeRouter()
  if (!fresh) await router.push('/dashboard')
  await router.push(path)
  return router.currentRoute.value.name
}

describe('authorized route guard', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('lets anyone through to routes without permissions', async () => {
    signIn()
    expect(await visit('/open')).toBe('open')
  })

  it('blocks guests from protected routes', async () => {
    expect(await visit('/users', { fresh: true })).toBeUndefined()
  })

  it('requires the ability named on the route', async () => {
    signIn(['read users'])
    expect(await visit('/users')).toBe('users')

    signIn([])
    expect(await visit('/users')).toBe('dashboard')
    expect(useToast().error).toHaveBeenCalledWith('You are not authorised to access that page.')
  })

  it('sends a blocked first page load to the dashboard', async () => {
    signIn([])
    expect(await visit('/users', { fresh: true })).toBe('dashboard')
  })

  it('accepts any one of a list of abilities', async () => {
    signIn(['manage settings'])
    expect(await visit('/settings')).toBe('settings')

    signIn(['read settings'])
    expect(await visit('/settings')).toBe('settings')

    signIn(['read users'])
    expect(await visit('/settings')).toBe('dashboard')
  })

  it('checks role-only routes against the user role', async () => {
    signIn(['read users', 'write users'])
    expect(await visit('/permissions')).toBe('dashboard')

    signIn([], { role: 'super_admin' })
    expect(await visit('/permissions')).toBe('permissions')
  })

  it('supports callback checks', async () => {
    signIn(['read users'])
    expect(await visit('/custom')).toBe('dashboard')

    signIn(['read users', 'write users'])
    expect(await visit('/custom')).toBe('custom')
  })
})

describe('app route permissions', () => {
  const meta = (name: string) => appRoutes.find((route) => route.name === name)?.meta

  it('users pages need "read users"', () => {
    expect(meta('users')?.authorize).toBe('read users')
    expect(meta('users.details')?.authorize).toBe('read users')
  })

  it('the settings page needs "read settings" or "manage settings"', () => {
    expect(meta('settings')?.authorize).toEqual(['read settings', 'manage settings'])
  })

  it('the user permissions page is for super admins only', () => {
    expect(meta('users.permissions')?.roles).toEqual(['super_admin'])
  })

  it('the dashboard and account pages are open to every signed-in user', () => {
    for (const name of ['dashboard', 'account']) {
      expect(meta(name)?.authorize).toBeUndefined()
      expect(meta(name)?.roles).toBeUndefined()
      expect(meta(name)?.restricted).toBe(true)
    }
  })
})
