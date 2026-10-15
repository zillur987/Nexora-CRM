import http from './http';

/** Builds multipart FormData (nested arrays/objects use bracket keys, booleans become 1/0, null becomes ''). */
export function toFormData(payload) {
    const fd = new FormData();
    const append = (key, value) => {
        if (value === undefined) return;
        if (value instanceof Blob) fd.append(key, value);
        else if (Array.isArray(value)) value.forEach((v, i) => append(`${key}[${i}]`, v));
        else if (value !== null && typeof value === 'object') Object.entries(value).forEach(([k, v]) => append(`${key}[${k}]`, v));
        else if (typeof value === 'boolean') fd.append(key, value ? '1' : '0');
        else fd.append(key, value ?? '');
    };
    Object.entries(payload).forEach(([k, v]) => append(k, v));
    return fd;
}

// PHP only parses multipart bodies on POST, so updates are POST + _method=PUT.
const save = (url, payload, update = false) => {
    const fd = toFormData(payload);
    if (update) fd.append('_method', 'PUT');
    return http.post(url, fd).then((r) => r.data.data);
};

/** Header menu tree: link | mega > column > item. */
export const menusApi = {
    tree: () => http.get('/menus').then((r) => r.data.data),
    create: (payload) => save('/menus', payload),
    update: (id, payload) => save(`/menus/${id}`, payload, true),
    toggle: (id) => http.patch(`/menus/${id}/toggle`).then((r) => r.data.data),
    remove: (id) => http.delete(`/menus/${id}`),
    /** ids = every sibling id of one level in the new order; parentId = null for the top level. */
    reorder: (parentId, ids) => http.put('/menus/reorder', { parent_id: parentId, ids }),
};

export const bannerSlidesApi = {
    list: () => http.get('/banner-slides').then((r) => r.data.data),
    create: (payload) => save('/banner-slides', payload),
    update: (id, payload) => save(`/banner-slides/${id}`, payload, true),
    toggle: (id) => http.patch(`/banner-slides/${id}/toggle`).then((r) => r.data.data),
    remove: (id) => http.delete(`/banner-slides/${id}`),
    reorder: (ids) => http.put('/banner-slides/reorder', { ids }),
};

/** group = 'header' | 'hero' */
export const siteSettingsApi = {
    get: (group) => http.get(`/site-settings/${group}`).then((r) => r.data.data),
    update: (group, payload) => save(`/site-settings/${group}`, payload, true),
};
