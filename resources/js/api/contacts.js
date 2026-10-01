import http from './http';

export const contactsApi = {
    /** Returns the paginator payload: { data, links, meta }. */
    list: (params) => http.get('/contacts', { params }).then((r) => r.data),
    get: (id) => http.get(`/contacts/${id}`).then((r) => r.data.data),
    create: (payload) => http.post('/contacts', payload).then((r) => r.data.data),
    update: (id, payload) => http.patch(`/contacts/${id}`, payload).then((r) => r.data.data),
    remove: (id) => http.delete(`/contacts/${id}`),
    options: () => http.get('/contacts/options').then((r) => r.data.data),
};
