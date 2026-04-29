<template>
  <div class="flex flex-col h-[calc(100vh-64px)]">
    <!-- Countdown Timer Bar -->
    <div
      v-if="isInProgress"
      class="relative bg-surface dark:bg-gray-900 border-b border-border px-4 py-2 flex items-center justify-between gap-4"
    >
      <div class="flex items-center gap-2 px-3 py-2 rounded-lg border-2 border-error bg-error/5">
        <AlertTriangle class="w-5 h-5 text-error flex-shrink-0" />
        <p class="text-md font-medium text-error">Session en cours - Ne pas quitter cette page</p>
      </div>
      <div class="absolute left-1/2 -translate-x-1/2 text-lg font-semibold text-text-muted truncate max-w-xs text-center">
        {{ attemptStore.attempt?.name }}
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
          class="w-12 h-12 mx-1 rounded-full text-lg font-bold flex-shrink-0 transition-colors cursor-pointer"
          :class="questionStatusClass(answer, idx)"
        >
          {{ idx + 1 }}
        </button>
      </div>
    </div>

    <!-- Question Carousel with side navigation -->
    <div class="flex-1 flex items-center justify-center overflow-hidden">
      <!-- Prev Button -->
      <button
        @click="prevQuestion"
        :disabled="currentIndex === 0"
        class="flex-shrink-0 w-12 h-12 rounded-full bg-primary hover:bg-primary-hover cursor-pointer text-white disabled:opacity-30 transition-colors flex items-center justify-center"
      >
        <ChevronLeft class="w-6 h-6" />
      </button>

      <div class="w-full max-w-2xl mx-4 relative overflow-y-auto h-full px-6">
        <div class="min-h-full flex flex-col items-center justify-center py-6">        
          <div v-if="isShuffled && isInProgress" class="text-center mb-3 text-xs text-text-muted bg-gray-100 dark:bg-gray-800 rounded-lg px-3 py-1 inline-block">
            Questions mélangées
          </div>
          <Transition :name="slideDirection" mode="out-in">
            <div v-if="currentQuestion" :key="currentIndex" class="w-full">
              <img
                :src="'/' + currentQuestion.image"
                :alt="'Question ' + (currentIndex + 1)"
                class="mx-auto max-w-full rounded-lg all-around-shadow mb-6 select-none pointer-events-none"
                draggable="false"
                oncontextmenu="return false;"
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
                <label class="text-sm text-text-muted">Réponse numérique</label>
                <div class="flex flex-col items-center gap-3">
                  <div
                    class="w-32 h-16 text-3xl font-bold flex items-center justify-center rounded-xl border-2 bg-surface dark:bg-gray-900 text-text-main dark:text-surface select-none"
                    :class="numericInputClass"
                  >
                    {{ numericInputValue !== '' ? numericInputValue : '—' }}
                  </div>
                  <p v-if="!isInProgress" class="text-sm text-text-muted">
                    Correct : <span class="font-bold text-success">{{ currentQuestion?.correct_answer }}</span>
                  </p>
                  <!-- Keypad Toggle Button -->
                  <button
                    v-if="isInProgress"
                    @click="showKeypad = !showKeypad"
                    class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-sm font-medium"
                  >
                    {{ showKeypad ? 'Masquer le clavier' : 'Afficher le clavier' }}
                  </button>
                </div>
              </div>

              <!-- Keypad Overlay -->
              <div
                v-if="isInProgress && showKeypad"
                class="fixed inset-0 z-40 bg-black/30 flex items-center justify-center"
                @click.self="showKeypad = false"
              >
                <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 shadow-xl flex flex-col items-center gap-4 relative">
                  <!-- Close Button -->
                  <button
                    @click="showKeypad = false"
                    class="absolute top-3 right-3 p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors"
                  >
                    <X class="w-5 h-5 text-text-main dark:text-surface" />
                  </button>
                  <!-- Answer Display in Overlay -->
                  <div
                    class="w-32 h-16 text-3xl font-bold flex items-center justify-center rounded-xl border-2 bg-surface dark:bg-gray-800 text-text-main dark:text-surface select-none"
                    :class="numericInputClass"
                  >
                    {{ numericInputValue !== '' ? numericInputValue : '—' }}
                  </div>
                  <!-- Keypad Buttons -->
                  <div class="grid grid-cols-3 gap-2">
                    <button
                      v-for="key in ['1','2','3','4','5','6','7','8','9','delete','0']"
                      :key="key"
                      @click="handleNumericKey(key === 'delete' ? '⌫' : key)"
                      class="w-16 h-16 rounded-xl text-2xl font-bold transition-colors cursor-pointer shadow flex items-center justify-center"
                      :class="key === 'delete' ? 'bg-gray-200 dark:bg-gray-700 text-error col-start-1' : 'bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface hover:bg-gray-200 dark:hover:bg-gray-700'"
                    >
                      <Delete v-if="key === 'delete'" class="w-6 h-6" />
                      <span v-else>{{ key }}</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </Transition>
        </div>
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
        class="px-6 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface cursor-pointer font-medium transition-colors"
      >
        Terminer la session
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
        <h3 class="text-lg font-bold text-text-main dark:text-surface mb-2">Terminer la session ?</h3>
        <p class="text-sm text-text-muted mb-1">
          Répondu : {{ answeredCount }} / 26
        </p>
        <p class="text-sm text-text-muted mb-4">
          Vous ne pourrez plus modifier vos réponses après la soumission.
        </p>
        <div class="flex gap-3">
          <button
            @click="showSubmitModal = false"
            class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 cursor-pointer text-text-main dark:text-surface transition-colors"
          >
            Annuler
          </button>
          <button
            @click="handleSubmit"
            :disabled="attemptStore.isLoading"
            class="flex-1 px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover cursor-pointer text-surface font-medium transition-colors disabled:opacity-50"
          >
            {{ attemptStore.isLoading ? 'Chargement...' : 'Terminer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronLeft, ChevronRight, AlertTriangle, X, Delete } from 'lucide-vue-next';
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
const showKeypad = ref(false);
const questionOrder = ref([0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25]);
const isShuffled = ref(false);

let timerInterval = null;
let blurInterval = null;

const session = computed(() => sessionStore.session);
const answers = computed(() => attemptStore.attempt?.answers || []);
const isInProgress = computed(() => attemptStore.isInProgress);

const questions = computed(() => session.value?.paper?.questions || []);
const currentOriginalIndex = computed(() => questionOrder.value[currentIndex.value]);
const currentQuestion = computed(() => questions.value[currentOriginalIndex.value]);
const isNumericQuestion = computed(() => currentQuestion.value?.tier === 4);

const numericInputClass = computed(() => {
  if (isInProgress.value) return 'border-border';
  const answer = answers.value[currentOriginalIndex.value]?.answer;
  const correct = currentQuestion.value?.correct_answer;
  if (answer === null || answer === undefined || answer === '') return 'border-border';
  return answer === correct ? 'border-success bg-success/5' : 'border-error bg-error/5';
});

watch(currentIndex, (newIdx) => {
  const origIdx = questionOrder.value[newIdx];
  if (origIdx >= 24) {
    numericInputValue.value = answers.value[origIdx]?.answer ?? '';
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
  const origAnswer = answers.value[questionOrder.value[idx]];

  if (!isInProgress.value) {
    if (origAnswer?.status === 'correct') return base + 'bg-success text-white';
    if (origAnswer?.status === 'incorrect') return base + 'bg-error text-white';
  }
  if (origAnswer?.answer !== null && origAnswer?.answer !== undefined) return base + 'bg-primary/20 text-primary dark:text-primary-hover';
  return base + 'bg-gray-100 dark:bg-gray-800 text-text-muted';
}

function answerButtonClass(letter) {
  const currentAnswer = answers.value[currentOriginalIndex.value]?.answer;
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
    if (!isNaN(num) && num <= 1000000) {
      numericInputValue.value = String(num);
    }
  }
  saveNumericAnswer();
}

async function saveNumericAnswer() {
  if (!isInProgress.value) return;
  const val = numericInputValue.value;
  if (val === '' || val === null || val === undefined) {
    await attemptStore.updateAnswer(attemptStore.attempt.id, currentOriginalIndex.value, null, remainingSeconds.value);
    return;
  }
  const num = parseInt(val, 10);
  if (isNaN(num) || num < 0 || num > 1000000) return;
  await attemptStore.updateAnswer(attemptStore.attempt.id, currentOriginalIndex.value, String(num), remainingSeconds.value);
}

async function selectAnswer(letter) {
  if (!isInProgress.value) return;

  const currentAnswer = answers.value[currentOriginalIndex.value]?.answer;
  const newAnswer = currentAnswer === letter ? null : letter;

  try {
    await attemptStore.updateAnswer(attemptStore.attempt.id, currentOriginalIndex.value, newAnswer, remainingSeconds.value);
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
  // Find the first display index whose original answer is null
  const idx = questionOrder.value.findIndex((origIdx) => answers.value[origIdx]?.answer === null);
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

function shuffleArray(array) {
  const arr = [...array];
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

function buildQuestionOrder(qs, shuffleMode) {
  if (!shuffleMode || shuffleMode === 'none') {
    return qs.map((_, i) => i);
  }

  if (shuffleMode === 'by_tier') {
    const byTier = {};
    qs.forEach((q, i) => {
      const t = q.tier ?? 1;
      if (!byTier[t]) byTier[t] = [];
      byTier[t].push(i);
    });
    const tiers = Object.keys(byTier).map(Number).sort((a, b) => a - b);
    return tiers.flatMap(t => shuffleArray(byTier[t]));
  }

  if (shuffleMode === 'tiers_1_3') {
    const tier1to3 = qs.map((q, i) => ({ q, i })).filter(({ q }) => (q.tier ?? 1) !== 4).map(({ i }) => i);
    const tier4 = qs.map((q, i) => ({ q, i })).filter(({ q }) => (q.tier ?? 1) === 4).map(({ i }) => i);
    return [...shuffleArray(tier1to3), ...tier4];
  }

  if (shuffleMode === 'complete') {
    return shuffleArray(qs.map((_, i) => i));
  }

  return qs.map((_, i) => i);
}

function startCountdown(timeLimitMinutes) {
  const attemptCreatedAt = new Date(attemptStore.attempt.created_at);
  const extraTimeSeconds = attemptStore.attempt.extra_time ?? 0;
  const deadline = new Date(attemptCreatedAt.getTime() + timeLimitMinutes * 60 * 1000 + extraTimeSeconds * 1000);

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
      const preferences = session.value?.preferences || {};
      const shuffleMode = preferences.shuffle ?? 'none';
      if (shuffleMode !== 'none') {
        questionOrder.value = buildQuestionOrder(questions.value, shuffleMode);
        isShuffled.value = true;
      }

      const timeLimitMinutes = preferences.time_limit ?? 50;
      startCountdown(timeLimitMinutes);

      const blurSecurity = preferences.blur_security ?? true;
      if (blurSecurity) {
        window.addEventListener('blur', handleBlur);
        window.addEventListener('focus', handleFocus);
      }

      if (session.value?.id) {
        window.Echo.channel(`session.${session.value.id}`)
          .listen('.SessionExpired', () => {
            clearInterval(timerInterval);
            timerInterval = null;
            autoSubmit('timeout');
          });
      }

      if (attemptStore.attempt?.id) {
        window.Echo.channel(`attempt.${attemptStore.attempt.id}`)
          .listen('.AttemptNameUpdated', (event) => {
            attemptStore.attempt.name = event.name;
          });
      }
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
  if (session.value?.id) {
    window.Echo.leave(`session.${session.value.id}`);
  }
  if (attemptStore.attempt?.id) {
    window.Echo.leave(`attempt.${attemptStore.attempt.id}`);
  }
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
img {
  -webkit-user-select: none; /* Safari */
  -ms-user-select: none;      /* IE 10 and 11 */
  user-select: none;         /* Standard syntax */
  
  /* Also prevents the image from being "dragged" */
  -webkit-user-drag: none;
}
</style>
