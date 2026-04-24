<template>
  <div class="container mx-auto p-6 max-w-2xl">
    <div class="mb-4">
      <router-link
        v-if="authStore.isAuthenticated"
        :to="{ name: 'MyAttempts' }"
        class="inline-flex items-center gap-1 text-sm text-text-muted hover:text-primary transition-colors"
      >
        ← Mes tentatives
      </router-link>
    </div>

    <div v-if="isLoading" class="text-center text-text-muted">Chargement des résultats...</div>

    <div v-else-if="attempt">
      <!-- Score Summary -->
      <div v-if="correctionAvailable" class="text-center mb-8">
        <h2 class="text-2xl font-bold text-text-main dark:text-surface mb-2">Résultat</h2>
        <p class="text-sm text-text-muted mb-4">{{ session?.paper?.title }}</p>
        <div class="inline-block bg-surface dark:bg-gray-900 border border-border rounded-2xl px-8 py-6 shadow-md">
          <div class="text-5xl font-bold text-primary">{{ attempt.score }}</div>
          <div class="text-sm text-text-muted mt-1">points</div>
        </div>
        <div class="flex flex-wrap justify-center gap-1.5 mt-4">
          <div
            v-for="(answer, idx) in attempt.answers"
            :key="idx"
            class="w-4 h-4 rounded-full"
            :class="answer.status === 'correct' ? 'bg-success' : answer.status === 'incorrect' ? 'bg-error' : 'bg-gray-300 dark:bg-gray-600'"
            :title="`Q${idx + 1}`"
          ></div>
        </div>
      </div>

      <!-- Delayed correction notice -->
      <div v-if="!correctionAvailable" class="mb-8 p-4 bg-info/10 border border-info/30 rounded-lg text-center space-y-3">
        <p class="text-sm text-text-main dark:text-surface">La correction sera disponible à la fin de la session.</p>
        <div v-if="rejoinApproved" class="text-sm text-success font-medium">Demande acceptée ! Consultez le centre d'alertes pour rejoindre.</div>
        <div v-else-if="rejoinDenied" class="text-sm text-error font-medium">Votre demande de reprise a été refusée.</div>
        <div v-else-if="pendingDemandId" class="text-sm text-text-muted italic">Demande de reprise envoyée, en attente de validation...</div>
        <button
          v-else
          @click="requestRejoin"
          :disabled="isRequestingRejoin"
          class="px-4 py-2 rounded-lg bg-info hover:bg-info/80 text-white text-sm font-medium transition-colors disabled:opacity-50"
        >
          {{ isRequestingRejoin ? 'Envoi...' : 'Demander à reprendre' }}
        </button>
      </div>

      <!-- Question Review -->
      <div v-if="correctionAvailable" class="space-y-4">
        <div
          v-for="(answer, idx) in attempt.answers"
          :key="idx"
          class="rounded-lg border overflow-hidden"
          :class="reviewRowClass(answer)"
        >
          <!-- Question image -->
          <div v-if="session?.paper?.questions?.[idx]?.image" class="border-b border-inherit">
            <img
              :src="'/' + session.paper.questions[idx].image"
              :alt="`Question ${idx + 1}`"
              class="w-full object-contain max-h-64 bg-white"
            />
          </div>

          <!-- Answer row -->
          <div class="flex items-center gap-4 p-3">
            <!-- <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
              :class="reviewBadgeClass(answer)"
            >
              {{ idx + 1 }}
            </div> -->

            <div class="flex-1 text-sm">
              <span class="text-text-main dark:text-surface">Votre réponse : </span>
              <span class="font-bold" :class="answer.status === 'correct' ? 'text-success' : answer.status === 'incorrect' ? 'text-error' : 'text-text-muted'">
                {{ answer.answer || '—' }}
              </span>
            </div>

            <div class="text-sm text-text-muted">
              Correct : <span class="font-bold text-success">{{ correctAnswers[idx] }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Back button -->
      <div class="mt-8 text-center">
        <router-link
          to="/"
          class="inline-block px-6 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors"
        >
          Retour à l'accueil
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';
import { useAttemptStore } from '@/stores/attemptStore';
import { useRejoinDemandStore } from '@/stores/rejoinDemandStore';
import { useAuthStore } from '@/stores/authStore';

const route = useRoute();
const sessionStore = useKangourouSessionStore();
const attemptStore = useAttemptStore();
const rejoinDemandStore = useRejoinDemandStore();
const authStore = useAuthStore();

const isLoading = ref(true);
const session = ref(null);
const attempt = ref(null);
const isRequestingRejoin = ref(false);

// Derived from store so AlertCenter and this view stay in sync
const currentDemand = computed(() =>
  rejoinDemandStore.studentDemands.find(d => d.attemptId === attempt.value?.id)
);
const pendingDemandId = computed(() => currentDemand.value?.status === 'pending' ? currentDemand.value.id : null);
const rejoinApproved = computed(() => currentDemand.value?.status === 'approved');
const rejoinDenied = computed(() => currentDemand.value?.status === 'denied');

const correctAnswers = computed(() => {
  if (!session.value?.paper?.questions) return [];
  return session.value.paper.questions.map(q => q.correct_answer);
});

const correctionAvailable = computed(() => {
  return correctAnswers.value.length > 0 && correctAnswers.value[0] != null;
});

function reviewRowClass(answer) {
  if (answer.status === 'correct') return 'border-success/30 bg-success/5';
  if (answer.status === 'incorrect') return 'border-error/30 bg-error/5';
  return 'border-border bg-gray-50 dark:bg-gray-900';
}

function reviewBadgeClass(answer) {
  if (answer.status === 'correct') return 'bg-success text-white';
  if (answer.status === 'incorrect') return 'bg-error text-white';
  return 'bg-gray-200 dark:bg-gray-700 text-text-muted';
}

async function requestRejoin() {
  if (!attempt.value) return;
  isRequestingRejoin.value = true;
  try {
    const demand = await rejoinDemandStore.createDemand(attempt.value.id);
    rejoinDemandStore.addStudentDemand({
      id: demand.id,
      attemptId: attempt.value.id,
      sessionCode: route.params.code,
      sessionTitle: session.value?.paper?.title ?? null,
    });
  } catch {
    // error handled by store
  } finally {
    isRequestingRejoin.value = false;
  }
}

onMounted(async () => {
  try {
    const { code, attemptId } = route.params;
    await attemptStore.fetchAttempt(attemptId);
    await sessionStore.fetchSession(code, { attemptId });
    session.value = sessionStore.session;
    attempt.value = attemptStore.attempt;
  } catch {
    // error
  } finally {
    isLoading.value = false;
  }
});
</script>
