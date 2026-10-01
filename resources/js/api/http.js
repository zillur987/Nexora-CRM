import axios from 'axios';

export const TOKEN_KEY = 'crm_token';

const http = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
});

http.interceptors.request.use((config) => {
    const token = localStorage.getItem(TOKEN_KEY);
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

http.interceptors.response.use(
    (response) => response,
    (error) => {
        const isLogin = error.config?.url?.includes('auth/login');
        if (error.response?.status === 401 && !isLogin) {
            window.dispatchEvent(new Event('auth:expired'));
        }
        return Promise.reject(error);
    },
);

export default http;
