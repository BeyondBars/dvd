/** The truck's time zone. Order and pickup times always show in it, wherever the admin is opened. */
export const TRUCK_TIME_ZONE = 'Australia/Adelaide'

/** "2026-10-12T10:30:00+10:30" -> "10:30 am" */
export const formatTime = (iso: string) =>
  new Date(iso).toLocaleTimeString('en-AU', { hour: 'numeric', minute: '2-digit', timeZone: TRUCK_TIME_ZONE })

/** "Mon, 12 Oct, 10:30 am" */
export const formatDateTime = (iso: string) =>
  new Date(iso).toLocaleString('en-AU', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    hour: 'numeric',
    minute: '2-digit',
    timeZone: TRUCK_TIME_ZONE,
  })
