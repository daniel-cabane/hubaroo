<template>
  <div class="flex flex-col h-[calc(100vh-64px)]">
    <!-- Countdown Timer Bar -->
    <div
      v-if="isInProgress"
      class="relative bg-surface dark:bg-gray-900 border-b border-border px-4 py-2 flex items-center justify-between gap-4"
    >
      <div class="flex items-center gap-2 px-3 py-2 rounded-lg border-2 border-error bg-error/5">
        <AlertTriangle class="w-5 h-5 text-error flex-shrink-0" />
        <p class="text-md font-medium text-error">Ne pas quitter cette page</p>
      </div>
      <div class="absolute left-1/2 -translate-x-1/2 text-4xl font-semibold text-secondary truncate max-w-xs text-center">
        {{ authStore.user?.name }}
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

    <!-- Image Zoom Overlay -->
    <div
      v-if="showImageOverlay"
      class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center cursor-zoom-out"
      @click="showImageOverlay = false"
    >
      <img
        :src="'/' + currentQuestion?.image"
        :alt="'Question ' + (currentIndex + 1)"
        class="rounded-lg select-none pointer-events-none"
        style="width: 90vw; max-height: 90vh; object-fit: contain;"
        draggable="false"
        oncontextmenu="return false;"
      />
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
                class="mx-auto max-w-full rounded-lg all-around-shadow mb-6 select-none cursor-zoom-in"
                draggable="false"
                oncontextmenu="return false;"
                @click="showImageOverlay = true"
              />

              <!-- Answer Buttons (A-E) -->
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

              <!-- Numeric Answer Display (Tier 4) -->
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
                      @click="handleNumericKey(key === 'delete' ? '\u232b' : key)"
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
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router';
import { ChevronLeft, ChevronRight, AlertTriangle, X, Delete } from 'lucide-vue-next';
import { useJumpAttemptStore } from '@/stores/jumpAttemptStore';
import { useAuthStore } from '@/stores/authStore';

const route = useRoute();
const router = useRouter();
const jumpAttemptStore = useJumpAttemptStore();
const authStore = useAuthStore();

const currentIndex = ref(0);
const slideDirection = ref('slide-left');
const showSubmitModal = ref(false);
const navBar = ref(null);
const showTimer = ref(false);
const showBlurAlarm = ref(false);
const blurCountdown = ref(10);
const remainingSeconds = ref(0);
const showImageOverlay = ref(false);
const numericInputValue = ref('');
const showKeypad = ref(false);

let timerInterval = null;
let blurInterval = null;
const isSubmitting = ref(false);

const questionList = computed(() => jumpAttemptStore.attempt?.question_list ?? []);
const isInProgress = computed(() => jumpAttemptStore.isInProgress);
const isExpired = computed(() => jumpAttemptStore.attempt?.jump?.status === 'expired');

const currentQuestion = computed(() => {
  const item = questionList.value[currentIndex.value];
  if (!item) return null;
  return item;
});

const isNumericQuestion = computed(() => currentQuestion.value?.tier === 4);

