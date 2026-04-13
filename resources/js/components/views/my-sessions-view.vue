<template>
  <div class="container mx-auto p-6">
    <h2 class="text-2xl font-bold mb-6 text-text-main dark:text-surface">My Sessions</h2>

    <div v-if="sessionStore.isLoading" class="text-text-muted">Loading...</div>

    <div v-else-if="sessionStore.mySessions.length === 0" class="text-text-muted">
      No sessions created yet.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="session in sessionStore.mySessions"
        :key="session.id"
        class="flex items-center justify-between p-4 bg-surface dark:bg-gray-900 border border-border rounded-lg"
      >
        <div>
          <p class="font-medium text-text-main dark:text-surface">{{ session.paper?.title }}</p>
          <p class="text-sm text-text-muted">
            Code: <span class="font-mono font-bold">{{ session.code }}</span>
            &middot; {{ session.status }}
            &middot; {{ new Date(session.created_at).toLocaleDateString() }}
          </p>
        </div>
        <router-link
          :to="{ name: 'Session', params: { code: session.code } }"
          class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface text-sm font-medium transition-colors"
        >
          View
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';

const sessionStore = useKangourouSessionStore();

onMounted(() => {
  sessionStore.fetchMySessions();
});
</script>
