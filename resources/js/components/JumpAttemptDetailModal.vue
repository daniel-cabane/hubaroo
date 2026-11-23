<template>
  <div
    v-if="open && attemptId"
    class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
    @click.self="emit('close')"
  >
    <div class="bg-surface dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
      <div class="flex items-center justify-between p-4 border-b border-border">
        <div>
          <h3 class="text-lg font-semibold text-text-main dark:text-surface">Saut {{ number }}</h3>
          <p class="text-sm text-text-muted">{{ date }}</p>
        </div>
        <button @click="emit('close')" class="text-text-muted hover:text-text-main transition-colors cursor-pointer">
          <X class="w-5 h-5" />
        </button>
      </div>
      <div v-if="loading" class="flex items-center justify-center p-10">
        <RefreshCw class="w-6 h-6 animate-spin text-text-muted" />
      </div>
      <div v-else-if="attempt" class="p-4 overflow-y-auto flex-1 space-y-3">
        <p class="text-center text-3xl font-bold text-primary mb-1">Score : {{ attempt.score }}</p>
        <JumpQuestionBreakdown :items="attempt.question_list" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { RefreshCw, X } from 'lucide-vue-next';
import JumpQuestionBreakdown from '@/components/JumpQuestionBreakdown.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  attemptId: { type: Number, default: null },
  number: { type: [Number, String], default: '' },
  date: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const attempt = ref(null);

watch(() => [props.open, props.attemptId], async ([isOpen, attemptId]) => {
  if (!isOpen || !attemptId) {
    attempt.value = null;
    return;
  }

  loading.value = true;
  try {
    const res = await axios.get(`/api/jump-attempts/${attemptId}`);
    attempt.value = res.data.attempt;
  } finally {
    loading.value = false;
  }
}, { immediate: true });
</script>
