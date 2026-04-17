import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const usePaperStore = defineStore('paper', () => {
  const papers = ref([]);
  const currentPaper = ref(null);
  const isLoading = ref(false);
  const error = ref(null);

  async function fetchPapers() {
    isLoading.value = true;
    error.value = null;
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

  async function fetchPaper(paperId) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.get(`/api/papers/${paperId}`);
      currentPaper.value = response.data.paper;
      return response.data.paper;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch paper';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  return {
    papers,
    currentPaper,
    isLoading,
    error,
    fetchPapers,
    fetchPaper,
  };
});
