/**
 * Single source of truth for the deal CSV export: the header row and the
 * value readers live side by side so they can never drift apart.
 */

const COLUMNS = [
    ['name', (d) => d.name],
    ['owner', (d) => d.owner?.name],
    ['company', (d) => d.company?.name],
    ['contact', (d) => d.contact?.name],
    ['amount', (d) => d.amount],
    ['currency', (d) => d.currency],
    ['probability', (d) => d.probability],
    ['weighted_amount', (d) => d.weighted_amount],
    ['deal_stage', (d) => d.stage?.name],
    ['deal_type', (d) => d.type?.name],
    ['lead_source', (d) => d.lead_source?.name],
    ['priority', (d) => d.priority],
    ['expected_close_date', (d) => d.expected_close_date],
    ['actual_close_date', (d) => d.actual_close_date],
    ['next_step', (d) => d.next_step],
    ['lost_reason', (d) => d.lost_reason],
    ['description', (d) => d.description],
];

export const DEAL_CSV_COLUMNS = COLUMNS.map(([header]) => header);

/** One CSV row, in DEAL_CSV_COLUMNS order. Missing values become empty cells. */
export const dealToCsvRow = (deal) => COLUMNS.map(([, read]) => read(deal) ?? '');
