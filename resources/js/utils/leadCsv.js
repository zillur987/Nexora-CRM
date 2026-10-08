/**
 * Single source of truth for the lead CSV format.
 * The import contract and the export both read from here, so a file
 * downloaded from the leads list can be imported again unchanged.
 */

export const LEAD_CSV_REQUIRED = ['first_name', 'last_name', 'email'];

export const LEAD_CSV_OPTIONAL = [
    'phone',
    'company',
    'job_title',
    'source',
    'score',
    'estimated_value',
    'currency',
    'owner_email',
    'notes',
];

export const LEAD_CSV_COLUMNS = [...LEAD_CSV_REQUIRED, ...LEAD_CSV_OPTIONAL];

/** How each CSV column is read from a lead returned by the API. */
const VALUE = {
    first_name: (lead) => lead.first_name,
    last_name: (lead) => lead.last_name,
    email: (lead) => lead.email,
    phone: (lead) => lead.phone,
    company: (lead) => lead.company,
    job_title: (lead) => lead.job_title,
    source: (lead) => lead.source, // raw value (not the label) so it imports back
    score: (lead) => lead.score,
    estimated_value: (lead) => lead.estimated_value,
    currency: (lead) => lead.currency,
    owner_email: (lead) => lead.owner?.email,
    notes: (lead) => lead.notes,
};

/** One CSV row, in LEAD_CSV_COLUMNS order. Missing values become empty cells. */
export const leadToCsvRow = (lead) => LEAD_CSV_COLUMNS.map((column) => VALUE[column]?.(lead) ?? '');