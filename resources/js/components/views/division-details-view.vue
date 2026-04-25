<template>
  <div class="container mx-auto p-6">
    <!-- Back link -->
    <div class="flex items-center gap-4 mb-6">
      <router-link to="/my/divisions" class="text-primary hover:underline flex items-center gap-2">
        <ChevronLeft class="w-4 h-4" />
        Retour
      </router-link>
    </div>

    <!-- Loading -->
    <div v-if="divisionStore.isLoading && !divisionStore.division" class="text-text-muted">
      Chargement...
    </div>

    <!-- Error -->
    <div v-else-if="divisionStore.error && !divisionStore.division" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg">
      {{ divisionStore.error }}
    </div>

    <!-- Teacher View -->
    <template v-else-if="isTeacher && divisionStore.division">
      <!-- Header -->
      <div class="flex items-start justify-between mb-6">
        <div>
          <h2 class="text-2xl font-bold text-text-main dark:text-surface">{{ divisionStore.division.name }}</h2>
          <p class="text-text-muted text-sm mt-1">{{ divisionStore.division.students_count }} élève{{ divisionStore.division.students_count !== 1 ? 's' : '' }}</p>
        </div>
        <div class="flex gap-2">
          <button
            v-if="!divisionStore.division.archived"
            @click="showArchiveConfirm = true"
            class="px-3 py-1.5 text-sm border border-border text-text-muted rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
          >
            Archiver
          </button>
          <button
            v-else
            @click="showUnarchiveConfirm = true"
            class="px-3 py-1.5 text-sm border border-success text-success rounded-lg hover:bg-success/10 transition-colors cursor-pointer"
          >
            Activer
          </button>
          <button
            @click="showDeleteConfirm = true"
            class="px-3 py-1.5 text-sm bg-error/10 text-error border border-error/30 rounded-lg hover:bg-error/20 transition-colors cursor-pointer"
          >
            Supprimer
          </button>
        </div>
      </div>

      <div v-if="divisionStore.division.archived" class="mb-6 p-4 bg-warning/10 border border-warning rounded-lg">
        <p class="text-sm text-warning font-medium">Cette classe est archivée. Cliquez sur "Activer" pour la rendre active.</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left column: settings -->
        <div class="space-y-4">
          <!-- Settings card -->
          <div class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4 space-y-4">
            <h3 class="font-semibold text-text-main dark:text-surface">Paramètres</h3>

            <!-- Name -->
            <div>
              <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Nom</label>
              <div class="flex gap-2">
                <input
                  v-model="editName"
                  type="text"
                  :disabled="divisionStore.division.archived"
                  class="flex-1 px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                />
                <button
                  @click="handleRename"
                  :disabled="editName === divisionStore.division.name || divisionStore.division.archived"
                  class="px-3 py-2 bg-primary hover:bg-primary-hover text-surface rounded-lg text-sm disabled:opacity-40 transition-colors cursor-pointer"
                >
                  OK
                </button>
              </div>
            </div>
        </div>
        <div class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4">
            <!-- Code -->
            <div>
              <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Code d'invitation</label>
              <div class="flex gap-2">
                <input
                  :value="divisionStore.division.code"
                  disabled
                  class="flex-1 px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface/50 bg-gray-50 cursor-not-allowed font-mono font-bold text-sm"
                />
                <button
                  @click="showChangeCodeConfirm = true"
                  :disabled="divisionStore.division.archived"
                  title="Générer un nouveau code"
                  class="text-text-muted hover:text-primary cursor-pointer transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  <RefreshCw class="w-5 h-5" />
                </button>
              </div>
            </div>

            <!-- Accepting students -->
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-text-main dark:text-surface/80">Ouvert aux inscriptions</span>
              <button
                @click="handleToggleAccepting"
                :disabled="divisionStore.division.archived"
                :class="[
                  'relative inline-flex h-6 w-11 items-center rounded-full transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed',
                  divisionStore.division.accepting_students ? 'bg-success' : 'bg-gray-300 dark:bg-gray-600'
                ]"
              >
                <span
                  :class="[
                    'inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow',
                    divisionStore.division.accepting_students ? 'translate-x-6' : 'translate-x-1'
                  ]"
                />
              </button>
            </div>
          </div>
          <!-- Invite form -->
          <div class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4">
            <h3 class="font-semibold text-text-main dark:text-surface mb-3">Inviter par email</h3>
            <form @submit.prevent="handleInvite" class="flex gap-2">
              <input
                v-model="inviteEmail"
                type="email"
                placeholder="email@exemple.com"
                :disabled="divisionStore.division.archived"
                required
                class="flex-1 px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              />
              <button
                type="submit"
                :disabled="divisionStore.isLoading || divisionStore.division.archived"
                class="px-3 py-2 bg-primary cursor-pointer hover:bg-primary-hover text-surface rounded-lg text-sm disabled:opacity-50 transition-colors"
              >
                Inviter
              </button>
            </form>
            <div v-if="inviteSuccess" class="mt-2 text-sm text-success">Invitation envoyée !</div>
            <div v-if="divisionStore.error" class="mt-2 text-sm text-error">{{ divisionStore.error }}</div>

            <!-- Pending invites list -->
            <div v-if="pendingInvites.length" class="mt-3 space-y-1">
              <p class="text-xs text-text-muted font-medium uppercase tracking-wide">En attente</p>
              <div
                v-for="invite in pendingInvites"
                :key="invite.id"
                class="flex items-center justify-between text-sm"
              >
                <span class="text-text-muted">{{ invite.email }}</span>
                <span class="text-xs bg-warning/20 text-warning px-2 py-0.5 rounded-full">En attente</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Middle column: students + invites -->
        <div v-if="!divisionStore.division.archived" class="space-y-4">
          <!-- Students -->
          <div class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4">
            <h3 class="font-semibold text-text-main dark:text-surface mb-3">Élèves ({{ divisionStore.division.students?.length ?? 0 }})</h3>
            <ul v-if="divisionStore.division.students?.length" class="space-y-2">
              <li
                v-for="student in divisionStore.division.students"
                :key="student.id"
                class="flex items-center justify-between py-1"
              >
                <div>
                  <p class="text-sm font-medium text-text-main dark:text-surface">{{ student.pivot?.class_name ?? student.name }}</p>
                  <p class="text-xs text-text-muted">{{ student.email }}</p>
                </div>
                <div class="flex items-center gap-1">
                  <button
                    @click="openEditClassNameModal(student)"
                    :disabled="divisionStore.division.archived"
                    class="text-text-muted hover:text-primary transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                    title="Modifier le nom de classe"
                  >
                    <Pencil class="w-4 h-4" />
                  </button>
                  <button
                    @click="() => { studentIdToRemove = student.id; showRemoveStudentConfirm = true; }"
                    :disabled="divisionStore.division.archived"
                    class="text-text-muted hover:text-error transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                    title="Retirer l'élève"
                  >
                    <X class="w-4 h-4" />
                  </button>
                </div>
              </li>
            </ul>
            <p v-else class="text-sm text-text-muted">Aucun élève.</p>
          </div>

          
        </div>

        <!-- Right column: sessions -->
        <div v-if="!divisionStore.division.archived" class="space-y-4">
        <div class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4">
          <h3 class="font-semibold text-text-main dark:text-surface">Sessions Actives</h3>
          <p class="text-sm text-text-muted mb-3">Ouvrir ou fermer vos sessions pour cette classe.</p>
          <div v-if="teacherSessions.length === 0" class="text-sm text-text-muted">
            Aucune session active.
          </div>
          <ul v-else class="space-y-3">
            <li
              v-for="session in teacherSessions"
              :key="session.id"
              class="flex items-center justify-between"
            >
              <div>
                <p class="text-sm font-medium text-text-main dark:text-surface">{{ session.paper?.title }}</p>
                <p class="text-xs text-text-muted">{{ session.code }}</p>
              </div>
              <div class="flex items-center gap-2">
                <button
                  @click="openActiveSessionModal(session)"
                  class="text-text-muted hover:text-primary transition-colors cursor-pointer"
                  title="Voir les tentatives"
                >
                  <Eye class="w-6 h-6" />
                </button>
                <button
                  @click="handleToggleSession(session)"
                  :class="[
                  'relative inline-flex h-6 w-11 items-center rounded-full transition-colors cursor-pointer',
                    isSessionOpen(session.id) ? 'bg-success' : 'bg-gray-300 dark:bg-gray-600'
                  ]"
                >
                  <span
                    :class="[
                      'inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow',
                      isSessionOpen(session.id) ? 'translate-x-6' : 'translate-x-1'
                    ]"
                  />
                </button>
              </div>
            </li>
          </ul>
        </div>

        <!-- Expired Sessions with Attempts -->
        <div v-if="expiredSessionsWithAttempts.length > 0" class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4">
          <h3 class="font-semibold text-text-main dark:text-surface">Sessions expirées</h3>
          <p class="text-sm text-text-muted mb-3">Sessions terminées avec des tentatives d'élèves.</p>
          <ul class="space-y-3">
            <li
              v-for="session in expiredSessionsWithAttempts"
              :key="session.id"
            >
              <button
                @click="openExpiredSessionModal(session)"
                class="w-full flex items-center cursor-pointer justify-between group hover:bg-gray-50 dark:hover:bg-gray-800 -mx-2 px-2 py-1 rounded-lg transition-colors text-left"
              >
                <div>
                  <p class="text-sm font-medium text-text-main dark:text-surface group-hover:text-primary transition-colors">{{ session.paper?.title }}</p>
                  <p class="text-xs text-text-muted">{{ session.attempts_count }} tentative{{ session.attempts_count > 1 ? 's' : '' }}</p>
                </div>
                <span class="text-xs bg-gray-100 dark:bg-gray-800 text-text-muted px-2 py-0.5 rounded-full">{{ formatExpiredTime(session.expires_at) }}</span>
              </button>
            </li>
          </ul>
        </div>
        </div>
      </div>
    </template>

    <!-- Student View -->
    <template v-else-if="!isTeacher && divisionStore.division">
      <div class="mb-6">
        <h2 class="text-2xl font-bold text-text-main dark:text-surface">{{ divisionStore.division.name }}</h2>
        <p class="text-text-muted text-sm mt-1">{{ divisionStore.division.teacher?.name }}</p>
      </div>

      <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-3">Sessions disponibles</h3>
      <div v-if="!divisionStore.division.kangourou_sessions?.length" class="text-text-muted">
        Aucune session disponible pour le moment.
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <router-link
          v-for="session in divisionStore.division.kangourou_sessions"
          :key="session.id"
          :to="{ name: 'Session', params: { code: session.code } }"
          class="block p-5 rounded-xl border border-border bg-surface dark:bg-gray-900 hover:border-primary hover:shadow-md transition-all"
        >
          <p class="font-semibold text-text-main dark:text-surface">{{ session.paper?.title }}</p>
          <p class="text-sm text-text-muted mt-1">{{ formatTimeUntilExpiration(session.expires_at) }}</p>
        </router-link>
      </div>
    </template>

    <!-- Expired Session Attempts Modal -->
    <div
      v-if="showExpiredSessionModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-6"
      @click.self="closeExpiredSessionModal"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-7xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-border">
          <h3 class="text-lg font-semibold text-text-main dark:text-surface">{{ expiredSessionDetail?.paper?.title }}</h3>
          <button @click="closeExpiredSessionModal" class="text-text-muted hover:text-text-main transition-colors cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>
        <div class="p-4 overflow-y-auto flex-1">
          <DivisionAttemptsTable
            :students="divisionStore.division?.students ?? []"
            :attempts="expiredSessionDetail?.attempts ?? []"
            :loading="isLoadingExpiredSession"
          />
        </div>
      </div>
    </div>

    <!-- Active Session Attempts Modal -->
    <div
      v-if="showActiveSessionModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-6"
      @click.self="closeActiveSessionModal"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-7xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-border">
          <h3 class="text-lg font-semibold text-text-main dark:text-surface">{{ activeSessionDetail?.paper?.title }}</h3>
          <button @click="closeActiveSessionModal" class="text-text-muted hover:text-text-main transition-colors cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>
        <div class="p-4 overflow-y-auto flex-1">
          <DivisionAttemptsTable
            :students="divisionStore.division?.students ?? []"
            :attempts="activeSessionDetail?.attempts ?? []"
            :loading="isLoadingActiveSession"
          />
        </div>
      </div>
    </div>

    <!-- Archive Confirmation Modal -->
    <div
      v-if="showArchiveConfirm"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showArchiveConfirm = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-2">Archiver la classe ?</h3>
        <p class="text-sm text-text-muted mb-4">Une fois archivée, cette classe ne sera plus accessible et toutes les sessions et invitations seront masquées.</p>
        <div class="flex justify-end gap-2">
          <button @click="showArchiveConfirm = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors cursor-pointer">
            Annuler
          </button>
          <button
            @click="handleArchive"
            :disabled="divisionStore.isLoading"
            class="px-4 py-2 bg-warning text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors cursor-pointer"
          >
            Archiver
          </button>
        </div>
      </div>
    </div>

    <!-- Unarchive Confirmation Modal -->
    <div
      v-if="showUnarchiveConfirm"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showUnarchiveConfirm = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-2">Activer la classe ?</h3>
        <p class="text-sm text-text-muted mb-4">Cette classe deviendra à nouveau active et toutes les sessions et invitations seront visibles.</p>
        <div class="flex justify-end gap-2">
          <button @click="showUnarchiveConfirm = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors cursor-pointer">
            Annuler
          </button>
          <button
            @click="handleUnarchive"
            :disabled="divisionStore.isLoading"
            class="px-4 py-2 bg-success text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors cursor-pointer"
          >
            Activer
          </button>
        </div>
      </div>
    </div>

    <!-- Change Code Confirmation Modal -->
    <div
      v-if="showChangeCodeConfirm"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showChangeCodeConfirm = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-2">Changer le code d'invitation ?</h3>
        <p class="text-sm text-text-muted mb-4">Un nouveau code sera généré. L'ancien code ne fonctionnera plus.</p>
        <div class="flex justify-end gap-2">
          <button @click="showChangeCodeConfirm = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors cursor-pointer">
            Annuler
          </button>
          <button
            @click="handleChangeCode"
            :disabled="divisionStore.isLoading"
            class="px-4 py-2 bg-primary hover:bg-primary-hover text-surface rounded-lg text-sm font-medium disabled:opacity-50 transition-colors cursor-pointer"
          >
            Changer
          </button>
        </div>
      </div>
    </div>

    <!-- Edit Class Name Modal -->
    <div
      v-if="showEditClassNameModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showEditClassNameModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-4">Modifier le nom de classe</h3>
        <form @submit.prevent="handleEditClassName" class="space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Prénom</label>
              <input
                v-model="editClassFirstName"
                type="text"
                required
                class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Nom</label>
              <input
                v-model="editClassLastName"
                type="text"
                required
                class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm"
              />
            </div>
          </div>
          <div v-if="divisionStore.error" class="bg-error/10 border border-error/30 text-error px-3 py-2 rounded-lg text-sm">
            {{ divisionStore.error }}
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" @click="showEditClassNameModal = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors cursor-pointer">
              Annuler
            </button>
            <button
              type="submit"
              :disabled="divisionStore.isLoading"
              class="px-4 py-2 bg-primary hover:bg-primary-hover text-surface rounded-lg text-sm font-medium disabled:opacity-50 transition-colors cursor-pointer"
            >
              Enregistrer
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Remove Student Confirmation Modal -->
    <div
      v-if="showRemoveStudentConfirm"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showRemoveStudentConfirm = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-2">Retirer l'élève ?</h3>
        <p class="text-sm text-text-muted mb-4">L'élève sera retiré de la classe. Il pourra la rejoindre en utilisant le code.</p>
        <div class="flex justify-end gap-2">
          <button @click="showRemoveStudentConfirm = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors cursor-pointer">
            Annuler
          </button>
          <button
            @click="() => { handleRemoveStudent(studentIdToRemove); }"
            :disabled="divisionStore.isLoading"
            class="px-4 py-2 bg-error text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors cursor-pointer"
          >
            Retirer
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Modal -->
    <div
      v-if="showDeleteConfirm"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showDeleteConfirm = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-2">Supprimer la classe ?</h3>
        <p class="text-sm text-text-muted mb-4">Cette action est irréversible. Tous les élèves seront retirés.</p>
        <div class="flex justify-end gap-2">
          <button @click="showDeleteConfirm = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors cursor-pointer">
            Annuler
          </button>
          <button
            @click="handleDelete"
            :disabled="divisionStore.isLoading"
            class="px-4 py-2 bg-error text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors cursor-pointer"
          >
            Supprimer
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronLeft, RefreshCw, X, Eye, Pencil } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/authStore';
import { useDivisionStore } from '@/stores/divisionStore';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';
import DivisionAttemptsTable from '@/components/DivisionAttemptsTable.vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const divisionStore = useDivisionStore();
const sessionStore = useKangourouSessionStore();

