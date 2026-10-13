<template>
  <PageHeader title="Email Templates" description="Every email this app sends. Preview one or send yourself a test copy." />

  <div class="card">
    <!-- Toolbar: type tabs with counts, then search -->
    <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1" role="tablist" aria-label="Filter by type">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          type="button"
          role="tab"
          :aria-selected="type === tab.value"
          class="flex flex-1 items-center justify-center gap-2 rounded-md px-2.5 py-1.5 text-sm font-medium whitespace-nowrap transition sm:flex-none sm:px-3"
          :class="type === tab.value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
          @click="type = tab.value"
        >
          {{ tab.label }}
          <span class="rounded-full px-1.5 text-xs" :class="type === tab.value ? 'bg-brand-50 text-brand-700' : 'bg-slate-200 text-slate-600'">
            {{ countFor(tab.value) }}
          </span>
        </button>
      </div>
      <div class="relative sm:w-72">
        <FontAwesomeIcon icon="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" />
        <input v-model="search" type="search" class="form-input py-2 pl-9" placeholder="Search templates..." aria-label="Search templates" />
      </div>
    </div>

    <!-- Desktop table -->
    <table class="hidden w-full table-fixed text-sm md:table">
      <thead class="border-b border-slate-200 bg-slate-50/60 text-left text-xs font-medium text-slate-500">
        <tr>
          <th class="w-[34%] px-4 py-3 font-medium">Name</th>
          <th class="w-[26%] px-4 py-3 font-medium">Subject</th>
          <th class="w-36 px-4 py-3 font-medium">Type</th>
          <th class="px-4 py-3 font-medium">Triggered by</th>
          <th class="w-14 px-4 py-3"><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <tr
          v-for="template in filtered"
          :key="template.key"
          class="group cursor-pointer transition hover:bg-slate-50"
          @click="openPreview(template)"
        >
          <td class="px-4 py-3.5">
            <div class="flex items-center gap-3">
              <span class="flex size-9 shrink-0 items-center justify-center rounded-lg" :class="typeStyle[template.type].tile">
                <FontAwesomeIcon icon="envelope" />
              </span>
              <div class="min-w-0">
                <div class="font-medium text-slate-900">{{ template.name }}</div>
                <div class="truncate text-xs text-slate-500" :title="template.description">{{ template.description }}</div>
              </div>
            </div>
          </td>
          <td class="px-4 py-3.5 text-slate-600">
            <div class="truncate" :title="template.subject">{{ template.subject }}</div>
          </td>
          <td class="px-4 py-3.5">
            <TypeBadge :template="template" />
          </td>
          <td class="px-4 py-3.5">
            <code class="block w-fit max-w-full truncate rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700" :title="template.triggered_by">{{ template.triggered_by }}</code>
          </td>
          <td class="px-4 py-3.5 text-right" @click.stop>
            <RowMenu :template="template" />
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Phone list -->
    <ul class="divide-y divide-slate-100 md:hidden">
      <li v-for="template in filtered" :key="template.key" class="flex gap-3 px-4 py-4">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg" :class="typeStyle[template.type].tile">
          <FontAwesomeIcon icon="envelope" />
        </span>
        <button type="button" class="min-w-0 flex-1 text-left" @click="openPreview(template)">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-medium text-slate-900">{{ template.name }}</span>
            <TypeBadge :template="template" />
          </div>
          <div class="mt-1 truncate text-sm text-slate-600">{{ template.subject }}</div>
          <code class="mt-2 inline-block rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">{{ template.triggered_by }}</code>
        </button>
        <RowMenu :template="template" />
      </li>
    </ul>

    <div v-if="store.isLoading && store.templates.length === 0" class="px-4 py-12 text-center text-sm text-slate-500">
      Loading templates...
    </div>
    <div v-else-if="filtered.length === 0" class="px-4 py-12 text-center">
      <FontAwesomeIcon icon="magnifying-glass" class="text-2xl text-slate-300" />
      <p class="mt-2 text-sm font-medium text-slate-700">No templates found</p>
      <p class="text-sm text-slate-500">Try a different search or type.</p>
    </div>
  </div>

  <AppModal
    v-if="preview"
    title="Email template preview"
    description="How this email looks to recipients, filled with sample data."
    size="xl"
    @close="preview = null"
  >
    <dl class="mb-4 grid grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-4">
      <div class="col-span-2 sm:col-span-1">
        <dt class="text-xs text-slate-500">Template</dt>
        <dd class="mt-0.5 font-medium text-slate-900">{{ preview.name }}</dd>
      </div>
      <div>
        <dt class="text-xs text-slate-500">Type</dt>
        <dd class="mt-1"><TypeBadge :template="preview" /></dd>
      </div>
      <div class="sm:col-span-2">
        <dt class="text-xs text-slate-500">Triggered by</dt>
        <dd class="mt-0.5"><code class="font-mono text-xs text-slate-700">{{ preview.triggered_by }}</code></dd>
      </div>
    </dl>

    <!-- Inbox-style frame around the rendered email -->
    <div class="overflow-hidden rounded-xl border border-slate-200">
      <div class="space-y-1 border-b border-slate-200 bg-white px-4 py-3 text-sm">
        <div class="text-base font-semibold text-slate-900">{{ preview.subject }}</div>
        <div class="text-slate-500">To: <span class="text-slate-700">{{ loggedInUser.name }} &lt;{{ loggedInUser.email }}&gt;</span></div>
      </div>
      <div v-if="!preview.html" class="flex h-[45vh] items-center justify-center bg-slate-50 text-sm text-slate-500">
        Loading preview...
      </div>
      <!-- Sandboxed with no permissions: the email's HTML can't run scripts or reach the app. -->
      <iframe v-else :srcdoc="preview.html" sandbox="" title="Email preview" class="block h-[45vh] w-full bg-white" />
    </div>

    <form class="mt-5" novalidate @submit.prevent="sendTest(preview)">
      <label class="form-label" for="preview-test-email">Send test email to</label>
      <div class="flex flex-col gap-2 sm:flex-row">
        <input
          id="preview-test-email"
          v-model="testEmail"
          type="email"
          class="form-input"
          :class="{ 'is-invalid': testError }"
          placeholder="Enter email address"
          required
        />
        <button type="submit" class="btn btn-primary shrink-0" :disabled="sending">
          <FontAwesomeIcon icon="paper-plane" /> {{ sending ? 'Sending...' : 'Send test' }}
        </button>
      </div>
      <span v-if="testError" class="form-error">{{ testError }}</span>
      <p class="mt-2 text-xs text-slate-500">Sent with sample data and "[TEST]" in the subject. Links in it won't work.</p>
    </form>

    <template #footer>
      <button type="button" class="btn btn-secondary" @click="preview = null">Close</button>
    </template>
  </AppModal>

  <AppModal
    v-if="testTemplate"
    title="Send test email"
    :description="`${testTemplate.name}, sent with sample data and &quot;[TEST]&quot; in the subject.`"
    @close="testTemplate = null"
  >
    <form id="send-test-form" novalidate @submit.prevent="sendTest(testTemplate)">
      <label class="form-label" for="test-email">Send to</label>
      <input id="test-email" v-model="testEmail" type="email" class="form-input" :class="{ 'is-invalid': testError }" required />
      <span v-if="testError" class="form-error">{{ testError }}</span>
      <p class="mt-2 text-xs text-slate-500">Links in test emails won't work.</p>
    </form>

    <template #footer>
      <button type="button" class="btn btn-secondary" @click="testTemplate = null">Cancel</button>
      <button type="submit" form="send-test-form" class="btn btn-primary" :disabled="sending">
        <FontAwesomeIcon icon="paper-plane" /> {{ sending ? 'Sending...' : 'Send test' }}
      </button>
    </template>
  </AppModal>
