<template>
  <div class="container mx-auto p-6 max-w-3xl">
    <div class="flex items-center gap-3 mb-6">
      <button
        @click="$router.back()"
        class="w-10 h-10 rounded-full flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
      >
        <ChevronLeft class="w-5 h-5 text-text-main dark:text-surface" />
      </button>
      <h2 class="text-2xl font-bold text-text-main dark:text-surface">Voir un sujet</h2>
    </div>

    <!-- Year and Level selects -->
    <div class="flex flex-wrap gap-4 mb-6">
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
      <div class="flex items-end">
        <button
          @click="loadPaper"
          :disabled="!matchedPaper || paperStore.isLoading"
          class="px-5 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors disabled:opacity-50"
        >
          Voir
        </button>
      </div>
    </div>

    <p v-if="!matchedPaper && selectedYear && selectedLevel" class="text-sm text-text-muted mb-4">
      Aucun sujet disponible pour cette combinaison.
    </p>

    <!-- Loading -->
    <div v-if="paperStore.isLoading" class="text-text-muted">Chargement...</div>

    <!-- Paper content -->
    <div v-else-if="paperStore.currentPaper && paperStore.currentPaper.questions">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-semibold text-text-main dark:text-surface">
          {{ paperStore.currentPaper.title }}
        </h3>
        <router-link
          v-if="authStore.isAuthenticated"
          :to="{ name: 'CreateSession', query: { paper_id: paperStore.currentPaper.id } }"
          class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface text-sm font-medium transition-colors"
        >
          Créer une session
        </router-link>
      </div>

      <div class="space-y-6">
        <div
          v-for="(question, index) in paperStore.currentPaper.questions"
          :key="question.id"
          class="rounded-lg border border-border bg-surface dark:bg-gray-900 p-4"
        >
          <p class="text-sm font-medium text-text-muted mb-2">Question {{ index + 1 }}</p>
          <img
            :src="'/' + question.image"
            :alt="'Question ' + (index + 1)"
            class="w-full rounded"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ChevronLeft } from 'lucide-vue-next';
import { usePaperStore } from '@/stores/paperStore';
import { useAuthStore } from '@/stores/authStore';

const route = useRoute();
const paperStore = usePaperStore();
const authStore = useAuthStore();

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
  const years = [...new Set(paperStore.papers.map(p => p.year))];
  return years.sort((a, b) => b - a);
});

const availableLevels = computed(() => {
  const levels = [...new Set(paperStore.papers.map(p => p.level))];
  const levelOrder = ['e', 'b', 'c', 'p', 'j', 's'];
  const sortedLevels = levels.sort((a, b) => levelOrder.indexOf(a) - levelOrder.indexOf(b));
  return sortedLevels.map(l => ({ value: l, label: levelNames[l] || l }));
});

const matchedPaper = computed(() => {
  if (!selectedYear.value || !selectedLevel.value) {
    return null;
  }
  return paperStore.papers.find(
    p => p.year === Number(selectedYear.value) && p.level === selectedLevel.value
  );
});

async function loadPaper() {
  if (!matchedPaper.value) {
    return;
  }
  await paperStore.fetchPaper(matchedPaper.value.id);
}

onMounted(async () => {
  await paperStore.fetchPapers();

  // If navigated with query params, pre-select and load
  if (route.query.year && route.query.level) {
    selectedYear.value = Number(route.query.year);
    selectedLevel.value = route.query.level;
    if (matchedPaper.value) {
      loadPaper();
    }
  }
});
</script>
