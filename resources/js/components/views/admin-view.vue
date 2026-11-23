<template>
  <div class="container mx-auto p-6 max-w-5xl">
    <div class="flex items-center justify-between gap-4 mb-6">
      <h2 class="text-2xl font-bold text-text-main dark:text-surface">Administration</h2>
      <button
        type="button"
        @click="openRegradeModal"
        class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium cursor-pointer hover:bg-primary-hover transition-colors"
      >
        Recalculer les scores
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex border-b border-border mb-6">
      <button
        @click="activeTab = 'papers'"
        class="px-4 py-2 text-sm font-medium transition-colors"
        :class="activeTab === 'papers' ? 'border-b-2 border-primary text-primary' : 'text-text-muted hover:text-text-main'"
      >
        Sujets
      </button>
      <button
        @click="activeTab = 'users'"
        class="px-4 py-2 text-sm font-medium transition-colors"
        :class="activeTab === 'users' ? 'border-b-2 border-primary text-primary' : 'text-text-muted hover:text-text-main'"
      >
        Utilisateurs
      </button>
      <button
        @click="switchToBugReports"
        class="px-4 py-2 text-sm font-medium transition-colors"
        :class="activeTab === 'bug-reports' ? 'border-b-2 border-primary text-primary' : 'text-text-muted hover:text-text-main'"
      >
        Bugs
        <span v-if="openBugReportsCount > 0" class="ml-1 bg-error text-white text-xs rounded-full px-1.5 py-0.5">{{ openBugReportsCount }}</span>
      </button>
    </div>

    <!-- Error -->
    <div v-if="adminStore.error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg text-sm mb-4">
      {{ adminStore.error }}
    </div>

    <!-- Papers Tab -->
    <div v-if="activeTab === 'papers'">
      <div v-if="adminStore.isLoading && !adminStore.papers.length" class="text-text-muted">Chargement...</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border">
              <th class="text-left py-2 px-2 text-text-muted font-medium">Titre</th>
              <th class="text-left py-2 px-2 text-text-muted font-medium w-24">Année</th>
              <th class="text-left py-2 px-2 text-text-muted font-medium w-24">Niveau</th>
              <th class="text-right py-2 px-2 text-text-muted font-medium w-24">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="paper in adminStore.papers" :key="paper.id" class="border-b border-border/50 hover:bg-gray-50 dark:hover:bg-gray-800/50">
              <td class="py-2 px-2">
                <input
                  v-if="editingPaper?.id === paper.id"
                  v-model="editingPaper.title"
                  class="w-full px-2 py-1 border border-border rounded dark:bg-gray-800 dark:text-surface text-sm"
                />
                <span v-else class="text-text-main dark:text-surface">{{ paper.title }}</span>
              </td>
              <td class="py-2 px-2">
                <input
                  v-if="editingPaper?.id === paper.id"
                  v-model.number="editingPaper.year"
                  type="number"
                  class="w-20 px-2 py-1 border border-border rounded dark:bg-gray-800 dark:text-surface text-sm"
                />
                <span v-else class="text-text-main dark:text-surface">{{ paper.year }}</span>
              </td>
              <td class="py-2 px-2">
                <select
                  v-if="editingPaper?.id === paper.id"
                  v-model="editingPaper.level"
                  class="w-16 px-1 py-1 border border-border rounded dark:bg-gray-800 dark:text-surface text-sm"
                >
                  <option v-for="l in levels" :key="l" :value="l">{{ l }}</option>
                </select>
                <span v-else class="text-text-main dark:text-surface">{{ paper.level }}</span>
              </td>
              <td class="py-2 px-2 text-right">
                <template v-if="editingPaper?.id === paper.id">
                  <button @click="savePaper" class="text-xs text-primary hover:underline mr-2">Sauver</button>
                  <button @click="editingPaper = null" class="text-xs text-text-muted hover:underline">Annuler</button>
                </template>
                <button v-else @click="startEditPaper(paper)" class="text-xs text-primary hover:underline">Modifier</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Users Tab -->
    <div v-if="activeTab === 'users'">
      <div class="flex gap-2 mb-4">
        <input
          v-model="userSearch"
          @keyup.enter="searchUsers"
          placeholder="Rechercher par nom ou email..."
          class="flex-1 px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm"
        />
        <button
          @click="searchUsers"
          :disabled="!userSearch || userSearch.length < 2"
          class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface text-sm font-medium transition-colors disabled:opacity-50"
        >
          Chercher
        </button>
      </div>

      <div v-if="adminStore.isLoading" class="text-text-muted text-sm">Recherche...</div>

      <div v-if="adminStore.users.length" class="space-y-2">
        <div
          v-for="user in adminStore.users"
          :key="user.id"
          class="flex items-center justify-between p-3 rounded-lg border border-border/50 hover:bg-gray-50 dark:hover:bg-gray-800/50"
        >
          <div>
            <p class="text-sm font-medium text-text-main dark:text-surface">{{ user.name }}</p>
            <p class="text-xs text-text-muted">{{ user.email }}</p>
          </div>
          <div class="flex items-center gap-2">
            <select
              :value="user.roles[0] || ''"
              @change="changeRole(user, $event.target.value)"
              class="px-2 py-1 border border-border rounded dark:bg-gray-800 dark:text-surface text-sm"
            >
              <option v-for="role in adminStore.roles" :key="role" :value="role">{{ role }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Bug Reports Tab -->
    <div v-if="activeTab === 'bug-reports'">
      <div v-if="adminStore.isLoading && !adminStore.bugReports.length" class="text-text-muted text-sm">Chargement...</div>
      <div v-else-if="!adminStore.bugReports.length" class="text-text-muted text-sm">Aucun rapport de bug.</div>
      <div v-else class="space-y-3">
        <div
          v-for="report in adminStore.bugReports"
          :key="report.id"
          class="p-4 rounded-lg border border-border/50 hover:bg-gray-50 dark:hover:bg-gray-800/50 space-y-2"
        >
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-xs text-text-muted">{{ report.user?.name }} &bull; {{ report.user?.email }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <select
                :value="report.status"
                @change="changeBugReportStatus(report, $event.target.value)"
                class="px-2 py-1 border border-border rounded dark:bg-gray-800 dark:text-surface text-xs"
              >
                <option value="new">Nouveau</option>
                <option value="important">Important</option>
                <option value="tbd">À voir</option>
                <option value="fixed">Corrigé</option>
                <option value="irrelevant">Non pertinent</option>
              </select>
              <button
                @click="deleteBugReport(report.id)"
                class="text-xs text-error hover:underline"
              >
                Supprimer
              </button>
            </div>
          </div>
          <p class="text-sm text-text-main dark:text-surface whitespace-pre-wrap">{{ report.comment }}</p>
        </div>
      </div>
    </div>

    <div
      v-if="showRegradeModal"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
      @click.self="closeRegradeModal"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-md p-6">
        <div class="flex items-start justify-between gap-4">
          <h3 class="text-lg font-semibold text-text-main dark:text-surface">Recalculer les scores des sauts</h3>
          <button
            type="button"
            @click="closeRegradeModal"
            class="text-text-muted hover:text-text-main cursor-pointer"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <p v-if="regradeLoading" class="mt-4 text-sm text-text-muted">Chargement...</p>
        <p v-else-if="regradeStatus.state === 'running'" class="mt-4 text-sm text-text-main dark:text-surface">Recalcul en cours…</p>
        <p v-else-if="regradeStatus.state === 'done'" class="mt-4 text-sm text-text-main dark:text-surface">
          {{ regradeStatus.attempts }} copies mises à jour.
        </p>
        <p v-else-if="regradeStatus.state === 'failed'" class="mt-4 text-sm text-error">
          Le recalcul a échoué. Vous pouvez le relancer.
        </p>
        <p v-else-if="regradeCount && regradeCount.attempts > 0" class="mt-4 text-sm text-text-main dark:text-surface">
          {{ regradeCount.jumps }} sauts expirés, {{ regradeCount.attempts }} copies déjà notées. Les scores seront recalculés. La maîtrise des élèves et la difficulté des questions ne changent pas.
        </p>
        <p v-else class="mt-4 text-sm text-text-muted">Aucun saut à recalculer.</p>

        <p v-if="regradeError" class="mt-3 text-sm text-error">{{ regradeError }}</p>

        <div class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            @click="closeRegradeModal"
            :disabled="regradeStatus.state === 'running'"
            class="px-4 py-2 rounded-lg border border-border text-sm font-medium text-text-main dark:text-surface cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Annuler
          </button>
          <button
            type="button"
            @click="confirmRegrade"
            :disabled="confirmDisabled"
            class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium cursor-pointer hover:bg-primary-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Recalculer
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { X } from 'lucide-vue-next';
import { useAdminStore } from '@/stores/adminStore';

const adminStore = useAdminStore();
const activeTab = ref('papers');
const editingPaper = ref(null);
const userSearch = ref('');
const levels = ['e', 'b', 'c', 'p', 'j', 's'];
const unsolvedStatuses = ['new', 'important', 'tbd'];

const openBugReportsCount = computed(() =>
  adminStore.bugReports.filter(b => unsolvedStatuses.includes(b.status)).length,
);

onMounted(() => {
  adminStore.fetchPapers();
  adminStore.fetchRoles();
});

function startEditPaper(paper) {
  editingPaper.value = { ...paper };
}

async function savePaper() {
  if (!editingPaper.value) {
    return;
  }
  try {
    await adminStore.updatePaper(editingPaper.value.id, {
      title: editingPaper.value.title,
      year: editingPaper.value.year,
      level: editingPaper.value.level,
    });
    editingPaper.value = null;
  } catch {
    // error shown by store
  }
}

function searchUsers() {
  if (userSearch.value.length >= 2) {
    adminStore.searchUsers(userSearch.value);
  }
}

async function changeRole(user, role) {
  await adminStore.updateUserRole(user.id, role);
}

function switchToBugReports() {
  activeTab.value = 'bug-reports';
  if (!adminStore.bugReports.length) {
    adminStore.fetchBugReports();
  }
}

async function changeBugReportStatus(report, status) {
  await adminStore.updateBugReport(report.id, status);
}

async function deleteBugReport(id) {
  await adminStore.destroyBugReport(id);
}

const showRegradeModal = ref(false);
const regradeLoading = ref(false);
const regradeCount = ref(null);
const regradeStatus = ref({ state: 'idle' });
const regradeError = ref('');
let regradePollTimer = null;

const confirmDisabled = computed(() =>
  regradeLoading.value
  || regradeStatus.value.state === 'running'
  || regradeStatus.value.state === 'done'
  || !regradeCount.value
  || regradeCount.value.attempts === 0,
);

function stopRegradePolling() {
  if (regradePollTimer) {
    clearInterval(regradePollTimer);
    regradePollTimer = null;
  }
}

function startRegradePolling() {
  stopRegradePolling();
  regradePollTimer = setInterval(() => {
    void refreshRegradeStatus();
  }, 2000);
}

async function refreshRegradeStatus() {
  try {
    const response = await axios.get('/api/admin/jump-scores/regrade');
    regradeStatus.value = response.data;

    if (response.data.state === 'done' || response.data.state === 'failed') {
      stopRegradePolling();
    }
  } catch {
    // keep polling; the job may still be running
  }
}

async function openRegradeModal() {
  showRegradeModal.value = true;
  regradeError.value = '';
  regradeStatus.value = { state: 'idle' };
  regradeCount.value = null;
  regradeLoading.value = true;

  try {
    const [countResponse, statusResponse] = await Promise.all([
      axios.get('/api/admin/jump-scores'),
      axios.get('/api/admin/jump-scores/regrade'),
    ]);
    regradeCount.value = countResponse.data;

    if (statusResponse.data.state === 'running') {
      regradeStatus.value = statusResponse.data;
      startRegradePolling();
    }
  } catch {
    regradeError.value = 'Impossible de charger le recalcul.';
  } finally {
    regradeLoading.value = false;
  }
}

async function confirmRegrade() {
  regradeError.value = '';

  try {
    await axios.post('/api/admin/jump-scores/regrade');
    regradeStatus.value = { state: 'running' };
    startRegradePolling();
  } catch (error) {
    if (error.response?.status === 409) {
      regradeError.value = 'Un recalcul est déjà en cours.';
      regradeStatus.value = { state: 'running' };
      startRegradePolling();
      return;
    }

    regradeError.value = 'Le recalcul a échoué. Vous pouvez le relancer.';
  }
}

function closeRegradeModal() {
  stopRegradePolling();
  showRegradeModal.value = false;
}

onUnmounted(() => {
  stopRegradePolling();
});
</script>
