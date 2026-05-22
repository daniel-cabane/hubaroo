import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

const JUMP_STORAGE_KEY = 'hubaroo_active_jump_attempt';

export const useJumpAttemptStore = defineStore('jumpAttempt', () => {
  const attempt = ref(null);
  const isLoading = ref(false);
  const error = ref(null);
  const activeJumpRecovery = ref(null);

  const isInProgress = computed(() => attempt.value?.status === 'inProgress');
  const isFinished = computed(() => attempt.value?.status === 'finished');

  function saveToLocalStorage(attemptId, jumpId) {
    localStorage.setItem(JUMP_STORAGE_KEY, JSON.stringify({ attempt_id: attemptId, jump_id: jumpId }));
  }

  function getFromLocalStorage() {
    const stored = localStorage.getItem(JUMP_STORAGE_KEY);
    return stored ? JSON.parse(stored) : null;
  }

  function clearLocalStorage() {
    localStorage.removeItem(JUMP_STORAGE_KEY);
  }

  async function startAttempt(jumpId) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.post(`/api/jumps/${jumpId}/attempts`);
      attempt.value = response.data.attempt;
      if (response.data.attempt?.status === 'inProgress') {
        saveToLocalStorage(response.data.attempt.id, jumpId);
      }
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to start attempt';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchAttempt(attemptId) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.get(`/api/jump-attempts/${attemptId}`);
      attempt.value = response.data.attempt;
      return response.data.attempt;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch attempt';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateAnswer(attemptId, questionIndex, answer, timer) {
    error.value = null;
    try {
      const response = await axios.patch(`/api/jump-attempts/${attemptId}/answer`, {
        question_index: questionIndex,
        answer,
        timer,
      });
      attempt.value = response.data.attempt;
      return response.data.attempt;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update answer';
      throw err;
    }
  }

  async function submitAttempt(attemptId, timer, termination = 'submitted') {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.post(`/api/jump-attempts/${attemptId}/submit`, {
        timer,
        termination,
      });
      attempt.value = response.data.attempt;
      clearLocalStorage();
      activeJumpRecovery.value = null;
      return response.data.attempt;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to submit attempt';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function createRejoinDemand(attemptId) {
    error.value = null;
    try {
      const response = await axios.post(`/api/jump-attempts/${attemptId}/rejoin-demand`);
      return response.data.demand;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create rejoin demand';
      throw err;
    }
  }

  async function checkJumpRecovery() {
    const stored = getFromLocalStorage();
    if (!stored) {
      activeJumpRecovery.value = null;
      return;
    }
    try {
      const response = await axios.get(`/api/jump-attempts/${stored.attempt_id}`);
      const recovered = response.data.attempt;
      if (recovered?.status === 'inProgress') {
        activeJumpRecovery.value = stored;
      } else {
        clearLocalStorage();
        activeJumpRecovery.value = null;
      }
    } catch {
      clearLocalStorage();
      activeJumpRecovery.value = null;
    }
  }

  function dismissJumpRecovery() {
    clearLocalStorage();
    activeJumpRecovery.value = null;
  }

  return {
    attempt,
    isLoading,
    error,
    isInProgress,
    isFinished,
    activeJumpRecovery,
    startAttempt,
    fetchAttempt,
    updateAnswer,
    submitAttempt,
    createRejoinDemand,
    checkJumpRecovery,
    dismissJumpRecovery,
    clearLocalStorage,
  };
});
