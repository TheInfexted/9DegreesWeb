const TZ = 'Asia/Kuala_Lumpur'

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
  const d = new Date(monthStr + '-01')
  return new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric', timeZone: TZ }).format(d)
}
