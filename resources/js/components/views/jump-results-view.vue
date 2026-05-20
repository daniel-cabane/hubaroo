<template>
  <div class="container mx-auto p-6 max-w-2xl">
    <div v-if="jumpAttemptStore.isLoading && !jumpAttemptStore.attempt" class="text-text-muted">Chargement...</div>
    <div v-else-if="jumpAttemptStore.error && !jumpAttemptStore.attempt" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg">{{ jumpAttemptStore.error }}</div>

    <template v-else-if="jumpAttemptStore.attempt">
      <!-- Header -->
      <div class="flex items-center gap-4 mb-6">
        <router-link v-if="jumpAttemptStore.attempt.jump?.course?.division_id" :to="{ name: 'DivisionDetails', params: { id: jumpAttemptStore.attempt.jump?.course?.division_id } }" class="text-primary hover:underline flex items-center gap-2">
          <ChevronLeft class="w-4 h-4" />
          Retour à la classe
        </router-link>
      </div>

      <h1 class="text-2xl font-bold text-text-main dark:text-surface mb-1">
        {{ jumpAttemptStore.attempt.jump?.course?.title }}
      </h1>
      <p class="text-text-muted mb-6">
        Saut {{ jumpAttemptStore.attempt.jump?.rank }}
      </p>

      <!-- Awaiting expiry -->
      <div v-if="!isJumpExpired" class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-6 text-center space-y-4">
        <Clock class="w-12 h-12 text-text-muted mx-auto" />
        <p class="text-lg font-medium text-text-main dark:text-surface">Résultats disponibles après expiration</p>
        <p class="text-sm text-text-muted">Expiration {{ formatRelativeDate(jumpAttemptStore.attempt.jump?.expiration) }}</p>

        <p v-if="terminationLabel" class="text-sm font-medium" :class="jumpAttemptStore.attempt.termination === 'submitted' ? 'text-success' : 'text-warning'">{{ terminationLabel }}</p>

        <div v-if="jumpAttemptStore.attempt.status === 'finished' && !isJumpExpired && jumpAttemptStore.attempt.termination !== 'timeout'" class="pt-4">
          <div v-if="!rejoinDemandSent">
            <p class="text-sm text-text-muted mb-3">Avez-vous quitté par erreur ?</p>
            <button
              @click="handleRejoinRequest"
              :disabled="jumpAttemptStore.isLoading"
              class="px-6 py-2 rounded-lg bg-info hover:bg-info-hover text-surface font-medium cursor-pointer transition-colors disabled:opacity-50"
            >
              Demander à reprendre
            </button>
          </div>
          <div v-else class="text-success font-medium">
            Demande envoyée. Attendez la réponse de votre enseignant.
          </div>
        </div>
      </div>

      <!-- Results (jump expired) -->
      <div v-else class="space-y-6">
        <!-- Score summary -->
        <div class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-6 text-center">
          <p class="text-sm text-text-muted mb-2">Score total</p>
          <p class="text-5xl font-bold text-primary">{{ jumpAttemptStore.attempt.score }}</p>
          <p class="text-sm text-text-muted mt-2">
            {{ correctCount }} / {{ questionList.length }} réponse<span v-if="correctCount>1">s</span> correcte<span v-if="correctCount>1">s</span>
          </p>
        </div>

        <!-- Question breakdown -->
        <div class="space-y-3">
          <div
            v-for="(item, idx) in questionList"
            :key="idx"
            class="flex flex-col rounded-xl border overflow-hidden"
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
              class="flex items-center justify-between p-4"
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
                <p class="font-semibold text-sm"
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
    </template>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronLeft, Clock } from 'lucide-vue-next';
import { useJumpAttemptStore } from '@/stores/jumpAttemptStore';
import { useJumpRejoinDemandStore } from '@/stores/jumpRejoinDemandStore';

const route = useRoute();
const router = useRouter();
const jumpAttemptStore = useJumpAttemptStore();
const jumpRejoinDemandStore = useJumpRejoinDemandStore();

const rejoinDemandSent = ref(false);
const currentDemandId = ref(null);
let expiryWatcher = null;
let jumpChannel = null;

const questionList = computed(() => jumpAttemptStore.attempt?.question_list ?? []);

const isJumpExpired = computed(() => jumpAttemptStore.attempt?.jump?.status === 'expired');

const correctCount = computed(() => questionList.value.filter(q => q.status === 'correct').length);

const terminationLabel = computed(() => {
  const map = { blurred: 'Vous avez quitté la page', timeout: 'Temps expiré', submitted: 'Tentative validée' };
  return map[jumpAttemptStore.attempt?.termination] ?? null;
});

function formatDate(val) {
  if (!val) return '—';
  return new Date(val).toLocaleString('fr-FR');
}

function formatRelativeDate(val) {
  if (!val) return '—';
  const diffMs = new Date(val).getTime() - Date.now();
  const diffMin = Math.round(diffMs / 60000);
  if (diffMin > 60) {
    const diffH = Math.round(diffMin / 60);
    return `dans ${diffH} heure${diffH > 1 ? 's' : ''}`;
  }
  if (diffMin > 1) return `dans ${diffMin} minutes`;
  if (diffMin === 1) return 'dans 1 minute';
  if (diffMin === 0) return 'dans moins d\'une minute';
  return formatDate(val);
}

async function handleRejoinRequest() {
  try {
    const data = await jumpAttemptStore.createRejoinDemand(jumpAttemptStore.attempt.id);
    currentDemandId.value = data?.id;
    rejoinDemandSent.value = true;

    if (currentDemandId.value) {
      jumpRejoinDemandStore.addStudentDemand({
        id: currentDemandId.value,
        attemptId: jumpAttemptStore.attempt.id,
        jumpId: route.params.jumpId,
      });

      window.Echo.channel(`jump-rejoin.${currentDemandId.value}`)
        .listen('.JumpRejoinDemandResolved', (event) => {
          jumpRejoinDemandStore.resolveStudentDemand(currentDemandId.value, event.resolution, event.extra_time);
        });
    }
  } catch {
    // error from store
  }
}

async function loadAttempt() {
  await jumpAttemptStore.fetchAttempt(route.params.attemptId);

  if (!isJumpExpired.value && jumpAttemptStore.attempt?.jump?.id) {
    const jumpId = jumpAttemptStore.attempt.jump.id;

    // Listen for real-time expiry event
    jumpChannel = window.Echo.channel(`jump.${jumpId}`)
      .listen('.JumpExpired', async () => {
        if (expiryWatcher) {
          clearTimeout(expiryWatcher);
          expiryWatcher = null;
        }
        await jumpAttemptStore.fetchAttempt(route.params.attemptId);
      });

    // Fallback timeout for jumps expiring within the hour (covers missed events)
    const expiration = jumpAttemptStore.attempt.jump.expiration;
    if (expiration) {
      const msLeft = new Date(expiration).getTime() - Date.now();
      if (msLeft > 0 && msLeft < 3_600_000) {
        expiryWatcher = setTimeout(async () => {
          await jumpAttemptStore.fetchAttempt(route.params.attemptId);
        }, msLeft + 2000);
      }
    }
  }
}

onMounted(async () => {
  await loadAttempt();
});

onUnmounted(() => {
  if (expiryWatcher) clearTimeout(expiryWatcher);
  if (jumpChannel) {
    window.Echo.leave(`jump.${jumpAttemptStore.attempt?.jump?.id}`);
    jumpChannel = null;
  }
  if (currentDemandId.value) {
    window.Echo.leave(`jump-rejoin.${currentDemandId.value}`);
  }
});
</script>
