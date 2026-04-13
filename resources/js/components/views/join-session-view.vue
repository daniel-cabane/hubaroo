<template>
  <div class="container mx-auto p-6 max-w-lg">
    <h2 class="text-2xl font-bold mb-6 text-text-main dark:text-surface">Join a Kangourou Session</h2>

    <!-- Recovery banner -->
    <div v-if="storedAttempt" class="mb-6 p-4 bg-info/10 border border-info/30 rounded-lg">
      <p class="text-sm text-text-main dark:text-surface">You have an active attempt.</p>
      <button
        @click="resumeAttempt"
        class="mt-2 text-sm text-primary hover:text-primary-hover underline"
      >
        Resume attempt
      </button>
    </div>

    <form @submit.prevent="handleJoin" class="space-y-6">
      <div class="space-y-2">
        <label class="block text-sm font-medium text-text-main dark:text-surface/80">Session Code</label>
        <input
          v-model="code"
          type="text"
          maxlength="6"
          placeholder="Enter 6-character code"
          class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-center text-2xl font-mono tracking-widest uppercase"
          required
        />
      </div>

      <div v-if="error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg text-sm">
        {{ error }}
      </div>

      <button
        type="submit"
        class="w-full bg-primary hover:bg-primary-hover text-surface font-medium py-2 px-4 rounded-lg transition-colors"
      >
        Join Session
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAttemptStore } from '@/stores/attemptStore';

const router = useRouter();
const attemptStore = useAttemptStore();
const code = ref('');
const error = ref(null);
const storedAttempt = ref(null);

onMounted(() => {
  storedAttempt.value = attemptStore.getFromLocalStorage();
});

function handleJoin() {
  if (code.value.length !== 6) {
    error.value = 'Please enter a 6-character code.';
    return;
  }
  router.push({ name: 'Session', params: { code: code.value.toUpperCase() } });
}

function resumeAttempt() {
  const stored = storedAttempt.value;
  router.push({
    name: 'Attempt',
    params: { code: stored.session_code, attemptId: stored.attempt_id },
  });
}
</script>
