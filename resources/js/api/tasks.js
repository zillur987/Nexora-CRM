import http from './http';

export const tasksApi = {
    list: (params = {}) => http.get('/tasks', { params }).then((r) => r.data),
    /** Resolves { total, open, overdue, due_today, completed }. */
    summary: () => http.get('/tasks/summary').then((r) => r.data.data),
    /** Resolves { users, companies, contacts, opportunities, quotes, types, priorities, statuses, defaults }. */
    lookups: () => http.get('/tasks/lookups').then((r) => r.data.data),
    get: (id) => http.get(`/tasks/${id}`).then((r) => r.data.data),
    create: (payload) => http.post('/tasks', payload).then((r) => r.data.data),
    update: (id, payload) => http.patch(`/tasks/${id}`, payload).then((r) => r.data.data),
    remove: (id) => http.delete(`/tasks/${id}`),
    /** kind = 'type' | 'priority' | 'status'. Resolves the new { id, name, color }. */
    createOption: (kind, payload) => http.post(`/task-options/${kind}`, payload).then((r) => r.data.data),
};
