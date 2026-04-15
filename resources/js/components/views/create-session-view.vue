<template>
  <div class="container mx-auto p-6 max-w-lg">
    <h2 class="text-2xl font-bold mb-6 text-text-main dark:text-surface">Créer une session Kangourou</h2>

    <form v-if="!createdSession" @submit.prevent="handleCreate" class="space-y-6">
      <!-- Paper Selection -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-text-main dark:text-surface/80">Sujet</label>
        <select
          v-model="form.paper_id"
          class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
          required
        >
          <option value="" disabled>Choisir un sujet</option>
          <option v-for="paper in sessionStore.papers" :key="paper.id" :value="paper.id">
            {{ paper.title }}
          </option>
        </select>
      </div>

      <!-- Status -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-text-main dark:text-surface/80">Statut</label>
        <select
          v-model="form.status"
          class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
        >
          <option value="draft">Brouillon</option>
          <option value="active">Active</option>
        </select>
      </div>

      <!-- Error Message -->
      <div v-if="sessionStore.error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg text-sm">
        {{ sessionStore.error }}
      </div>

      <button
        type="submit"
        :disabled="sessionStore.isLoading"
        class="w-full bg-primary hover:bg-primary-hover text-surface font-medium py-2 px-4 rounded-lg transition-colors disabled:opacity-50"
      >
        {{ sessionStore.isLoading ? 'Création...' : 'Créer la session' }}
      </button>
    </form>

    <!-- Show created session code -->
    <div v-if="createdSession" class="mt-8 p-6 bg-success/10 border border-success/30 rounded-lg text-center">
      <p class="text-sm text-text-muted mb-2">Session créée ! Partagez ce code :</p>
      <p class="text-4xl font-mono font-bold tracking-widest text-success">{{ createdSession.code }}</p>
      <button
        @click="goToSession"
        class="mt-4 bg-primary hover:bg-primary-hover text-surface font-medium py-2 px-6 rounded-lg transition-colors"
      >
        Accéder à la session
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';

const router = useRouter();
const authStore = useAuthStore();
const sessionStore = useKangourouSessionStore();
const createdSession = ref(null);

const form = reactive({
  paper_id: '',
  status: 'draft',
});

onMounted(() => {
  sessionStore.fetchPapers();
});

async function handleCreate() {
  try {
    const session = await sessionStore.createSession(
      form.paper_id,
      'public',
      null,
      form.status,
    );
    createdSession.value = session;
  } catch {
    // error displayed by store
  }
}

function goToSession() {
  // Guests navigate using code, authenticated users navigate to details page
  if (authStore.isAuthenticated) {
    router.push({ name: 'SessionDetails', params: { id: createdSession.value.id } });
  } else {
    router.push({ name: 'Session', params: { code: createdSession.value.code } });
  }
}
</script>
