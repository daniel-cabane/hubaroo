<template>
  <div class="container mx-auto p-6 max-w-2xl">
    <div v-if="isLoading" class="text-center text-text-muted">Chargement des résultats...</div>

    <div v-else-if="attempt">
      <!-- Score Summary -->
      <div class="text-center mb-8">
        <h2 class="text-2xl font-bold text-text-main dark:text-surface mb-2">Résultats</h2>
        <p class="text-sm text-text-muted mb-4">{{ session?.paper?.title }}</p>
        <div class="inline-block bg-surface dark:bg-gray-900 border border-border rounded-2xl px-8 py-6 shadow-md">
          <div class="text-5xl font-bold text-primary">{{ attempt.score }}</div>
          <div class="text-sm text-text-muted mt-1">points</div>
        </div>
      </div>

      <!-- Delayed correction notice -->
      <div v-if="!correctionAvailable" class="mb-8 p-4 bg-info/10 border border-info/30 rounded-lg text-center">
        <p class="text-sm text-text-main dark:text-surface">La correction sera disponible à la fin de la session.</p>
      </div>

      <!-- Question Review -->
      <div v-if="correctionAvailable" class="space-y-4">
        <div
          v-for="(answer, idx) in attempt.answers"
          :key="idx"
          class="flex items-center gap-4 p-3 rounded-lg border"
          :class="reviewRowClass(answer)"
        >
          <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
            :class="reviewBadgeClass(answer)"
          >
            {{ idx + 1 }}
          </div>

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

const route = useRoute();
const sessionStore = useKangourouSessionStore();
const attemptStore = useAttemptStore();

const isLoading = ref(true);
const session = ref(null);
const attempt = ref(null);

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
