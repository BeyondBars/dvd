import type { RouteRecordRaw } from 'vue-router'
import { routes as createRoutes } from '@/utils/route'

const orders: RouteRecordRaw[] = createRoutes({ template: 'app', restricted: true, guest: false }, [
  {
    path: '/orders',
    name: 'orders',
    component: () => import('@/views/orders/Orders.vue'),
    meta: { authorize: ['read orders', 'manage orders'] },
  },
])

export default orders
