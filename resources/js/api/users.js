import http from './http';

export const usersApi = {
    options: () => http.get('/users/options').then((r) => r.data.data),
};
