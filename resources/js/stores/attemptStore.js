import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

const STORAGE_KEY = 'hubaroo_active_attempt';
const GUEST_ATTEMPTS_KEY = 'hubaroo_guest_attempts';
export const ATTEMPT_SYNC_INTERVAL_MS = 3000;

export const useAttemptStore = defineStore('attempt', () => {
  const attempt = ref(null);
  const myAttempts = ref([]);
  const guestAttempts = ref([]);
  const isLoading = ref(false);
  const error = ref(null);
  const activeRecovery = ref(null);
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

  function saveToLocalStorage(attemptData, sessionCode) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      attempt_id: attemptData.id,
      attempt_code: attemptData.code,
      session_code: sessionCode,
    }));
  }

  function getFromLocalStorage() {
    const stored = localStorage.getItem(STORAGE_KEY);
    return stored ? JSON.parse(stored) : null;
  }

  function clearLocalStorage() {
    localStorage.removeItem(STORAGE_KEY);
  }

  function getGuestAttemptIds() {
    const stored = localStorage.getItem(GUEST_ATTEMPTS_KEY);
    return stored ? JSON.parse(stored) : [];
  }

  function addGuestAttemptId(attemptId) {
    const ids = getGuestAttemptIds();
    if (!ids.includes(attemptId)) {
      ids.push(attemptId);
      localStorage.setItem(GUEST_ATTEMPTS_KEY, JSON.stringify(ids));
    }
  }

  function removeGuestAttemptIds(attemptIds) {
    const ids = getGuestAttemptIds().filter(id => !attemptIds.includes(id));
    if (ids.length === 0) {
      localStorage.removeItem(GUEST_ATTEMPTS_KEY);
    } else {
      localStorage.setItem(GUEST_ATTEMPTS_KEY, JSON.stringify(ids));
    }
    guestAttempts.value = guestAttempts.value.filter(a => !attemptIds.includes(a.id));
  }

  async function fetchGuestAttempts() {
    const ids = getGuestAttemptIds();
    if (ids.length === 0) {
      guestAttempts.value = [];
      return;
    }
    try {
      const results = await Promise.all(
        ids.map(id => axios.get(`/api/attempts/${id}`).then(r => r.data.attempt).catch(() => null))
      );
      guestAttempts.value = results.filter(Boolean);
      const validIds = guestAttempts.value.map(a => a.id);
      localStorage.setItem(GUEST_ATTEMPTS_KEY, JSON.stringify(validIds));
    } catch {
      guestAttempts.value = [];
    }
  }

  async function claimAttempts(attemptIds) {
    try {
      const response = await axios.post('/api/attempts/claim', { attempt_ids: attemptIds });
      removeGuestAttemptIds(attemptIds);
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to claim attempts';
      throw err;
    }
  }

  async function createAttempt(sessionCode, name = null) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.post(`/api/kangourou-sessions/${sessionCode}/attempts`, {
        name,
      });
      attempt.value = response.data.attempt;
      resetAnswerSyncState();
      saveToLocalStorage(response.data.attempt, sessionCode);
      if (!response.data.attempt.user_id) {
        addGuestAttemptId(response.data.attempt.id);
      }
      return response.data.attempt;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create attempt';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchAttempt(attemptId) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.get(`/api/attempts/${attemptId}`);
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

    try {
      await axios.patch(`/api/attempts/${attemptId}/sync`, {
        timer,
        changes,
      });
      return true;
    } catch (err) {
      pendingAnswerChanges.value = {
        ...changeSnapshot,
        ...pendingAnswerChanges.value,
      };
      error.value = err.response?.data?.message || 'Failed to update answer';
      throw err;
    } finally {
      const shouldFlushAgain = flushQueued && Object.keys(pendingAnswerChanges.value).length > 0;
      const nextTimer = queuedTimer ?? timer;
      isSyncingAnswers.value = false;
      flushQueued = false;
      queuedTimer = null;

      if (shouldFlushAgain) {
        void flushAnswerChanges(attemptId, nextTimer);
      }
    }
  }

  async function submitAttempt(attemptId, timer = null, termination = 'submitted', answers = null) {
    isLoading.value = true;
    error.value = null;
    try {
      const payload = {
        timer,
        termination,
      };
      if (answers) {
        payload.answers = answers;
      }
      const response = await axios.post(`/api/attempts/${attemptId}/submit`, payload);
      attempt.value = response.data.attempt;
      resetAnswerSyncState();
      clearLocalStorage();
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to submit attempt';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function recoverAttempt(code) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.get(`/api/attempts/recover/${code}`);
      attempt.value = response.data.attempt;
      resetAnswerSyncState();
      return response.data.attempt;
    } catch (err) {
      error.value = err.response?.data?.message || 'Attempt not found';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchMyAttempts() {
    isLoading.value = true;
    try {
      const response = await axios.get('/api/my/attempts');
      myAttempts.value = response.data.attempts;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch attempts';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  function clearError() {
    error.value = null;
  }

  async function checkRecovery() {
    const stored = getFromLocalStorage();
    if (!stored) {
      activeRecovery.value = null;
      return;
    }
    try {
      const recovered = await recoverAttempt(stored.attempt_code);
      const sessionActive = recovered.kangourou_session?.status === 'active';
      if (recovered.status === 'inProgress' && sessionActive) {
        activeRecovery.value = stored;
      } else {
        activeRecovery.value = null;
        if (!sessionActive) {
          clearLocalStorage();
        }
      }
    } catch {
      clearLocalStorage();
      activeRecovery.value = null;
    }
  }

  function dismissRecovery() {
    clearLocalStorage();
    activeRecovery.value = null;
  }

  return {
    attempt,
    myAttempts,
    guestAttempts,
    isLoading,
    error,
    activeRecovery,
    pendingAnswerChanges,
    isSyncingAnswers,
    isInProgress,
    isFinished,
    createAttempt,
    fetchAttempt,
    queueAnswerChange,
    flushAnswerChanges,
    hasPendingAnswerChanges,
    submitAttempt,
    recoverAttempt,
    fetchMyAttempts,
    fetchGuestAttempts,
    getGuestAttemptIds,
    claimAttempts,
    removeGuestAttemptIds,
    getFromLocalStorage,
    clearLocalStorage,
    clearError,
    checkRecovery,
    dismissRecovery,
    resetAnswerSyncState,
  };
});
