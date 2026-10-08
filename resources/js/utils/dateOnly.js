/**
 * Formats a calendar date ("1990-05-15") without timezone drift.
 * `new Date("1990-05-15")` is parsed as UTC midnight and can render as the previous day in
 * timezones behind UTC, so the parts are read manually and built as a local date.
 */
export function formatDateOnly(value) {
    if (!value) return '—';
    const [year, month, day] = String(value).slice(0, 10).split('-').map(Number);
    if (!year || !month || !day) return '—';
    return new Date(year, month - 1, day).toLocaleDateString(undefined, { dateStyle: 'medium' });
}