const divisionId = computed(() => Number(route.params.id));
const isTeacher = computed(() => {
  const div = divisionStore.division;
  return div && authStore.user && div.teacher_id === authStore.user.id;
});

const editName = ref('');
const inviteEmail = ref('');
const inviteSuccess = ref(false);
const showDeleteConfirm = ref(false);
const showArchiveConfirm = ref(false);
const showUnarchiveConfirm = ref(false);
const showChangeCodeConfirm = ref(false);
const showRemoveStudentConfirm = ref(false);
const studentIdToRemove = ref(null);
const showEditClassNameModal = ref(false);
const editClassStudentId = ref(null);
const editClassFirstName = ref('');
const editClassLastName = ref('');
const showExpiredSessionModal = ref(false);
const expiredSessionDetail = ref(null);
const isLoadingExpiredSession = ref(false);
const expiredSessionChannelId = ref(null);
const showActiveSessionModal = ref(false);
const activeSessionDetail = ref(null);
const isLoadingActiveSession = ref(false);
const activeSessionChannelId = ref(null);

const pendingInvites = computed(() =>
  (divisionStore.division?.invites ?? []).filter(i => i.status === 'pending')
);

const teacherSessions = computed(() =>
  (sessionStore.mySessions ?? []).filter(s => s.status === 'active')
);

