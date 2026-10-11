/**
 * Single source of truth for the quote CSV export: the header row and the
 * value readers live side by side so they can never drift apart.
 */

const COLUMNS = [
    ['quote_number', (q) => q.quote_number],
    ['subject', (q) => q.subject],
    ['status', (q) => q.status],
    ['owner', (q) => q.owner?.name],
    ['company', (q) => q.company?.name],
    ['contact', (q) => q.contact?.name],
    ['deal', (q) => q.deal?.name],
    ['currency', (q) => q.currency],
    ['subtotal', (q) => q.subtotal],
    ['discount_total', (q) => q.discount_total],
    ['tax_total', (q) => q.tax_total],
    ['total', (q) => q.total],
    ['issue_date', (q) => q.issue_date],
    ['valid_until', (q) => q.valid_until],
    ['sent_at', (q) => q.sent_at],
    ['accepted_at', (q) => q.accepted_at],
    ['rejected_at', (q) => q.rejected_at],
];

export const QUOTE_CSV_COLUMNS = COLUMNS.map(([header]) => header);

/** One CSV row, in QUOTE_CSV_COLUMNS order. Missing values become empty cells. */
export const quoteToCsvRow = (quote) => COLUMNS.map(([, read]) => read(quote) ?? '');
