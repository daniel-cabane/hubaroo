<template>
  <div class="container mx-auto p-6">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-2xl font-bold text-text-main dark:text-surface">Mes classes</h2>
      <button
        v-if="isTeacher"
        @click="showCreateModal = true"
        class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors"
      >
        + Créer une classe
      </button>
      <button
        v-else
        @click="showJoinModal = true"
        class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover cursor-pointer text-surface font-medium transition-colors"
      >
        Rejoindre une classe
      </button>
    </div>

    <!-- Pending Invites (students only) -->
    <div v-if="!isTeacher && divisionStore.invites.length > 0" class="mb-6 space-y-2">
      <h3 class="text-lg font-semibold text-text-main dark:text-surface">Invitations en attente</h3>
      <div
        v-for="invite in divisionStore.invites"
        :key="invite.id"
        class="flex items-center justify-between p-4 rounded-lg border border-primary/30 bg-primary/5"
      >
        <div>
          <p class="font-medium text-text-main dark:text-surface">{{ invite.division?.name }}</p>
          <p class="text-sm text-text-muted">par {{ invite.division?.teacher?.name }}</p>
        </div>
        <div class="flex gap-2">
          <button
            @click="openInviteNameModal(invite.id)"
            class="px-3 py-1.5 text-sm bg-success/10 text-success border border-success/30 rounded-lg hover:bg-success/20 transition-colors cursor-pointer"
          >
            Accepter
          </button>
          <button
            @click="handleDeclineInvite(invite.id)"
            class="px-3 py-1.5 text-sm bg-error/10 text-error border border-error/30 rounded-lg hover:bg-error/20 transition-colors cursor-pointer"
          >
            Refuser
          </button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="divisionStore.isLoading && divisionStore.divisions.length === 0" class="text-text-muted">
      Chargement...
    </div>

    <!-- Empty state -->
    <div v-else-if="divisionStore.divisions.length === 0 || (activeDivisions.length === 0 && archivedDivisions.length === 0)" class="text-text-muted">
      {{ isTeacher ? 'Aucune classe créée.' : 'Vous ne faites partie d\'aucune classe.' }}
    </div>

    <!-- Division list -->
    <div v-else class="space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <router-link
          v-for="d in activeDivisions"
          :key="d.id"
          :to="{ name: 'DivisionDetails', params: { id: d.id } }"
          class="group block p-6 rounded-xl border border-border bg-surface dark:bg-gray-900 hover:border-primary hover:shadow-md transition-all"
        >
          <div class="flex items-start justify-between mb-3">
            <h3 class="text-lg font-semibold text-text-main dark:text-surface group-hover:text-primary transition-colors">
              {{ d.name }}
            </h3>
            <span v-if="!d.accepting_students" class="text-xs bg-warning/20 text-warning px-2 py-0.5 rounded-full">Fermée</span>
          </div>
          <p class="text-sm text-text-muted">
            <span v-if="isTeacher">Code : <span class="font-mono font-bold">{{ d.code }}</span></span>
            <span v-else>{{ d.teacher?.name }}</span>
          </p>
          <p class="text-sm text-text-muted mt-1">
            {{ d.students_count }} élève{{ d.students_count !== 1 ? 's' : '' }}
          </p>
        </router-link>
      </div>

      <!-- Archived classes menu -->
      <div v-if="archivedDivisions.length > 0" class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4">
        <button
          @click="showArchivedMenu = !showArchivedMenu"
          class="w-full flex items-center justify-between"
        >
          <h3 class="text-lg font-semibold text-text-muted">Classes archivées ({{ archivedDivisions.length }})</h3>
          <span :class="['transition-transform', showArchivedMenu ? 'rotate-180' : '']">▼</span>
        </button>
        <div v-if="showArchivedMenu" class="mt-4 space-y-2">
          <router-link
            v-for="d in archivedDivisions"
            :key="d.id"
            :to="{ name: 'DivisionDetails', params: { id: d.id } }"
            class="block p-3 rounded-lg border border-border/50 bg-gray-50 dark:bg-gray-800 hover:border-primary transition-all"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="font-medium text-text-main dark:text-surface">{{ d.name }}</p>
                <p class="text-xs text-text-muted">Code: {{ d.code }}</p>
              </div>
              <span class="text-xs bg-text-muted/20 text-text-muted px-2 py-0.5 rounded-full">Archivée</span>
            </div>
          </router-link>
        </div>
      </div>
    </div>

    <!-- Create Division Modal (Teacher) -->
    <div
      v-if="showCreateModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showCreateModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-4">Créer une classe</h3>
        <form @submit.prevent="handleCreate" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Nom de la classe</label>
            <input
              v-model="newDivisionName"
              type="text"
              required
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
          <div v-if="divisionStore.error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg text-sm">
            {{ divisionStore.error }}
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" @click="showCreateModal = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors">
              Annuler
            </button>
            <button type="submit" :disabled="divisionStore.isLoading" class="px-4 py-2 bg-primary hover:bg-primary-hover text-surface rounded-lg text-sm font-medium disabled:opacity-50 transition-colors">
              Créer
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Join Division Modal (Student) -->
    <div
      v-if="showJoinModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showJoinModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-4">Rejoindre une classe</h3>
        <form @submit.prevent="handleJoin" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Code de la classe</label>
            <input
              v-model="joinCode"
              type="text"
              maxlength="6"
              placeholder="XXXXXX"
              required
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface font-mono uppercase focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Prénom</label>
              <input
                v-model="joinFirstName"
                type="text"
                required
                class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Nom</label>
              <input
                v-model="joinLastName"
                type="text"
                required
                class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
              />
            </div>
          </div>
          <div v-if="divisionStore.error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg text-sm">
            {{ divisionStore.error }}
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" @click="showJoinModal = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors">
              Annuler
            </button>
            <button type="submit" :disabled="divisionStore.isLoading" class="px-4 py-2 bg-primary hover:bg-primary-hover cursor-pointer text-surface rounded-lg text-sm font-medium disabled:opacity-50 transition-colors">
              Rejoindre
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Accept Invite Name Modal -->
    <div
      v-if="showInviteNameModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showInviteNameModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-4">Accepter l'invitation</h3>
        <form @submit.prevent="handleAcceptInviteWithName" class="space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Prénom</label>
              <input
                v-model="inviteFirstName"
                type="text"
                required
                class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Nom</label>
              <input
                v-model="inviteLastName"
                type="text"
                required
                class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
              />
            </div>
          </div>
          <div v-if="divisionStore.error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg text-sm">
            {{ divisionStore.error }}
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" @click="showInviteNameModal = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors">
              Annuler
            </button>
            <button type="submit" :disabled="divisionStore.isLoading" class="px-4 py-2 bg-primary hover:bg-primary-hover cursor-pointer text-surface rounded-lg text-sm font-medium disabled:opacity-50 transition-colors">
              Accepter
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '@/stores/authStore';
import { useDivisionStore } from '@/stores/divisionStore';

