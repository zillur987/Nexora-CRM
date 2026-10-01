export const CONTACT_STATUSES = [
    { value: 'lead', label: 'Lead', color: 'info' },
    { value: 'active', label: 'Active', color: 'success' },
    { value: 'inactive', label: 'Inactive', color: 'secondary' },
];

export const DEAL_STAGES = [
    { value: 'new', label: 'New', color: 'secondary' },
    { value: 'qualified', label: 'Qualified', color: 'info' },
    { value: 'proposal', label: 'Proposal', color: 'primary' },
    { value: 'negotiation', label: 'Negotiation', color: 'warning' },
    { value: 'won', label: 'Won', color: 'success' },
    { value: 'lost', label: 'Lost', color: 'danger' },
];

/** The happy path shown as a stepper (Lost is a side exit). */
export const DEAL_FLOW = ['new', 'qualified', 'proposal', 'negotiation', 'won'];

const byValue = (list) => Object.fromEntries(list.map((i) => [i.value, i]));

export const contactStatusMeta = byValue(CONTACT_STATUSES);
export const dealStageMeta = byValue(DEAL_STAGES);
