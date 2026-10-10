import { DEFAULT_CURRENCY } from '@/utils/dealOptions';

/**
 * Formats an amount in its currency ("$1,250.00", "BDT 40K" when compact).
 * Amounts from the API are decimal strings, so they are converted first; anything that is not a
 * finite number renders as an em dash.
 */
export function formatMoney(amount, currency = DEFAULT_CURRENCY, { compact = false } = {}) {
    if (amount === null || amount === undefined || amount === '') return '—';

    const value = Number(amount);
    if (!Number.isFinite(value)) return '—';

    const code = currency || DEFAULT_CURRENCY;

    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: code,
            notation: compact ? 'compact' : 'standard',
            maximumFractionDigits: compact ? 1 : 2,
        }).format(value);
    } catch {
        return `${code} ${value.toLocaleString()}`; // unknown currency code
    }
}
