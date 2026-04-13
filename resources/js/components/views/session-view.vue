<template>
  <div class="container mx-auto p-6 max-w-lg text-center">
    <div v-if="isLoading" class="text-text-muted">Loading session...</div>

    <div v-else-if="error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg">
      {{ error }}
    </div>

    <div v-else-if="session">
      <h2 class="text-2xl font-bold mb-2 text-text-main dark:text-surface">{{ session.paper?.title }}</h2>
      <p class="text-lg font-mono tracking-widest text-primary mb-4">{{ session.code }}</p>

      <div v-if="session.status === 'active' && !isExpired" class="space-y-4">
        <p class="text-text-muted">Session is active. Joining...</p>
        <div class="animate-pulse text-primary text-sm">Creating your attempt...</div>
      </div>

      <div v-else class="space-y-4">
        <p class="text-text-muted">This session has expired.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';
import { useAttemptStore } from '@/stores/attemptStore';

const route = useRoute();
const router = useRouter();
const sessionStore = useKangourouSessionStore();
const attemptStore = useAttemptStore();

const session = ref(null);
const isLoading = ref(true);
const error = ref(null);

const isExpired = computed(() => {
  if (!session.value) return true;
  return session.value.status === 'expired' || new Date(session.value.expires_at) < new Date();
});

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
        error.value = 'You already have an active attempt for another session. Please finish or dismiss it first.';
        return;
      }

      // Create a new attempt
      const attempt = await attemptStore.createAttempt(code);
      router.replace({
        name: 'Attempt',
        params: { code, attemptId: attempt.id },
      });
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Session not found.';
  } finally {
    isLoading.value = false;
  }
});
</script>
