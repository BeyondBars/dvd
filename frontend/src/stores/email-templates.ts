import { defineStore } from 'pinia'
import { http } from '@/utils/http'
import type { EmailTemplateResource } from '@/types/email-template'

/**
 * Email templates are registered in code (App\Providers\EmailTemplateServiceProvider), so the
 * API only lists, previews and test-sends them. The list isn't paginated.
 */
export const useEmailTemplateStore = defineStore('emailTemplate', {
  state: () => ({
    templates: [] as EmailTemplateResource[],
    isLoading: false,
  }),

  actions: {
    async fetchTemplates() {
      try {
        this.isLoading = true

        const { data } = await http.get<{ data: EmailTemplateResource[] }>('email-templates')
        this.templates = data.data
      } finally {
        this.isLoading = false
      }
    },

    async fetchPreview(key: string): Promise<EmailTemplateResource> {
      const { data } = await http.get<{ data: EmailTemplateResource }>(`email-templates/${key}`)

      return data.data
    },

    async sendTest(key: string, email: string): Promise<string> {
      const { data } = await http.post<{ message: string }>(`email-templates/${key}/test`, { email })

      return data.message
    },
  },
})
