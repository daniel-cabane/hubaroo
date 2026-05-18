<template>
  <template v-if="!isAttemptView && displayAttempts.length > 0">
    <!-- Toggle button fixed to left side -->
    <button
      @click="isOpen = !isOpen"
      class="fixed left-0 top-1/2 -translate-y-1/2 z-40 bg-surface dark:bg-gray-900 border border-l-0 border-border rounded-r-lg px-1.5 py-3 shadow-md hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
      title="Tentatives récentes"
    >
      <History class="w-8 h-8 text-text-muted cursor-pointer" />
    </button>

    <!-- Slide-in panel from left -->
    <div
      class="fixed left-0 top-1/2 -translate-y-1/2 z-50 w-76 bg-surface dark:bg-gray-900 border border-border rounded-r-xl shadow-2xl transition-transform duration-300"
      :class="isOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="p-3">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-bold text-text-main">Tentatives récentes</h3>
          <button
            @click="isOpen = false"
            class="text-text-muted hover:text-text-main transition-colors"
            title="Fermer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="flex flex-col gap-2">
          <router-link
            v-for="item in displayAttempts"
            :key="`${item.type}-${item.id}`"
            :to="item.route"
            @click="isOpen = false"
            class="group flex flex-col gap-1.5 p-2.5 rounded-lg bg-gray-50 dark:bg-gray-800 border border-border hover:border-primary hover:shadow-md transition-all"
          >
            <div class="flex items-center justify-between">
              
              <span class="text-sm font-medium text-text-main truncate flex items-center">
                <Sparkle v-if="item.type === 'session'" class="w-3 h-3 flex-shrink-0 mr-1 text-primary" />
                <Zap v-if="item.type === 'jump'" class="w-3 h-3 flex-shrink-0 mr-1 text-secondary" />
                {{ item.title }}
              </span>
              <span
                :class="item.status === 'finished' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'"
                class="px-1.5 py-0.5 rounded text-xs font-semibold flex-shrink-0 ml-2"
              >
                {{ item.status === 'finished' ? 'Terminée' : 'En cours' }}
              </span>
            </div>
            <div class="flex items-center justify-between text-xs text-text-muted">
              <span class="flex items-center gap-1">
                
                <span class="truncate">{{ item.subtitle }}</span>
              </span>
              <span v-if="item.score !== null" class="flex-shrink-0 ml-2">{{ item.score }}</span>
            </div>
          </router-link>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { Sparkle, History, X, Zap } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/authStore';
import { useAttemptStore } from '@/stores/attemptStore';
import { useJumpAttemptStore } from '@/stores/jumpAttemptStore';

const route = useRoute();
const authStore = useAuthStore();
const attemptStore = useAttemptStore();
const jumpAttemptStore = useJumpAttemptStore();

const isOpen = ref(false);
const displayAttempts = ref([]);

const isAttemptView = computed(() => route.name === 'Attempt' || route.name === 'JumpAttempt');

function mapSessionAttempt(ga) {
  return {
    id: ga.id,
    type: 'session',
    title: 'Session Kangourou',
    subtitle: ga.created_at ? new Date(ga.created_at).toLocaleDateString() : '',
    status: ga.status,
    score: (ga.score !== null && ga.kangourou_session?.status === 'expired') ? ga.score : null,
    updatedAt: new Date(ga.updated_at ?? Date.now()),
    route: ga.status === 'inProgress' && ga.kangourou_session?.status === 'active'
      ? { name: 'Attempt', params: { code: ga.kangourou_session.code, attemptId: ga.id } }
      : { name: 'Results', params: { code: ga.kangourou_session?.code, attemptId: ga.id } },
  };
}

function mapJumpAttempt(ja) {
  return {
    id: ja.id,
    type: 'jump',
    title: `Saut #${ja.jump?.rank ?? ja.jump_id}`,
    subtitle: ja.jump?.course?.division?.name && ja.jump?.course?.title
      ? `${ja.jump.course.division.name} - ${ja.jump.course.title}`
      : ja.jump?.course?.title || ja.jump?.course?.division?.name || 'Saut',
    status: ja.status,
    score: (ja.score !== null && ja.jump?.status === 'expired') ? ja.score : null,
    updatedAt: new Date(ja.updated_at ?? Date.now()),
    route: ja.status === 'inProgress' && ja.jump?.status === 'active'
      ? { name: 'JumpAttempt', params: { jumpId: ja.jump_id, attemptId: ja.id } }
      : { name: 'JumpResults', params: { jumpId: ja.jump_id, attemptId: ja.id } },
  };
}

function prependItem(item) {
  displayAttempts.value = [
    item,
    ...displayAttempts.value.filter(i => !(i.type === item.type && i.id === item.id)),
  ].slice(0, 5);
}

async function loadAttempts() {
  if (authStore.isAuthenticated) {
    const [sessionRes, jumpRes] = await Promise.allSettled([
      axios.get('/api/my/attempts'),
      axios.get('/api/my/jump-attempts'),
    ]);
    const sessionAttempts = sessionRes.status === 'fulfilled' ? sessionRes.value.data.attempts : [];
    const jumpAttempts = jumpRes.status === 'fulfilled' ? jumpRes.value.data.attempts : [];

    displayAttempts.value = [
      ...sessionAttempts.map(mapSessionAttempt),
      ...jumpAttempts.map(mapJumpAttempt),
    ].sort((a, b) => b.updatedAt - a.updatedAt).slice(0, 5);
  } else {
    await attemptStore.fetchGuestAttempts();
    displayAttempts.value = attemptStore.guestAttempts.map(mapSessionAttempt);
  }
}

// When leaving an attempt view, prepend only the just-completed attempt
watch(() => route.name, (newName, oldName) => {
  if (oldName === 'Attempt' && newName !== 'Attempt') {
    const a = attemptStore.attempt;
    if (a) prependItem(mapSessionAttempt(a));
  } else if (oldName === 'JumpAttempt' && newName !== 'JumpAttempt') {
    const a = jumpAttemptStore.attempt;
    if (a) prependItem(mapJumpAttempt(a));
  }
});

// Re-load when auth resolves (checkAuth runs in router.beforeEach, after onMounted)
watch(() => authStore.isAuthenticated, (isAuthenticated, wasAuthenticated) => {
  if (isAuthenticated && !wasAuthenticated) {
    loadAttempts();
  }
});

onMounted(loadAttempts);
</script>
