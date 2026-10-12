import http from './http';

/** Roles, the permission matrix, the signed-in user's access, and per-user role assignment. */
export const rolesApi = {
    list: (params = {}) => http.get('/roles', { params }).then((r) => r.data),
    /** Resolves { total, custom, permissions, users_without_role }. */
    summary: () => http.get('/roles/summary').then((r) => r.data.data),
    get: (id) => http.get(`/roles/${id}`).then((r) => r.data.data),
    create: (payload) => http.post('/roles', payload).then((r) => r.data.data),
    update: (id, payload) => http.patch(`/roles/${id}`, payload).then((r) => r.data.data),
    remove: (id) => http.delete(`/roles/${id}`),
    /** Resolves [{ module, label, icon, permissions: [{ id, name, action, label }] }]. */
    permissions: () => http.get('/permissions').then((r) => r.data.data),
};

export const accessApi = {
    /** Resolves { is_super_admin, roles, permissions: ['products.view', ...] } for the signed-in user. */
    me: () => http.get('/auth/permissions').then((r) => r.data.data),
};

export const userAccessApi = {
    list: (params = {}) => http.get('/access/users', { params }).then((r) => r.data),
    /** Resolves { total, with_roles, without_roles, super_admins }. */
    summary: () => http.get('/access/users/summary').then((r) => r.data.data),
    lookups: () => http.get('/access/users/lookups').then((r) => r.data.data),
    get: (id) => http.get(`/access/users/${id}`).then((r) => r.data.data),
    updateRoles: (id, roleIds) => http.put(`/access/users/${id}/roles`, { role_ids: roleIds }).then((r) => r.data.data),
};
