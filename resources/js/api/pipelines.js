import http from './http';

export const pipelinesApi = {
    list: (params = {}) => http.get('/pipelines', { params }).then((r) => r.data),
    get: (id) => http.get(`/pipelines/${id}`).then((r) => r.data.data),
    create: (payload) => http.post('/pipelines', payload).then((r) => r.data.data),
    update: (id, payload) => http.patch(`/pipelines/${id}`, payload).then((r) => r.data.data),
    remove: (id) => http.delete(`/pipelines/${id}`),
    stages: (id) => http.get(`/pipelines/${id}/stages`).then((r) => r.data.data),
    createStage: (id, payload) => http.post(`/pipelines/${id}/stages`, payload).then((r) => r.data.data),
    updateStage: (pipelineId, stageId, payload) => http.patch(`/pipelines/${pipelineId}/stages/${stageId}`, payload).then((r) => r.data.data),
    removeStage: (pipelineId, stageId) => http.delete(`/pipelines/${pipelineId}/stages/${stageId}`),
};
