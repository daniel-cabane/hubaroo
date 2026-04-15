import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useDivisionStore = defineStore('division', () => {
  const divisions = ref([]);
  const division = ref(null);
  const invites = ref([]);
  const isLoading = ref(false);
  const error = ref(null);

  async function fetchMyDivisions() {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.get('/api/my/divisions');
      divisions.value = response.data.divisions;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch divisions';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchDivision(id) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.get(`/api/divisions/${id}`);
      division.value = response.data.division;
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch division';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function createDivision(name) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.post('/api/divisions', { name });
      divisions.value.unshift(response.data.division);
      return response.data.division;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create division';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateDivision(id, data) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.patch(`/api/divisions/${id}`, data);
      division.value = response.data.division;
      const idx = divisions.value.findIndex(d => d.id === id);
      if (idx !== -1) {
        divisions.value[idx] = response.data.division;
      }
      return response.data.division;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update division';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function deleteDivision(id) {
    isLoading.value = true;
    error.value = null;
    try {
      await axios.delete(`/api/divisions/${id}`);
      divisions.value = divisions.value.filter(d => d.id !== id);
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete division';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function changeCode(id) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.patch(`/api/divisions/${id}/change-code`);
      division.value = response.data.division;
      return response.data.division;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to change code';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function inviteStudent(divisionId, email) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.post(`/api/divisions/${divisionId}/invite`, { email });
      return response.data.invite;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to send invite';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function removeStudent(divisionId, studentId) {
    isLoading.value = true;
    error.value = null;
    try {
      await axios.delete(`/api/divisions/${divisionId}/students/${studentId}`);
      if (division.value) {
        division.value.students = division.value.students.filter(s => s.id !== studentId);
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to remove student';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function joinDivision(code) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.post('/api/divisions/join', { code });
      divisions.value.unshift(response.data.division);
      return response.data.division;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to join class';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchMyInvites() {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.get('/api/my/invites');
      invites.value = response.data.invites;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch invites';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function acceptInvite(inviteId) {
    isLoading.value = true;
    error.value = null;
    try {
      await axios.post(`/api/invites/${inviteId}/accept`);
      invites.value = invites.value.filter(i => i.id !== inviteId);
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to accept invite';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function declineInvite(inviteId) {
    isLoading.value = true;
    error.value = null;
    try {
      await axios.post(`/api/invites/${inviteId}/decline`);
      invites.value = invites.value.filter(i => i.id !== inviteId);
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to decline invite';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function openSessionForDivision(sessionId, divisionId) {
    isLoading.value = true;
    error.value = null;
    try {
      await axios.post(`/api/kangourou-sessions/${sessionId}/divisions/${divisionId}`);
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to open session for division';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function closeSessionForDivision(sessionId, divisionId) {
    isLoading.value = true;
    error.value = null;
    try {
      await axios.delete(`/api/kangourou-sessions/${sessionId}/divisions/${divisionId}`);
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to close session for division';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  function clearError() {
    error.value = null;
  }

  return {
    divisions,
    division,
    invites,
    isLoading,
    error,
    fetchMyDivisions,
    fetchDivision,
    createDivision,
    updateDivision,
    deleteDivision,
    changeCode,
    inviteStudent,
    removeStudent,
    joinDivision,
    fetchMyInvites,
    acceptInvite,
    declineInvite,
    openSessionForDivision,
    closeSessionForDivision,
    clearError,
  };
});
