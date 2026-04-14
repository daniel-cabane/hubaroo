import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useKangourouSessionStore = defineStore('kangourouSession', () => {
  const session = ref(null);
  const papers = ref([]);
  const mySessions = ref([]);
  const isLoading = ref(false);
  const error = ref(null);

  async function fetchPapers() {
    isLoading.value = true;
    try {
      const response = await axios.get('/api/papers');
      papers.value = response.data.papers;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch papers';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function createSession(paperId, privacy = 'public', preferences = null) {
    isLoading.value = true;
    error.value = null;
    try {
      const payload = { paper_id: paperId, privacy };
      if (preferences) {
        payload.preferences = preferences;
      }
      const response = await axios.post('/api/kangourou-sessions', payload);
      session.value = response.data.session;
      return response.data.session;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create session';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchSession(code, { attemptId } = {}) {
    isLoading.value = true;
    error.value = null;
    try {
      const params = {};
      if (attemptId) {
        params.attempt_id = attemptId;
      }
      const response = await axios.get(`/api/kangourou-sessions/${code}`, { params });
      session.value = response.data.session;
      return response.data.session;
    } catch (err) {
      error.value = err.response?.data?.message || 'Session not found';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function activateSession(sessionId) {
    isLoading.value = true;
    try {
      const response = await axios.patch(`/api/kangourou-sessions/${sessionId}/activate`);
      session.value = response.data.session;
      return response.data.session;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to activate session';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchMySessions() {
    isLoading.value = true;
    try {
      const response = await axios.get('/api/my/kangourou-sessions');
      mySessions.value = response.data.sessions;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch sessions';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchSessionDetails(sessionId) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.get(`/api/kangourou-sessions/${sessionId}/details`);
      session.value = response.data.session;
      return response.data.session;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch session details';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateSession(sessionId, data) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.patch(`/api/kangourou-sessions/${sessionId}`, data);
      const updated = response.data.session;
      const idx = mySessions.value.findIndex(s => s.id === sessionId);
      if (idx !== -1) {
        mySessions.value[idx] = updated;
      }
      return updated;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update session';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function changeSessionCode(sessionId) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.patch(`/api/kangourou-sessions/${sessionId}/change-code`);
      const updated = response.data.session;
      const idx = mySessions.value.findIndex(s => s.id === sessionId);
      if (idx !== -1) {
        mySessions.value[idx] = updated;
      }
      return updated;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to change session code';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  function clearError() {
    error.value = null;
  }

  return {
    session,
    papers,
    mySessions,
    isLoading,
    error,
    fetchPapers,
    createSession,
    fetchSession,
    fetchSessionDetails,
    activateSession,
    fetchMySessions,
    updateSession,
    changeSessionCode,
    clearError,
  };
});
