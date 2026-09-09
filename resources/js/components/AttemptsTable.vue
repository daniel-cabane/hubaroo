<template>
  <div class="bg-surface dark:bg-gray-900 rounded-lg border border-border p-6 space-y-4">
    <div class="flex items-center justify-between gap-4" v-if="attempts.length">
      <span></span>
      <input
        v-model="filterName"
        type="text"
        placeholder="Filtrer par nom..."
        class="w-1/2 max-w-md px-4 py-2 border border-border dark:border-border/50 rounded-lg bg-white dark:bg-gray-800 text-text-main dark:text-surface placeholder-text-muted dark:placeholder-text-muted/50"
      />
    </div>

    <div v-if="attempts.length === 0" class="text-text-muted py-8 text-center">
      Aucune tentative pour cette session.
    </div>

    <div v-else class="space-y-4">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="border-b border-border">
            <tr class="text-left">
              <th
                @click="toggleSort('name')"
                class="px-4 py-3 font-semibold text-text-main text-center dark:text-surface/80 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                :title="sortBy === 'name' ? (sortOrder === 'asc' ? 'Ordre croissant' : 'Ordre décroissant') : 'Cliquer pour trier'"
              >
                Participant
                <span v-if="sortBy === 'name'" class="ml-1">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
              </th>
              <th
                @click="toggleSort('status')"
                class="px-4 py-3 font-semibold text-text-main text-center dark:text-surface/80 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                :title="sortBy === 'status' ? (sortOrder === 'asc' ? 'Ordre croissant' : 'Ordre décroissant') : 'Cliquer pour trier'"
              >
                Statut
                <span v-if="sortBy === 'status'" class="ml-1">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
              </th>
              <th
                @click="toggleSort('score')"
                class="px-4 py-3 font-semibold text-text-main text-center dark:text-surface/80 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                :title="sortBy === 'score' ? (sortOrder === 'asc' ? 'Ordre croissant' : 'Ordre décroissant') : 'Cliquer pour trier'"
              >
                Score
                <span v-if="sortBy === 'score'" class="ml-1">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
              </th>
              <th
                @click="toggleSort('timer')"
                class="px-4 py-3 font-semibold text-text-main text-center dark:text-surface/80 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                :title="sortBy === 'timer' ? (sortOrder === 'asc' ? 'Ordre croissant' : 'Ordre décroissant') : 'Cliquer pour trier'"
              >
                Temps
                <span v-if="sortBy === 'timer'" class="ml-1">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
              </th>
              <th
                @click="toggleSort('termination')"
                class="px-4 py-3 font-semibold text-text-main text-center dark:text-surface/80 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                :title="sortBy === 'termination' ? (sortOrder === 'asc' ? 'Ordre croissant' : 'Ordre décroissant') : 'Cliquer pour trier'"
              >
                Clôture
                <span v-if="sortBy === 'termination'" class="ml-1">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
              </th>
              <th
                @click="toggleSort('updated')"
                class="px-4 py-3 font-semibold text-text-main text-center dark:text-surface/80 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                :title="sortBy === 'updated' ? (sortOrder === 'asc' ? 'Ordre croissant' : 'Ordre décroissant') : 'Cliquer pour trier'"
              >
                Mis à jour
                <span v-if="sortBy === 'updated'" class="ml-1">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <template v-for="attempt in filteredAndSortedAttempts" :key="attempt.id">
              <tr>
                <td rowspan="2" class="px-4 py-3">
                  <div class="space-y-2">
                    <div class="font-medium text-text-main text-center dark:text-surface">{{ attempt.name || `Utilisateur #${attempt.user_id}` }}</div>
                    <div v-if="editable" class="flex justify-center pt-2 gap-4">
                      <button
                        @click="$emit('edit', attempt)"
                        title="Éditer le nom"
                        class="text-text-muted hover:text-info transition-colors cursor-pointer"
                      >
                        <Edit class="w-4 h-4" />
                      </button>
                      <button
                        @click="$emit('delete', attempt)"
                        title="Supprimer la tentative"
                        :disabled="attempt.status !== 'finished'"
                        class="text-text-muted hover:text-error transition-colors cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:text-text-muted"
                      >
                        <Trash2 class="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="statusBadgeClass(attempt)">
                    {{ statusLabel(attempt) }}
                  </span>
                </td>
                <td class="px-4 py-3 font-semibold text-text-main text-center dark:text-surface">
                  {{ attempt.score !== null && attempt.score !== undefined ? attempt.score : '—' }}
                </td>
                <td class="px-4 py-3 text-text-muted text-center font-mono">
                  {{ formatDisplayedTimer(attempt) }}
                </td>
                <td class="px-4 py-3 text-text-muted text-center">{{ terminationLabel(attempt.termination) }}</td>
                <td class="px-4 py-3 text-text-muted text-xs text-center" :title="new Date(attempt.updated_at).toLocaleString('fr-FR')">
                  {{ timeAgo(attempt.updated_at) }}
                </td>
              </tr>
              <tr>
                <td colspan="7" class="px-4 py-4">
                  <div v-if="attempt.status === 'inProgress'" class="flex flex-col items-center gap-1 text-center">
                    <span class="font-medium text-text-main dark:text-surface">{{ formatProgress(attempt) }}</span>
                    <span class="text-xs" :class="isStale(attempt) ? 'text-warning' : 'text-text-muted'">
                      {{ formatFreshness(attempt) }}
                    </span>
                  </div>
                  <div v-else-if="answersFor(attempt)" class="flex flex-wrap gap-2">
                    <div
                      v-for="(answer, idx) in answersFor(attempt)"
                      :key="idx"
                      :title="`Q${idx + 1}: ${answer.status == 'unanswered' ? 'Pas de réponse' : answer.status}`"
                      :class="`
                        w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold cursor-help
                        ${getAnswerColorClass(answer.status)}
                      `"
                    >
                      {{ answer.answer || '' }}
                    </div>
                  </div>
                  <div v-else-if="answerLoadState[attempt.id]?.loading" class="text-center text-sm text-text-muted">
                    Chargement des réponses...
                  </div>
                  <div v-else class="flex flex-col items-center gap-2 text-center">
                    <p class="text-sm text-error">Impossible de charger les réponses.</p>
                    <button
                      @click="loadAttemptAnswers(attempt.id)"
                      class="px-3 py-1.5 rounded-lg bg-error text-white text-xs font-medium hover:bg-error/90 cursor-pointer"
                    >
                      Réessayer
                    </button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Edit, Trash2 } from 'lucide-vue-next';
import axios from 'axios';
import { ATTEMPT_SYNC_INTERVAL_MS } from '@/stores/attemptStore';

const props = defineProps({
  attempts: {
    type: Array,
    required: true,
  },
  editable: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['edit', 'delete']);

const filterName = ref('');
const sortBy = ref('name');
const sortOrder = ref('asc');
const observationNow = ref(Date.now());
const fetchedAnswers = ref({});
const answerLoadState = ref({});

let observationClockTimer = null;

onMounted(() => {
  observationClockTimer = setInterval(() => {
    observationNow.value = Date.now();
  }, 1000);
});

onUnmounted(() => {
  if (observationClockTimer) {
    clearInterval(observationClockTimer);
  }
});

watch(
  () => props.attempts.map((attempt) => `${attempt.id}:${attempt.status}:${Array.isArray(attempt.answers)}`).join('|'),
  () => {
    props.attempts.forEach((attempt) => {
      if (attempt.status === 'finished' && !answersFor(attempt)) {
        void loadAttemptAnswers(attempt.id);
      }
    });
  },
  { immediate: true },
);

function answersFor(attempt) {
  if (Array.isArray(attempt.answers)) {
    return attempt.answers;
  }

  return fetchedAnswers.value[attempt.id] ?? null;
}

async function loadAttemptAnswers(attemptId) {
  if (!attemptId || answerLoadState.value[attemptId]?.loading) {
    return;
  }

  answerLoadState.value = {
    ...answerLoadState.value,
    [attemptId]: { loading: true, error: false },
  };

  try {
    const response = await axios.get(`/api/attempts/${attemptId}`);
    fetchedAnswers.value = {
      ...fetchedAnswers.value,
      [attemptId]: response.data.attempt?.answers ?? [],
    };
    answerLoadState.value = {
      ...answerLoadState.value,
      [attemptId]: { loading: false, error: false },
    };
  } catch {
    answerLoadState.value = {
      ...answerLoadState.value,
      [attemptId]: { loading: false, error: true },
    };
  }
}

function getSyncTimestamp(attempt) {
  if (typeof attempt?.last_sync_received_at === 'number') {
    return attempt.last_sync_received_at;
  }

  if (attempt?.updated_at) {
    return new Date(attempt.updated_at).getTime();
  }

  return observationNow.value;
}

function isStale(attempt) {
  if (!attempt || attempt.status !== 'inProgress') {
    return false;
  }

  return (observationNow.value - getSyncTimestamp(attempt)) > ATTEMPT_SYNC_INTERVAL_MS * 2;
}

function statusLabel(attempt) {
  if (attempt.status === 'finished') {
    return 'Terminée';
  }

  return isStale(attempt) ? 'Sync lent' : 'En cours';
}

function statusBadgeClass(attempt) {
  if (attempt.status === 'finished') {
    return 'px-2 py-1 rounded text-xs font-semibold bg-success/10 text-success';
  }

  if (isStale(attempt)) {
    return 'px-2 py-1 rounded text-xs font-semibold bg-warning/10 text-warning';
  }

  return 'px-2 py-1 rounded text-xs font-semibold bg-success/10 text-success';
}

function formatProgress(attempt) {
  const answeredCount = attempt.answered_count ?? (Array.isArray(attempt.answers)
    ? attempt.answers.filter((item) => item?.answer !== null && item?.answer !== undefined && item?.answer !== '').length
    : 0);
  const totalQuestions = attempt.total_questions ?? attempt.answers?.length ?? 26;

  return `${answeredCount} / ${totalQuestions}`;
}

function formatTimer(seconds) {
  if (seconds === null || seconds === undefined) {
    return '—';
  }

  const safeSeconds = Math.max(0, seconds);
  const m = Math.floor(safeSeconds / 60);
  const s = safeSeconds % 60;

  return `${m}:${String(s).padStart(2, '0')}`;
}

function getRemainingSeconds(attempt) {
  if (!attempt || attempt.status !== 'inProgress') {
    return attempt?.timer ?? null;
  }

  const elapsedSeconds = Math.max(0, Math.floor((observationNow.value - getSyncTimestamp(attempt)) / 1000));

  return Math.max(0, (attempt.timer ?? 0) - elapsedSeconds);
}

function formatDisplayedTimer(attempt) {
  if (attempt.status === 'inProgress') {
    return formatTimer(getRemainingSeconds(attempt));
  }

  return attempt.timer !== null && attempt.timer !== undefined ? formatTimer(attempt.timer) : '—';
}

function formatFreshness(attempt) {
  const ageSeconds = Math.max(0, Math.floor((observationNow.value - getSyncTimestamp(attempt)) / 1000));
  if (ageSeconds < 2) {
    return 'à l’instant';
  }

  return `mise à jour il y a ${ageSeconds}s`;
}

const terminationLabels = { none: 'Aucune', submitted: 'Soumise', blurred: 'Floutée', timeout: 'Timeout', abandoned: 'Abandonnée' };

function terminationLabel(termination) {
  return terminationLabels[termination] || termination;
}

function getAnswerColorClass(status) {
  if (status === 'correct') return 'bg-success text-white';
  if (status === 'incorrect') return 'bg-error text-white';
  if (status === 'unanswered') return 'bg-gray-200 text-text-muted dark:bg-gray-700';
  return 'bg-gray-300 text-text-muted dark:bg-gray-600';
}

function timeAgo(dateStr) {
  const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
  if (diff < 60) return 'À l\'instant';
  if (diff < 3600) return `Il y a ${Math.floor(diff / 60)} min`;
  if (diff < 86400) return `Il y a ${Math.floor(diff / 3600)} h`;
  if (diff < 2592000) return `Il y a ${Math.floor(diff / 86400)} j`;
  return new Date(dateStr).toLocaleDateString('fr-FR');
}

function toggleSort(column) {
  if (sortBy.value === column) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortBy.value = column;
    sortOrder.value = 'asc';
  }
}

const filteredAndSortedAttempts = computed(() => {
  let filtered = props.attempts.filter(attempt => {
    const name = (attempt.name || `Utilisateur #${attempt.user_id}`).toLowerCase();
    return name.includes(filterName.value.toLowerCase());
  });

  filtered.sort((a, b) => {
    let aVal, bVal;

    switch (sortBy.value) {
      case 'name':
        aVal = (a.name || `Utilisateur #${a.user_id}`).toLowerCase();
        bVal = (b.name || `Utilisateur #${b.user_id}`).toLowerCase();
        return sortOrder.value === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
      case 'status':
        aVal = a.status || '';
        bVal = b.status || '';
        return sortOrder.value === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
      case 'score':
        aVal = a.score ?? -1;
        bVal = b.score ?? -1;
        return sortOrder.value === 'asc' ? aVal - bVal : bVal - aVal;
      case 'timer':
        aVal = a.timer ?? -1;
        bVal = b.timer ?? -1;
        return sortOrder.value === 'asc' ? aVal - bVal : bVal - aVal;
      case 'termination':
        aVal = a.termination || '';
        bVal = b.termination || '';
        return sortOrder.value === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
      case 'updated':
        aVal = new Date(a.updated_at).getTime();
        bVal = new Date(b.updated_at).getTime();
        return sortOrder.value === 'asc' ? aVal - bVal : bVal - aVal;
      default:
        return 0;
    }
  });

  return filtered;
});
</script>
