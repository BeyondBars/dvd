export type EmailTemplateType = 'transactional' | 'marketing'

/** Shape of App\Http\Resources\EmailTemplateResource. `html` is only included when fetching one template. */
export type EmailTemplateResource = {
  key: string
  name: string
  type: EmailTemplateType
  type_title: string
  triggered_by: string
  description: string
  subject: string
  html?: string
}
