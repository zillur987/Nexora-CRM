/** Static product option lists shared by the list, form and detail views. */

export const DEFAULT_CURRENCY = 'USD';

/** Same list as the Deals module so money behaves identically across the app. */
export const CURRENCIES = ['USD', 'EUR', 'GBP', 'BDT', 'INR', 'AED', 'SAR', 'CAD', 'AUD', 'SGD', 'JPY', 'CNY'];

export const PRODUCT_TYPES = [
    { value: 'physical', label: 'Physical', badge: 'text-bg-primary' },
    { value: 'digital', label: 'Digital', badge: 'text-bg-info' },
    { value: 'service', label: 'Service', badge: 'text-bg-secondary' },
];

export const STATUSES = [
    { value: '1', label: 'Active' },
    { value: '0', label: 'Inactive' },
];

export const typeMeta = (value) => PRODUCT_TYPES.find((t) => t.value === value) ?? null;

/** Bootstrap badge class for the active / inactive state. */
export const statusBadge = (active) => (active ? 'text-bg-success' : 'text-bg-secondary');

/** Gross margin as a percentage of the sale price, or null when it cannot be worked out. */
export function marginPercent(salePrice, costPrice) {
    const sale = Number(salePrice);
    const cost = Number(costPrice);
    if (!Number.isFinite(sale) || !Number.isFinite(cost) || sale <= 0) return null;
    return ((sale - cost) / sale) * 100;
}

/** "12 each", trimming useless trailing zeros from the 3-decimal quantity ("12.000" -> "12"). */
export function formatQuantity(quantity, unit) {
    if (quantity === null || quantity === undefined || quantity === '') return '—';
    const value = Number(quantity);
    if (!Number.isFinite(value)) return '—';
    return `${value.toLocaleString(undefined, { maximumFractionDigits: 3 })}${unit ? ` ${unit}` : ''}`;
}
