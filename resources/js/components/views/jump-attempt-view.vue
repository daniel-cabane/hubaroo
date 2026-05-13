<template>
  <div class="flex flex-col h-[calc(100vh-64px)]">
    <!-- Countdown Timer Bar -->
    <div
      v-if="isInProgress"
      class="relative bg-surface dark:bg-gray-900 border-b border-border px-4 py-2 flex items-center justify-between gap-4"
    >
      <div class="flex items-center gap-2 px-3 py-2 rounded-lg border-2 border-error bg-error/5">
        <AlertTriangle class="w-5 h-5 text-error flex-shrink-0" />
        <p class="text-md font-medium text-error">Saut en cours - Ne pas quitter cette page</p>
      </div>
      <div class="absolute left-1/2 -translate-x-1/2 text-lg font-semibold text-text-muted truncate max-w-xs text-center">
        {{ jumpAttemptStore.attempt?.jump?.course?.title }}
      </div>
      <button
        @click="toggleTimer"
        class="text-xl font-mono px-3 py-1 rounded-lg transition-colors cursor-pointer"
        :class="showTimer || isLastMinute ? 'bg-error/10 text-error font-bold' : 'bg-gray-100 dark:bg-gray-800 text-text-muted'"
      >
        <span v-if="showTimer || isLastMinute">{{ formattedTime }}</span>
        <span v-else>Afficher le chrono</span>
      </button>
    </div>

    <!-- Tab Blur Alarm Overlay -->
    <div
      v-if="showBlurAlarm"
      class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-8 text-center max-w-sm mx-4">
        <div class="text-6xl font-bold text-error mb-4 font-mono">{{ blurCountdown }}</div>
        <p class="text-lg text-text-main dark:text-surface mb-2">Revenez !</p>
        <p class="text-sm text-text-muted">Votre tentative sera soumise automatiquement si vous ne revenez pas.</p>
      </div>
    </div>

    <!-- Question Nav Bar -->
    <div ref="navBar" class="bg-surface dark:bg-gray-900 border-b border-border px-4 pt-3 pb-4 overflow-x-auto flex justify-center">
      <div class="flex gap-[4px] min-w-max">
        <button
          v-for="(item, idx) in questionList"
          :key="idx"
          @click="goToQuestion(idx)"
          class="w-12 h-12 mx-1 rounded-full text-lg font-bold flex-shrink-0 transition-colors cursor-pointer"
          :class="questionStatusClass(item, idx)"
        >
          {{ idx + 1 }}
        </button>
      </div>
    </div>

    <!-- Question Carousel with side navigation -->
    <div class="flex-1 flex items-center justify-center overflow-hidden">
      <button
        @click="prevQuestion"
        :disabled="currentIndex === 0"
        class="flex-shrink-0 w-12 h-12 rounded-full bg-primary hover:bg-primary-hover cursor-pointer text-white disabled:opacity-30 transition-colors flex items-center justify-center"
      >
        <ChevronLeft class="w-6 h-6" />
      </button>

      <div class="w-full max-w-2xl mx-4 relative overflow-y-auto h-full px-6">
        <div class="min-h-full flex flex-col items-center justify-center py-6">
          <Transition :name="slideDirection" mode="out-in">
            <div v-if="currentQuestion" :key="currentIndex" class="w-full">
              <img
                :src="'/' + currentQuestion.image"
                :alt="'Question ' + (currentIndex + 1)"
                class="mx-auto max-w-full rounded-lg all-around-shadow mb-6 select-none pointer-events-none"
                draggable="false"
                oncontextmenu="return false;"
              />

              <!-- Answer Buttons (A-E) -->
              <div class="flex justify-center gap-3">
                <button
                  v-for="letter in ['A', 'B', 'C', 'D', 'E']"
                  :key="letter"
                  @click="selectAnswer(letter)"
                  :disabled="!isInProgress"
                  class="w-16 h-16 rounded-xl text-xl font-bold transition-all shadow-lg"
                  :class="answerButtonClass(letter)"
                >
                  {{ letter }}
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </div>

      <button
        @click="nextQuestion"
        :disabled="currentIndex === questionList.length - 1"
        class="flex-shrink-0 w-12 h-12 rounded-full bg-primary hover:bg-primary-hover cursor-pointer text-white disabled:opacity-30 transition-colors flex items-center justify-center"
      >
        <ChevronRight class="w-6 h-6" />
      </button>
    </div>

    <!-- Submit -->
    <div v-if="isInProgress" class="bg-surface dark:bg-gray-900 border-t border-border px-4 py-3 flex items-center justify-center">
      <button
        @click="showSubmitModal = true"
        class="px-6 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface cursor-pointer font-medium transition-colors"
      >
        Terminer le saut
      </button>
    </div>

    <!-- Submit Confirmation Modal -->
    <div
      v-if="showSubmitModal"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-sm mx-4 shadow-xl">
        <h3 class="text-lg font-bold text-text-main dark:text-surface mb-2">Terminer le saut ?</h3>
        <p class="text-sm text-text-muted mb-1">Répondu : {{ answeredCount }} / {{ questionList.length }}</p>
        <p class="text-sm text-text-muted mb-4">Vous ne pourrez plus modifier vos réponses après la soumission.</p>
        <div class="flex gap-3">
          <button @click="showSubmitModal = false" class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 cursor-pointer text-text-main dark:text-surface transition-colors">Annuler</button>
          <button @click="handleSubmit" :disabled="jumpAttemptStore.isLoading" class="flex-1 px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover cursor-pointer text-surface font-medium transition-colors disabled:opacity-50">
            {{ jumpAttemptStore.isLoading ? 'Chargement...' : 'Terminer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronLeft, ChevronRight, AlertTriangle } from 'lucide-vue-next';
import { useJumpAttemptStore } from '@/stores/jumpAttemptStore';

const route = useRoute();
const router = useRouter();
const jumpAttemptStore = useJumpAttemptStore();

const currentIndex = ref(0);
const slideDirection = ref('slide-left');
const showSubmitModal = ref(false);
const navBar = ref(null);
const showTimer = ref(false);
const showBlurAlarm = ref(false);
const blurCountdown = ref(10);
const remainingSeconds = ref(0);

let timerInterval = null;
let blurInterval = null;

const questionList = computed(() => jumpAttemptStore.attempt?.question_list ?? []);
const isInProgress = computed(() => jumpAttemptStore.isInProgress);
const isExpired = computed(() => jumpAttemptStore.attempt?.jump?.status === 'expired');

const currentQuestion = computed(() => {
  const item = questionList.value[currentIndex.value];
  if (!item) return null;
  return item;
});

const answeredCount = computed(() =>
  questionList.value.filter(q => q.answer != null).length
);

const isLastMinute = computed(() => remainingSeconds.value > 0 && remainingSeconds.value <= 60);

const formattedTime = computed(() => {
  const mins = Math.floor(remainingSeconds.value / 60);
  const secs = remainingSeconds.value % 60;
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
});

function questionStatusClass(item, idx) {
  const isCurrent = idx === currentIndex.value;
  const base = isCurrent ? 'ring-2 ring-primary ' : '';

  if (!isInProgress.value) {
    if (item.status === 'correct') return base + 'bg-success text-white';
    if (item.status === 'incorrect') return base + 'bg-error text-white';
  }
  if (item.answer != null) return base + 'bg-primary/20 text-primary dark:text-primary-hover';
  return base + 'bg-gray-100 dark:bg-gray-800 text-text-muted';
}

function answerButtonClass(letter) {
  const item = questionList.value[currentIndex.value];
  const isSelected = item?.answer === letter;

  if (!isInProgress.value) {
    if (isExpired.value && item?.correct_answer) {
      if (letter === item.correct_answer) return 'bg-success text-white';
      if (isSelected && letter !== item.correct_answer) return 'bg-error text-white';
      return 'bg-gray-100 dark:bg-gray-800 text-text-muted';
    }
    if (item?.status === 'correct' && isSelected) return 'bg-success text-white';
    if (item?.status === 'incorrect' && isSelected) return 'bg-error text-white';
    return isSelected ? 'bg-primary/20 text-primary' : 'bg-gray-100 dark:bg-gray-800 text-text-muted';
  }

  if (isSelected) return 'bg-primary text-surface scale-110 shadow-lg';
  return 'bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface hover:bg-gray-200 dark:hover:bg-gray-700';
}

function goToQuestion(idx) {
  slideDirection.value = idx > currentIndex.value ? 'slide-left' : 'slide-right';
  currentIndex.value = idx;
}

function prevQuestion() {
  if (currentIndex.value > 0) {
    slideDirection.value = 'slide-right';
    currentIndex.value--;
  }
}

function nextQuestion() {
  if (currentIndex.value < questionList.value.length - 1) {
    slideDirection.value = 'slide-left';
    currentIndex.value++;
  }
}

async function selectAnswer(letter) {
  if (!isInProgress.value) return;
  const item = questionList.value[currentIndex.value];
  const newAnswer = item?.answer === letter ? null : letter;
  try {
    await jumpAttemptStore.updateAnswer(
      jumpAttemptStore.attempt.id,
      currentIndex.value,
      newAnswer,
      remainingSeconds.value
    );
  } catch {
    // handled by store
  }
}

async function handleSubmit() {
  showSubmitModal.value = false;
  try {
    await jumpAttemptStore.submitAttempt(jumpAttemptStore.attempt.id, remainingSeconds.value, 'submitted');
    router.replace({
      name: 'JumpResults',
      params: { jumpId: route.params.jumpId, attemptId: jumpAttemptStore.attempt.id },
    });
  } catch {
    // handled by store
  }
}

function toggleTimer() {
  if (!isLastMinute.value) {
    showTimer.value = !showTimer.value;
  }
}

function handleKeydown(e) {
  if (showSubmitModal.value || showBlurAlarm.value) return;

  if (e.key === ' ') {
    e.preventDefault();
    toggleTimer();
    return;
  }
  if (e.key === 'ArrowLeft') { e.preventDefault(); prevQuestion(); return; }
  if (e.key === 'ArrowRight') { e.preventDefault(); nextQuestion(); return; }

  const letterMap = { 1: 'A', 2: 'B', 3: 'C', 4: 'D', 5: 'E' };
  const digit = parseInt(e.key, 10);
  if (digit >= 1 && digit <= 5 && isInProgress.value) {
    e.preventDefault();
    selectAnswer(letterMap[digit]);
  }
}

function startCountdown(timeLimitMinutes, extraTimeSeconds = 0) {
  // For jumps, deadline = now + remaining time
  const totalSeconds = timeLimitMinutes * 60 + extraTimeSeconds;
  const spentSeconds = jumpAttemptStore.attempt.timer ?? 0;
  remainingSeconds.value = Math.max(0, totalSeconds - spentSeconds);

  timerInterval = setInterval(() => {
    remainingSeconds.value = Math.max(0, remainingSeconds.value - 1);
    if (remainingSeconds.value <= 0) {
      clearInterval(timerInterval);
      autoSubmit();
    }
  }, 1000);
}

async function autoSubmit(termination = 'timeout') {
  if (!isInProgress.value) return;
  try {
    await jumpAttemptStore.submitAttempt(jumpAttemptStore.attempt.id, remainingSeconds.value, termination);
    router.replace({
      name: 'JumpResults',
      params: { jumpId: route.params.jumpId, attemptId: jumpAttemptStore.attempt.id },
    });
  } catch {
    // handled by store
  }
}

function handleBlur() {
  if (isInProgress.value) {
    showBlurAlarm.value = true;
    blurInterval = setInterval(() => {
      blurCountdown.value--;
      if (blurCountdown.value <= 0) {
        clearInterval(blurInterval);
        showBlurAlarm.value = false;
        autoSubmit('blurred');
      }
    }, 1000);
  }
}

function handleFocus() {
  showBlurAlarm.value = false;
  if (blurInterval) {
    clearInterval(blurInterval);
    blurInterval = null;
  }
}

watch(currentIndex, () => {
  nextTick(() => {
    const container = navBar.value;
    if (!container) return;
    const btn = container.querySelectorAll('button')[currentIndex.value];
    if (btn) btn.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
  });
});

onMounted(async () => {
  const attemptId = route.params.attemptId;
  const jumpId = route.params.jumpId;

  if (attemptId) {
    await jumpAttemptStore.fetchAttempt(attemptId);
  } else {
    try {
      await jumpAttemptStore.startAttempt(jumpId);
    } catch (err) {
      if (err.response?.status === 409 && err.response?.data?.requires_rejoin) {
        // Handle rejoin
        router.replace({ name: 'JumpResults', params: { jumpId, attemptId: err.response.data.attempt.id } });
        return;
      }
      throw err;
    }
  }

  if (isInProgress.value) {
    const jump = jumpAttemptStore.attempt?.jump;
    const extraTimeSeconds = jumpAttemptStore.attempt?.extra_time ?? 0;
    startCountdown(jump?.time ?? 15, extraTimeSeconds);
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('blur', handleBlur);
    window.addEventListener('focus', handleFocus);
  }
});

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval);
  if (blurInterval) clearInterval(blurInterval);
  window.removeEventListener('keydown', handleKeydown);
  window.removeEventListener('blur', handleBlur);
  window.removeEventListener('focus', handleFocus);
});
</script>
