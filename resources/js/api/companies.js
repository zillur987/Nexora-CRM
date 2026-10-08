import http from './http';

export const companiesApi = {
    /** Returns the paginator payload: { data, links, meta }. */
    list: (params) => http.get('/companies', { params }).then((r) => r.data),
    /** Resolves { total, by_type: { [typeId]: count } }. */
    summary: () => http.get('/companies/summary').then((r) => r.data.data),
    /** id + name pairs for the parent-company dropdown; pass { exclude } to leave one out. */
    options: (params) => http.get('/companies/options', { params }).then((r) => r.data.data),
    get: (id) => http.get(`/companies/${id}`).then((r) => r.data.data),
    create: (payload) => http.post('/companies', payload).then((r) => r.data.data),
    update: (id, payload) => http.patch(`/companies/${id}`, payload).then((r) => r.data.data),
    remove: (id) => http.delete(`/companies/${id}`),
};

/** The user-extensible dropdowns. `kind` is 'industries' or 'company-types'. */
export const companyLookupsApi = {
    list: (kind) => http.get(`/company-lookups/${kind}`).then((r) => r.data.data),
    create: (kind, name) => http.post(`/company-lookups/${kind}`, { name }).then((r) => r.data.data),
};
