<template>
  <div v-if="totalCount > 0" class="relative" ref="container">
    <button
      @click="toggle"
      class="w-9 h-9 rounded-full flex items-center justify-center cursor-pointer hover:bg-surface/20 transition-colors relative"
    >
      <span class="absolute inset-0 rounded-full bg-surface/20 animate-ping"></span>
      <Bell class="w-5 h-5 relative" />
      <span
        class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-error text-surface text-[10px] font-bold rounded-full flex items-center justify-center"
      >
        {{ totalCount }}
      </span>
    </button>

    <div
      v-if="isOpen"
      class="absolute right-0 mt-2 w-96 bg-surface dark:bg-gray-900 rounded-lg shadow-xl border border-border z-50 max-h-[80vh] overflow-y-auto"
    >
      <div class="px-4 py-3 border-b border-border">
        <p class="text-sm font-semibold text-text-main">Centre d'alertes</p>
      </div>

      <!-- Student demands -->
      <template v-if="demandStore.studentDemands.length > 0">
        <div
          v-for="sd in demandStore.studentDemands"
          :key="'s-' + sd.id"
          class="px-4 py-3 border-b border-border space-y-2"
        >
          <div class="flex items-center justify-between gap-2">
            <div>
              <p class="text-sm font-semibold text-text-main">Demande de reprise</p>
              <p v-if="sd.sessionTitle" class="text-xs text-text-muted">{{ sd.sessionTitle }}</p>
              <p class="text-xs text-text-muted font-mono">{{ sd.sessionCode }}</p>
            </div>
            <span
              v-if="sd.status === 'pending'"
              class="text-xs px-2 py-0.5 rounded-full bg-warning/10 text-warning font-medium whitespace-nowrap"
            >En cours d'examen</span>
            <span
              v-else-if="sd.status === 'approved'"
              class="text-xs px-2 py-0.5 rounded-full bg-success/10 text-success font-medium whitespace-nowrap"
            >Acceptée</span>
            <span
              v-else
              class="text-xs px-2 py-0.5 rounded-full bg-error/10 text-error font-medium whitespace-nowrap"
            >Refusée</span>
          </div>
          <div v-if="sd.status === 'approved'" class="flex gap-2">
            <router-link
              :to="{ name: 'Attempt', params: { code: sd.sessionCode, attemptId: sd.attemptId } }"
              @click="isOpen = false; demandStore.removeStudentDemand(sd.id)"
              class="flex-1 text-center px-3 py-1.5 text-xs font-medium rounded-lg bg-success text-white hover:bg-success/80 transition-colors"
            >
              Rejoindre
            </router-link>
            <button
              @click="demandStore.removeStudentDemand(sd.id)"
              class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 dark:bg-gray-800 text-text-muted hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
            >
              Ignorer
            </button>
          </div>
          <button
            v-else-if="sd.status === 'denied'"
            @click="demandStore.removeStudentDemand(sd.id)"
            class="text-xs text-text-muted hover:text-text-main transition-colors"
          >
            Fermer
          </button>
        </div>
      </template>

      <!-- Teacher demands -->
      <div v-if="demandStore.demands.length === 0 && demandStore.studentDemands.length === 0" class="px-4 py-6 text-center text-sm text-text-muted">
        Aucune demande en attente
      </div>

      <div class="divide-y divide-border">
        <div
          v-for="demand in demandStore.demands"
          :key="demand.id"
          class="px-4 py-4 space-y-3"
        >
          <!-- Header -->
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="text-sm font-semibold text-text-main">{{ demand.attempt.name || 'Invité' }}</p>
              <p class="text-xs text-text-muted">{{ demand.attempt.session.paper_title }}</p>
              <p class="text-xs text-text-muted font-mono">{{ demand.attempt.session.code }}</p>
            </div>
            <span class="text-xs text-text-muted whitespace-nowrap">{{ formatDate(demand.created_at) }}</span>
          </div>

          <!-- Attempt stats -->
          <div class="grid grid-cols-2 gap-2 text-xs text-text-muted bg-gray-50 dark:bg-gray-800 rounded-lg p-2">
            <div>
              <span class="font-medium">Questions répondues :</span>
              {{ demand.attempt.answered_count }} / 26
            </div>
            <div>
              <span class="font-medium">Terminaison :</span>
              {{ formatTermination(demand.attempt.termination) }}
            </div>
            <div>
              <span class="font-medium">Temps restant :</span>
              {{ formatTimer(demand.attempt.timer, demand.attempt.session.preferences) }}
            </div>
            <div>
              <span class="font-medium">Mis à jour :</span>
              {{ formatDate(demand.attempt.updated_at) }}
            </div>
          </div>

          <!-- Time adjustment -->
          <!-- <div class="flex items-center gap-2">
            <label class="text-xs text-text-muted whitespace-nowrap">Temps ajouté (s) :</label>
            <input
              v-model.number="extraTimes[demand.id]"
              type="number"
              min="-3600"
              max="3600"
              step="60"
              class="w-24 px-2 py-1 text-xs border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-1 focus:ring-primary"
            />
          </div> -->

          <!-- Actions -->
          <div class="flex gap-2">
            <button
              @click="approve(demand)"
              class="flex-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-success/10 text-success hover:bg-success/20 transition-colors"
            >
              Accepter
            </button>
            <button
              @click="reject(demand)"
              class="flex-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-error/10 text-error hover:bg-error/20 transition-colors"
            >
              Refuser
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import { Bell } from 'lucide-vue-next';
import { useRejoinDemandStore } from '@/stores/rejoinDemandStore';
import { useAuthStore } from '@/stores/authStore';

