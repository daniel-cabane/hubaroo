import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

const JUMP_STORAGE_KEY = 'hubaroo_active_jump_attempt';
export const JUMP_ATTEMPT_SYNC_INTERVAL_MS = 3000;

export const useJumpAttemptStore = defineStore('jumpAttempt', () => {
  const attempt = ref(null);
  const isLoading = ref(false);
  const error = ref(null);
  const activeJumpRecovery = ref(null);
  const pendingAnswerChanges = ref({});
  const isSyncingAnswers = ref(false);

  let flushQueued = false;
  let queuedTimer = null;

  const isInProgress = computed(() => attempt.value?.status === 'inProgress');
  const isFinished = computed(() => attempt.value?.status === 'finished');

  function resetAnswerSyncState() {
    pendingAnswerChanges.value = {};
    isSyncingAnswers.value = false;
    flushQueued = false;
    queuedTimer = null;
  }

  function hasPendingAnswerChanges() {
    return Object.keys(pendingAnswerChanges.value).length > 0;
  }

  function queueAnswerChange(questionIndex, answer) {
    pendingAnswerChanges.value = {
      ...pendingAnswerChanges.value,
      [questionIndex]: answer,
    };
  }

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
      resetAnswerSyncState();
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
      resetAnswerSyncState();
      return response.data.attempt;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch attempt';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

    async function flushAnswerChanges(attemptId, timer) {
    error.value = null;

    if (!attemptId) {
      return false;
    }

    if (isSyncingAnswers.value) {
      flushQueued = true;
      queuedTimer = timer;
      return false;
    }

    if (!hasPendingAnswerChanges()) {
      return false;
    }

    const changeSnapshot = { ...pendingAnswerChanges.value };
    const changes = Object.entries(changeSnapshot).map(([questionIndex, answer]) => ({
      question_index: Number(questionIndex),
      answer,
    }));

    pendingAnswerChanges.value = {};
    isSyncingAnswers.value = true;

    let jumpClosed = false;

    try {
      const response = await axios.patch(`/api/jump-attempts/${attemptId}/sync`, {
        timer,
        changes,
      });

      jumpClosed = Boolean(response.data?.jump_closed);

      return {
        jumpClosed,
        saved: response.data?.saved !== false,
      };
    } catch (err) {
      pendingAnswerChanges.value = {
        ...changeSnapshot,
        ...pendingAnswerChanges.value,
      };
      error.value = err.response?.data?.message || 'Failed to update answer';
      throw err;
    } finally {
      const shouldFlushAgain = !jumpClosed && flushQueued && Object.keys(pendingAnswerChanges.value).length > 0;
      const nextTimer = queuedTimer ?? timer;
      isSyncingAnswers.value = false;
      flushQueued = false;
      queuedTimer = null;

      if (shouldFlushAgain) {
        void flushAnswerChanges(attemptId, nextTimer);
      }
    }
  }

  async function submitAttempt(attemptId, timer, termination = 'submitted', questionList = null) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.post(`/api/jump-attempts/${attemptId}/submit`, {
        timer,
        termination,
        question_list: questionList,
      });
      attempt.value = response.data.attempt;
      resetAnswerSyncState();
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
    pendingAnswerChanges,
    isSyncingAnswers,
    startAttempt,
    fetchAttempt,
    queueAnswerChange,
    flushAnswerChanges,
    hasPendingAnswerChanges,
    submitAttempt,
    createRejoinDemand,
    checkJumpRecovery,
    dismissJumpRecovery,
    clearLocalStorage,
    resetAnswerSyncState,
  };
});
