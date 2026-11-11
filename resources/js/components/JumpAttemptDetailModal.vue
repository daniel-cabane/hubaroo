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
        <div
          v-for="(item, idx) in attempt.question_list"
          :key="idx"
          class="flex flex-col rounded-lg border overflow-hidden"
          :class="{
            'border-success': item.status === 'correct',
            'border-error': item.status === 'incorrect',
            'border-border': item.status === 'pending',
          }"
        >
          <img
            v-if="item.image"
            :src="'/' + item.image"
            :alt="'Question ' + (idx + 1)"
            class="w-full object-contain bg-gray-50 dark:bg-gray-800 select-none pointer-events-none"
            draggable="false"
            oncontextmenu="return false;"
          />
          <div
            class="flex items-center justify-between p-3"
            :class="{
              'bg-success/5': item.status === 'correct',
              'bg-error/5': item.status === 'incorrect',
            }"
          >
            <div class="flex items-center gap-3">
              <span
                class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0"
                :class="{
                  'bg-success text-white': item.status === 'correct',
                  'bg-error text-white': item.status === 'incorrect',
                  'bg-gray-200 dark:bg-gray-700 text-text-muted': item.status === 'pending',
                }"
              >{{ idx + 1 }}</span>
              <div>
                <p class="text-sm font-medium text-text-main dark:text-surface">Question #{{ item.id }}</p>
                <p class="text-xs text-text-muted">Difficulté : {{ item.difficulty }}</p>
              </div>
            </div>
            <div class="text-right">
              <p class="text-sm font-medium"
                :class="{
                  'text-success': item.status === 'correct',
                  'text-error': item.status === 'incorrect',
                  'text-text-muted': item.status === 'pending',
                }"
              >
                {{ item.status === 'correct' ? '+' + item.difficulty : item.status === 'incorrect' ? '0' : '—' }}
              </p>
              <p v-if="item.answer" class="text-xs text-text-muted">Réponse : {{ item.answer }}</p>
              <p v-if="item.status === 'incorrect' && item.correct_answer" class="text-xs text-success font-medium">Correcte : {{ item.correct_answer }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { RefreshCw, X } from 'lucide-vue-next';

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
