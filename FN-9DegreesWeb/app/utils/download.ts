export function downloadBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const a   = document.createElement('a')
  a.href     = url
  a.download = filename
  a.click()
  setTimeout(() => URL.revokeObjectURL(url), 100)
}

/**
 * Parse a Content-Disposition header value and return the filename.
 * Prefers RFC 5987 `filename*=UTF-8''...` over the legacy `filename="..."`.
 */
export function filenameFromContentDisposition(header: string | null): string | null {
  if (!header) return null

  const star = /filename\*\s*=\s*([^']*)'[^']*'([^;]+)/i.exec(header)
  if (star) {
    try { return decodeURIComponent(star[2].trim()) } catch { /* fall through */ }
  }

  const quoted = /filename\s*=\s*"([^"]+)"/i.exec(header)
  if (quoted) return quoted[1].trim()

  const bare = /filename\s*=\s*([^;]+)/i.exec(header)
  if (bare) return bare[1].trim().replace(/^["']|["']$/g, '')

  return null
}

/**
 * Download a PDF from `url`. Uses the server's Content-Disposition filename
 * when present (CORS must expose `Content-Disposition`); otherwise falls back
 * to the provided default.
 */
export async function downloadPdf(url: string, fallbackFilename: string, token: string): Promise<void> {
  const res = await fetch(url, { headers: { Authorization: `Bearer ${token}` } })
  if (!res.ok) throw new Error('Failed to download file.')
  const filename = filenameFromContentDisposition(res.headers.get('Content-Disposition')) ?? fallbackFilename
  const blob = await res.blob()
  downloadBlob(blob, filename)
}
