import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useBugReportStore = defineStore('bugReport', () => {
  const unsolvedCount = ref(null);
  const isSubmitting = ref(false);
  const error = ref(null);

  const canSubmit = () => unsolvedCount.value === null || unsolvedCount.value < 5;

  async function fetchUnsolvedCount() {
    try {
      const response = await axios.get('/api/my/bug-reports/unsolved-count');
      unsolvedCount.value = response.data.count;
    } catch {
      // silent
    }
  }

  async function submitBugReport(comment) {
    isSubmitting.value = true;
    error.value = null;
    try {
      await axios.post('/api/bug-reports', { comment });
      if (unsolvedCount.value !== null) {
        unsolvedCount.value += 1;
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to submit bug report';
      throw err;
    } finally {
      isSubmitting.value = false;
    }
  }

  return {
    unsolvedCount,
    isSubmitting,
    error,
    canSubmit,
    fetchUnsolvedCount,
    submitBugReport,
  };
});
