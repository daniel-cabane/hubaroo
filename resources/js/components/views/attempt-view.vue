<template>
  <div class="flex flex-col h-[calc(100vh-64px)]">
    <!-- Countdown Timer Bar -->
    <div
      v-if="isInProgress"
      class="bg-surface dark:bg-gray-900 border-b border-border px-4 py-2 flex items-center justify-between gap-4"
    >
      <div class="flex items-center gap-2 px-3 py-2 rounded-lg border-2 border-error bg-error/5">
        <AlertTriangle class="w-5 h-5 text-error flex-shrink-0" />
        <p class="text-md font-medium text-error">Session en cours - Ne pas quitter cette page</p>
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
    <div ref="navBar" class="bg-surface dark:bg-gray-900 border-b border-border px-4 py-2 overflow-x-auto">
      <div class="flex gap-[4px] min-w-max">
        <button
          v-for="(answer, idx) in answers"
          :key="idx"
          @click="goToQuestion(idx)"
          class="w-12 h-12 mx-1 rounded-full text-lg font-bold flex-shrink-0 transition-colors"
          :class="questionStatusClass(answer, idx)"
        >
          {{ idx + 1 }}
        </button>
      </div>
    </div>

    <!-- Question Carousel with side navigation -->
    <div class="flex-1 flex items-center justify-center overflow-y-auto p-4">
      <!-- Prev Button -->
      <button
        @click="prevQuestion"
        :disabled="currentIndex === 0"
        class="flex-shrink-0 w-12 h-12 rounded-full bg-primary hover:bg-primary-hover cursor-pointer text-white disabled:opacity-30 transition-colors flex items-center justify-center"
      >
        <ChevronLeft class="w-6 h-6" />
      </button>

      <div class="max-w-2xl mx-4 flex-1 relative overflow-visible">
        <Transition :name="slideDirection" mode="out-in">
          <div v-if="currentQuestion" :key="currentIndex" class="w-full">
            <img
              :src="'/' + currentQuestion.image"
              :alt="'Question ' + (currentIndex + 1)"
              class="mx-auto max-w-full rounded-lg all-around-shadow mb-6"
            />

            <!-- Answer Buttons (Q1-24) -->
            <div v-if="!isNumericQuestion" class="flex justify-center gap-3">
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

            <!-- Numeric Answer Display (Q25-26) -->
            <div v-else class="flex flex-col items-center gap-4">
              <label class="text-sm text-text-muted">Entrez votre réponse (0–100)</label>
              <div
                class="w-32 h-16 text-3xl font-bold flex items-center justify-center rounded-xl border-2 bg-surface dark:bg-gray-900 text-text-main dark:text-surface select-none"
                :class="numericInputClass"
              >
                {{ numericInputValue !== '' ? numericInputValue : '—' }}
              </div>
              <p v-if="!isInProgress" class="text-sm text-text-muted">
                Correct : <span class="font-bold text-success">{{ currentQuestion?.correct_answer }}</span>
              </p>
              <!-- On-screen keypad -->
              <div v-if="isInProgress" class="grid grid-cols-3 gap-2">
                <button
                  v-for="key in ['1','2','3','4','5','6','7','8','9','⌫','0']"
                  :key="key"
                  @click="handleNumericKey(key)"
                  class="w-14 h-14 rounded-xl text-xl font-bold transition-colors shadow"
                  :class="key === '⌫' ? 'bg-gray-200 dark:bg-gray-700 text-error col-start-1' : 'bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface hover:bg-gray-200 dark:hover:bg-gray-700'"
                >
                  {{ key }}
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Next Button -->
      <button
        @click="nextQuestion"
        :disabled="currentIndex === 25"
        class="flex-shrink-0 w-12 h-12 rounded-full bg-primary hover:bg-primary-hover cursor-pointer text-white disabled:opacity-30 transition-colors flex items-center justify-center"
      >
        <ChevronRight class="w-6 h-6" />
      </button>
    </div>

    <!-- Submit -->
    <div v-if="isInProgress" class="bg-surface dark:bg-gray-900 border-t border-border px-4 py-3 flex items-center justify-center">
      <button
        @click="showSubmitModal = true"
        class="px-6 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors"
      >
        Soumettre
      </button>
    </div>

    <!-- Jump to Question Modal -->
    <div
      v-if="showJumpModal"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
      @click.self="showJumpModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-xs mx-4 shadow-xl">
        <h3 class="text-lg font-bold text-text-main dark:text-surface mb-4">Aller à la question</h3>
        <input
          ref="jumpInput"
          v-model="jumpNumber"
          type="number"
          min="1"
          max="26"
          placeholder="1–26"
          class="w-full px-4 py-2 rounded-lg border border-border bg-gray-50 dark:bg-gray-800 text-text-main dark:text-surface text-center text-lg font-bold focus:outline-none focus:ring-2 focus:ring-primary"
          @keydown.enter.stop="confirmJump"
          @keydown.escape.stop="showJumpModal = false"
        />
        <div class="flex gap-3 mt-4">
          <button
            @click="showJumpModal = false"
            class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors"
          >
            Annuler
          </button>
          <button
            @click="confirmJump"
            class="flex-1 px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors"
          >
            Aller
          </button>
        </div>
      </div>
    </div>

    <!-- Submit Confirmation Modal -->
    <div
      v-if="showSubmitModal"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-sm mx-4 shadow-xl">
        <h3 class="text-lg font-bold text-text-main dark:text-surface mb-2">Soumettre la tentative ?</h3>
        <p class="text-sm text-text-muted mb-1">
          Répondu : {{ answeredCount }} / 26
        </p>
        <p class="text-sm text-text-muted mb-4">
          Vous ne pourrez plus modifier vos réponses après la soumission.
        </p>
        <div class="flex gap-3">
          <button
            @click="showSubmitModal = false"
            class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors"
          >
            Annuler
          </button>
          <button
            @click="handleSubmit"
            :disabled="attemptStore.isLoading"
            class="flex-1 px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors disabled:opacity-50"
          >
            {{ attemptStore.isLoading ? 'Soumission...' : 'Soumettre' }}
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
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';
import { useAttemptStore } from '@/stores/attemptStore';

