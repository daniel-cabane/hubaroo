<template>
  <div class="flex items-center justify-center min-h-screen bg-bg dark:bg-text-main">
    <div class="w-full max-w-md">
      <div class="bg-surface dark:bg-gray-900 shadow-lg rounded-lg p-8">
        <div class="mb-8 text-center">
          <h1 class="text-3xl font-bold text-text-main dark:text-surface">Bienvenue</h1>
          <p class="text-text-muted dark:text-text-muted/70 mt-2">Connectez-vous à votre compte</p>
        </div>

        <form @submit.prevent="handleLogin" class="space-y-6">
          <!-- Google Sign In -->
          <a
            href="/auth/google/redirect"
            class="flex justify-center"
          >
            <img :src="'/google-signin.png'" alt="Sign in with Google" class="h-16" />
          </a>

          <!-- Divider -->
          <div class="relative">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-border dark:border-border/50"></div>
            </div>
            <div class="relative flex justify-center text-sm">
              <span class="px-2 bg-surface dark:bg-gray-900 text-text-muted dark:text-text-muted/70">ou</span>
            </div>
          </div>

          <!-- Email Field -->
          <div class="space-y-2">
            <label
              for="email"
              class="block text-sm font-medium text-text-main dark:text-surface/80"
            >
              Email
            </label>
            <input
              id="email"
              v-model="form.email"
              type="email"
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
              Mot de passe
            </label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary dark:focus:ring-primary-hover"
              required
            />
          </div>

          <!-- Error Message -->
          <div v-if="authStore.error" class="bg-error/10 dark:bg-error/20 border border-error/30 dark:border-error/40 text-error dark:text-error px-4 py-3 rounded-lg text-sm">
            {{ authStore.error }}
          </div>

          <!-- Login Button -->
          <button
            type="submit"
            :disabled="authStore.isLoading"
            class="w-full bg-primary hover:bg-primary-hover dark:bg-primary-hover dark:hover:bg-primary text-surface dark:text-text-main font-medium py-2 px-4 rounded-lg transition-colors disabled:opacity-50"
          >
            {{ authStore.isLoading ? 'Connexion...' : 'Se connecter' }}
          </button>

        </form>

        <!-- Forgot Password Link -->
        <div class="mt-6 text-center">
          <router-link
            to="/forgot-password"
            class="text-text-muted dark:text-text-muted/70 hover:text-text-main dark:hover:text-surface text-sm"
          >
            Mot de passe oublié ?
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';

const authStore = useAuthStore();
const router = useRouter();

const form = ref({
  email: '',
  password: '',
});

async function handleLogin() {
  try {
    await authStore.login(form.value.email, form.value.password);
    router.push('/');
  } catch (error) {
    // Error is handled by the store
  }
}
</script>
