import http from './http';

export const contactsApi = {
    /** Returns the paginator payload: { data, links, meta }. */
    list: (params) => http.get('/contacts', { params }).then((r) => r.data),
    /** Resolves { total, by_stage: { [stageId]: count } }. */
    summary: () => http.get('/contacts/summary').then((r) => r.data.data),
    /** id + full-name pairs for contact dropdowns (e.g. the deal form). */
    options: (params) => http.get('/contacts/options', { params }).then((r) => r.data.data),
    get: (id) => http.get(`/contacts/${id}`).then((r) => r.data.data),
    create: (payload) => http.post('/contacts', payload).then((r) => r.data.data),
    update: (id, payload) => http.patch(`/contacts/${id}`, payload).then((r) => r.data.data),
    remove: (id) => http.delete(`/contacts/${id}`),
};

/** The user-extensible dropdowns. `kind` is 'contact-sources' or 'contact-stages'. (Industries use companyLookupsApi.) */
export const contactLookupsApi = {
    list: (kind) => http.get(`/contact-lookups/${kind}`).then((r) => r.data.data),
    create: (kind, name) => http.post(`/contact-lookups/${kind}`, { name }).then((r) => r.data.data),
};
