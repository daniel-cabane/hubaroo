import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const isLoading = ref(false);
  const error = ref(null);

  const isAuthenticated = computed(() => user.value !== null);

  async function login(email, password) {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await axios.post('/login', {
        email,
        password,
      });

      user.value = response.data.user;
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Login failed';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function logout() {
    isLoading.value = true;
    error.value = null;

    try {
      await axios.post('/logout');
      user.value = null;
    } catch (err) {
      error.value = err.response?.data?.message || 'Logout failed';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function checkAuth() {
    isLoading.value = true;

    try {
      const response = await axios.get('/check');
      if (response.data.authenticated) {
        user.value = response.data.user;
      }
    } catch (err) {
      user.value = null;
    } finally {
      isLoading.value = false;
    }
  }

  async function forgotPassword(email) {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await axios.post('/forgot-password', { email });
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to send reset link';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function resetPassword(token, email, password, passwordConfirmation) {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await axios.post('/reset-password', {
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to reset password';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateName(name) {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await axios.patch('/user/name', { name });
      user.value = response.data.user;
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.errors?.name?.[0] || err.response?.data?.message || 'Failed to update name';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  function clearError() {
    error.value = null;
  }

  return {
    user,
    isLoading,
    error,
    isAuthenticated,
    login,
    logout,
    checkAuth,
    forgotPassword,
    resetPassword,
    updateName,
    clearError,
  };
});
