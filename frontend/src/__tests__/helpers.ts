import { createPinia, setActivePinia, type Pinia } from 'pinia'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { vi } from 'vitest'
import routes from '@/router/routes'
import { http } from '@/utils/http'
import { useAppStore } from '@/stores/app'
import type { UserResource } from '@/types/user'

/** The mocked API client (see setup.ts). */
export const api = http as unknown as Record<'get' | 'post' | 'put' | 'delete', ReturnType<typeof vi.fn>>

/** Wrap data the way Laravel resources do. */
export const ok = <T>(data: T) => Promise.resolve({ data: { data } })

export const makeUser = (overrides: Partial<UserResource> = {}): UserResource => ({
  id: 10,
  name: 'Jane Doe',
  first_name: 'Jane',
  last_name: 'Doe',
  email: 'jane@example.com',
  new_email: null,
  mobile: '0400000000',
  status: 'enabled',
  role: { id: 2, name: 'user', title: 'User', description: null },
  provider: null,
  email_verified: true,
  terms_accepted: true,
  last_login_at: null,
  last_login_ip: null,
  two_factor_required: false,
  two_factor_method_enabled: null,
  two_factor_method_preferred: null,
  two_factor_verified: null,
  abilities: [],
  ...overrides,
})

/**
 * Fresh Pinia with a logged in user holding the given abilities.
 * Pass `role: 'super_admin'` for a super admin.
 */
export const signIn = (abilities: string[] = [], { role = 'user', id = 10 } = {}): Pinia => {
  const pinia = createPinia()
  setActivePinia(pinia)

  useAppStore().user = makeUser({
    id,
    role: { id: 1, name: role, title: role, description: null },
    abilities: abilities.map((name, index) => ({ id: index + 1, name, title: null, group: null })),
  })

  return pinia
}

/** A router with the app's real routes and in-memory history (no guards). */
export const makeRouter = async (path = '/dashboard'): Promise<Router> => {
  const router = createRouter({ history: createMemoryHistory(), routes })
  await router.push(path)
  await router.isReady()
  return router
}

/** FontAwesome stub that renders the icon name, so tests can look for it. */
export const iconStub = { FontAwesomeIcon: { props: ['icon'], template: '<i :data-icon="icon" />' } }
