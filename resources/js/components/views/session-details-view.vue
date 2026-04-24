<template>
  <div class="container mx-auto p-6">
    <!-- Header -->
    <div class="flex items-center gap-4 mb-6">
      <router-link to="/my/sessions" class="text-primary hover:underline flex items-center gap-2">
        <ChevronLeft class="w-4 h-4" />
        Retour
      </router-link>
      <h1 class="text-3xl font-bold text-text-main dark:text-surface">{{ session?.paper?.title }}</h1>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="text-text-muted"> Chargement des détails de la session... </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg">
      {{ error }}
    </div>

    <!-- Content -->
    <div v-else-if="session" class="space-y-8">
      <!-- Session Details Section -->
      <div class="bg-surface dark:bg-gray-900 rounded-lg border border-border p-6 space-y-4">
        <h2 class="text-xl font-bold text-text-main dark:text-surface">Détails de la session</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Code -->
          <div class="space-y-1">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Code</label>
            <div class="flex gap-2">
              <input
                :value="session.code"
                disabled
                class="flex-1 px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface/50 bg-gray-50 cursor-not-allowed font-mono font-bold"
              />
              <button
                @click="showChangeCodeModal = true"
                class="text-text-main dark:text-surface hover:text-primary cursor-pointer transition-colors"
                title="Changer le code"
              >
                <RefreshCw class="w-5 h-5" />
              </button>
            </div>
          </div>

          <!-- Status -->
          <div class="space-y-1">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Statut</label>
            <div class="flex gap-2">
              <input
                :value="statusLabel(session.status)"
                disabled
                :class="`flex-1 px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface/50 bg-gray-50 cursor-not-allowed font-medium ${
                  session.status === 'active' ? 'text-success' : ''
                }`"
              />
              <button
                v-if="session.status === 'draft'"
                @click="showActivateModal = true"
                title="Activer la session"
                class="flex items-center justify-center text-text-main dark:text-surface hover:text-success cursor-pointer transition-colors"
              >
                <Play class="w-5 h-5" />
              </button>
              <div v-else class="flex items-center justify-center text-text-muted/30">
                <Play class="w-5 h-5" />
              </div>
            </div>
          </div>

          <!-- Created At -->
          <div class="space-y-1">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Créée le</label>
            <input
              :value="new Date(session.created_at).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })"
              disabled
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface/50 bg-gray-50 cursor-not-allowed"
            />
          </div>

          <!-- Expires At -->
          <div class="space-y-1">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Expire le</label>
            <div class="flex gap-2">
              <input
                :value="session.expires_at ? new Date(session.expires_at).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : 'N/A'"
                disabled
                class="flex-1 px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface/50 bg-gray-50 cursor-not-allowed"
              />
              <button
                @click="showExpiryModal = true"
                class="text-text-main dark:text-surface hover:text-primary cursor-pointer transition-colors"
                title="Gérer l'expiration"
              >
                <Clock class="w-5 h-5" />
              </button>
            </div>
          </div>
        </div>

        <!-- Editable Settings -->
        <div class="border-t border-border pt-4 space-y-4 mt-6">
          <h3 class="text-lg font-semibold text-text-main dark:text-surface">Paramètres</h3>

          <!-- Privacy -->
          <div class="space-y-1">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Confidentialité</label>
            <select
              v-model="editForm.privacy"
              :disabled="session.status !== 'draft'"
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <option value="public">Public</option>
              <option value="private">Privé</option>
            </select>
          </div>

          <!-- Class Access (private sessions only) -->
          <div v-if="editForm.privacy === 'private'" class="rounded-lg border border-border p-4 space-y-3">
            <p class="text-sm font-medium text-text-main dark:text-surface/80">Accès par classe</p>
            <p v-if="session.privacy !== 'private'" class="text-xs text-warning">Enregistrez d'abord les paramètres pour activer les accès aux classes.</p>
            <div v-if="divisionStore.divisions.length === 0" class="text-sm text-text-muted italic py-1">
              Aucune classe disponible.
            </div>
            <div
              v-for="division in divisionStore.divisions"
              :key="division.id"
              class="flex items-center justify-between"
            >
              <span class="text-sm text-text-main dark:text-surface">{{ division.name }}</span>
              <button
                type="button"
                @click="toggleDivision(division)"
                :disabled="isTogglingDivision === division.id || session.privacy !== 'private'"
                class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                :class="linkedDivisionIds.includes(division.id) ? 'bg-primary' : 'bg-gray-300 dark:bg-gray-600'"
              >
                <span
                  class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                  :class="linkedDivisionIds.includes(division.id) ? 'translate-x-6' : 'translate-x-1'"
                />
              </button>
            </div>
          </div>

          <!-- Time Limit -->
          <div class="space-y-1">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Durée limite (minutes)</label>
            <input
              v-model.number="editForm.preferences.time_limit"
              type="number"
              min="1"
              max="300"
              step="1"
              :disabled="session.status !== 'draft'"
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
            />
          </div>

          <!-- Correction -->
          <div class="space-y-1">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Correction</label>
            <select
              v-model="editForm.preferences.correction"
              :disabled="session.status !== 'draft'"
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <option value="delayed">Différée (après expiration)</option>
              <option value="immediate">Immédiate</option>
            </select>
          </div>

          <!-- Shuffle -->
          <div class="space-y-1">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Ordre des questions</label>
            <select
              v-model="editForm.preferences.shuffle"
              :disabled="session.status !== 'draft'"
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <option value="none">Ordre normal</option>
              <option value="by_tier">Mélange par palier</option>
              <option value="tiers_1_3">Mélange paliers 1–3</option>
              <option value="complete">Mélange complet</option>
            </select>
          </div>

          <!-- Blur Security -->
          <div class="space-y-1">
            <label class="flex items-center gap-3 cursor-pointer">
              <input
                v-model="editForm.preferences.blur_security"
                type="checkbox"
                :disabled="session.status !== 'draft'"
                class="w-4 h-4 disabled:opacity-50 disabled:cursor-not-allowed"
              />
              <span class="text-sm font-medium text-text-main dark:text-surface/80">Sécurité anti-zapping (surveillance de l'onglet actif)</span>
            </label>
          </div>

          <!-- Only count tier 4 if all before correct -->
          <div class="space-y-1">
            <label class="flex items-center gap-3 cursor-pointer">
              <input
                v-model="editForm.preferences.only_count_tier4_if_all_before_correct"
                type="checkbox"
                :disabled="session.status !== 'draft'"
                class="w-4 h-4 disabled:opacity-50 disabled:cursor-not-allowed"
              />
              <span class="text-sm font-medium text-text-main dark:text-surface/80">Compter le palier 4 uniquement si toutes les questions précédentes sont correctes</span>
            </label>
          </div>

          <!-- Grading -->
          <fieldset class="space-y-3 border border-border rounded-lg p-4">
            <legend class="text-sm font-medium text-text-main dark:text-surface/80 px-1">Barème</legend>
            <div class="grid grid-cols-3 gap-3">
              <div class="space-y-1">
                <label class="block text-xs text-text-muted">Palier 1 (Q1–8)</label>
                <input
                  v-model.number="editForm.preferences.grading.tier1"
                  type="number"
                  min="0"
                  step="1"
                  :disabled="session.status !== 'draft'"
                  class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                />
              </div>
              <div class="space-y-1">
                <label class="block text-xs text-text-muted">Palier 2 (Q9–16)</label>
                <input
                  v-model.number="editForm.preferences.grading.tier2"
                  type="number"
                  min="0"
                  step="1"
                  :disabled="session.status !== 'draft'"
                  class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                />
              </div>
              <div class="space-y-1">
                <label class="block text-xs text-text-muted">Palier 3 (Q17–24)</label>
                <input
                  v-model.number="editForm.preferences.grading.tier3"
                  type="number"
                  min="0"
                  step="1"
                  :disabled="session.status !== 'draft'"
                  class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1">
                <label class="block text-xs text-text-muted">Fraction de pénalité</label>
                <input
                  v-model.number="editForm.preferences.grading.penalty_fraction"
                  type="number"
                  min="0"
                  max="1"
                  step="0.05"
                  :disabled="session.status !== 'draft'"
                  class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                />
                <p class="text-xs text-text-muted">ex. 0,25 = 1/4 des points déduits</p>
              </div>
              <div class="space-y-1">
                <label class="block text-xs text-text-muted">Bonus palier 4 (Q25–26)</label>
                <input
                  v-model.number="editForm.preferences.grading.tier4_bonus"
                  type="number"
                  min="0"
                  step="1"
                  :disabled="session.status !== 'draft'"
                  class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                />
              </div>
            </div>
          </fieldset>

          <!-- Save Button -->
          <div v-if="session.status === 'draft'" class="flex gap-3 pt-4">
            <button
              @click="reloadSessionDetails"
              class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors"
            >
              Annuler
            </button>
            <button
              @click="saveChanges"
              :disabled="isSaving"
              class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors disabled:opacity-50"
            >
              {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Attempts Section -->
      <AttemptsTable
        :attempts="session.attempts || []"
        :editable="true"
        @edit="openEditNameModal"
        @delete="openDeleteModal"
      />
    </div>

    <!-- Activate Session Modal -->
    <div
      v-if="showActivateModal"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
      @click.self="showActivateModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-xl space-y-5">
        <h3 class="text-lg font-bold text-text-main dark:text-surface">Activer la session ?</h3>
        <p class="text-sm text-text-muted">Une fois activée, la session sera visible aux étudiants et ils pourront commencer leurs tentatives.</p>

        <div class="flex gap-3">
          <button
            @click="showActivateModal = false"
            class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors"
          >
            Annuler
          </button>
          <button
            @click="activateSessionHandler"
            :disabled="isActivating"
            class="flex-1 px-4 py-2 rounded-lg bg-success hover:bg-success/80 text-white font-medium transition-colors disabled:opacity-50"
          >
            {{ isActivating ? 'Activation...' : 'Activer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Change Code Modal -->
    <div
      v-if="showChangeCodeModal"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
      @click.self="showChangeCodeModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-xl space-y-5">
        <h3 class="text-lg font-bold text-text-main dark:text-surface">Changer le code de la session ?</h3>
        <p class="text-sm text-text-muted">Le code actuel est <span class="font-mono font-bold">{{ session?.code }}</span>.</p>
        <p class="text-sm text-text-muted">Un nouveau code aléatoire sera généré.</p>

        <div class="flex gap-3">
          <button
            @click="showChangeCodeModal = false"
            class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors"
          >
            Annuler
          </button>
          <button
            @click="changeSessionCode"
            :disabled="isLoadingCodeChange"
            class="flex-1 px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors disabled:opacity-50"
          >
            {{ isLoadingCodeChange ? 'Changement...' : 'Confirmer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Expiry Management Modal -->
    <div
      v-if="showExpiryModal"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
      @click.self="showExpiryModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-2xl w-full mx-4 shadow-xl space-y-5">
        <h3 class="text-lg font-bold text-text-main dark:text-surface">Gérer l'expiration de la session</h3>

        <!-- In-Progress Attempts -->
        <div class="space-y-3">
          <h4 class="text-sm font-semibold text-text-main dark:text-surface">Tentatives en cours</h4>
          <div
            v-if="inProgressAttempts.length === 0"
            class="text-sm text-text-muted py-4 text-center bg-gray-50 dark:bg-gray-800 rounded-lg"
          >
            Aucune tentative en cours.
          </div>
          <div v-else class="space-y-2 max-h-64 overflow-y-auto">
            <div
              v-for="attempt in inProgressAttempts"
              :key="attempt.id"
              class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
            >
              <div>
                <p class="text-sm font-medium text-text-main dark:text-surface">
                  {{ attempt.name || `Utilisateur #${attempt.user_id}` }}
                </p>
                <p class="text-xs text-text-muted">
                  Commencée à {{ new Date(attempt.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}
                </p>
              </div>
              <div class="text-right">
                <p class="text-sm font-semibold text-primary">
                  {{ getTimeRemaining(attempt) }}
                </p>
                <p class="text-xs text-text-muted">restante</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="space-y-3 pt-4 border-t border-border">
          <div class="grid grid-cols-3 gap-2">
            <button
              @click="delayExpiry(10)"
              :disabled="isManagingExpiry"
              class="px-3 py-2 rounded-lg bg-warning hover:bg-warning-hover text-surface font-medium transition-colors disabled:opacity-50 text-sm"
            >
              {{ isManagingExpiry ? 'Traitement...' : '+10 min' }}
            </button>
            <button
              @click="delayExpiry(30)"
              :disabled="isManagingExpiry"
              class="px-3 py-2 rounded-lg bg-warning hover:bg-warning-hover text-surface font-medium transition-colors disabled:opacity-50 text-sm"
            >
              {{ isManagingExpiry ? 'Traitement...' : '+30 min' }}
            </button>
            <button
              @click="delayExpiry(60)"
              :disabled="isManagingExpiry"
              class="px-3 py-2 rounded-lg bg-warning hover:bg-warning-hover text-surface font-medium transition-colors disabled:opacity-50 text-sm"
            >
              {{ isManagingExpiry ? 'Traitement...' : '+1 h' }}
            </button>
          </div>
          <div class="flex gap-3">
            <button
              @click="showExpiryModal = false"
              class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors"
            >
              Annuler
            </button>
            <button
              @click="expireNow"
              :disabled="isManagingExpiry"
              class="flex-1 px-4 py-2 rounded-lg bg-error hover:bg-error-hover text-surface font-medium transition-colors disabled:opacity-50"
            >
              {{ isManagingExpiry ? 'Traitement...' : 'Expirer maintenant' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Name Modal -->
    <div
      v-if="showEditNameModal"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
      @click.self="closeEditNameModal"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-xl space-y-5">
        <h3 class="text-lg font-bold text-text-main dark:text-surface">Éditer le nom du participant</h3>
        
        <input
          v-model="editedName"
          type="text"
          class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg bg-white dark:bg-gray-800 text-text-main dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
        />

        <div class="flex gap-3">
          <button
            @click="closeEditNameModal"
            class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors cursor-pointer"
          >
            Annuler
          </button>
          <button
            @click="saveEditedName"
            :disabled="isOperationLoading || !editedName.trim()"
            class="flex-1 px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors disabled:opacity-50 cursor-pointer"
          >
            {{ isOperationLoading ? 'Enregistrement...' : 'Enregistrer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Attempt Modal -->
    <div
      v-if="showDeleteModal"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
      @click.self="closeDeleteModal"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-xl space-y-5">
        <h3 class="text-lg font-bold text-text-main dark:text-surface">Supprimer la tentative ?</h3>
        
        <p class="text-sm text-text-muted">
          Êtes-vous sûr de vouloir supprimer la tentative de <span class="font-semibold">{{ selectedAttempt?.name || `Utilisateur #${selectedAttempt?.user_id}` }}</span> ?
        </p>

        <label class="flex items-center gap-3 p-3 bg-error/10 border border-error/30 rounded-lg">
          <input
            v-model="deleteConfirmed"
            type="checkbox"
            class="w-4 h-4"
          />
          <span class="text-sm text-error font-medium cursor-pointer">Je confirme la suppression</span>
        </label>

        <div class="flex gap-3">
          <button
            @click="closeDeleteModal"
            class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors cursor-pointer"
          >
            Annuler
          </button>
          <button
            @click="confirmDeleteAttempt"
            :disabled="isOperationLoading || !deleteConfirmed"
            class="flex-1 px-4 py-2 rounded-lg bg-error hover:bg-error-hover text-surface font-medium transition-colors disabled:opacity-50 cursor-pointer"
          >
            {{ isOperationLoading ? 'Suppression...' : 'Supprimer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import { ChevronLeft, RefreshCw, Clock, Play } from 'lucide-vue-next';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';
import { useDivisionStore } from '@/stores/divisionStore';
import AttemptsTable from '@/components/AttemptsTable.vue';

const route = useRoute();
const sessionStore = useKangourouSessionStore();
const divisionStore = useDivisionStore();

const isLoading = ref(false);
const isOperationLoading = ref(false);
const error = ref(null);
const session = ref(null);
const showChangeCodeModal = ref(false);
const isLoadingCodeChange = ref(false);
const showExpiryModal = ref(false);
const isManagingExpiry = ref(false);
const showActivateModal = ref(false);
const isActivating = ref(false);
const showEditNameModal = ref(false);
const showDeleteModal = ref(false);
const selectedAttempt = ref(null);
const editedName = ref('');
const deleteConfirmed = ref(false);
const linkedDivisionIds = ref([]);
const isTogglingDivision = ref(null);
const isSaving = ref(false);

const editForm = reactive({
  privacy: 'public',
  preferences: {
    time_limit: 50,
    blur_security: true,
    only_count_tier4_if_all_before_correct: true,
    shuffle: 'none',
    correction: 'delayed',
    grading: { tier1: 3, tier2: 4, tier3: 5, tier4_bonus: 1, penalty_fraction: 0.25 },
  },
});

const statusLabels = { active: 'Active', expired: 'Expirée', draft: 'Brouillon', inProgress: 'En cours', finished: 'Terminée' };

function statusLabel(status) {
  return statusLabels[status] || status;
}

function initializeForm() {
  if (session.value) {
    const prefs = session.value.preferences || {};
    const grading = prefs.grading || {};
    editForm.privacy = session.value.privacy;
    editForm.preferences.time_limit = prefs.time_limit ?? 50;
    editForm.preferences.blur_security = prefs.blur_security ?? true;
    editForm.preferences.only_count_tier4_if_all_before_correct = prefs.only_count_tier4_if_all_before_correct ?? true;
    editForm.preferences.shuffle = prefs.shuffle ?? 'none';
    editForm.preferences.correction = prefs.correction ?? 'delayed';
    linkedDivisionIds.value = (session.value?.divisions ?? []).map(d => d.id);
    editForm.preferences.grading.tier1 = grading.tier1 ?? 3;
    editForm.preferences.grading.tier2 = grading.tier2 ?? 4;
    editForm.preferences.grading.tier3 = grading.tier3 ?? 5;
    editForm.preferences.grading.tier4_bonus = grading.tier4_bonus ?? 1;
    editForm.preferences.grading.penalty_fraction = grading.penalty_fraction ?? 0.25;
  }
}

async function loadSessionDetails() {
  try {
    isLoading.value = true;
    error.value = null;
    const sessionId = route.params.id;
    session.value = await sessionStore.fetchSessionDetails(sessionId);
    initializeForm();
  } catch (err) {
    error.value = err.message || 'Failed to load session details';
  } finally {
    isLoading.value = false;
  }
}

async function reloadSessionDetails() {
  await loadSessionDetails();
}

async function saveChanges() {
  try {
    isSaving.value = true;
    error.value = null;
    await sessionStore.updateSession(session.value.id, {
      privacy: editForm.privacy,
      preferences: editForm.preferences,
    });
    const sessionId = route.params.id;
    session.value = await sessionStore.fetchSessionDetails(sessionId);
  } catch (err) {
    error.value = err.message || 'Failed to save changes';
  } finally {
    isSaving.value = false;
  }
}

async function activateSession() {
  try {
    isActivating.value = true;
    error.value = null;
    await sessionStore.activateSession(session.value.id);
    await loadSessionDetails();
    showActivateModal.value = false;
  } catch (err) {
    error.value = err.message || 'Failed to activate session';
  } finally {
    isActivating.value = false;
  }
}

async function activateSessionHandler() {
  try {
    isActivating.value = true;
    error.value = null;
    await sessionStore.activateSession(session.value.id);
    await loadSessionDetails();
    showActivateModal.value = false;
  } catch (err) {
    error.value = err.message || 'Failed to activate session';
  } finally {
    isActivating.value = false;
  }
}

async function changeSessionCode() {
  try {
    isLoadingCodeChange.value = true;
    error.value = null;
    await sessionStore.changeSessionCode(session.value.id);
    await loadSessionDetails();
    showChangeCodeModal.value = false;
  } catch (err) {
    error.value = err.message || 'Failed to change session code';
  } finally {
    isLoadingCodeChange.value = false;
  }
}

function getInProgressAttempts() {
  if (!session.value?.attempts) return [];
  return session.value.attempts.filter(attempt => attempt.status !== 'finished');
}

function getTimeRemaining(attempt) {
  if (!session.value?.expires_at || !attempt.created_at) return '—';
  const expiresAt = new Date(session.value.expires_at).getTime();
  const now = Date.now();
  const diffMs = expiresAt - now;
  
  if (diffMs <= 0) return 'Expiré';
  
  const diffMins = Math.floor(diffMs / 60000);
  if (diffMins < 60) return `${diffMins}m`;
  
  const diffHours = Math.floor(diffMins / 60);
  const remainingMins = diffMins % 60;
  return `${diffHours}h ${remainingMins}m`;
}

const inProgressAttempts = computed(() => getInProgressAttempts());

function openEditNameModal(attempt) {
  selectedAttempt.value = attempt;
  editedName.value = attempt.name || '';
  showEditNameModal.value = true;
}

function closeEditNameModal() {
  showEditNameModal.value = false;
  selectedAttempt.value = null;
  editedName.value = '';
}

async function saveEditedName() {
  if (!selectedAttempt.value || !editedName.value.trim()) return;
  
  try {
    isOperationLoading.value = true;
    error.value = null;
    await sessionStore.updateAttemptName(selectedAttempt.value.id, editedName.value);
    
    // Update local state with proper Vue reactivity
    if (session.value?.attempts) {
      const attempt = session.value.attempts.find(a => a.id === selectedAttempt.value.id);
      if (attempt) {
        attempt.name = editedName.value;
      }
    }
    closeEditNameModal();
  } catch (err) {
    error.value = err.message || 'Failed to update attempt name';
  } finally {
    isOperationLoading.value = false;
  }
}

function openDeleteModal(attempt) {
  selectedAttempt.value = attempt;
  deleteConfirmed.value = false;
  showDeleteModal.value = true;
}

function closeDeleteModal() {
  showDeleteModal.value = false;
  selectedAttempt.value = null;
  deleteConfirmed.value = false;
}

async function confirmDeleteAttempt() {
  if (!selectedAttempt.value || !deleteConfirmed.value) return;
  
  try {
    isOperationLoading.value = true;
    error.value = null;
    await sessionStore.deleteAttempt(selectedAttempt.value.id);
    
    // Update local state with proper Vue reactivity
    if (session.value?.attempts) {
      const index = session.value.attempts.findIndex(a => a.id === selectedAttempt.value.id);
      if (index !== -1) {
        session.value.attempts.splice(index, 1);
      }
    }
    closeDeleteModal();
  } catch (err) {
    error.value = err.message || 'Failed to delete attempt';
  } finally {
    isOperationLoading.value = false;
  }
}

async function delayExpiry(minutes) {
  try {
    isManagingExpiry.value = true;
    error.value = null;
    // Add the specified minutes to the expiry time
    const newExpiresAt = new Date(session.value.expires_at);
    newExpiresAt.setMinutes(newExpiresAt.getMinutes() + minutes);
    
    await sessionStore.updateSession(session.value.id, {
      expires_at: newExpiresAt.toISOString(),
    });
    await loadSessionDetails();
    showExpiryModal.value = false;
  } catch (err) {
    error.value = err.message || 'Failed to delay expiry';
  } finally {
    isManagingExpiry.value = false;
  }
}

async function expireNow() {
  try {
    isManagingExpiry.value = true;
    error.value = null;
    // Set expiry to current time
    await sessionStore.updateSession(session.value.id, {
      expires_at: new Date().toISOString(),
    });
    await loadSessionDetails();
    showExpiryModal.value = false;
  } catch (err) {
    error.value = err.message || 'Failed to expire session';
  } finally {
    isManagingExpiry.value = false;
  }
}

async function toggleDivision(division) {
  if (isTogglingDivision.value) return;
  isTogglingDivision.value = division.id;
  try {
    if (linkedDivisionIds.value.includes(division.id)) {
      await divisionStore.closeSessionForDivision(session.value.id, division.id);
      linkedDivisionIds.value = linkedDivisionIds.value.filter(id => id !== division.id);
    } else {
      await divisionStore.openSessionForDivision(session.value.id, division.id);
      linkedDivisionIds.value = [...linkedDivisionIds.value, division.id];
    }
  } catch {
    // error handled by store
  } finally {
    isTogglingDivision.value = null;
  }
}

onMounted(() => {
  loadSessionDetails().then(() => {
    if (session.value?.id) {
      window.Echo.private(`session.${session.value.id}`)
        .listen('.AttemptUpdated', (e) => {
          if (!session.value?.attempts) {
            return;
          }

          const updated = e.attempt;
          const index = session.value.attempts.findIndex(a => a.id === updated.id);

          if (index !== -1) {
            session.value.attempts[index] = updated;
          } else {
            session.value.attempts.push(updated);
          }
        });
    }
  });
  divisionStore.fetchMyDivisions();
});

onUnmounted(() => {
  if (session.value?.id) {
    window.Echo.leave(`session.${session.value.id}`);
  }
});
</script>
