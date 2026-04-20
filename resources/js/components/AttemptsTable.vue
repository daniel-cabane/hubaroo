<template>
  <div class="bg-surface dark:bg-gray-900 rounded-lg border border-border p-6 space-y-4">
    <div class="flex items-center justify-between gap-4">
      <h2 class="text-xl font-bold text-text-main dark:text-surface">Tentatives ({{ attempts.length }})</h2>
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
                @click="toggleSort('date')"
                class="px-4 py-3 font-semibold text-text-main text-center dark:text-surface/80 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                :title="sortBy === 'date' ? (sortOrder === 'asc' ? 'Ordre croissant' : 'Ordre décroissant') : 'Cliquer pour trier'"
              >
                Date
                <span v-if="sortBy === 'date'" class="ml-1">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
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
                        class="text-text-muted hover:text-info transition-colors"
                      >
                        <Edit class="w-4 h-4" />
                      </button>
                      <button
                        @click="$emit('delete', attempt)"
                        title="Supprimer la tentative"
                        class="text-text-muted hover:text-error transition-colors"
                      >
                        <Trash2 class="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3 text-center">
                  <span
                    :class="`px-2 py-1 rounded text-xs font-semibold ${
                      attempt.status === 'finished'
                        ? 'bg-success/10 text-success'
                        : 'bg-warning/10 text-warning'
                    }`"
                  >
                    {{ statusLabel(attempt.status) }}
                  </span>
                </td>
                <td class="px-4 py-3 font-semibold text-text-main text-center dark:text-surface">
                  {{ attempt.score !== null ? attempt.score : '—' }}
                </td>
                <td class="px-4 py-3 text-text-muted text-center">
                  {{ attempt.timer !== null ? `${Math.floor(attempt.timer / 60)}:${String(attempt.timer % 60).padStart(2, '0')}` : '—' }}
                </td>
                <td class="px-4 py-3 text-text-muted text-center">{{ terminationLabel(attempt.termination) }}</td>
                <td class="px-4 py-3 text-text-muted text-xs text-center">
                  <div>{{ new Date(attempt.created_at).toLocaleDateString('fr-FR') }}</div>
                  <div>{{ new Date(attempt.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}</div>
                </td>
              </tr>
              <tr>
                <td colspan="7" class="px-4 py-4">
                  <div class="flex flex-wrap gap-2">
                    <div
                      v-for="(answer, idx) in attempt.answers"
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
import { ref, computed } from 'vue';
import { Edit, Trash2 } from 'lucide-vue-next';

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

const statusLabels = { active: 'Active', expired: 'Expirée', draft: 'Brouillon', inProgress: 'En cours', finished: 'Terminée' };

function statusLabel(status) {
  return statusLabels[status] || status;
}

const terminationLabels = { none: 'Aucune', submitted: 'Soumise', blurred: 'Floutée', timeout: 'Timeout' };

function terminationLabel(termination) {
  return terminationLabels[termination] || termination;
}

function getAnswerColorClass(status) {
  if (status === 'correct') return 'bg-success text-white';
  if (status === 'incorrect') return 'bg-error text-white';
  if (status === 'unanswered') return 'bg-gray-200 text-text-muted dark:bg-gray-700';
  return 'bg-gray-300 text-text-muted dark:bg-gray-600';
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
      case 'date':
        aVal = new Date(a.created_at).getTime();
        bVal = new Date(b.created_at).getTime();
        return sortOrder.value === 'asc' ? aVal - bVal : bVal - aVal;
      default:
        return 0;
    }
  });

  return filtered;
});
</script>