const authStore = useAuthStore();
const divisionStore = useDivisionStore();

const isTeacher = computed(() => authStore.user?.is_teacher);

const showCreateModal = ref(false);
const showJoinModal = ref(false);
const showArchivedMenu = ref(false);
const newDivisionName = ref('');
const joinCode = ref('');
const joinFirstName = ref('');
const joinLastName = ref('');
const showInviteNameModal = ref(false);
const pendingInviteId = ref(null);
const inviteFirstName = ref('');
const inviteLastName = ref('');

const activeDivisions = computed(() =>
  divisionStore.divisions.filter(d => !d.archived)
);

const archivedDivisions = computed(() =>
  divisionStore.divisions.filter(d => d.archived)
);

onMounted(async () => {
  await divisionStore.fetchMyDivisions();
  if (!isTeacher.value) {
    await divisionStore.fetchMyInvites();
  }
});

async function handleCreate() {
  await divisionStore.createDivision(newDivisionName.value);
  if (!divisionStore.error) {
    showCreateModal.value = false;
    newDivisionName.value = '';
  }
}

async function handleJoin() {
  await divisionStore.joinDivision(joinCode.value, joinFirstName.value, joinLastName.value);
  if (!divisionStore.error) {
    showJoinModal.value = false;
    joinCode.value = '';
    joinFirstName.value = '';
    joinLastName.value = '';
  }
}

function openInviteNameModal(id) {
  pendingInviteId.value = id;
  inviteFirstName.value = '';
  inviteLastName.value = '';
  divisionStore.clearError();
  showInviteNameModal.value = true;
}

async function handleAcceptInviteWithName() {
  await divisionStore.acceptInvite(pendingInviteId.value, inviteFirstName.value, inviteLastName.value);
  if (!divisionStore.error) {
    showInviteNameModal.value = false;
    await divisionStore.fetchMyDivisions();
  }
}

async function handleAcceptInvite(id) {
  await divisionStore.acceptInvite(id);
  if (!divisionStore.error) {
    await divisionStore.fetchMyDivisions();
  }
}

async function handleDeclineInvite(id) {
  await divisionStore.declineInvite(id);
}
</script>