const expiredSessionsWithAttempts = computed(() =>
  (divisionStore.division?.kangourou_sessions ?? []).filter(s => s.status === 'expired' && s.attempts_count > 0)
);

const openSessionIds = computed(() =>
  (divisionStore.division?.kangourou_sessions ?? []).map(s => s.id)
);

function isSessionOpen(sessionId) {
  return openSessionIds.value.includes(sessionId);
}

onMounted(async () => {
  await divisionStore.fetchDivision(divisionId.value);
  if (divisionStore.division) {
    editName.value = divisionStore.division.name;
  }
  if (isTeacher.value) {
    await sessionStore.fetchMySessions();
  }

  window.Echo.private(`division.${divisionId.value}`)
    .listen('.StudentJoinedDivision', (e) => {
      if (!divisionStore.division) {
        return;
      }

      const student = e.student;
      const alreadyExists = (divisionStore.division.students ?? []).some(s => s.id === student.id);

      if (!alreadyExists) {
        if (!divisionStore.division.students) {
          divisionStore.division.students = [];
        }
        divisionStore.division.students.push(student);
        divisionStore.division.students_count = (divisionStore.division.students_count ?? 0) + 1;
      }
    })
    .listen('.SessionOpenedForDivision', (e) => {
      if (!divisionStore.division) {
        return;
      }

      const session = e.session;
      const alreadyExists = (divisionStore.division.kangourou_sessions ?? []).some(s => s.id === session.id);

      if (!alreadyExists) {
        if (!divisionStore.division.kangourou_sessions) {
          divisionStore.division.kangourou_sessions = [];
        }
        divisionStore.division.kangourou_sessions.push(session);
      }
    });
});

