<template>
  <div class="container mx-auto p-6 max-w-lg text-center">
    <div class="mb-4 text-left">
      <router-link
        :to="{ name: 'JoinSession' }"
        class="inline-flex items-center gap-1 text-sm text-text-muted hover:text-primary transition-colors"
      >
        ← Rejoindre une session
      </router-link>
    </div>

    <div v-if="isLoading" class="text-text-muted">Chargement de la session...</div>

    <div v-else-if="error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg">
      {{ error }}
    </div>

    <!-- Rejoin demand: waiting for approval -->
    <div v-else-if="pendingDemandId" class="space-y-4">
      <div class="bg-info/10 border border-info/30 rounded-lg p-6 space-y-3">
        <p class="text-lg font-semibold text-text-main dark:text-surface">Demande de reprise envoyée</p>
        <p class="text-sm text-text-muted">En attente de l'approbation de l'enseignant...</p>
        <div class="animate-pulse flex justify-center">
          <div class="w-2 h-2 bg-info rounded-full mx-1"></div>
          <div class="w-2 h-2 bg-info rounded-full mx-1 animation-delay-150"></div>
          <div class="w-2 h-2 bg-info rounded-full mx-1 animation-delay-300"></div>
        </div>
      </div>
    </div>

    <!-- Rejoin demand: denied -->
    <div v-else-if="rejoinDenied" class="bg-error/10 border border-error/30 rounded-lg p-6 space-y-2">
      <p class="text-lg font-semibold text-error">Demande refusée</p>
      <p class="text-sm text-text-muted">L'enseignant a refusé votre demande de reprise.</p>
    </div>

    <!-- Existing attempt: show rejoin form -->
    <div v-else-if="existingAttempt" class="space-y-4">
      <div class="bg-warning/10 border border-warning/30 rounded-lg p-5 text-left space-y-3">
        <p class="text-base font-semibold text-text-main dark:text-surface">Vous avez déjà une tentative pour cette session</p>

        <div class="grid grid-cols-2 gap-2 text-sm text-text-muted">
          <div><span class="font-medium">Nom :</span> {{ existingAttempt.name || 'Invité' }}</div>
          <div><span class="font-medium">Réponses :</span> {{ answeredCount }} / 26</div>
          <div><span class="font-medium">Terminaison :</span> {{ formatTermination(existingAttempt.termination) }}</div>
          <div v-if="existingAttempt.timer !== null">
            <span class="font-medium">Temps restant :</span> {{ formatTimer(existingAttempt.timer) }}
          </div>
        </div>
      </div>

      <p class="text-sm text-text-muted">Demandez à l'enseignant l'autorisation de reprendre votre tentative.</p>

      <button
        @click="requestRejoin"
        :disabled="isRequestingRejoin"
        class="w-full bg-primary hover:bg-primary-hover text-surface font-medium py-2 px-4 rounded-lg transition-colors disabled:opacity-50"
      >
        {{ isRequestingRejoin ? 'Envoi en cours...' : 'Demander à reprendre' }}
      </button>
    </div>

    <div v-else-if="session">
      <h2 class="text-2xl font-bold mb-2 text-text-main dark:text-surface">Session Kangourou</h2>
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
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';
import { useAttemptStore } from '@/stores/attemptStore';
import { useAuthStore } from '@/stores/authStore';
import { useRejoinDemandStore } from '@/stores/rejoinDemandStore';

const route = useRoute();
const router = useRouter();
const sessionStore = useKangourouSessionStore();
const attemptStore = useAttemptStore();
const authStore = useAuthStore();
const rejoinDemandStore = useRejoinDemandStore();

const session = ref(null);
const isLoading = ref(true);
const error = ref(null);
const showNamePrompt = ref(false);
const studentName = ref('');
const existingAttempt = ref(null);
const pendingDemandId = ref(null);
const isRequestingRejoin = ref(false);
const rejoinDenied = ref(false);

const isExpired = computed(() => {
  if (!session.value) return true;
  return session.value.status === 'expired' || new Date(session.value.expires_at) < new Date();
});

const answeredCount = computed(() => {
  if (!existingAttempt.value?.answers) return 0;
  return existingAttempt.value.answers.filter(a => a.answer !== null).length;
});

function formatTermination(termination) {
  const map = {
    none: 'Aucune',
    submitted: 'Soumis',
    blurred: 'Quitté la page',
    timeout: 'Temps écoulé',
  };
  return map[termination] || termination;
}

function formatTimer(seconds) {
  if (seconds === null || seconds === undefined) return '—';
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

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
    const data = err.response?.data;
    if (err.response?.status === 409 && data?.requires_rejoin) {
      existingAttempt.value = data.attempt;
    } else {
      error.value = data?.message || 'Erreur lors de la création de la tentative.';
    }
  }
}

async function requestRejoin() {
  if (!existingAttempt.value) return;
  isRequestingRejoin.value = true;
  try {
    const demand = await rejoinDemandStore.createDemand(existingAttempt.value.id);
    rejoinDemandStore.addStudentDemand({
      id: demand.id,
      attemptId: existingAttempt.value.id,
      sessionCode: route.params.code,
      sessionTitle: session.value?.paper?.title ?? null,
    });
    pendingDemandId.value = demand.id;
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de la demande de reprise.';
  } finally {
    isRequestingRejoin.value = false;
  }
}

// React to store updates for the pending demand (AlertCenter owns the Echo subscription)
watch(() => rejoinDemandStore.studentDemands, (demands) => {
  if (!pendingDemandId.value) return;
  const demand = demands.find(d => d.id === pendingDemandId.value);
  if (!demand || demand.status === 'pending') return;

  if (demand.status === 'approved') {
    router.replace({
      name: 'Attempt',
      params: { code: route.params.code, attemptId: demand.attemptId },
    });
  } else {
    rejoinDenied.value = true;
    pendingDemandId.value = null;
  }
}, { deep: true });

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
          // Existing attempt for this session: load its details for the rejoin form
          try {
            const attemptData = await attemptStore.fetchAttempt(stored.attempt_id);
            if (attemptData.status === 'inProgress') {
              existingAttempt.value = attemptData;
            } else {
              // Attempt is finished, allow starting fresh
              attemptStore.clearLocalStorage();
              if (authStore.isAuthenticated) {
                await startAttempt();
              } else {
                showNamePrompt.value = true;
              }
            }
          } catch {
            // Attempt not found, clear and let them start fresh
            attemptStore.clearLocalStorage();
            if (authStore.isAuthenticated) {
              await startAttempt();
            } else {
              showNamePrompt.value = true;
            }
          }
          return;
        }
        // Block: user has an active attempt for a different session
        error.value = 'Vous avez déjà une tentative en cours pour une autre session. Veuillez la terminer ou l\'ignorer d\'abord.';
        return;
      }

      if (authStore.isAuthenticated) {
        // Logged-in user: create attempt immediately (may return 409 if already has one)
        await startAttempt();
      } else {
        // Guest: show name prompt
        showNamePrompt.value = true;
      }
    }
  } catch (err) {
    error.value = err.response?.status === 404
      ? 'Code de session invalide. Vérifiez le code et réessayez.'
      : (err.response?.data?.message || 'Session introuvable.');
  } finally {
    isLoading.value = false;
  }
});

onUnmounted(() => {
});
</script>

