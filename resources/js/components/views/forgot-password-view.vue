<template>
  <div class="flex items-center justify-center min-h-screen bg-bg dark:bg-text-main">
    <div class="w-full max-w-md">
      <div class="bg-surface dark:bg-gray-900 shadow-lg rounded-lg p-8">
        <div class="mb-8">
          <router-link to="/login" class="text-text-muted dark:text-text-muted/70 hover:text-text-main dark:hover:text-surface text-sm flex items-center mb-4">
            ← Retour à la connexion
          </router-link>
          <h1 class="text-3xl font-bold text-text-main dark:text-surface">Réinitialiser le mot de passe</h1>
          <p class="text-text-muted dark:text-text-muted/70 mt-2">Entrez votre adresse e-mail et nous vous enverrons un lien pour réinitialiser votre mot de passe</p>
        </div>

        <form v-if="!submitted" @submit.prevent="handleForgotPassword" class="space-y-6">
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
              placeholder="name@example.com"
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
            {{ authStore.isLoading ? 'Envoi...' : 'Envoyer le lien' }}
          </button>
        </form>

        <!-- Success Message -->
        <div v-else class="space-y-4">
          <div class="bg-success/10 dark:bg-success/20 border border-success/30 dark:border-success/40 text-success dark:text-success px-4 py-3 rounded-lg">
            <p class="font-medium">Vérifiez vos e-mails</p>
            <p class="text-sm mt-1">Nous avons envoyé un lien de réinitialisation à <strong>{{ form.email }}</strong></p>
          </div>

          <button
            @click="submitted = false; authStore.clearError()"
            class="w-full bg-border dark:bg-border/30 hover:bg-border/80 dark:hover:bg-border/50 text-text-main dark:text-surface font-medium py-2 px-4 rounded-lg transition-colors"
          >
            Envoyer un autre lien
          </button>

          <router-link
            to="/login"
            class="block text-center text-text-muted dark:text-text-muted/70 hover:text-text-main dark:hover:text-surface text-sm"
          >
            Retour à la connexion
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/authStore';

const authStore = useAuthStore();

const form = ref({
  email: '',
});

const submitted = ref(false);

async function handleForgotPassword() {
  try {
    await authStore.forgotPassword(form.value.email);
    submitted.value = true;
  } catch (error) {
    // Error is handled by the store
  }
}
</script>
