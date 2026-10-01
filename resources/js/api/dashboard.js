import http from './http';

export const dashboardApi = {
    get: () => http.get('/dashboard').then((r) => r.data.data),
};