const numericInputClass = computed(() => {
  if (isInProgress.value) return 'border-border';
  const item = questionList.value[currentIndex.value];
  const answer = item?.answer;
  const correct = item?.correct_answer;
  if (answer === null || answer === undefined || answer === '') return 'border-border';
  return answer === correct ? 'border-success bg-success/5' : 'border-error bg-error/5';
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
  if (currentIndex.value < questionList.value.length - 1) {
    if (isNumericQuestion.value) saveNumericAnswer();
    slideDirection.value = 'slide-left';
    currentIndex.value++;
  }
}

function handleNumericKey(key) {
  if (!isInProgress.value) return;
  if (key === '\u232b') {
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
  const answer = val === '' ? null : String(parseInt(val, 10));
  if (val !== '' && (isNaN(parseInt(val, 10)) || parseInt(val, 10) < 0 || parseInt(val, 10) > 1000000)) return;
  try {
    await jumpAttemptStore.updateAnswer(
      jumpAttemptStore.attempt.id,
      currentIndex.value,
      answer,
      remainingSeconds.value
    );
  } catch {
    // handled by store
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
  isSubmitting.value = true;
  try {
    await jumpAttemptStore.submitAttempt(jumpAttemptStore.attempt.id, remainingSeconds.value, 'submitted');
    router.replace({
      name: 'JumpResults',
      params: { jumpId: route.params.jumpId, attemptId: jumpAttemptStore.attempt.id },
    });
  } catch {
    isSubmitting.value = false;
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

  if (isNumericQuestion.value && isInProgress.value) {
    if (e.key === 'Backspace') {
      e.preventDefault();
      handleNumericKey('\u232b');
      return;
    }
    if (/^[0-9]$/.test(e.key)) {
      e.preventDefault();
      handleNumericKey(e.key);
      return;
    }
    return;
  }

  const letterMap = { 1: 'A', 2: 'B', 3: 'C', 4: 'D', 5: 'E' };
  const digit = parseInt(e.key, 10);
  if (digit >= 1 && digit <= 5 && isInProgress.value) {
    e.preventDefault();
    selectAnswer(letterMap[digit]);
  }
}

function startCountdown(timeLimitMinutes, extraTimeSeconds = 0) {
  const storedTimer = jumpAttemptStore.attempt.timer ?? 0;
  // timer stores remaining seconds at last save; 0 means fresh start.
  remainingSeconds.value = storedTimer > 0
    ? storedTimer
    : timeLimitMinutes * 60 + extraTimeSeconds;

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
  isSubmitting.value = true;
  try {
    await jumpAttemptStore.submitAttempt(jumpAttemptStore.attempt.id, remainingSeconds.value, termination);
    router.replace({
      name: 'JumpResults',
      params: { jumpId: route.params.jumpId, attemptId: jumpAttemptStore.attempt.id },
    });
  } catch {
    isSubmitting.value = false;
    // handled by store
  }
}

function submitBeacon() {
  if (!jumpAttemptStore.attempt?.id) return;
  const csrfMatch = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
  const csrfToken = csrfMatch ? decodeURIComponent(csrfMatch[1]) : '';
  fetch(`/api/jump-attempts/${jumpAttemptStore.attempt.id}/submit`, {
    method: 'POST',
    keepalive: true,
    headers: {
      'Content-Type': 'application/json',
      'X-XSRF-TOKEN': csrfToken,
    },
    body: JSON.stringify({ timer: remainingSeconds.value, termination: 'blurred' }),
  });
}

function handleBeforeUnload(e) {
  if (!isInProgress.value) return;
  e.preventDefault();
  e.returnValue = '';
  submitBeacon();
}

function handlePageHide() {
  if (!isInProgress.value) return;
  submitBeacon();
}

onBeforeRouteLeave(async () => {
  if (!isInProgress.value || isSubmitting.value) {
    return true;
  }
  const confirmed = window.confirm('Êtes-vous sûr de vouloir quitter ? Votre saut sera soumis automatiquement.');
  if (!confirmed) {
    return false;
  }
  await autoSubmit('abandoned');
  return false;
});

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

watch(currentIndex, (newIdx) => {
  const item = questionList.value[newIdx];
  if (item?.tier === 4) {
    numericInputValue.value = item.answer ?? '';
  }
  nextTick(() => {
    const container = navBar.value;
    if (!container) return;
    const btn = container.querySelectorAll('button')[newIdx];
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
        const existingAttempt = err.response.data.attempt;
        if (existingAttempt?.status === 'inProgress') {
          router.replace({ name: 'JumpAttempt', params: { jumpId, attemptId: existingAttempt.id } });
        } else {
          router.replace({ name: 'JumpResults', params: { jumpId, attemptId: existingAttempt.id } });
        }
        return;
      }
      throw err;
    }
  }

  const loadedAttempt = jumpAttemptStore.attempt;
  if (!loadedAttempt) {
    router.replace({ name: 'Home' });
    return;
  }
  if (loadedAttempt.jump?.status !== 'active') {
    router.replace({ name: 'JumpResults', params: { jumpId, attemptId: loadedAttempt.id } });
    return;
  }
  if (loadedAttempt.status === 'finished') {
    router.replace({ name: 'JumpResults', params: { jumpId, attemptId: loadedAttempt.id } });
    return;
  }

  let isAppActive = true;

  function updateAppState() {
    // The app is truly active ONLY if it is visible AND has user focus
    const currentlyActive = document.visibilityState === 'visible' && document.hasFocus();

    // Only run your logic if the state actually changed
    if (currentlyActive !== isAppActive) {
      isAppActive = currentlyActive;
      
      if (isAppActive) {
        handleFocus();
      } else {
        handleBlur();
      }
    }
  }

  if (isInProgress.value) {
    const jump = jumpAttemptStore.attempt?.jump;
    const extraTimeSeconds = jumpAttemptStore.attempt?.extra_time ?? 0;
    startCountdown(jump?.time ?? 15, extraTimeSeconds);
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('beforeunload', handleBeforeUnload);
    window.addEventListener('pagehide', handlePageHide);
    document.addEventListener('visibilitychange', updateAppState);
    window.addEventListener('focus', updateAppState);
    window.addEventListener('blur', updateAppState);
    // window.addEventListener('blur', handleBlur);
    // window.addEventListener('focus', handleFocus);

    window.Echo.channel(`jump.${route.params.jumpId}`)
      .listen('.JumpExpired', () => {
        clearInterval(timerInterval);
        timerInterval = null;
        clearInterval(blurInterval);
        blurInterval = null;
        showBlurAlarm.value = false;
        window.removeEventListener('keydown', handleKeydown);
        window.removeEventListener('beforeunload', handleBeforeUnload);
        window.removeEventListener('pagehide', handlePageHide);
        window.removeEventListener('blur', handleBlur);
        window.removeEventListener('focus', handleFocus);
        router.replace({
          name: 'JumpResults',
          params: { jumpId: route.params.jumpId, attemptId: jumpAttemptStore.attempt.id },
        });
      });
  }
});

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval);
  if (blurInterval) clearInterval(blurInterval);
  window.removeEventListener('keydown', handleKeydown);
  window.removeEventListener('beforeunload', handleBeforeUnload);
  window.removeEventListener('pagehide', handlePageHide);
  window.removeEventListener('blur', handleBlur);
  window.removeEventListener('focus', handleFocus);
  window.Echo.leave(`jump.${route.params.jumpId}`);

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
  box-shadow: 0 0 15px 5px rgba(0, 0, 0, 0.1);
}
img {
  -webkit-user-select: none;
  -ms-user-select: none;
  user-select: none;
  -webkit-user-drag: none;
}
</style>