const route = useRoute();
const router = useRouter();
const sessionStore = useKangourouSessionStore();
const attemptStore = useAttemptStore();

const currentIndex = ref(0);
const slideDirection = ref('slide-left');
const showSubmitModal = ref(false);
const showJumpModal = ref(false);
const jumpNumber = ref('');
const jumpInput = ref(null);
const navBar = ref(null);
const showTimer = ref(false);
const showBlurAlarm = ref(false);
const blurCountdown = ref(10);
const remainingSeconds = ref(0);
const numericInputValue = ref('');

let timerInterval = null;
let blurInterval = null;

const session = computed(() => sessionStore.session);
const answers = computed(() => attemptStore.attempt?.answers || []);
const isInProgress = computed(() => attemptStore.isInProgress);

const questions = computed(() => session.value?.paper?.questions || []);
const currentQuestion = computed(() => questions.value[currentIndex.value]);
const isNumericQuestion = computed(() => currentQuestion.value?.tier === 4);

const numericInputClass = computed(() => {
  if (isInProgress.value) return 'border-border';
  const answer = answers.value[currentIndex.value]?.answer;
  const correct = currentQuestion.value?.correct_answer;
  if (answer === null || answer === undefined || answer === '') return 'border-border';
  return answer === correct ? 'border-success bg-success/5' : 'border-error bg-error/5';
});

watch(currentIndex, (newIdx) => {
  if (newIdx >= 24) {
    numericInputValue.value = answers.value[newIdx]?.answer ?? '';
  }
  nextTick(() => {
    const container = navBar.value;
    if (!container) return;
    const btn = container.querySelectorAll('button')[newIdx];
    if (btn) {
      btn.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
    }
  });
});

const isLastMinute = computed(() => remainingSeconds.value > 0 && remainingSeconds.value <= 60);

const formattedTime = computed(() => {
  const mins = Math.floor(remainingSeconds.value / 60);
  const secs = remainingSeconds.value % 60;
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
});

