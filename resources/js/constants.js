export const CONTACT_STATUSES = [
    { value: 'lead', label: 'Lead', color: 'info' },
    { value: 'active', label: 'Active', color: 'success' },
    { value: 'inactive', label: 'Inactive', color: 'secondary' },
];

export const LEAD_STATUSES = [
    { value: 'new', label: 'New', color: 'secondary' },
    { value: 'contacted', label: 'Contacted', color: 'info' },
    { value: 'qualified', label: 'Qualified', color: 'primary' },
    { value: 'unqualified', label: 'Unqualified', color: 'danger' },
    { value: 'converted', label: 'Converted', color: 'success' },
];

export const LEAD_SOURCES = [
    { value: 'website', label: 'Website' },
    { value: 'referral', label: 'Referral' },
    { value: 'social_media', label: 'Social media' },
    { value: 'email_campaign', label: 'Email campaign' },
    { value: 'cold_call', label: 'Cold call' },
    { value: 'event', label: 'Event' },
    { value: 'other', label: 'Other' },
];

export const DEAL_STAGES = [
    { value: 'new', label: 'New', color: 'secondary' },
    { value: 'qualified', label: 'Qualified', color: 'info' },
    { value: 'proposal', label: 'Proposal', color: 'primary' },
    { value: 'negotiation', label: 'Negotiation', color: 'warning' },
    { value: 'won', label: 'Won', color: 'success' },
    { value: 'lost', label: 'Lost', color: 'danger' },
];

// Add the new code right here, after LEAD_STATUSES
const LEAD_STATUS_COLORS = {
    new: 'primary',
    contacted: 'info',
    qualified: 'success',
    unqualified: 'secondary',
    lost: 'danger',
    converted: 'success',
};

/* -------------------------------------------------------------------------- */
/* Companies                                                                  */
/* -------------------------------------------------------------------------- */

export const COMPANY_SIZES = [
    { value: '1-10', label: '1–10 employees' },
    { value: '11-50', label: '11–50 employees' },
    { value: '51-200', label: '51–200 employees' },
    { value: '201-500', label: '201–500 employees' },
    { value: '501-1000', label: '501–1,000 employees' },
    { value: '1001-5000', label: '1,001–5,000 employees' },
    { value: '5001+', label: '5,001+ employees' },
];

/** The happy path shown as a stepper (Lost is a side exit). */
export const DEAL_FLOW = ['new', 'qualified', 'proposal', 'negotiation', 'won'];

const byValue = (list) => Object.fromEntries(list.map((i) => [i.value, i]));

export const contactStatusMeta = byValue(CONTACT_STATUSES);
export const dealStageMeta = byValue(DEAL_STAGES);
export const leadStatusMeta = byValue(LEAD_STATUSES);
export const leadSourceMeta = byValue(LEAD_SOURCES);

export const CURRENCIES = ['USD', 'EUR', 'GBP', 'BDT', 'INR', 'AED'];
export const companySizeMeta = byValue(COMPANY_SIZES);
