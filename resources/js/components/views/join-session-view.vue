<template>
  <div class="container mx-auto p-6 max-w-lg">
    <h2 class="text-2xl font-bold mb-6 text-text-main dark:text-surface">Rejoindre une session Kangourou</h2>

      <div v-if="deletionMessage" class="mb-6 p-4 bg-warning/10 border border-warning/30 text-warning rounded-lg text-sm font-medium">
        {{ deletionMessage }}
      </div>

      <!-- Recovery banner -->
    <div v-if="storedAttempt" class="mb-6 p-4 bg-info/10 border border-info/30 rounded-lg">
      <p class="text-sm text-text-main dark:text-surface">Vous avez une tentative en cours.</p>
      <button
        @click="resumeAttempt"
        class="mt-2 text-sm text-primary hover:text-primary-hover underline"
      >
        Reprendre la tentative
      </button>
    </div>

    <form @submit.prevent="handleJoin" class="space-y-6">
      <div class="space-y-2">
        <label class="block text-sm font-medium text-text-main dark:text-surface/80">Code de session</label>
        <input
          v-model="code"
          type="text"
          maxlength="6"
          placeholder="Entrez le code à 6 caractères"
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
        Rejoindre
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAttemptStore } from '@/stores/attemptStore';
import { useAuthStore } from '@/stores/authStore';

const route = useRoute();
const router = useRouter();
const attemptStore = useAttemptStore();
const authStore = useAuthStore();
const code = ref('');
const error = ref(null);
const deletionMessage = computed(() => route.query.message || null);

const storedAttempt = ref(null);

onMounted(() => {
  storedAttempt.value = attemptStore.getFromLocalStorage();
});

async function handleJoin() {
  if (code.value.length !== 6) {
    error.value = 'Veuillez entrer un code à 6 caractères.';
    return;
  }

  const upperCode = code.value.toUpperCase();

  // For guests, check if they already have an attempt for this session
  if (!authStore.isAuthenticated) {
    if (attemptStore.guestAttempts.length === 0) {
      await attemptStore.fetchGuestAttempts();
    }
    const existing = attemptStore.guestAttempts.find(
      a => a.kangourou_session?.code === upperCode
    );
    if (existing) {
      router.push({ name: 'Results', params: { code: upperCode, attemptId: existing.id } });
      return;
    }
  }

  router.push({ name: 'Session', params: { code: upperCode } });
}

function resumeAttempt() {
  const stored = storedAttempt.value;
  router.push({
    name: 'Attempt',
    params: { code: stored.session_code, attemptId: stored.attempt_id },
  });
}
</script>
