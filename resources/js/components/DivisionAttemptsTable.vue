<template>
  <div v-if="loading" class="text-text-muted text-center py-8">Chargement...</div>
  <div v-else class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="border-b border-border">
        <tr class="text-left">
          <th class="px-4 py-3 font-semibold text-text-main dark:text-surface/80">Élève</th>
          <th class="px-4 py-3 font-semibold text-text-main text-center dark:text-surface/80">Statut</th>
          <th class="px-4 py-3 font-semibold text-text-main text-center dark:text-surface/80">Score</th>
          <th class="px-4 py-3 font-semibold text-text-main text-center dark:text-surface/80">Temps</th>
          <th class="px-4 py-3 font-semibold text-text-main text-center dark:text-surface/80">Clôture</th>
          <th class="px-4 py-3 font-semibold text-text-main text-center dark:text-surface/80">Mis à jour</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-border">
        <template v-for="student in sortedStudents" :key="student.id">
          <template v-if="attemptMap[student.id]">
            <tr>
              <td rowspan="2" class="px-4 py-3">
                <div class="font-medium text-text-main dark:text-surface">{{ student.pivot?.class_name || student.name }}</div>
                <div class="text-xs text-text-muted">{{ student.email }}</div>
                <div class="flex justify-center mt-1">
                <button
                  @click="$emit('delete', attemptMap[student.id])"
                  title="Supprimer la tentative"
                  :disabled="attemptMap[student.id].status !== 'finished'"
                  class="text-text-muted hover:text-error transition-colors cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:text-text-muted"
                >
                  <Trash2 class="w-5 h-5" />
                </button>
                </div>
              </td>
              <td class="px-4 py-3 text-center">
                <span :class="`px-2 py-1 rounded text-xs font-semibold ${ attemptMap[student.id].status === 'finished' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning' }`">
                  {{ statusLabel(attemptMap[student.id].status) }}
                </span>
              </td>
              <td class="px-4 py-3 font-semibold text-text-main text-center dark:text-surface">
                {{ attemptMap[student.id].score !== null ? attemptMap[student.id].score : '—' }}
              </td>
              <td class="px-4 py-3 text-text-muted text-center">
                {{ attemptMap[student.id].timer !== null ? `${Math.floor(attemptMap[student.id].timer / 60)}:${String(attemptMap[student.id].timer % 60).padStart(2, '0')}` : '—' }}
              </td>
              <td class="px-4 py-3 text-text-muted text-center">{{ terminationLabel(attemptMap[student.id].termination) }}</td>
              <td class="px-4 py-3 text-text-muted text-xs text-center" :title="new Date(attemptMap[student.id].updated_at).toLocaleString('fr-FR')">
                {{ timeAgo(attemptMap[student.id].updated_at) }}
              </td>
            </tr>
            <tr>
              <td colspan="5" class="px-4 py-3">
                <div class="flex flex-wrap gap-2">
                  <div
                    v-for="(answer, idx) in attemptMap[student.id].answers"
                    :key="idx"
                    :title="`Q${idx + 1}: ${answer.status === 'unanswered' ? 'Pas de réponse' : answer.status}`"
                    :class="`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold cursor-help ${answerColorClass(answer.status)}`"
                  >
                    {{ answer.answer || '' }}
                  </div>
                </div>
              </td>
            </tr>
          </template>
          <tr v-else>
            <td class="px-4 py-3">
              <div class="font-medium text-text-main dark:text-surface">{{ student.pivot?.class_name || student.name }}</div>
              <div class="text-xs text-text-muted">{{ student.email }}</div>
            </td>
            <td colspan="5" class="px-4 py-3 text-center text-text-muted italic">
              Aucune tentative
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Trash2 } from 'lucide-vue-next';

defineEmits(['delete']);

const props = defineProps({
  students: {
    type: Array,
    required: true,
  },
  attempts: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const sortedStudents = computed(() =>
  [...props.students].sort((a, b) => (a.pivot?.class_name || a.name).localeCompare(b.pivot?.class_name || b.name))
);

const attemptMap = computed(() =>
  Object.fromEntries(props.attempts.map(a => [a.user_id, a]))
);

function statusLabel(status) {
  return { active: 'Active', expired: 'Expirée', draft: 'Brouillon', inProgress: 'En cours', finished: 'Terminée' }[status] || status;
}

function terminationLabel(termination) {
  return { none: 'Aucune', submitted: 'Soumise', blurred: 'Floutée', timeout: 'Timeout' }[termination] || termination;
}

function answerColorClass(status) {
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
</script>
