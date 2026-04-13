<template>
  <div class="container mx-auto p-6 max-w-lg text-center">
    <div v-if="isLoading" class="text-text-muted">Chargement de la session...</div>

    <div v-else-if="error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg">
      {{ error }}
    </div>

    <div v-else-if="session">
      <h2 class="text-2xl font-bold mb-2 text-text-main dark:text-surface">{{ session.paper?.title }}</h2>
      <p class="text-lg font-mono tracking-widest text-primary mb-4">{{ session.code }}</p>

      <div v-if="session.status === 'active' && !isExpired" class="space-y-4">
        <!-- Name prompt for guests -->
        <div v-if="showNamePrompt" class="space-y-4">
          <p class="text-text-muted">Entrez votre nom pour commencer.</p>
          <input
            v-model="studentName"
            type="text"
            maxlength="255"
            placeholder="Votre nom"
            class="w-full px-4 py-2 rounded-lg border border-border bg-gray-50 dark:bg-gray-800 text-text-main dark:text-surface text-center text-lg focus:outline-none focus:ring-2 focus:ring-primary"
            @keydown.enter="startAttempt"
          />
          <button
            @click="startAttempt"
            :disabled="!studentName.trim()"
            class="px-6 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors disabled:opacity-50"
          >
            Commencer
          </button>
        </div>

        <div v-else>
          <p class="text-text-muted">Session active. Connexion en cours...</p>
          <div class="animate-pulse text-primary text-sm">Création de votre tentative...</div>
        </div>
      </div>

      <div v-else class="space-y-4">
        <p class="text-text-muted">Cette session a expiré.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';
import { useAttemptStore } from '@/stores/attemptStore';
import { useAuthStore } from '@/stores/authStore';

const route = useRoute();
const router = useRouter();
const sessionStore = useKangourouSessionStore();
const attemptStore = useAttemptStore();
const authStore = useAuthStore();

const session = ref(null);
const isLoading = ref(true);
const error = ref(null);
const showNamePrompt = ref(false);
const studentName = ref('');

const isExpired = computed(() => {
  if (!session.value) return true;
  return session.value.status === 'expired' || new Date(session.value.expires_at) < new Date();
});

async function startAttempt() {
  if (!authStore.isAuthenticated && !studentName.value.trim()) return;

  try {
    const code = route.params.code;
    const name = authStore.isAuthenticated ? null : studentName.value.trim();
    const attempt = await attemptStore.createAttempt(code, name);
    router.replace({
      name: 'Attempt',
      params: { code, attemptId: attempt.id },
    });
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de la création de la tentative.';
  }
}

onMounted(async () => {
  try {
    const code = route.params.code;
    const data = await sessionStore.fetchSession(code);
    session.value = data;

    if (data.status === 'active' && new Date(data.expires_at) > new Date()) {
      // Check if we already have an active attempt (same or different session)
      const stored = attemptStore.getFromLocalStorage();
      if (stored) {
        if (stored.session_code === code) {
          // Resume existing attempt for this session
          router.replace({
            name: 'Attempt',
            params: { code, attemptId: stored.attempt_id },
          });
          return;
        }
        // Block: user has an active attempt for a different session
        error.value = 'Vous avez déjà une tentative en cours pour une autre session. Veuillez la terminer ou l\'ignorer d\'abord.';
        return;
      }

      if (authStore.isAuthenticated) {
        // Logged-in user: create attempt immediately
        await startAttempt();
      } else {
        // Guest: show name prompt
        showNamePrompt.value = true;
      }
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Session introuvable.';
  } finally {
    isLoading.value = false;
  }
});
</script>
