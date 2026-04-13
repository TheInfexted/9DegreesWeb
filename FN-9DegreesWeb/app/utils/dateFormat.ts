const TZ = 'Asia/Kuala_Lumpur'

/** `YYYY-MM-DD` → `DD/MM/YYYY` for form display */
export function isoDateToDdMmYyyy(iso: string): string {
  if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return ''
  const [y, m, d] = iso.split('-')
  return `${d}/${m}/${y}`
}

/** Parse strict `DD/MM/YYYY` → `YYYY-MM-DD`, or null if invalid */
export function ddMmYyyyToIso(display: string): string | null {
  const trimmed = display.trim()
  const match = trimmed.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/)
  if (!match) return null
  const [, dStr, mStr, yStr] = match
  if (dStr === undefined || mStr === undefined || yStr === undefined) return null
  const day = parseInt(dStr, 10)
  const month = parseInt(mStr, 10)
  const year = parseInt(yStr, 10)
  if (month < 1 || month > 12 || day < 1 || day > 31) return null
  const d = new Date(year, month - 1, day)
  if (d.getFullYear() !== year || d.getMonth() !== month - 1 || d.getDate() !== day) return null
  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

export function formatDate(dateStr: string): string {
  const d = new Date(dateStr)
  return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: TZ })
    .format(d)
}

export function formatDateTime(dateStr: string): string {
  const d = new Date(dateStr)
  return new Intl.DateTimeFormat('en-GB', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit', hour12: true, timeZone: TZ,
  }).format(d)
}

export function formatMonthLabel(monthStr: string): string {
  // monthStr: 'YYYY-MM' or 'YYYY-MM-DD'
  const d = new Date(monthStr.slice(0, 7) + '-01T00:00:00')
  return new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric', timeZone: TZ }).format(d)
}
