<template>
  <div class="container mx-auto p-6 max-w-lg">
    <h2 class="text-2xl font-bold mb-6 text-text-main dark:text-surface">Créer une session Kangourou</h2>

    <form v-if="!createdSession" @submit.prevent="handleCreate" class="space-y-6">
      <!-- Paper Selection: Year and Level -->
      <div class="space-y-2">
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
        <button
          type="button"
          @click="previewPaper"
          :disabled="!selectedYear || !selectedLevel"
          class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg border border-border dark:border-border/50 text-sm font-medium text-text-main dark:text-surface hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
        >
          <Eye class="w-4 h-4 shrink-0" />
          Aperçu
        </button>
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

    <!-- Paper Preview Modal -->
    <div
      v-if="showPreviewModal"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
      @click.self="showPreviewModal = false"
    >
        <div class="bg-surface dark:bg-gray-900 rounded-2xl shadow-xl flex flex-col w-full max-w-2xl h-[90vh]">
        <!-- Modal header -->
        <div class="flex items-center gap-2 p-4 border-b border-border shrink-0">
          <button
            @click="changePreviewYear(-1)"
            :disabled="previewYearIndex >= previewYears.length - 1"
            class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors disabled:opacity-30"
          >
            <ChevronLeft class="w-5 h-5 text-text-main dark:text-surface" />
          </button>
          <div class="flex-1 text-center">
            <p class="text-xs text-text-muted">{{ previewLevelLabel }}</p>
            <p class="text-lg font-bold text-text-main dark:text-surface">{{ previewYear }}</p>
          </div>
          <button
            @click="changePreviewYear(1)"
            :disabled="previewYearIndex <= 0"
            class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors disabled:opacity-30"
          >
            <ChevronRight class="w-5 h-5 text-text-main dark:text-surface" />
          </button>
          <button
            @click="selectPreviewPaper"
            class="ml-2 px-4 py-1.5 rounded-lg bg-primary hover:bg-primary-hover text-surface text-sm font-medium transition-colors"
          >
            Sélectionner
          </button>
          <button
            @click="showPreviewModal = false"
            class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
          >
            <X class="w-5 h-5 text-text-main dark:text-surface" />
          </button>
        </div>

        <!-- Modal body -->
        <div class="overflow-y-auto p-4 space-y-4">
          <div v-if="paperStore.isLoading" class="text-center py-8 text-text-muted">Chargement...</div>
          <template v-else-if="paperStore.currentPaper?.questions">
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
          </template>
          <div v-else class="text-center py-8 text-text-muted">Aucun sujet disponible.</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive, computed, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { Eye, ChevronLeft, ChevronRight, X } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/authStore';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';
import { usePaperStore } from '@/stores/paperStore';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const sessionStore = useKangourouSessionStore();
const paperStore = usePaperStore();
const createdSession = ref(null);
const showPreviewModal = ref(false);
const previewYear = ref(null);

const selectedYear = ref('');
const selectedLevel = ref('');

const levelNames = {
  e: 'E - Écoliers (CE2-CM2)',
  b: 'B - Benjamin (6e-5e)',
  c: 'C - Cadet (4e-3e)',
  p: 'P - Pro (Lycée Pro)',
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

const previewYears = computed(() => {
  const years = sessionStore.papers
    .filter(p => p.level === selectedLevel.value)
    .map(p => p.year);
  return [...new Set(years)].sort((a, b) => b - a);
});

const previewYearIndex = computed(() => previewYears.value.indexOf(Number(previewYear.value)));

const previewLevelLabel = computed(() => levelNames[selectedLevel.value] || selectedLevel.value);

function changePreviewYear(direction) {
  const newIndex = previewYearIndex.value - direction;
  if (newIndex >= 0 && newIndex < previewYears.value.length) {
    previewYear.value = previewYears.value[newIndex];
    loadPreviewPaper();
  }
}

async function loadPreviewPaper() {
  const paper = sessionStore.papers.find(
    p => p.year === Number(previewYear.value) && p.level === selectedLevel.value
  );
  if (paper) {
    await paperStore.fetchPaper(paper.id);
  }
}

async function previewPaper() {
  previewYear.value = selectedYear.value;
  paperStore.currentPaper = null;
  showPreviewModal.value = true;
  await loadPreviewPaper();
}

function selectPreviewPaper() {
  selectedYear.value = previewYear.value;
  showPreviewModal.value = false;
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
