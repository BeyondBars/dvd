<script setup lang="ts">
/** Settings > Online ordering: on/off, opening hours, prep time and who hears about new orders. */
import { computed, onMounted, ref } from 'vue'
import { isAxiosError } from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useToast } from 'vue-toast-notification'
import { useOrderingStore } from '@/stores/ordering'
import type { Day, OrderingSettings } from '@/types/order'

const props = defineProps<{ canManage: boolean }>()

const DAYS: { key: Day; label: string }[] = [
  { key: 'mon', label: 'Monday' },
  { key: 'tue', label: 'Tuesday' },
  { key: 'wed', label: 'Wednesday' },
  { key: 'thu', label: 'Thursday' },
  { key: 'fri', label: 'Friday' },
  { key: 'sat', label: 'Saturday' },
  { key: 'sun', label: 'Sunday' },
]

type DayForm = { open: boolean; from: string; to: string }
type Form = Omit<OrderingSettings, 'opening_hours' | 'alert_email' | 'alert_phone'> & {
  alert_email: string
  alert_phone: string
  days: Record<Day, DayForm>
}

const orderingStore = useOrderingStore()
const toast = useToast()

const form = ref<Form | null>(null)
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)

const toForm = (settings: OrderingSettings): Form => ({
  ordering_enabled: settings.ordering_enabled,
  prep_minutes: settings.prep_minutes,
  alert_email: settings.alert_email ?? '',
  alert_phone: settings.alert_phone ?? '',
  days: Object.fromEntries(
    DAYS.map(({ key }) => {
      const hours = settings.opening_hours[key]
      return [key, { open: hours !== null, from: hours?.open ?? '07:00', to: hours?.close ?? '15:00' }]
    })
  ) as Record<Day, DayForm>,
})

const payload = (form: Form): OrderingSettings => ({
  ordering_enabled: form.ordering_enabled,
  prep_minutes: Number(form.prep_minutes),
  alert_email: form.alert_email.trim() || null,
  alert_phone: form.alert_phone.trim() || null,
  opening_hours: Object.fromEntries(
    DAYS.map(({ key }) => [key, form.days[key].open ? { open: form.days[key].from, close: form.days[key].to } : null])
  ) as OrderingSettings['opening_hours'],
})

// Both sides go through payload() so key order and empty values compare the same.
const dirty = computed(
  () =>
    form.value !== null &&
    orderingStore.settings !== null &&
    JSON.stringify(payload(form.value)) !== JSON.stringify(payload(toForm(orderingStore.settings)))
)

const dayError = (day: Day) => errors.value[`opening_hours.${day}.open`]?.[0] ?? errors.value[`opening_hours.${day}.close`]?.[0]

onMounted(async () => {
  try {
    await orderingStore.fetchSettings()
    form.value = toForm(orderingStore.settings!)
  } catch {
    toast.error("Couldn't load the ordering settings.")
  }
})

const discard = () => {
  form.value = toForm(orderingStore.settings!)
  errors.value = {}
}

const save = async () => {
  if (!form.value || !props.canManage) return
  saving.value = true
  errors.value = {}

  try {
    await orderingStore.updateSettings(payload(form.value))
    form.value = toForm(orderingStore.settings!)
    toast.success('Ordering settings saved.')
  } catch (error) {
    if (isAxiosError(error) && error.response?.status === 422) {
      errors.value = error.response.data.errors ?? {}
      return
    }
    throw error
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="card mb-6 p-6" data-test="ordering-settings">
    <div class="mb-4 flex items-center gap-2">
      <FontAwesomeIcon icon="receipt" class="text-brand-600" />
      <h2 class="text-base font-semibold text-slate-900">Online ordering</h2>
    </div>
    <p class="mb-6 text-sm text-slate-500">
      When customers can order on the website for pickup. Times are the truck's local time (Adelaide).
    </p>

    <p v-if="!form" class="text-sm text-slate-500">Loading...</p>
    <form v-else novalidate @submit.prevent="save">
      <fieldset :disabled="!canManage">
        <label class="mb-6 flex items-center gap-2 text-sm font-medium text-slate-900">
          <input v-model="form.ordering_enabled" type="checkbox" class="form-check" />
          Take online orders
          <span v-if="!form.ordering_enabled" class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Paused</span>
        </label>

        <div class="mb-6 max-w-xl">
          <p class="form-label">Opening hours</p>
          <div class="divide-y divide-slate-100 rounded-lg border border-slate-200">
            <div v-for="day in DAYS" :key="day.key" class="flex flex-wrap items-center gap-3 px-3 py-2" :data-day="day.key">
              <label class="flex w-32 items-center gap-2 text-sm text-slate-700">
                <input v-model="form.days[day.key].open" type="checkbox" class="form-check" />
                {{ day.label }}
              </label>
              <template v-if="form.days[day.key].open">
                <input v-model="form.days[day.key].from" type="time" class="form-input w-32 py-1.5" :aria-label="`${day.label} opens`" />
                <span class="text-sm text-slate-500">to</span>
                <input v-model="form.days[day.key].to" type="time" class="form-input w-32 py-1.5" :aria-label="`${day.label} closes`" />
              </template>
              <span v-else class="text-sm text-slate-400">Closed</span>
              <span v-if="dayError(day.key)" class="form-error w-full">{{ dayError(day.key) }}</span>
            </div>
          </div>
        </div>

        <div class="grid max-w-xl gap-x-6 sm:grid-cols-3">
          <div class="mb-4">
            <label class="form-label" for="prep_minutes">Prep time (minutes)</label>
            <input id="prep_minutes" v-model="form.prep_minutes" class="form-input" :class="{ 'is-invalid': errors.prep_minutes }" type="number" min="0" max="240" />
            <span v-if="errors.prep_minutes" class="form-error">{{ errors.prep_minutes[0] }}</span>
          </div>
          <div class="mb-4">
            <label class="form-label" for="alert_email">New order email</label>
            <input id="alert_email" v-model="form.alert_email" class="form-input" :class="{ 'is-invalid': errors.alert_email }" type="email" placeholder="Optional" />
            <span v-if="errors.alert_email" class="form-error">{{ errors.alert_email[0] }}</span>
          </div>
          <div class="mb-4">
            <label class="form-label" for="alert_phone">New order SMS</label>
            <input id="alert_phone" v-model="form.alert_phone" class="form-input" :class="{ 'is-invalid': errors.alert_phone }" type="tel" placeholder="Optional" />
            <span v-if="errors.alert_phone" class="form-error">{{ errors.alert_phone[0] }}</span>
          </div>
        </div>
      </fieldset>

      <div v-if="canManage" class="flex justify-end gap-3">
        <button type="button" class="btn btn-secondary" :disabled="!dirty || saving" @click="discard">Discard</button>
        <button type="submit" class="btn btn-primary" :disabled="!dirty || saving">Save ordering settings</button>
      </div>
    </form>
  </div>
</template>
