/**
 * Single source of truth for the contact CSV export: the header row and the
 * value readers live side by side so they can never drift apart.
 */

const COLUMNS = [
    ['first_name', (c) => c.first_name],
    ['last_name', (c) => c.last_name],
    ['owner', (c) => c.owner?.name],
    ['email', (c) => c.email],
    ['birthday', (c) => c.birthday],
    ['company', (c) => c.company?.name],
    ['job_title', (c) => c.job_title],
    ['phone', (c) => c.phone],
    ['department', (c) => c.department],
    ['industry', (c) => c.industry?.name],
    ['contact_source', (c) => c.source?.name],
    ['contact_stage', (c) => c.stage?.name],
    ['present_address', (c) => c.present_address],
    ['present_city', (c) => c.present_city],
    ['present_zip', (c) => c.present_zip],
    ['present_state', (c) => c.present_state],
    ['present_country', (c) => c.present_country],
    ['permanent_address', (c) => c.permanent_address],
    ['permanent_city', (c) => c.permanent_city],
    ['permanent_zip', (c) => c.permanent_zip],
    ['permanent_state', (c) => c.permanent_state],
    ['permanent_country', (c) => c.permanent_country],
    ['twitter', (c) => c.twitter],
    ['linkedin', (c) => c.linkedin],
    ['description', (c) => c.description],
];

export const CONTACT_CSV_COLUMNS = COLUMNS.map(([header]) => header);

/** One CSV row, in CONTACT_CSV_COLUMNS order. Missing values become empty cells. */
export const contactToCsvRow = (contact) => COLUMNS.map(([, read]) => read(contact) ?? '');
