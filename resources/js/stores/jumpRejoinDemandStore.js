import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useJumpRejoinDemandStore = defineStore('jumpRejoinDemand', () => {
    const demands = ref([]);
    const isLoading = ref(false);
    const error = ref(null);

    const studentDemands = ref([]);

    async function fetchMyDemands() {
        isLoading.value = true;
        error.value = null;
        try {
            const response = await axios.get('/api/my/jump-rejoin-demands');
            demands.value = response.data.demands;
        } catch (err) {
            error.value = err.response?.data?.message || 'Failed to fetch jump rejoin demands';
        } finally {
            isLoading.value = false;
        }
    }

    async function approveDemand(demandId, extraTime = 0) {
        error.value = null;
        try {
            await axios.post(`/api/jump-rejoin-demands/${demandId}/approve`, { extra_time: extraTime });
            demands.value = demands.value.filter(d => d.id !== demandId);
        } catch (err) {
            error.value = err.response?.data?.message || 'Failed to approve demand';
            throw err;
        }
    }

    async function rejectDemand(demandId) {
        error.value = null;
        try {
            await axios.delete(`/api/jump-rejoin-demands/${demandId}`);
            demands.value = demands.value.filter(d => d.id !== demandId);
        } catch (err) {
            error.value = err.response?.data?.message || 'Failed to reject demand';
            throw err;
        }
    }

    function addDemand(demand) {
        const exists = demands.value.some(d => d.id === demand.id);
        if (!exists) {
            demands.value.unshift(demand);
        }
    }

    function addStudentDemand({ id, attemptId, jumpId }) {
        const exists = studentDemands.value.some(d => d.id === id);
        if (!exists) {
            studentDemands.value.unshift({ id, attemptId, jumpId, status: 'pending', extraTime: 0 });
        }
    }

    function resolveStudentDemand(demandId, resolution, extraTime = 0) {
        const idx = studentDemands.value.findIndex(d => d.id === demandId);
        if (idx !== -1) {
            studentDemands.value.splice(idx, 1, { ...studentDemands.value[idx], status: resolution, extraTime });
        }
    }

    function removeStudentDemand(demandId) {
        studentDemands.value = studentDemands.value.filter(d => d.id !== demandId);
    }

    return {
        demands,
        studentDemands,
        isLoading,
        error,
        fetchMyDemands,
        approveDemand,
        rejectDemand,
        addDemand,
        addStudentDemand,
        resolveStudentDemand,
        removeStudentDemand,
    };
});