onUnmounted(() => {
  window.Echo.leave(`division.${divisionId.value}`);
  if (expiredSessionChannelId.value !== null) {
    window.Echo.leave(`session.${expiredSessionChannelId.value}`);
  }
  if (activeSessionChannelId.value !== null) {
    window.Echo.leave(`session.${activeSessionChannelId.value}`);
  }
});

async function handleRename() {
  await divisionStore.updateDivision(divisionId.value, { name: editName.value });
}

async function handleChangeCode() {
  await divisionStore.changeCode(divisionId.value);
  if (!divisionStore.error) {
    showChangeCodeConfirm.value = false;
  }
}

async function handleToggleAccepting() {
  const current = divisionStore.division.accepting_students;
  await divisionStore.updateDivision(divisionId.value, { accepting_students: !current });
}

async function handleArchive() {
  await divisionStore.updateDivision(divisionId.value, { archived: true });
  if (!divisionStore.error) {
    showArchiveConfirm.value = false;
  }
}

async function handleUnarchive() {
  await divisionStore.updateDivision(divisionId.value, { archived: false });
  if (!divisionStore.error) {
    showUnarchiveConfirm.value = false;
  }
}

async function handleDelete() {
  await divisionStore.deleteDivision(divisionId.value);
  if (!divisionStore.error) {
    router.push({ name: 'MyDivisions' });
  }
  showDeleteConfirm.value = false;
}