const answeredCount = computed(() =>
  answers.value.filter(a => a.answer !== null).length
);

function questionStatusClass(answer, idx) {
  const isCurrent = idx === currentIndex.value;
  const base = isCurrent ? 'ring-2 ring-primary ' : '';

  if (answer.status === 'correct') return base + 'bg-success text-white';
  if (answer.status === 'incorrect') return base + 'bg-error text-white';
  if (answer.answer !== null) return base + 'bg-primary/20 text-primary dark:text-primary-hover';
  return base + 'bg-gray-100 dark:bg-gray-800 text-text-muted';
}

function answerButtonClass(letter) {
  const currentAnswer = answers.value[currentIndex.value]?.answer;
  const isSelected = currentAnswer === letter;

  if (!isInProgress.value) {
    const correctAnswer = currentQuestion.value?.correct_answer;
    if (letter === correctAnswer) return 'bg-success text-white';
    if (isSelected && letter !== correctAnswer) return 'bg-error text-white';
    return 'bg-gray-100 dark:bg-gray-800 text-text-muted';
  }

  if (isSelected) return 'bg-primary text-surface scale-110 shadow-lg';
  return 'bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface hover:bg-gray-200 dark:hover:bg-gray-700';
}

function goToQuestion(idx) {
  if (isNumericQuestion.value) saveNumericAnswer();
  slideDirection.value = idx > currentIndex.value ? 'slide-left' : 'slide-right';
  currentIndex.value = idx;
}

function prevQuestion() {
  if (currentIndex.value > 0) {
    if (isNumericQuestion.value) saveNumericAnswer();
    slideDirection.value = 'slide-right';
    currentIndex.value--;
  }
}

function nextQuestion() {
  if (currentIndex.value < 25) {
    if (isNumericQuestion.value) saveNumericAnswer();
    slideDirection.value = 'slide-left';
    currentIndex.value++;
  }
}

function handleNumericKey(key) {
  if (!isInProgress.value) return;
  if (key === '⌫') {
    numericInputValue.value = numericInputValue.value.slice(0, -1);
  } else {
    const newStr = numericInputValue.value + key;
    const num = parseInt(newStr, 10);
    if (!isNaN(num) && num <= 100) {
      numericInputValue.value = String(num);
    }
  }
  saveNumericAnswer();
}

async function saveNumericAnswer() {
  if (!isInProgress.value) return;
  const val = numericInputValue.value;
  if (val === '' || val === null || val === undefined) {
    await attemptStore.updateAnswer(attemptStore.attempt.id, currentIndex.value, null, remainingSeconds.value);
    return;
  }
  const num = parseInt(val, 10);
  if (isNaN(num) || num < 0 || num > 100) return;
  await attemptStore.updateAnswer(attemptStore.attempt.id, currentIndex.value, String(num), remainingSeconds.value);
}

async function selectAnswer(letter) {
  if (!isInProgress.value) return;

  const currentAnswer = answers.value[currentIndex.value]?.answer;
  const newAnswer = currentAnswer === letter ? null : letter;

  try {
    await attemptStore.updateAnswer(attemptStore.attempt.id, currentIndex.value, newAnswer, remainingSeconds.value);
  } catch {
    // error handled by store
  }
}

async function handleSubmit() {
  showSubmitModal.value = false;
  try {
    const result = await attemptStore.submitAttempt(attemptStore.attempt.id, remainingSeconds.value, 'submitted');
    router.replace({
      name: 'Results',
      params: { code: route.params.code, attemptId: attemptStore.attempt.id },
    });
  } catch {
    // error handled by store
  }
}

function toggleTimer() {
  if (!isLastMinute.value) {
    showTimer.value = !showTimer.value;
  }
}

function openJumpModal() {
  jumpNumber.value = '';
  showJumpModal.value = true;
  nextTick(() => jumpInput.value?.focus());
}

function confirmJump() {
  const num = parseInt(jumpNumber.value, 10);
  if (num >= 1 && num <= 26) {
    slideDirection.value = (num - 1) > currentIndex.value ? 'slide-left' : 'slide-right';
    currentIndex.value = num - 1;
  }
  showJumpModal.value = false;
}

