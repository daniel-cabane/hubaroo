<template>
  <template v-if="!isAttemptView && displayAttempts.length > 0">
    <!-- Toggle button fixed to left side -->
    <button
      @click="isOpen = !isOpen"
      class="fixed left-0 top-1/2 -translate-y-1/2 z-40 bg-surface dark:bg-gray-900 border border-l-0 border-border rounded-r-lg px-1.5 py-3 shadow-md hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
      title="Tentatives récentes"
    >
      <History class="w-4 h-4 text-text-muted cursor-pointer" />
    </button>

    <!-- Slide-in panel from left -->
    <div
      class="fixed left-0 top-1/2 -translate-y-1/2 z-50 w-64 bg-surface dark:bg-gray-900 border border-border rounded-r-xl shadow-2xl transition-transform duration-300"
      :class="isOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="p-3">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-bold text-text-main">Tentatives récentes</h3>
          <button
            @click="isOpen = false"
            class="text-text-muted hover:text-text-main transition-colors"
            title="Fermer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="flex flex-col gap-2">
          <router-link
            v-for="ga in displayAttempts"
            :key="ga.id"
            :to="ga.status === 'inProgress' && ga.kangourou_session?.status === 'active'
              ? { name: 'Attempt', params: { code: ga.kangourou_session.code, attemptId: ga.id } }
              : { name: 'Results', params: { code: ga.kangourou_session?.code, attemptId: ga.id } }"
            @click="isOpen = false"
            class="group flex flex-col gap-1.5 p-2.5 rounded-lg bg-gray-50 dark:bg-gray-800 border border-border hover:border-primary hover:shadow-md transition-all"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-text-main truncate">{{ ga.kangourou_session?.paper?.title || 'Session' }}</span>
              <span
                :class="ga.status === 'finished' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'"
                class="px-1.5 py-0.5 rounded text-xs font-semibold flex-shrink-0 ml-2"
              >
                {{ ga.status === 'finished' ? 'Terminée' : 'En cours' }}
              </span>
            </div>
            <div class="flex items-center justify-between text-xs text-text-muted">
              <span class="truncate">{{ ga.name || 'Anonyme' }}</span>
              <span v-if="ga.score !== null && ga.kangourou_session?.status === 'expired'" class="flex-shrink-0 ml-2">{{ ga.score }}</span>
            </div>
          </router-link>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { History, X } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/authStore';
import { useAttemptStore } from '@/stores/attemptStore';

const route = useRoute();
const authStore = useAuthStore();
const attemptStore = useAttemptStore();

const isOpen = ref(false);

const isAttemptView = computed(() => route.name === 'Attempt');

const displayAttempts = computed(() => {
  if (authStore.isAuthenticated) {
    return attemptStore.myAttempts.slice(0, 3);
  }
  return attemptStore.guestAttempts;
});

async function refreshAttempts() {
  if (authStore.isAuthenticated) {
    await attemptStore.fetchMyAttempts();
  } else {
    await attemptStore.fetchGuestAttempts();
  }
}

watch(isAttemptView, (isAttempt, wasAttempt) => {
  if (wasAttempt && !isAttempt) {
    refreshAttempts();
  }
});

onMounted(async () => {
  if (authStore.isAuthenticated) {
    if (attemptStore.myAttempts.length === 0) {
      await attemptStore.fetchMyAttempts();
    }
  } else {
    if (attemptStore.guestAttempts.length === 0) {
      await attemptStore.fetchGuestAttempts();
    }
  }
});
</script>
