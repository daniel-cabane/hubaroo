<template>
  <div class="min-h-screen flex flex-col">
    <header class="bg-primary text-surface p-1 shadow-md">
      <div class="container mx-auto flex items-center justify-between">
        <h1 class="text-xl font-bold">
          <router-link v-if="!isAttemptView" to="/" class="hover:opacity-80 transition-opacity">
            <img :src="logoSrc" alt="Hubaroo" class="h-12" />
          </router-link>
          <img v-else :src="logoSrc" alt="Hubaroo" class="h-12 opacity-40" />
        </h1>
        <nav class="flex space-x-4 items-center">
          <!-- Active attempt banner -->
          <div
            v-if="attemptStore.activeRecovery"
            class="flex items-center gap-2 bg-surface/20 rounded-lg px-3 py-1"
          >
            <span class="text-sm font-medium text-surface/70">Tentative en cours</span>
            <button
              @click="resumeStoredAttempt"
              class="text-md font-bold cursor-pointer bg-surface/20 hover:bg-surface/30 rounded px-2 py-0.5 transition-colors"
            >
              Reprendre
            </button>
            <div class="relative" ref="bannerMenuContainer">
              <button
                @click.stop="showBannerMenu = !showBannerMenu"
                class="w-6 h-6 rounded flex items-center justify-center hover:bg-surface/30 transition-colors cursor-pointer"
              >
                <ChevronDown class="w-4 h-4 text-surface/70" />
              </button>
              <div
                v-if="showBannerMenu"
                class="absolute right-0 mt-1 w-32 bg-surface dark:bg-gray-900 rounded-lg shadow-xl border border-border py-1 z-50"
              >
                <button
                  @click="dismissAttempt"
                  class="w-full text-left px-3 py-1.5 text-sm text-text-main hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  Ignorer
                </button>
              </div>
            </div>
          </div>

          <!-- Alert Center (for session authors) -->
          <AlertCenter v-if="!isAttemptView && authStore.isAuthenticated" />

          <!-- Account menu -->
          <div v-if="!isAttemptView" class="relative" ref="menuContainer">
            <button
              @click="showAccountMenu = !showAccountMenu"
              class="w-9 h-9 rounded-full flex items-center justify-center cursor-pointer hover:bg-surface/20 transition-colors"
              :class="!authStore.isAuthenticated ? 'bg-surface/30' : ''"
            >
              <User v-if="!authStore.isAuthenticated" class="w-5 h-5" />
              <CircleUserRound v-else class="w-9 h-9" />
            </button>

            <div
              v-if="showAccountMenu"
              class="absolute right-0 mt-2 w-56 bg-surface dark:bg-gray-900 rounded-lg shadow-xl border border-border py-1 z-50"
            >
              <template v-if="authStore.isAuthenticated">
                <button
                  @click="openEditNameModal"
                  class="w-full text-left px-4 py-2 border-b border-border hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  <p class="text-sm font-medium text-text-main truncate">{{ authStore.user?.name || 'Compte' }}</p>
                  <p class="text-xs text-text-muted truncate">{{ authStore.user?.email }}</p>
                </button>
                <router-link
                  to="/my/sessions"
                  @click="showAccountMenu = false"
                  class="flex items-center gap-3 px-4 py-2 text-sm text-text-main hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  <Layers class="w-4 h-4 shrink-0 text-text-muted" />
                  Mes sessions
                </router-link>
                <router-link
                  to="/my/attempts"
                  @click="showAccountMenu = false"
                  class="flex items-center gap-3 px-4 py-2 text-sm text-text-main hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  <ClipboardList class="w-4 h-4 shrink-0 text-text-muted" />
                  Mes tentatives
                </router-link>
                <router-link
                  to="/my/divisions"
                  @click="showAccountMenu = false"
                  class="flex items-center gap-3 px-4 py-2 text-sm text-text-main hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  <GraduationCap class="w-4 h-4 shrink-0 text-text-muted" />
                  Mes classes
                </router-link>
                <router-link
                  to="/papers"
                  @click="showAccountMenu = false"
                  class="flex items-center gap-3 px-4 py-2 text-sm text-text-main hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  <BookOpen class="w-4 h-4 shrink-0 text-text-muted" />
                  Voir un sujet
                </router-link>
                <router-link
                  v-if="authStore.user?.is_admin"
                  to="/admin"
                  @click="showAccountMenu = false"
                  class="flex items-center gap-3 px-4 py-2 text-sm text-text-main hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  <ShieldCheck class="w-4 h-4 shrink-0 text-text-muted" />
                  Administration
                </router-link>
                <button
                  v-if="bugReportStore.canSubmit()"
                  @click="openBugReportModal"
                  class="flex items-center gap-3 w-full text-left px-4 py-2 text-sm text-text-main hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  <Bug class="w-4 h-4 shrink-0 text-text-muted" />
                  Signaler un bug
                </button>
                <div class="border-t border-border">
                  <button
                    @click="openLogoutModal"
                    class="flex items-center gap-3 w-full text-left px-4 py-2 text-sm text-error hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                  >
                    <LogOut class="w-4 h-4 shrink-0" />
                    Déconnexion
                  </button>
                </div>
              </template>
              <template v-else>
                <router-link
                  to="/login"
                  @click="showAccountMenu = false"
                  class="flex items-center gap-3 px-4 py-2 text-sm text-text-main hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  <LogIn class="w-4 h-4 shrink-0 text-text-muted" />
                  Connexion
                </router-link>
                <router-link
                  to="/register"
                  @click="showAccountMenu = false"
                  class="flex items-center gap-3 px-4 py-2 text-sm text-text-main hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  <UserPlus class="w-4 h-4 shrink-0 text-text-muted" />
                  Inscription
                </router-link>
              </template>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main class="flex-1">
      <router-view />
    </main>

    <!-- Recent Attempts Panel -->
    <RecentAttemptsPanel />

    <!-- Logout Confirmation Modal -->
    <div
      v-if="showLogoutModal"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
      @click.self="showLogoutModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-xl space-y-4">
        <h3 class="text-lg font-bold text-text-main dark:text-surface">Se déconnecter ?</h3>
        <p class="text-sm text-text-muted">Vous serez redirigé vers la page de connexion.</p>
        <div class="flex gap-3">
          <button
            @click="showLogoutModal = false"
            class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors"
          >
            Annuler
          </button>
          <button
            @click="handleLogout"
            class="flex-1 px-4 py-2 rounded-lg bg-error hover:bg-error/80 text-white font-medium transition-colors"
          >
            Déconnexion
          </button>
        </div>
      </div>
    </div>

    <!-- Edit Name Modal -->
    <div
      v-if="showEditNameModal"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
      @click.self="closeEditNameModal"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-xl space-y-4">
        <h3 class="text-lg font-bold text-text-main dark:text-surface">Modifier le nom</h3>

        <input
          v-model="editNameValue"
          type="text"
          placeholder="Votre nom"
          class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
          @keyup.enter="submitEditName"
        />

        <div v-if="editNameError" class="text-xs text-error">{{ editNameError }}</div>

        <div class="flex gap-3">
          <button
            @click="closeEditNameModal"
            class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors"
          >
            Annuler
          </button>
          <button
            @click="submitEditName"
            :disabled="!editNameValue.trim() || authStore.isLoading"
            class="flex-1 px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors disabled:opacity-50"
          >
            {{ authStore.isLoading ? 'Enregistrement...' : 'Enregistrer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Bug Report Modal -->
    <div
      v-if="showBugReportModal"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
      @click.self="closeBugReportModal"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-md w-full mx-4 shadow-xl space-y-4">
        <h3 class="text-lg font-bold text-text-main dark:text-surface">Signaler un bug</h3>
        <p class="text-sm text-text-muted">Décrivez le problème rencontré. Nous ferons de notre mieux pour le corriger.</p>

        <textarea
          v-model="bugReportComment"
          placeholder="Décrivez le bug..."
          rows="5"
          class="w-full px-3 py-2 border border-border rounded-lg dark:bg-gray-800 dark:text-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary resize-none"
        ></textarea>

        <div v-if="bugReportStore.error" class="text-xs text-error">{{ bugReportStore.error }}</div>

        <div class="flex gap-3">
          <button
            @click="closeBugReportModal"
            class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors"
          >
            Annuler
          </button>
          <button
            @click="submitBugReport"
            :disabled="!bugReportComment.trim() || bugReportStore.isSubmitting"
            class="flex-1 px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors disabled:opacity-50"
          >
            {{ bugReportStore.isSubmitting ? 'Envoi...' : 'Envoyer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { CircleUserRound, ChevronDown, User, Layers, ClipboardList, GraduationCap, BookOpen, ShieldCheck, Bug, LogOut, LogIn, UserPlus } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/authStore';
import { useAttemptStore } from '@/stores/attemptStore';
import { useBugReportStore } from '@/stores/bugReportStore';
import AlertCenter from '@/components/AlertCenter.vue';
import RecentAttemptsPanel from '@/components/RecentAttemptsPanel.vue';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const attemptStore = useAttemptStore();
const bugReportStore = useBugReportStore();
const showAccountMenu = ref(false);
const showBannerMenu = ref(false);
const showBugReportModal = ref(false);
const showLogoutModal = ref(false);
const showEditNameModal = ref(false);
const editNameValue = ref('');
const editNameError = ref(null);
const bugReportComment = ref('');
const menuContainer = ref(null);
const bannerMenuContainer = ref(null);

const isAttemptView = computed(() => route.name === 'Attempt');
const logoSrc = '/logo%20dark.png';

function handleClickOutside(e) {
  if (menuContainer.value && !menuContainer.value.contains(e.target)) {
    showAccountMenu.value = false;
  }
  if (bannerMenuContainer.value && !bannerMenuContainer.value.contains(e.target)) {
    showBannerMenu.value = false;
  }
}

onMounted(() => {
  attemptStore.checkRecovery();
  document.addEventListener('click', handleClickOutside);
  if (authStore.isAuthenticated) {
    bugReportStore.fetchUnsolvedCount();
  }
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

watch(() => authStore.isAuthenticated, (isAuthenticated) => {
  if (isAuthenticated) {
    bugReportStore.fetchUnsolvedCount();
  }
});

function openEditNameModal() {
  showAccountMenu.value = false;
  editNameValue.value = authStore.user?.name || '';
  editNameError.value = null;
  showEditNameModal.value = true;
}

function closeEditNameModal() {
  showEditNameModal.value = false;
}

async function submitEditName() {
  if (!editNameValue.value.trim()) {
    return;
  }
  editNameError.value = null;
  try {
    await authStore.updateName(editNameValue.value.trim());
    showEditNameModal.value = false;
  } catch {
    editNameError.value = authStore.error;
  }
}

function openBugReportModal() {
  showAccountMenu.value = false;
  bugReportComment.value = '';
  bugReportStore.error = null;
  showBugReportModal.value = true;
}

function closeBugReportModal() {
  showBugReportModal.value = false;
}

async function submitBugReport() {
  if (!bugReportComment.value.trim()) {
    return;
  }
  try {
    await bugReportStore.submitBugReport(bugReportComment.value.trim());
    showBugReportModal.value = false;
  } catch {
    // error shown in modal
  }
}

function resumeStoredAttempt() {
  const stored = attemptStore.activeRecovery;
  attemptStore.activeRecovery = null;
  router.push({
    name: 'Attempt',
    params: { code: stored.session_code, attemptId: stored.attempt_id },
  });
}

function dismissAttempt() {
  showBannerMenu.value = false;
  attemptStore.dismissRecovery();
}

function openLogoutModal() {
  showAccountMenu.value = false;
  showLogoutModal.value = true;
}

async function handleLogout() {
  showLogoutModal.value = false;
  try {
    await authStore.logout();
    router.push('/');
  } catch (error) {
    console.error('Logout failed:', error);
  }
}
</script>