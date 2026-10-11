import { ref } from 'vue'

const STORAGE_KEY = 'orders-sound'

const readSetting = () => {
  try {
    return localStorage.getItem(STORAGE_KEY) === 'on'
  } catch {
    return false
  }
}

/**
 * A chime for new orders. Browsers only allow sound after a click on the page, so it is switched on
 * with a button (and remembered for next time, though the first chime may still need a click).
 */
export function useOrderSound() {
  const enabled = ref(readSetting())
  let context: AudioContext | null = null

  const play = () => {
    if (!enabled.value || typeof AudioContext === 'undefined') return

    context ??= new AudioContext()
    const start = context.currentTime

    // Two short rising notes.
    ;[880, 1320].forEach((frequency, index) => {
      const oscillator = context!.createOscillator()
      const gain = context!.createGain()
      const at = start + index * 0.18

      oscillator.frequency.value = frequency
      gain.gain.setValueAtTime(0.0001, at)
      gain.gain.exponentialRampToValueAtTime(0.3, at + 0.02)
      gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.16)
      oscillator.connect(gain).connect(context!.destination)
      oscillator.start(at)
      oscillator.stop(at + 0.17)
    })
  }

  const toggle = () => {
    enabled.value = !enabled.value

    try {
      localStorage.setItem(STORAGE_KEY, enabled.value ? 'on' : 'off')
    } catch {
      // Not remembered; it still works for this visit.
    }

    // Play once on switching on: it confirms the sound works, and the click unlocks audio.
    play()
  }

  return { enabled, play, toggle }
}