async function handleRemoveStudent(studentId) {
  await divisionStore.removeStudent(divisionId.value, studentId);
  if (!divisionStore.error) {
    studentIdToRemove.value = null;
    showRemoveStudentConfirm.value = false;
  }
}

function openEditClassNameModal(student) {
  editClassStudentId.value = student.id;
  editClassFirstName.value = '';
  editClassLastName.value = '';
  divisionStore.clearError();
  showEditClassNameModal.value = true;
}

async function handleEditClassName() {
  await divisionStore.updateStudentClassName(divisionId.value, editClassStudentId.value, editClassFirstName.value, editClassLastName.value);
  if (!divisionStore.error) {
    showEditClassNameModal.value = false;
  }
}

async function handleInvite() {
  inviteSuccess.value = false;
  await divisionStore.inviteStudent(divisionId.value, inviteEmail.value);
  if (!divisionStore.error) {
    inviteSuccess.value = true;
    inviteEmail.value = '';
    await divisionStore.fetchDivision(divisionId.value);
  }
}

async function openExpiredSessionModal(session) {
  showExpiredSessionModal.value = true;
  isLoadingExpiredSession.value = true;
  expiredSessionDetail.value = null;
  try {
    const data = await sessionStore.fetchSessionDetails(session.id);
    expiredSessionDetail.value = data;
    expiredSessionChannelId.value = session.id;
    window.Echo.private(`session.${session.id}`)
      .listen('.AttemptUpdated', (e) => {
        if (!expiredSessionDetail.value) {
          return;
        }
        const updated = e.attempt;
        const attempts = expiredSessionDetail.value.attempts ?? [];
        const idx = attempts.findIndex(a => a.id === updated.id);
        if (idx !== -1) {
          attempts[idx] = updated;
        } else {
          attempts.push(updated);
        }
        expiredSessionDetail.value = { ...expiredSessionDetail.value, attempts };
      });
  } catch (err) {
    // error handled by store
  } finally {
    isLoadingExpiredSession.value = false;
  }
}

