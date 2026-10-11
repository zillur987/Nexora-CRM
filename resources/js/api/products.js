import http from './http';

export const productsApi = {
    list: (params = {}) => http.get('/products', { params }).then((r) => r.data),
    /** Resolves { total, active, low_stock, by_type: { physical, digital, service }, totals: [{ currency, stock_cost, stock_retail }] }. */
    summary: () => http.get('/products/summary').then((r) => r.data.data),
    lookups: () => http.get('/products/lookups').then((r) => r.data.data),
    get: (id) => http.get(`/products/${id}`).then((r) => r.data.data),
    create: (payload) => http.post('/products', payload).then((r) => r.data.data),
    update: (id, payload) => http.patch(`/products/${id}`, payload).then((r) => r.data.data),
    remove: (id) => http.delete(`/products/${id}`),
};
