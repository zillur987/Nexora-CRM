import http from './http';

export const pipelinesApi = {
    list: (params = {}) => http.get('/pipelines', { params }).then((r) => r.data),
    get: (id) => http.get(`/pipelines/${id}`).then((r) => r.data.data),
    create: (payload) => http.post('/pipelines', payload).then((r) => r.data.data),
    update: (id, payload) => http.patch(`/pipelines/${id}`, payload).then((r) => r.data.data),
    remove: (id) => http.delete(`/pipelines/${id}`),
    board: (id) => http.get(`/pipelines/${id}/board`).then((r) => r.data.data),
    createStage: (pipelineId, payload) => http.post(`/pipelines/${pipelineId}/stages`, payload).then((r) => r.data.data),
    updateStage: (pipelineId, stageId, payload) => http.patch(`/pipelines/${pipelineId}/stages/${stageId}`, payload).then((r) => r.data.data),
    removeStage: (pipelineId, stageId) => http.delete(`/pipelines/${pipelineId}/stages/${stageId}`),
    reorderStages: (pipelineId, stages) => http.post(`/pipelines/${pipelineId}/stages/reorder`, { stages }),
};