function closeExpiredSessionModal() {
  showExpiredSessionModal.value = false;
  if (expiredSessionChannelId.value !== null) {
    window.Echo.leave(`session.${expiredSessionChannelId.value}`);
    expiredSessionChannelId.value = null;
  }
  expiredSessionDetail.value = null;
}

async function openActiveSessionModal(session) {
  showActiveSessionModal.value = true;
  isLoadingActiveSession.value = true;
  activeSessionDetail.value = null;
  try {
    const data = await sessionStore.fetchSessionDetails(session.id);
    activeSessionDetail.value = data;
    activeSessionChannelId.value = session.id;
    window.Echo.private(`session.${session.id}`)
      .listen('.AttemptUpdated', (e) => {
        if (!activeSessionDetail.value) {
          return;
        }
        const updated = e.attempt;
        const attempts = activeSessionDetail.value.attempts ?? [];
        const idx = attempts.findIndex(a => a.id === updated.id);
        if (idx !== -1) {
          attempts[idx] = updated;
        } else {
          attempts.push(updated);
        }
        activeSessionDetail.value = { ...activeSessionDetail.value, attempts };
      });
  } catch (err) {
    // error handled by store
  } finally {
    isLoadingActiveSession.value = false;
  }
}

function closeActiveSessionModal() {
  showActiveSessionModal.value = false;
  if (activeSessionChannelId.value !== null) {
    window.Echo.leave(`session.${activeSessionChannelId.value}`);
    activeSessionChannelId.value = null;
  }
  activeSessionDetail.value = null;
}