function jumpToFirstUnanswered() {
  const idx = answers.value.findIndex(a => a.answer === null);
  if (idx !== -1) {
    slideDirection.value = idx > currentIndex.value ? 'slide-left' : 'slide-right';
    currentIndex.value = idx;
  }
}

const letterMap = { 1: 'A', 2: 'B', 3: 'C', 4: 'D', 5: 'E' };

function handleKeydown(e) {
  if (showSubmitModal.value || showBlurAlarm.value) return;

  if (showJumpModal.value) return;

  if (e.key === ' ') {
    e.preventDefault();
    toggleTimer();
    return;
  }

  if (e.key === 'Enter' && e.ctrlKey) {
    e.preventDefault();
    jumpToFirstUnanswered();
    return;
  }

  if (e.key === 'Enter') {
    e.preventDefault();
    openJumpModal();
    return;
  }

  if (e.key === 'ArrowLeft') {
    e.preventDefault();
    prevQuestion();
    return;
  }

  if (e.key === 'ArrowRight') {
    e.preventDefault();
    nextQuestion();
    return;
  }

  if (isNumericQuestion.value && isInProgress.value) {
    if (e.key === 'Backspace') {
      e.preventDefault();
      handleNumericKey('⌫');
      return;
    }
    if (/^[0-9]$/.test(e.key)) {
      e.preventDefault();
      handleNumericKey(e.key);
      return;
    }
  }

  const digit = parseInt(e.key, 10);
  if (digit >= 1 && digit <= 5 && isInProgress.value && !isNumericQuestion.value) {
    e.preventDefault();
    selectAnswer(letterMap[digit]);
  }
}

function startCountdown(timeLimitMinutes) {
  const attemptCreatedAt = new Date(attemptStore.attempt.created_at);
  const deadline = new Date(attemptCreatedAt.getTime() + timeLimitMinutes * 60 * 1000);

  timerInterval = setInterval(() => {
    const now = new Date();
    const diff = Math.max(0, Math.floor((deadline - now) / 1000));
    remainingSeconds.value = diff;

    if (diff <= 0) {
      clearInterval(timerInterval);
      autoSubmit();
    }
  }, 1000);
}

async function autoSubmit(termination = 'timeout') {
  if (!isInProgress.value) return;
  try {
    await attemptStore.submitAttempt(attemptStore.attempt.id, remainingSeconds.value, termination);
    router.replace({
      name: 'Results',
      params: { code: route.params.code, attemptId: attemptStore.attempt.id },
    });
  } catch {
    // error handled by store
  }
}

function handleBlur() {
  if (isInProgress.value) {
    showBlurAlarm.value = true;
    // blurCountdown.value = 10;
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

onMounted(async () => {
  const { code, attemptId } = route.params;

  try {
    await sessionStore.fetchSession(code);
    await attemptStore.fetchAttempt(attemptId);

    if (currentIndex.value >= 24) {
      numericInputValue.value = answers.value[currentIndex.value]?.answer ?? '';
    }

    window.addEventListener('keydown', handleKeydown);

    if (isInProgress.value) {
      const preferences = session.value.preferences || { time_limit: 50 };
      startCountdown(preferences.time_limit);
      window.addEventListener('blur', handleBlur);
      window.addEventListener('focus', handleFocus);
    }
  } catch (err) {
    // navigate away on error
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

<style scoped>
.slide-left-enter-active,
.slide-left-leave-active,
.slide-right-enter-active,
.slide-right-leave-active {
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.slide-left-enter-from {
  transform: translateX(40px);
  opacity: 0;
}
.slide-left-leave-to {
  transform: translateX(-40px);
  opacity: 0;
}

.slide-right-enter-from {
  transform: translateX(-40px);
  opacity: 0;
}
.slide-right-leave-to {
  transform: translateX(40px);
  opacity: 0;
}
.all-around-shadow {
    /* 0 offset x, 0 offset y, 15px blur, 5px spread */
    box-shadow: 0 0 15px 5px rgba(0, 0, 0, 0.1);
}
</style>
