<template>
  <div
    v-if="question"
    class="fixed inset-0 bg-black/80 flex items-center justify-center z-[60]"
    @click.self="emit('close')"
  >
    <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-2xl p-6 w-full max-w-[80vw] flex flex-col items-center gap-4">
      <button @click="emit('close')" class="self-end text-text-muted hover:text-text-main transition-colors cursor-pointer">
        <X class="w-5 h-5" />
      </button>
      <img
        v-if="current?.question?.image"
        :src="'/' + current.question.image"
        class="max-h-64 object-contain"
        alt="Question"
      />
      <div
        v-if="manage && current?.question"
        class="min-h-7 text-xl font-bold text-success text-center"
        :class="{ invisible: !reveal }"
        :aria-hidden="!reveal"
      >
        Réponse : {{ current.question.correct_answer }}
      </div>
      <div v-if="manage" class="flex gap-3">
        <button
          type="button"
          class="grid px-4 py-2 bg-primary hover:bg-primary-hover text-surface rounded-lg text-sm font-medium transition-colors cursor-pointer"
          :aria-pressed="reveal"
          @click="reveal = !reveal"
        >
          <span class="invisible col-start-1 row-start-1" aria-hidden="true">Révéler la réponse</span>
          <span class="invisible col-start-1 row-start-1" aria-hidden="true">Masquer la réponse</span>
          <span class="col-start-1 row-start-1">{{ reveal ? 'Masquer la réponse' : 'Révéler la réponse' }}</span>
        </button>
        <button
          @click="togglePublic"
          :class="['px-4 py-2 rounded-lg text-sm font-medium transition-colors cursor-pointer flex items-center gap-2', current?.is_public ? 'bg-primary/10 text-primary hover:bg-primary/20' : 'bg-gray-100 dark:bg-gray-800 text-text-muted hover:text-primary']"
        >
          <Globe class="w-4 h-4" />
          {{ current?.is_public ? 'Retirer' : 'Rendre public' }}
        </button>
        <button
          @click="remove"
          class="px-4 py-2 bg-error/10 text-error hover:bg-error/20 rounded-lg text-sm font-medium transition-colors cursor-pointer flex items-center gap-2"
        >
          <Trash2 class="w-4 h-4" />
          Supprimer
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { Globe, Trash2, X } from 'lucide-vue-next';

const props = defineProps({
  question: {
    type: Object,
    default: null,
  },
  manage: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(['close', 'updated', 'deleted']);

const reveal = ref(false);
const current = ref(null);

watch(() => props.question, (question) => {
  if (question?.id !== current.value?.id) {
    reveal.value = false;
  }
  current.value = question ? { ...question, question: question.question ? { ...question.question } : null } : null;
}, { immediate: true });

async function togglePublic() {
  if (!current.value) {
    return;
  }
  try {
    const response = await axios.patch(`/api/suggested-questions/${current.value.id}/toggle-public`);
    current.value = { ...current.value, is_public: response.data.suggested_question.is_public };
    emit('updated', current.value);
  } catch {
    // Leave the public state unchanged.
  }
}

async function remove() {
  if (!current.value) {
    return;
  }
  try {
    await axios.delete(`/api/suggested-questions/${current.value.id}`);
    emit('deleted', current.value.id);
    emit('close');
  } catch {
    // Leave the dialog open.
  }
}
</script>
