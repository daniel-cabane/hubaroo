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
        <router-link
          v-else-if="attempt.status === 'finished'"
          class="px-4 py-2 rounded-lg bg-primary/40 text-surface text-sm font-medium cursor-not-allowed"
        >
          Voir les résultats
        </router-link>
        <div v-else class="flex flex-col items-end gap-1">
          <button
            @click="requestRejoin(attempt)"
            :disabled="sentRejoinIds.has(attempt.id)"
            class="px-4 py-2 rounded-lg bg-info hover:bg-info/80 text-surface text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ sentRejoinIds.has(attempt.id) ? 'Demande envoyée' : 'Demander à reprendre' }}
          </button>
          <span v-if="rejoinError[attempt.id]" class="text-xs text-error">{{ rejoinError[attempt.id] }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useAttemptStore } from '@/stores/attemptStore';
import { useRejoinDemandStore } from '@/stores/rejoinDemandStore';

const attemptStore = useAttemptStore();
const rejoinDemandStore = useRejoinDemandStore();

const sentRejoinIds = ref(new Set());
const rejoinError = ref({});

async function requestRejoin(attempt) {
  rejoinError.value[attempt.id] = null;
  try {
    const demand = await rejoinDemandStore.createDemand(attempt.id);
    rejoinDemandStore.addStudentDemand({
      id: demand.id,
      attemptId: attempt.id,
      sessionCode: attempt.kangourou_session?.code,
      sessionTitle: attempt.kangourou_session?.paper?.title ?? null,
    });
    sentRejoinIds.value = new Set([...sentRejoinIds.value, attempt.id]);
  } catch {
    rejoinError.value[attempt.id] = rejoinDemandStore.error || 'Erreur lors de l\'envoi.';
  }
}

onMounted(() => {
  attemptStore.fetchMyAttempts();
});
</script>
