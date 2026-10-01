import { defineStore } from 'pinia';

export const useToastStore = defineStore('toast', {
    state: () => ({ items: [], seq: 0 }),

    actions: {
        push(type, message, timeout = 4000) {
            const id = ++this.seq;
            this.items.push({ id, type, message });
            setTimeout(() => this.dismiss(id), timeout);
        },
        success(message) {
            this.push('success', message);
        },
        error(message) {
            this.push('danger', message, 6000);
        },
        dismiss(id) {
            this.items = this.items.filter((i) => i.id !== id);
        },
    },
});
