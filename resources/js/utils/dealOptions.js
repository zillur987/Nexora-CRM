/** Static deal option lists shared by the list, form and detail views. */

export const DEFAULT_CURRENCY = 'USD';

/** Keep in sync with DealRules::CURRENCIES (app/Http/Requests/Deals/Rules/DealRules.php). */
export const CURRENCIES = ['USD', 'EUR', 'GBP', 'BDT', 'INR', 'AED', 'SAR', 'CAD', 'AUD', 'SGD', 'JPY', 'CNY'];

export const PRIORITIES = [
    { value: 'low', label: 'Low', badge: 'text-bg-secondary' },
    { value: 'medium', label: 'Medium', badge: 'text-bg-warning' },
    { value: 'high', label: 'High', badge: 'text-bg-danger' },
];

export const OUTCOMES = [
    { value: 'open', label: 'Open' },
    { value: 'won', label: 'Won' },
    { value: 'lost', label: 'Lost' },
];

const STAGE_BADGES = { open: 'text-bg-primary', won: 'text-bg-success', lost: 'text-bg-danger' };

/** Bootstrap badge class for a stage, by what it means for the deal. */
export const stageBadge = (stage) => STAGE_BADGES[stage?.outcome] ?? 'text-bg-secondary';

export const priorityMeta = (value) => PRIORITIES.find((p) => p.value === value) ?? null;

/** The probability a deal gets when it moves into `stage`: closed stages are fixed, open ones use the stage default. */
export function stageProbability(stage) {
    if (!stage) return 0;
    if (stage.outcome === 'won') return 100;
    if (stage.outcome === 'lost') return 0;
    return stage.probability ?? 0;
}
