<template>
  <div class="space-y-3">
    <div
      v-for="(item, idx) in items"
      :key="idx"
      class="flex flex-col rounded-lg border overflow-hidden"
      :class="cardClass(item.status)"
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
        class="flex items-center justify-between gap-3 p-3"
        :class="tintClass(item.status)"
      >
        <div class="flex items-center gap-3 min-w-0">
          <span
            class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0"
            :class="badgeClass(item.status)"
          >{{ idx + 1 }}</span>
          <div class="min-w-0">
            <p class="text-sm text-text-main dark:text-surface">Réponse : {{ item.answer ? item.answer : '—' }}</p>
            <p
              v-if="item.status === 'incorrect' && item.correct_answer"
              class="text-xs text-success font-medium"
            >Correcte : {{ item.correct_answer }}</p>
          </div>
        </div>
        <p class="text-2xl font-bold flex-shrink-0" :class="pointTone(item)">{{ pointText(item) }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  items: { type: Array, default: () => [] },
});

function cardClass(status) {
  if (status === 'correct') {
    return 'border-success';
  }

  if (status === 'incorrect') {
    return 'border-error';
  }

  return 'border-border';
}

function tintClass(status) {
  if (status === 'correct') {
    return 'bg-success/5';
  }

  if (status === 'incorrect') {
    return 'bg-error/5';
  }

  return '';
}

function badgeClass(status) {
  if (status === 'correct') {
    return 'bg-success text-white';
  }

  if (status === 'incorrect') {
    return 'bg-error text-white';
  }

  return 'bg-gray-200 dark:bg-gray-700 text-text-muted';
}

function isBlank(item) {
  return item.answer === null || item.answer === undefined || item.answer === '';
}

function pointText(item) {
  if (item.status !== 'correct' && item.status !== 'incorrect') {
    return '—';
  }

  if (item.status === 'incorrect' && isBlank(item)) {
    return '0';
  }

  if (item.points === null || item.points === undefined || item.points === '') {
    return '—';
  }

  if (item.status === 'correct') {
    return `+${item.points}`;
  }

  return `${item.points}`;
}

function pointTone(item) {
  const text = pointText(item);

  if (text === '—') {
    return 'text-text-muted';
  }

  if (item.status === 'correct') {
    return 'text-success';
  }

  if (item.status === 'incorrect' && isBlank(item)) {
    return 'text-text-muted';
  }

  return 'text-error';
}
</script>