</template>

<script lang="ts" setup>
import { computed, defineComponent, h, onMounted, onUnmounted, ref } from 'vue'
import { isAxiosError } from 'axios'
import { useToast } from 'vue-toast-notification'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PageHeader from '@/components/app/PageHeader.vue'
import AppModal from '@/components/app/AppModal.vue'
import { useEmailTemplateStore } from '@/stores/email-templates'
import { useLoggedInUser } from '@/composables/use-logged-in-user'
import type { EmailTemplateResource, EmailTemplateType } from '@/types/email-template'

const store = useEmailTemplateStore()
const toast = useToast()
const { loggedInUser } = useLoggedInUser()

const typeStyle: Record<EmailTemplateType, { badge: string; dot: string; tile: string }> = {
  transactional: { badge: 'bg-brand-50 text-brand-700 ring-brand-600/20', dot: 'bg-brand-500', tile: 'bg-brand-50 text-brand-600' },
  marketing: { badge: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', dot: 'bg-emerald-500', tile: 'bg-emerald-50 text-emerald-600' },
}

const TypeBadge = defineComponent({
  props: { template: { type: Object as () => EmailTemplateResource, required: true } },
  setup: (props) => () =>
    h(
      'span',
      {
        class: `inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset ${typeStyle[props.template.type].badge}`,
      },
      [h('span', { class: `size-1.5 rounded-full ${typeStyle[props.template.type].dot}` }), props.template.type_title]
    ),
})

// Only one row menu is open at a time; any click elsewhere closes it.
const openMenu = ref<string | null>(null)
const closeMenu = () => (openMenu.value = null)
onMounted(() => document.addEventListener('click', closeMenu))
onUnmounted(() => document.removeEventListener('click', closeMenu))

const RowMenu = defineComponent({
  props: { template: { type: Object as () => EmailTemplateResource, required: true } },
  setup: (props) => () => {
    const isOpen = openMenu.value === props.template.key
    const item = (icon: string, label: string, action: () => void) =>
      h(
        'button',
        {
          type: 'button',
          role: 'menuitem',
          class: 'flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100',
          onClick: () => {
            closeMenu()
            action()
          },
        },
        [h(FontAwesomeIcon, { icon, class: 'w-4 text-slate-400' }), label]
      )

    return h('div', { class: 'relative inline-block', onClick: (event: MouseEvent) => event.stopPropagation() }, [
      h(
        'button',
        {
          type: 'button',
          class: 'rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700',
          'aria-label': `Actions for ${props.template.name}`,
          'aria-haspopup': 'menu',
          'aria-expanded': isOpen,
          onClick: () => (openMenu.value = isOpen ? null : props.template.key),
        },
        [h(FontAwesomeIcon, { icon: 'ellipsis' })]
      ),
      isOpen &&
        h('div', { role: 'menu', class: 'absolute right-0 z-20 mt-1 w-44 rounded-lg border border-slate-200 bg-white p-1 text-left shadow-lg' }, [
          item('eye', 'Preview', () => openPreview(props.template)),
          item('paper-plane', 'Send test', () => openSendTest(props.template)),
        ]),
    ])
  },
})

const tabs: { value: '' | EmailTemplateType; label: string }[] = [
  { value: '', label: 'All' },
  { value: 'transactional', label: 'Transactional' },
  { value: 'marketing', label: 'Marketing' },
]

const countFor = (value: '' | EmailTemplateType) =>
  value === '' ? store.templates.length : store.templates.filter((template) => template.type === value).length

const search = ref('')
const type = ref<'' | EmailTemplateType>('')

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()

  return store.templates.filter(
    (template) =>
      (type.value === '' || template.type === type.value) &&
      (term === '' ||
        [template.name, template.subject, template.triggered_by, template.description].some((value) =>
          value.toLowerCase().includes(term)
        ))
  )
})

