import type { RouteRecordRaw } from 'vue-router'
import { routes as createRoutes } from '@/utils/route'

const users: RouteRecordRaw[] = createRoutes({ template: 'app', restricted: true, guest: false }, [
  {
    path: '/users',
    name: 'users',
    component: () => import('@/views/users/Users.vue'),
    meta: { authorize: 'read users' },
  },
  {
    path: '/users/:id',
    name: 'users.details',
    props: true,
    component: () => import('@/views/users/UserDetails.vue'),
    meta: { authorize: 'read users' },
  },
  {
    path: '/users/:id/permissions',
    name: 'users.permissions',
    props: true,
    component: () => import('@/views/users/UserPermissions.vue'),
    meta: { roles: ['super_admin'] },
  },
])

export default users
