/**
 * Single source of truth for the company CSV export: the header row and the
 * value readers live side by side so they can never drift apart.
 */

const COLUMNS = [
    ['name', (c) => c.name],
    ['owner', (c) => c.owner?.name],
    ['industry', (c) => c.industry?.name],
    ['company_type', (c) => c.type?.name],
    ['size', (c) => c.size],
    ['annual_revenue', (c) => c.annual_revenue],
    ['currency', (c) => c.currency],
    ['parent_company', (c) => c.parent?.name],
    ['phone', (c) => c.phone],
    ['email', (c) => c.email],
    ['website', (c) => c.website],
    ['linkedin', (c) => c.linkedin],
    ['twitter', (c) => c.twitter],
    ['instagram', (c) => c.instagram],
    ['facebook', (c) => c.facebook],
    ['billing_street', (c) => c.billing_street],
    ['billing_city', (c) => c.billing_city],
    ['billing_zip', (c) => c.billing_zip],
    ['billing_state', (c) => c.billing_state],
    ['billing_country', (c) => c.billing_country],
    ['shipping_street', (c) => c.shipping_street],
    ['shipping_city', (c) => c.shipping_city],
    ['shipping_zip', (c) => c.shipping_zip],
    ['shipping_state', (c) => c.shipping_state],
    ['shipping_country', (c) => c.shipping_country],
    ['description', (c) => c.description],
];

export const COMPANY_CSV_COLUMNS = COLUMNS.map(([header]) => header);

/** One CSV row, in COMPANY_CSV_COLUMNS order. Missing values become empty cells. */
export const companyToCsvRow = (company) => COLUMNS.map(([, read]) => read(company) ?? '');