const testTemplate = ref<EmailTemplateResource | null>(null)
const testEmail = ref('')
const testError = ref('')
const sending = ref(false)

const resetTestForm = () => {
  testEmail.value = testEmail.value || loggedInUser.value.email
  testError.value = ''
}

const preview = ref<EmailTemplateResource | null>(null)

const openPreview = async (template: EmailTemplateResource) => {
  preview.value = { ...template }
  resetTestForm()

  try {
    const loaded = await store.fetchPreview(template.key)

    // Ignore a slow response for a preview that has since been closed or swapped.
    if (preview.value?.key === template.key) {
      preview.value = loaded
    }
  } catch {
    toast.error('Could not load the preview.')
    preview.value = null
  }
}

const openSendTest = (template: EmailTemplateResource) => {
  testTemplate.value = template
  resetTestForm()
}

const sendTest = async (template: EmailTemplateResource | null) => {
  if (!template || sending.value) return

  sending.value = true
  testError.value = ''

  try {
    toast.success(await store.sendTest(template.key, testEmail.value))
    testTemplate.value = null
  } catch (error) {
    if (isAxiosError(error) && error.response?.status === 422) {
      testError.value = error.response.data.errors?.email?.[0] ?? error.response.data.message
    } else if (isAxiosError(error) && error.response?.status === 429) {
      testError.value = 'Too many test emails. Try again in a minute.'
    } else {
      toast.error(isAxiosError(error) && error.response?.data?.message ? error.response.data.message : 'Could not send the test email.')
    }
  } finally {
    sending.value = false
  }
}

onMounted(() => store.fetchTemplates().catch(() => toast.error('Could not load email templates.')))
</script>
