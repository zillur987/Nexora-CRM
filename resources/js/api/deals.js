import http from './http';

export const dealsApi = {
    list: (params) => http.get('/deals', { params }).then((r) => r.data),
    get: (id) => http.get(`/deals/${id}`).then((r) => r.data.data),
    create: (payload) => http.post('/deals', payload).then((r) => r.data.data),
    update: (id, payload) => http.patch(`/deals/${id}`, payload).then((r) => r.data.data),
    changeStage: (id, stage) => http.patch(`/deals/${id}/stage`, { stage }).then((r) => r.data.data),
    remove: (id) => http.delete(`/deals/${id}`),
};
