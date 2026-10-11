import http from './http';

export const quotesApi = {
    /** Returns the paginator payload: { data, links, meta }. */
    list: (params) => http.get('/quotes', { params }).then((r) => r.data),
    /** Resolves { total, by_status: { [status]: count }, acceptance_rate, totals: [{ currency, open_amount, open_count, accepted_amount }] }. */
    summary: () => http.get('/quotes/summary').then((r) => r.data.data),
    get: (id) => http.get(`/quotes/${id}`).then((r) => r.data.data),
    create: (payload) => http.post('/quotes', payload).then((r) => r.data.data),
    update: (id, payload) => http.patch(`/quotes/${id}`, payload).then((r) => r.data.data),
    /** Quick status change: 'draft' | 'sent' | 'accepted' | 'rejected'. */
    setStatus: (id, status) => http.post(`/quotes/${id}/status`, { status }).then((r) => r.data.data),
    remove: (id) => http.delete(`/quotes/${id}`),
};
