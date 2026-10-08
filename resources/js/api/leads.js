import http from './http';

export const leadsApi = {
    /** Returns the paginator payload: { data, links, meta }. */
    list: (params) => http.get('/leads', { params }).then((r) => r.data),
    summary: () => http.get('/leads/summary').then((r) => r.data.data),
    get: (id) => http.get(`/leads/${id}`).then((r) => r.data.data),
    create: (payload) => http.post('/leads', payload).then((r) => r.data.data),
    update: (id, payload) => http.patch(`/leads/${id}`, payload).then((r) => r.data.data),
    changeStatus: (id, status) => http.patch(`/leads/${id}/status`, { status }).then((r) => r.data.data),
    /** Resolves { lead, contact, deal, contact_created }. */
    convert: (id, payload) => http.post(`/leads/${id}/convert`, payload).then((r) => r.data.data),
    remove: (id) => http.delete(`/leads/${id}`),

    import: (file) => {
        const data = new FormData();
        console.log(data, 'data')
        data.append('file', file);
        return http.post('/leads/import', data).then((r) => r.data);
    },
};