const demandStore = useRejoinDemandStore();
const authStore = useAuthStore();
const isOpen = ref(false);
const container = ref(null);
const extraTimes = reactive({});

const totalCount = computed(() => demandStore.demands.length + demandStore.studentDemands.length);

function toggle() {
  isOpen.value = !isOpen.value;
}

function handleClickOutside(e) {
  if (container.value && !container.value.contains(e.target)) {
    isOpen.value = false;
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

function formatTermination(termination) {
  const map = {
    none: 'Aucune',
    submitted: 'Soumis',
    blurred: 'Quitté la page',
    timeout: 'Temps écoulé',
  };
  return map[termination] || termination;
}

function formatTimer(timerSeconds, preferences) {
  if (timerSeconds === null || timerSeconds === undefined) {
    const limit = preferences?.time_limit ?? 50;
    return `${limit} min (non démarré)`;
  }
  const mins = Math.floor(timerSeconds / 60);
  const secs = timerSeconds % 60;
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

async function approve(demand) {
  const extra = extraTimes[demand.id] || 0;
  await demandStore.approveDemand(demand.id, extra);
  delete extraTimes[demand.id];
}

async function reject(demand) {
  await demandStore.rejectDemand(demand.id);
  delete extraTimes[demand.id];
}

function listenForStudentDemand(demand) {
  window.Echo.channel(`rejoin.${demand.id}`)
    .listen('.RejoinDemandResolved', (event) => {
      demandStore.resolveStudentDemand(demand.id, event.resolution, event.extra_time);
      if (event.resolution === 'approved') {
        isOpen.value = true;
      }
    });
}

onMounted(async () => {
  document.addEventListener('click', handleClickOutside);

  // Subscribe to any demands the student already has pending in the store
  demandStore.studentDemands
    .filter(d => d.status === 'pending')
    .forEach(listenForStudentDemand);
});

async function setupTeacherChannel() {
  await demandStore.fetchMyDemands();

  window.Echo.private(`App.Models.User.${authStore.user.id}`)
    .listen('.RejoinDemandCreated', (event) => {
      demandStore.addDemand(event.demand);
    });
}

// The component now mounts before auth resolves (no longer gated by v-if isAuthenticated),
// so we watch for authentication becoming true to set up the teacher channel subscription.
watch(() => authStore.isAuthenticated, (isAuth) => {
  if (isAuth) {
    setupTeacherChannel();
  }
}, { immediate: true });

// Subscribe whenever a new student demand is added
watch(() => demandStore.studentDemands.length, (newLen, oldLen) => {
  if (newLen > oldLen) {
    const newest = demandStore.studentDemands[0];
    if (newest?.status === 'pending') {
      listenForStudentDemand(newest);
    }
  }
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  if (authStore.user?.id) {
    window.Echo.private(`App.Models.User.${authStore.user.id}`)
      .stopListening('.RejoinDemandCreated');
  }
});
</script>
