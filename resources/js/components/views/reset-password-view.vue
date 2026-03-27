<template>
  <div class="flex items-center justify-center min-h-screen bg-bg dark:bg-text-main">
    <div class="w-full max-w-md">
      <div class="bg-surface dark:bg-gray-900 shadow-lg rounded-lg p-8">
        <div class="mb-8">
          <h1 class="text-3xl font-bold text-text-main dark:text-surface">Create New Password</h1>
          <p class="text-text-muted dark:text-text-muted/70 mt-2">Enter your new password below</p>
        </div>

        <form v-if="!submitted" @submit.prevent="handleResetPassword" class="space-y-6">
          <!-- Email Field -->
          <div class="space-y-2">
            <label
              for="email"
              class="block text-sm font-medium text-text-main dark:text-surface/80"
            >
              Email Address
            </label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              placeholder="name@example.com"
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary dark:focus:ring-primary-hover"
              required
            />
          </div>

          <!-- Password Field -->
          <div class="space-y-2">
            <label
              for="password"
              class="block text-sm font-medium text-text-main dark:text-surface/80"
            >
              New Password
            </label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              placeholder="••••••••"
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary dark:focus:ring-primary-hover"
              required
            />
            <p class="text-xs text-text-muted dark:text-text-muted/70">Must be at least 8 characters</p>
          </div>

          <!-- Password Confirmation Field -->
          <div class="space-y-2">
            <label
              for="password-confirmation"
              class="block text-sm font-medium text-text-main dark:text-surface/80"
            >
              Confirm Password
            </label>
            <input
              id="password-confirmation"
              v-model="form.passwordConfirmation"
              type="password"
              placeholder="••••••••"
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary dark:focus:ring-primary-hover"
              required
            />
          </div>

          <!-- Error Message -->
          <div v-if="authStore.error" class="bg-error/10 dark:bg-error/20 border border-error/30 dark:border-error/40 text-error dark:text-error px-4 py-3 rounded-lg text-sm">
            {{ authStore.error }}
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="authStore.isLoading"
            class="w-full bg-primary hover:bg-primary-hover dark:bg-primary-hover dark:hover:bg-primary text-surface dark:text-text-main font-medium py-2 px-4 rounded-lg transition-colors disabled:opacity-50"
          >
            {{ authStore.isLoading ? 'Resetting...' : 'Reset Password' }}
          </button>
        </form>

        <!-- Success Message -->
        <div v-else class="space-y-4">
          <div class="bg-success/10 dark:bg-success/20 border border-success/30 dark:border-success/40 text-success dark:text-success px-4 py-3 rounded-lg">
            <p class="font-medium">Password reset successfully</p>
            <p class="text-sm mt-1">You can now log in with your new password</p>
          </div>

          <router-link
            to="/login"
            class="block text-center bg-primary hover:bg-primary-hover dark:bg-primary-hover dark:hover:bg-primary text-surface dark:text-text-main font-medium py-2 px-4 rounded-lg transition-colors"
          >
            Back to Login
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';

const authStore = useAuthStore();
const route = useRoute();

const form = ref({
  email: '',
  password: '',
  passwordConfirmation: '',
});

const submitted = ref(false);

onMounted(() => {
  form.value.email = route.query.email || '';
});

async function handleResetPassword() {
  const token = route.query.token || '';

  if (!token) {
    authStore.error = 'Invalid reset link';
    return;
  }

  try {
    await authStore.resetPassword(
      token,
      form.value.email,
      form.value.password,
      form.value.passwordConfirmation
    );
    submitted.value = true;
  } catch (error) {
    // Error is handled by the store
  }
}
</script>
