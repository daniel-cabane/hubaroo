<template>
  <div class="container mx-auto p-6">
    <h2 class="text-2xl font-bold mb-6 text-text-main dark:text-surface">Mes tentatives</h2>

    <div v-if="attemptStore.isLoading" class="text-text-muted">Chargement...</div>

    <div v-else-if="attemptStore.myAttempts.length === 0" class="text-text-muted">
      Aucune tentative.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="attempt in attemptStore.myAttempts"
        :key="attempt.id"
        class="flex items-center justify-between p-4 bg-surface dark:bg-gray-900 border border-border rounded-lg"
      >
        <div>
          <p class="font-medium text-text-main dark:text-surface">
            {{ attempt.kangourou_session?.paper?.title }}
          </p>
          <p class="text-sm text-text-muted">{{ new Date(attempt.created_at).toLocaleDateString() }}
          </p>
        </div>
        <div v-if="attempt.kangourou_session?.status !== 'active'"">{{ attempt.score ?? 0}} pt<span v-if="attempt.score>=2">s</span></div>
        <router-link
          v-if="attempt.status === 'finished' && attempt.kangourou_session?.status !== 'active'"
          :to="{ name: 'Results', params: { code: attempt.kangourou_session?.code, attemptId: attempt.id } }"
          class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface text-sm font-medium transition-colors"
        >
          Voir les résultats
        </router-link>
        <span
          v-else-if="attempt.status === 'finished'"
          class="px-4 py-2 rounded-lg bg-primary/40 text-surface text-sm font-medium cursor-not-allowed"
        >
          Voir les résultats
        </span>
        <router-link
          v-else
          :to="{ name: 'Attempt', params: { code: attempt.kangourou_session?.code, attemptId: attempt.id } }"
          class="px-4 py-2 rounded-lg bg-info hover:bg-info/80 text-surface text-sm font-medium transition-colors"
        >
          Reprendre
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useAttemptStore } from '@/stores/attemptStore';

const attemptStore = useAttemptStore();

onMounted(() => {
  attemptStore.fetchMyAttempts();
});
</script>
