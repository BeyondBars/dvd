import type { RouteRecordRaw } from 'vue-router'
import { routes as createRoutes } from '@/utils/route'

const emailTemplates: RouteRecordRaw[] = createRoutes({ template: 'app', restricted: true, guest: false }, [
  {
    path: '/email-templates',
    name: 'email-templates',
    component: () => import('@/views/email-templates/EmailTemplates.vue'),
    meta: { authorize: 'manage email templates' },
  },
])

export default emailTemplates