async function handleToggleSession(session) {
  if (isSessionOpen(session.id)) {
    await divisionStore.closeSessionForDivision(session.id, divisionId.value);
  } else {
    await divisionStore.openSessionForDivision(session.id, divisionId.value);
  }
  await divisionStore.fetchDivision(divisionId.value);
}

function formatExpiredTime(expiresAt) {
  if (!expiresAt) return 'Expirée';

  const expiryDate = new Date(expiresAt);
  const now = new Date();
  const diffMs = now - expiryDate;
  const diffSecs = Math.floor(diffMs / 1000);
  const diffMins = Math.floor(diffSecs / 60);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);
  const diffWeeks = Math.floor(diffDays / 7);
  const diffMonths = Math.floor(diffDays / 30);

  if (diffMins < 1) return 'À l\'instant';
  if (diffMins < 60) return `il y a ${diffMins} minute${diffMins > 1 ? 's' : ''}`;
  if (diffHours < 24) return `il y a ${diffHours} heure${diffHours > 1 ? 's' : ''}`;
  if (diffDays < 7) return `il y a ${diffDays} jour${diffDays > 1 ? 's' : ''}`;
  if (diffWeeks < 4) return `il y a ${diffWeeks} semaine${diffWeeks > 1 ? 's' : ''}`;
  return `il y a ${diffMonths} mois`;
}

function formatTimeUntilExpiration(expiresAt) {
  if (!expiresAt) return 'Expirée';

  const expiryDate = new Date(expiresAt);
  const now = new Date();
  const diffMs = expiryDate - now;

  if (diffMs <= 0) return 'Expirée';

  const diffSecs = Math.floor(diffMs / 1000);
  const diffMins = Math.floor(diffSecs / 60);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMins < 1) return 'Expiration immédiate';
  if (diffMins < 60) return `expires dans ${diffMins} minute${diffMins > 1 ? 's' : ''}`;
  if (diffHours < 24) return `expires dans ${diffHours} heure${diffHours > 1 ? 's' : ''}`;
  return `expires dans ${diffDays} jour${diffDays > 1 ? 's' : ''}`;
}
</script>
