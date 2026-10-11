/** Static quote option lists shared by the list, form and detail views. */

export { CURRENCIES, DEFAULT_CURRENCY } from '@/utils/dealOptions';

export const STATUSES = [
    { value: 'draft', label: 'Draft', badge: 'text-bg-secondary' },
    { value: 'sent', label: 'Sent', badge: 'text-bg-primary' },
    { value: 'accepted', label: 'Accepted', badge: 'text-bg-success' },
    { value: 'rejected', label: 'Rejected', badge: 'text-bg-danger' },
];

export const statusMeta = (value) => STATUSES.find((s) => s.value === value) ?? null;

/** Bootstrap badge class for a quote status. */
export const statusBadge = (value) => statusMeta(value)?.badge ?? 'text-bg-secondary';

/** What the detail page offers next, by current status. */
export const STATUS_ACTIONS = {
    draft: [{ to: 'sent', label: 'Mark as sent', icon: 'bi-send', primary: true }],
    sent: [
        { to: 'accepted', label: 'Mark accepted', icon: 'bi-check-lg', primary: true },
        { to: 'rejected', label: 'Mark rejected', icon: 'bi-x-lg' },
    ],
    accepted: [{ to: 'draft', label: 'Reopen as draft', icon: 'bi-arrow-counterclockwise' }],
    rejected: [{ to: 'draft', label: 'Reopen as draft', icon: 'bi-arrow-counterclockwise' }],
};

const cents = (n) => Math.round((n + Number.EPSILON) * 100) / 100;

/**
 * Live preview of the totals while editing. It mirrors QuoteCalculator on the server (per-line
 * rounding to the cent) so the preview matches what is saved; the server stays the authority.
 */
export function previewTotals(items) {
    let subtotal = 0;
    let discount = 0;
    let tax = 0;

    const lines = items.map((item) => {
        const gross = cents((Number(item.quantity) || 0) * (Number(item.unit_price) || 0));
        const disc = cents((gross * (Number(item.discount_percent) || 0)) / 100);
        const net = cents(gross - disc);
        const lineTax = cents((net * (Number(item.tax_rate) || 0)) / 100);

        subtotal += gross;
        discount += disc;
        tax += lineTax;

        return { net, tax: lineTax };
    });

    return {
        lines,
        subtotal: cents(subtotal),
        discount: cents(discount),
        tax: cents(tax),
        total: cents(subtotal - discount + tax),
    };
}

/** "2.000" -> "2", "1.500" -> "1.5". */
export function formatQuantity(quantity) {
    const value = Number(quantity);
    return Number.isFinite(value) ? value.toLocaleString(undefined, { maximumFractionDigits: 3 }) : '—';
}
