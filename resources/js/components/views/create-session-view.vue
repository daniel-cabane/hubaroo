<template>
  <div class="container mx-auto p-6 max-w-lg">
    <h2 class="text-2xl font-bold mb-6 text-text-main dark:text-surface">Create a Kangourou Session</h2>

    <form @submit.prevent="handleCreate" class="space-y-6">
      <!-- Paper Selection -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-text-main dark:text-surface/80">Paper</label>
        <select
          v-model="form.paper_id"
          class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
          required
        >
          <option value="" disabled>Select a paper</option>
          <option v-for="paper in sessionStore.papers" :key="paper.id" :value="paper.id">
            {{ paper.title }}
          </option>
        </select>
      </div>

      <!-- Privacy -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-text-main dark:text-surface/80">Privacy</label>
        <select
          v-model="form.privacy"
          class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
        >
          <option value="public">Public</option>
          <option value="private">Private</option>
        </select>
      </div>

      <!-- Time Limit -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-text-main dark:text-surface/80">Time Limit (minutes)</label>
        <input
          v-model.number="form.preferences.time_limit"
          type="number"
          min="1"
          max="180"
          class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
        />
      </div>

      <!-- Correction Mode -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-text-main dark:text-surface/80">Correction</label>
        <select
          v-model="form.preferences.correction"
          class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
        >
          <option value="delayed">Delayed (after submission)</option>
          <option value="immediate">Immediate</option>
        </select>
      </div>

      <!-- Grading Rules -->
      <fieldset class="space-y-3 border border-border rounded-lg p-4">
        <legend class="text-sm font-medium text-text-main dark:text-surface/80 px-1">Grading Rules</legend>

        <div class="grid grid-cols-3 gap-3">
          <div class="space-y-1">
            <label class="block text-xs text-text-muted">Tier 1 (Q1–8)</label>
            <input
              v-model.number="form.preferences.grading.tier1"
              type="number"
              min="0"
              step="1"
              class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
          <div class="space-y-1">
            <label class="block text-xs text-text-muted">Tier 2 (Q9–16)</label>
            <input
              v-model.number="form.preferences.grading.tier2"
              type="number"
              min="0"
              step="1"
              class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
          <div class="space-y-1">
            <label class="block text-xs text-text-muted">Tier 3 (Q17–24)</label>
            <input
              v-model.number="form.preferences.grading.tier3"
              type="number"
              min="0"
              step="1"
              class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="block text-xs text-text-muted">Penalty fraction</label>
            <input
              v-model.number="form.preferences.grading.penalty_fraction"
              type="number"
              min="0"
              max="1"
              step="0.05"
              class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary"
            />
            <p class="text-xs text-text-muted">e.g. 0.25 = 1/4 of points deducted</p>
          </div>
          <div class="space-y-1">
            <label class="block text-xs text-text-muted">Tier 4 bonus (Q25–26)</label>
            <input
              v-model.number="form.preferences.grading.tier4_bonus"
              type="number"
              min="0"
              step="1"
              class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
        </div>
      </fieldset>

      <!-- Error Message -->
      <div v-if="sessionStore.error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg text-sm">
        {{ sessionStore.error }}
      </div>

      <button
        type="submit"
        :disabled="sessionStore.isLoading"
        class="w-full bg-primary hover:bg-primary-hover text-surface font-medium py-2 px-4 rounded-lg transition-colors disabled:opacity-50"
      >
        {{ sessionStore.isLoading ? 'Creating...' : 'Create Session' }}
      </button>
    </form>

    <!-- Show created session code -->
    <div v-if="createdSession" class="mt-8 p-6 bg-success/10 border border-success/30 rounded-lg text-center">
      <p class="text-sm text-text-muted mb-2">Session created! Share this code:</p>
      <p class="text-4xl font-mono font-bold tracking-widest text-success">{{ createdSession.code }}</p>
      <button
        @click="goToSession"
        class="mt-4 bg-primary hover:bg-primary-hover text-surface font-medium py-2 px-6 rounded-lg transition-colors"
      >
        Go to Session
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';

const router = useRouter();
const sessionStore = useKangourouSessionStore();
const createdSession = ref(null);

const form = reactive({
  paper_id: '',
  privacy: 'public',
  preferences: {
    time_limit: 50,
    correction: 'delayed',
    grading: {
      tier1: 3,
      tier2: 4,
      tier3: 5,
      tier4_bonus: 1,
      penalty_fraction: 0.25,
    },
  },
});

onMounted(() => {
  sessionStore.fetchPapers();
});

async function handleCreate() {
  try {
    const session = await sessionStore.createSession(
      form.paper_id,
      form.privacy,
      form.preferences,
    );
    createdSession.value = session;
  } catch {
    // error displayed by store
  }
}

function goToSession() {
  router.push({ name: 'Session', params: { code: createdSession.value.code } });
}
</script>
