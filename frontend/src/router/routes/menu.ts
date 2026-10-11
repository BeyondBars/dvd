import type { RouteRecordRaw } from 'vue-router'
import { routes as createRoutes } from '@/utils/route'

const menu: RouteRecordRaw[] = createRoutes({ template: 'app', restricted: true, guest: false }, [
  {
    path: '/menu',
    name: 'menu',
    component: () => import('@/views/menu/Menu.vue'),
    meta: { authorize: ['read menu', 'manage menu'] },
  },
])

export default menu
