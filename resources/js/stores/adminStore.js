import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useAdminStore = defineStore('admin', () => {
  const papers = ref([]);
  const users = ref([]);
  const roles = ref([]);
  const bugReports = ref([]);
  const isLoading = ref(false);
  const error = ref(null);

  async function fetchPapers() {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.get('/api/admin/papers');
      papers.value = response.data.papers;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch papers';
    } finally {
      isLoading.value = false;
    }
  }

  async function updatePaper(paperId, data) {
    error.value = null;
    try {
      const response = await axios.patch(`/api/admin/papers/${paperId}`, data);
      const index = papers.value.findIndex(p => p.id === paperId);
      if (index !== -1) {
        papers.value[index] = response.data.paper;
      }
      return response.data.paper;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update paper';
      throw err;
    }
  }

  async function searchUsers(query) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.get('/api/admin/users', { params: { q: query } });
      users.value = response.data.users;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to search users';
    } finally {
      isLoading.value = false;
    }
  }

  async function updateUserRole(userId, role) {
    error.value = null;
    try {
      const response = await axios.patch(`/api/admin/users/${userId}/role`, { role });
      const index = users.value.findIndex(u => u.id === userId);
      if (index !== -1) {
        users.value[index] = response.data.user;
      }
      return response.data.user;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update role';
      throw err;
    }
  }

  async function fetchRoles() {
    try {
      const response = await axios.get('/api/admin/roles');
      roles.value = response.data.roles;
    } catch (err) {
      // silent
    }
  }

  async function fetchBugReports() {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.get('/api/admin/bug-reports');
      bugReports.value = response.data.bug_reports;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch bug reports';
    } finally {
      isLoading.value = false;
    }
  }

  async function updateBugReport(bugReportId, status) {
    error.value = null;
    try {
      const response = await axios.patch(`/api/admin/bug-reports/${bugReportId}`, { status });
      const index = bugReports.value.findIndex(b => b.id === bugReportId);
      if (index !== -1) {
        bugReports.value[index] = response.data.bug_report;
      }
      return response.data.bug_report;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update bug report';
      throw err;
    }
  }

  async function destroyBugReport(bugReportId) {
    error.value = null;
    try {
      await axios.delete(`/api/admin/bug-reports/${bugReportId}`);
      bugReports.value = bugReports.value.filter(b => b.id !== bugReportId);
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete bug report';
      throw err;
    }
  }

  return {
    papers,
    users,
    roles,
    bugReports,
    isLoading,
    error,
    fetchPapers,
    updatePaper,
    searchUsers,
    updateUserRole,
    fetchRoles,
    fetchBugReports,
    updateBugReport,
    destroyBugReport,
  };
});
