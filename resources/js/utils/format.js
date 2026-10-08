export function money(amount, currency = 'USD') {
    const value = Number(amount ?? 0);
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(value);
    } catch {
        return `${value.toFixed(2)} ${currency}`;
    }
}

export function formatDate(value, withTime = false) {
    if (!value) return '—';
    const date = new Date(value);
    return withTime
        ? date.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
        : date.toLocaleDateString(undefined, { dateStyle: 'medium' });
}

/** "https://www.acme.com/about/" -> "www.acme.com/about" (display only; keep the real URL for the href). */
export function displayUrl(url) {
    return url ? url.replace(/^https?:\/\//i, '').replace(/\/+$/, '') : '';
}