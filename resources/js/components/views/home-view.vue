<template>
  <div class="min-h-[calc(100vh-64px)] p-6">
    <!-- Class Invites Section -->
    <div v-if="authStore.isAuthenticated && !authStore.user?.is_teacher && divisionStore.invites.length > 0" class="mb-8">
      <div class="bg-secondary/10 border-2 border-secondary/30 rounded-lg p-6">
        <h3 class="text-lg font-bold text-text-main dark:text-surface mb-4">Invitation<span v-if="divisionStore.invites.length>1">s</span></h3>
        <div class="space-y-3">
          <div v-for="invite in divisionStore.invites" :key="invite.id" class="flex items-center justify-between p-4 bg-surface dark:bg-gray-900 rounded-lg border border-secondary/20">
            <div>
              <p class="font-medium text-text-main dark:text-surface">{{ invite.division?.name }}</p>
              <p class="text-sm text-text-muted">Invitation de l'enseignant à rejoindre la classe</p>
            </div>
            <div class="flex gap-2">
              <button
                @click="handleAcceptInvite(invite.id)"
                class="px-3 py-1.5 rounded-lg bg-success hover:bg-success/80 text-white text-sm font-medium transition-colors"
              >
                Accepter
              </button>
              <button
                @click="divisionStore.declineInvite(invite.id)"
                class="px-3 py-1.5 rounded-lg bg-error/20 hover:bg-error/30 text-error text-sm font-medium transition-colors"
              >
                Refuser
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Active Sessions Overlay -->
    <div v-if="authStore.isAuthenticated && showActiveSessions && activeSessions.length > 0" class="fixed top-20 left-0 right-0 z-40 max-h-[400px] overflow-y-auto bg-gradient-to-b from-primary/10 to-primary/5 border-b border-primary/20 p-4">
      <div class="container mx-auto">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-text-main dark:text-surface">Sessions en cours</h3>
          <button
            @click="showActiveSessions = false"
            class="text-text-muted hover:text-text-main transition-colors"
            title="Fermer"
          >
            <X class="w-5 h-5" />
          </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
          <router-link
            v-for="session in activeSessions"
            :key="session.id"
            :to="{ name: 'Session', params: { code: session.code } }"
            class="group flex flex-col gap-2 p-3 rounded-lg bg-surface dark:bg-gray-900 border border-primary/30 hover:border-primary hover:shadow-md transition-all"
          >
            <div class="text-xs text-text-muted flex justify-end">{{ session.division_name }}</div>
            <p class="font-medium text-lg text-text-main dark:text-surface truncate">{{ session.paper?.title }}</p>
          </router-link>
        </div>
      </div>
    </div>

    <!-- Toggle Button for Sessions -->
    <div v-if="authStore.isAuthenticated && activeSessions.length > 0" class="fixed top-20 right-6 z-40">
      <button
        @click="showActiveSessions = !showActiveSessions"
        :class="[
          'px-3 py-2 text-sm font-medium rounded-lg transition-all flex items-center gap-2',
          showActiveSessions
            ? 'bg-primary text-white shadow-lg'
            : 'bg-primary/10 text-primary hover:bg-primary/20'
        ]"
      >
        <span class="inline-flex items-center justify-center w-5 h-5 bg-white/20 rounded-full text-xs">{{ activeSessions.length }}</span>
        {{ showActiveSessions ? 'Masquer' : 'Sessions actives' }}
      </button>
    </div>

    <div class="flex items-center justify-center min-h-[calc(100vh-64px)]">
      <div 
        class=" gap-8 max-w-3xl w-full justify-items-center"
        :class="authStore.isAuthenticated ? 'grid grid-cols-1 md:grid-cols-2' : 'grid grid-cols-1'"
      >
      <!-- Create Session -->
      <router-link
        v-if="authStore.isAuthenticated"
        to="/kangourou/create"
        class="group flex flex-col items-center justify-center gap-4 rounded-2xl border-2 border-primary/20 bg-surface p-10 shadow-sm transition-all hover:border-primary hover:shadow-lg hover:-translate-y-1"
      >
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white">
          <PlusCircle class="h-8 w-8" />
        </div>
        <h2 class="text-xl font-bold text-text-main">Créer une session</h2>
        <p class="text-sm text-text-muted text-center">Choisissez un sujet et lancez une nouvelle session Kangourou pour vos élèves.</p>
      </router-link>

      <!-- Join Session -->
      <router-link
        to="/kangourou/join"
        class="group flex flex-col items-center justify-center gap-4 rounded-2xl border-2 border-secondary/20 bg-surface p-10 shadow-sm transition-all hover:border-secondary hover:shadow-lg hover:-translate-y-1"
      >
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-secondary/10 text-secondary transition-colors group-hover:bg-secondary group-hover:text-white">
          <LogIn class="h-8 w-8" />
        </div>
        <h2 class="text-xl font-bold text-text-main">Rejoindre une session</h2>
        <p class="text-sm text-text-muted text-center">Entrez un code de session pour rejoindre une session Kangourou existante.</p>
      </router-link>
    </div>
  </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { PlusCircle, LogIn, X } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/authStore';
import { useDivisionStore } from '@/stores/divisionStore';
import { useAttemptStore } from '@/stores/attemptStore';

const authStore = useAuthStore();
const divisionStore = useDivisionStore();
const attemptStore = useAttemptStore();

const showActiveSessions = ref(true);

const activeSessions = computed(() => {
  const userAttemptSessionIds = new Set(
    attemptStore.myAttempts.map(attempt => attempt.kangourou_session_id)
  );

  const sessions = [];
  divisionStore.divisions.forEach(division => {
    if (division.kangourou_sessions) {
      division.kangourou_sessions.forEach(session => {
        if (session.status === 'active' && !userAttemptSessionIds.has(session.id)) {
          sessions.push({
            ...session,
            division_name: division.name,
          });
        }
      });
    }
  });
  return sessions;
});

async function handleAcceptInvite(inviteId) {
  try {
    await divisionStore.acceptInvite(inviteId);
    // Refresh divisions to include newly joined class and its active sessions
    await divisionStore.fetchMyDivisions();
  } catch {
    // error handled by store
  }
}

onMounted(async () => {
  if (authStore.isAuthenticated) {
    await divisionStore.fetchMyDivisions();
    await attemptStore.fetchMyAttempts();
    
    // Fetch invites if student
    if (!authStore.user?.is_teacher) {
      await divisionStore.fetchMyInvites();
    }
  }
});
</script>