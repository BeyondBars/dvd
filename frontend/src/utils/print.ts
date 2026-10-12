let queue: Promise<void> = Promise.resolve()

/** Give up waiting for the print dialog after this long, so one stuck print can't block the rest. */
const GIVE_UP_MS = 60_000

const printOnce = (html: string) =>
  new Promise<void>((resolve) => {
    const frame = document.createElement('iframe')
    frame.setAttribute('aria-hidden', 'true')
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0'

    frame.onload = () => {
      const view = frame.contentWindow
      let timer: ReturnType<typeof setTimeout> | undefined

      const done = () => {
        clearTimeout(timer)
        frame.remove()
        resolve()
      }

      if (!view) return done()

      view.addEventListener('afterprint', done, { once: true })
      timer = setTimeout(done, GIVE_UP_MS)
      view.focus()
      view.print()
    }

    frame.srcdoc = html
    document.body.appendChild(frame)
  })

/**
 * Print an HTML page through a hidden frame, without leaving the current page. Prints run one
 * after another, so several new orders at once each get their own receipt.
 */
export function printHtml(html: string): Promise<void> {
  queue = queue
    .then(() => printOnce(html))
    .catch((error) => console.error('Error printing:', error))

  return queue
}
