import { afterEach, vi } from 'vitest'

// No test talks to the real API: each test sets the responses it needs on these mocks.
vi.mock('@/utils/http', () => ({
  http: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
  default: { install: () => {} },
}))

const toast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }
vi.mock('vue-toast-notification', () => ({ useToast: () => toast }))

afterEach(() => {
  vi.clearAllMocks()
  document.documentElement.removeAttribute('style')
  document.title = ''
  localStorage.clear()
})
