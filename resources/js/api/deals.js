import http from './http';

export const dealsApi = {
    /** Returns the paginator payload: { data, links, meta }. */
    list: (params) => http.get('/deals', { params }).then((r) => r.data),
    /** Resolves { total, by_stage: { [stageId]: count }, totals: [{ currency, open_amount, weighted_amount, won_amount, open_count }] }. */
    summary: () => http.get('/deals/summary').then((r) => r.data.data),
    get: (id) => http.get(`/deals/${id}`).then((r) => r.data.data),
    create: (payload) => http.post('/deals', payload).then((r) => r.data.data),
    update: (id, payload) => http.patch(`/deals/${id}`, payload).then((r) => r.data.data),
    remove: (id) => http.delete(`/deals/${id}`),
};

/**
 * The user-extensible dropdowns. `kind` is 'deal-stages' or 'deal-types'.
 * Stages resolve { id, name, probability, outcome, sort_order } in pipeline order.
 * (Lead sources use contactLookupsApi with 'contact-sources'.)
 */
export const dealLookupsApi = {
    list: (kind) => http.get(`/deal-lookups/${kind}`).then((r) => r.data.data),
    create: (kind, name) => http.post(`/deal-lookups/${kind}`, { name }).then((r) => r.data.data),
};
