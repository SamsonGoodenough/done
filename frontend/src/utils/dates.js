// The API serializes date columns as UTC-midnight timestamps
// ("2026-01-01T00:00:00.000000Z"). A log date is a calendar day, not an
// instant, so only its Y-M-D part is meaningful: parsing the whole string
// with `new Date()` shifts it to the previous day in any timezone west of UTC.

/** A calendar day (any API date string) as a local-midnight Date. */
export function parseDay(value) {
  const [year, month, day] = String(value).slice(0, 10).split('-').map(Number)
  return new Date(year, month - 1, day)
}

export function formatDay(value) {
  return parseDay(value).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

export function yearOf(value) {
  return value ? Number(String(value).slice(0, 4)) : null
}

/** Today's local calendar day as YYYY-MM-DD. */
export function todayIso() {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}
