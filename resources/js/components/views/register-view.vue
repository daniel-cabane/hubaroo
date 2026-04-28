<template>
  <div class="flex items-center justify-center min-h-screen bg-bg dark:bg-text-main">
    <div class="w-full max-w-md">
      <div class="bg-surface dark:bg-gray-900 shadow-lg rounded-lg p-8">
        <div class="mb-8 text-center">
          <h1 class="text-3xl font-bold text-text-main dark:text-surface">Créer un compte</h1>
          <p class="text-text-muted dark:text-text-muted/70 mt-2">Rejoignez Hubaroo</p>
        </div>

        <form @submit.prevent="handleRegister" class="space-y-6">
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
              <div class="w-full border-t border-border"></div>
            </div>
            <div class="relative flex justify-center text-sm">
              <span class="px-2 bg-surface dark:bg-gray-900 text-text-muted">ou</span>
            </div>
          </div>

          <!-- First Name -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Prénom</label>
            <input
              v-model="form.firstName"
              type="text"
              @blur="touched.firstName = true"
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
              required
            />
            <p v-if="touched.firstName && !isFirstNameValid" class="text-sm text-error">Le prénom doit contenir au moins 2 caractères</p>
          </div>

          <!-- Last Name -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Nom de famille</label>
            <input
              v-model="form.lastName"
              type="text"
              @blur="touched.lastName = true"
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
              required
            />
            <p v-if="touched.lastName && !isLastNameValid" class="text-sm text-error">Le nom doit contenir au moins 2 caractères</p>
          </div>

          <!-- Email -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Email</label>
            <input
              v-model="form.email"
              type="email"
              @blur="touched.email = true"
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
              required
            />
            <p v-if="touched.email && !isEmailValid" class="text-sm text-error">Format d'email invalide</p>
          </div>

          <!-- Password -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Mot de passe</label>
            <input
              v-model="form.password"
              type="password"
              @blur="touched.password = true"
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
              required
            />
            <p v-if="touched.password && !isPasswordValid" class="text-sm text-error">Le mot de passe doit contenir au moins 8 caractères</p>
          </div>

          <!-- Confirm Password -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Confirmer le mot de passe</label>
            <input
              v-model="form.password_confirmation"
              type="password"
              @blur="touched.password_confirmation = true"
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
              required
            />
            <p v-if="touched.password_confirmation && !isPasswordMatchValid" class="text-sm text-error">Les mots de passe ne correspondent pas</p>
          </div>

          <!-- Role -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Je suis</label>
            <div class="grid grid-cols-2 gap-3">
              <button
                type="button"
                @click="form.role = 'Teacher'"
                :class="[
                  'px-4 py-3 rounded-lg border-2 text-sm font-medium transition-colors',
                  form.role === 'Teacher'
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-border text-text-muted hover:border-primary/50'
                ]"
              >
                Enseignant(e)
              </button>
              <button
                type="button"
                @click="form.role = 'Student'"
                :class="[
                  'px-4 py-3 rounded-lg border-2 text-sm font-medium transition-colors',
                  form.role === 'Student'
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-border text-text-muted hover:border-primary/50'
                ]"
              >
                Élève
              </button>
            </div>
          </div>

          <!-- Error Message -->
          <div v-if="error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg text-sm">
            {{ error }}
          </div>

          <!-- Legal consent notice -->
          <p class="text-xs text-text-muted dark:text-text-muted/70 text-center leading-relaxed">
            En créant un compte, vous acceptez nos
            <router-link to="/terms/service" class="text-primary hover:underline font-medium">Conditions d'utilisation</router-link>
            et notre
            <router-link to="/terms/privacy" class="text-primary hover:underline font-medium">Politique de confidentialité</router-link>.
          </p>

          <button
            type="submit"
            :disabled="isLoading || !isFormValid"
            class="w-full bg-primary hover:bg-primary-hover text-surface font-medium py-2 px-4 rounded-lg transition-colors disabled:opacity-50"
          >
            {{ isLoading ? 'Inscription...' : 'Créer un compte' }}
          </button>

        </form>

        <div class="mt-6 text-center">
          <router-link to="/login" class="text-text-muted hover:text-text-main text-sm">
            Déjà un compte ? Se connecter
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const isLoading = ref(false);
const error = ref(null);

const form = reactive({
  firstName: '',
  lastName: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: '',
});

const touched = reactive({
  firstName: false,
  lastName: false,
  email: false,
  password: false,
  password_confirmation: false,
});

// Validators
const isFirstNameValid = computed(() => form.firstName.length >= 2);

const isLastNameValid = computed(() => form.lastName.length >= 2);

const isEmailValid = computed(() => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(form.email);
});

const isPasswordValid = computed(() => form.password.length >= 8);

const isPasswordMatchValid = computed(() => form.password === form.password_confirmation);

const isFormValid = computed(() => 
  isFirstNameValid.value &&
  isLastNameValid.value &&
  isEmailValid.value &&
  isPasswordValid.value &&
  isPasswordMatchValid.value &&
  form.role !== ''
);

async function handleRegister() {
  if (!isFormValid.value) {
    return;
  }

  isLoading.value = true;
  error.value = null;

  const formatName = (name) => 
    name
      .split(' ')
      .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
      .join(' ');
  
  const fullName = formatName(form.firstName) + ' ' + formatName(form.lastName);

  try {
    await axios.post('/register', {
      name: fullName,
      email: form.email,
      password: form.password,
      password_confirmation: form.password_confirmation,
      role: form.role,
    });

    router.push('/');
  } catch (err) {
    error.value = err.response?.data?.message || 'Inscription échouée.';
  } finally {
    isLoading.value = false;
  }
}
</script>
