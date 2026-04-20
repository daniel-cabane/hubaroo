<template>
  <div class="container mx-auto p-6 max-w-lg">
    <h2 class="text-2xl font-bold mb-6 text-text-main dark:text-surface">Créer une session Kangourou</h2>

    <form v-if="!createdSession" @submit.prevent="handleCreate" class="space-y-6">
      <!-- Paper Selection: Year and Level -->
      <div class="flex flex-wrap gap-4">
        <div class="flex-1 min-w-[140px]">
          <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Année</label>
          <select
            v-model="selectedYear"
            class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
          >
            <option value="" disabled>Choisir une année</option>
            <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
          </select>
        </div>
        <div class="flex-1 min-w-[140px]">
          <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Niveau</label>
          <select
            v-model="selectedLevel"
            class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
          >
            <option value="" disabled>Choisir un niveau</option>
            <option v-for="level in availableLevels" :key="level.value" :value="level.value">{{ level.label }}</option>
          </select>
        </div>
      </div>
      <p v-if="!matchedPaper && selectedYear && selectedLevel" class="text-sm text-text-muted">
        Aucun sujet disponible pour cette combinaison.
      </p>

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
        :disabled="sessionStore.isLoading || !matchedPaper"
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
import { ref, onMounted, reactive, computed, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const sessionStore = useKangourouSessionStore();
const createdSession = ref(null);

const selectedYear = ref('');
const selectedLevel = ref('');

const levelNames = {
  e: 'E - Écoliers (CE2-CM2)',
  b: 'B - Benjamin (6e-5e)',
  c: 'C - Cadet (4e-3e)',
  p: 'P - Lycée Pro',
  j: 'J - Junior (Lycée G.&T.)',
  s: 'S - Spécialité (1re-Term)',
};

const availableYears = computed(() => {
  const years = [...new Set(sessionStore.papers.map(p => p.year))];
  return years.sort((a, b) => b - a);
});

const availableLevels = computed(() => {
  const levels = [...new Set(sessionStore.papers.map(p => p.level))];
  const levelOrder = ['e', 'b', 'c', 'p', 'j', 's'];
  const sortedLevels = levels.sort((a, b) => levelOrder.indexOf(a) - levelOrder.indexOf(b));
  return sortedLevels.map(l => ({ value: l, label: levelNames[l] || l }));
});

const matchedPaper = computed(() => {
  if (!selectedYear.value || !selectedLevel.value) {
    return null;
  }
  return sessionStore.papers.find(
    p => p.year === Number(selectedYear.value) && p.level === selectedLevel.value
  );
});

const form = reactive({
  paper_id: '',
  status: 'draft',
});

watch(matchedPaper, (paper) => {
  if (paper) {
    form.paper_id = paper.id;
  }
});

onMounted(() => {
  sessionStore.fetchPapers();
  if (route.query.paper_id) {
    const paper = sessionStore.papers.find(p => p.id === Number(route.query.paper_id));
    if (paper) {
      selectedYear.value = paper.year;
      selectedLevel.value = paper.level;
    }
  }
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
