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
                class="text-text-main dark:text-surface hover:text-primary transition-colors"
                title="Changer le code"
              >
                <RefreshCw class="w-5 h-5" />
              </button>
            </div>
          </div>

          <!-- Status -->
          <div class="space-y-1">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Statut</label>
            <input
              :value="statusLabel(session.status)"
              disabled
              :class="`w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface/50 bg-gray-50 cursor-not-allowed font-medium ${
                session.status === 'active' ? 'text-success' : ''
              }`"
            />
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
                class="text-text-main dark:text-surface hover:text-primary transition-colors"
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
              :disabled="session.status !== 'active'"
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <option value="public">Public</option>
              <option value="private">Privé</option>
            </select>
          </div>

          <!-- Correction -->
          <div class="space-y-1">
            <label class="block text-sm font-medium text-text-main dark:text-surface/80">Correction</label>
            <select
              v-model="editForm.preferences.correction"
              :disabled="session.status !== 'active'"
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <option value="delayed">Différée (après expiration)</option>
              <option value="immediate">Immédiate</option>
            </select>
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
                  :disabled="session.status !== 'active'"
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
                  :disabled="session.status !== 'active'"
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
                  :disabled="session.status !== 'active'"
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
                  :disabled="session.status !== 'active'"
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
                  :disabled="session.status !== 'active'"
                  class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                />
              </div>
            </div>
          </fieldset>

          <!-- Save Button -->
          <div v-if="session.status === 'active'" class="flex gap-3 pt-4">
            <button
              @click="reloadSessionDetails"
              class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors"
            >
              Annuler
            </button>
            <button
              @click="saveChanges"
              :disabled="isLoading"
              class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors disabled:opacity-50"
            >
              {{ isLoading ? 'Enregistrement...' : 'Enregistrer' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Attempts Section -->
      <div class="bg-surface dark:bg-gray-900 rounded-lg border border-border p-6 space-y-4">
        <h2 class="text-xl font-bold text-text-main dark:text-surface">Tentatives ({{ session.attempts?.length || 0 }})</h2>

        <div v-if="!session.attempts || session.attempts.length === 0" class="text-text-muted py-8 text-center">
          Aucune tentative pour cette session.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="border-b border-border">
              <tr class="text-left">
                <th class="px-4 py-3 font-semibold text-text-main dark:text-surface/80">Participant</th>
                <th class="px-4 py-3 font-semibold text-text-main dark:text-surface/80">Statut</th>
                <th class="px-4 py-3 font-semibold text-text-main dark:text-surface/80">Score</th>
                <th class="px-4 py-3 font-semibold text-text-main dark:text-surface/80">Temps</th>
                <th class="px-4 py-3 font-semibold text-text-main dark:text-surface/80">Termination</th>
                <th class="px-4 py-3 font-semibold text-text-main dark:text-surface/80">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <template v-for="attempt in session.attempts" :key="attempt.id">
                <tr>
                  <td rowspan="2" class="px-4 py-3">
                    <span class="font-medium text-text-main dark:text-surface">{{ attempt.name || `Utilisateur #${attempt.user_id}` }}</span>
                  </td>
                  <td class="px-4 py-3">
                    <span
                      :class="`px-2 py-1 rounded text-xs font-semibold ${
                        attempt.status === 'finished'
                          ? 'bg-success/10 text-success'
                          : 'bg-warning/10 text-warning'
                      }`"
                    >
                      {{ statusLabel(attempt.status) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 font-semibold text-text-main dark:text-surface">
                    {{ attempt.score !== null ? attempt.score : '—' }}
                  </td>
                  <td class="px-4 py-3 text-text-muted">
                    {{ attempt.timer !== null ? `${Math.floor(attempt.timer / 60)}:${String(attempt.timer % 60).padStart(2, '0')}` : '—' }}
                  </td>
                  <td class="px-4 py-3 text-text-muted">{{ terminationLabel(attempt.termination) }}</td>
                  <td class="px-4 py-3 text-text-muted text-xs text-center">
                    <div>{{ new Date(attempt.created_at).toLocaleDateString('fr-FR') }}</div>
                    <div>{{ new Date(attempt.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}</div>
                  </td>
                </tr>
                <tr>
                  <td colspan="7" class="px-4 py-4">
                    <div class="flex flex-wrap gap-2">
                      <div
                        v-for="(answer, idx) in attempt.answers"
                        :key="idx"
                        :title="`Q${idx + 1}: ${answer.status == 'unanswered' ? 'Pas de réponse' : answer.status}`"
                        :class="`
                          w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold cursor-help
                          ${getAnswerColorClass(answer.status)}
                        `"
                      >
                        {{ answer.answer || '' }}
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import { ChevronLeft, RefreshCw, Clock } from 'lucide-vue-next';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';

const route = useRoute();
const sessionStore = useKangourouSessionStore();

const isLoading = ref(false);
const error = ref(null);
const session = ref(null);
const showChangeCodeModal = ref(false);
const isLoadingCodeChange = ref(false);
const showExpiryModal = ref(false);
const isManagingExpiry = ref(false);

const editForm = reactive({
  privacy: 'public',
  preferences: {
    correction: 'delayed',
    grading: { tier1: 3, tier2: 4, tier3: 5, tier4_bonus: 1, penalty_fraction: 0.25 },
  },
});

const statusLabels = { active: 'Active', expired: 'Expirée', draft: 'Brouillon', inProgress: 'En cours', finished: 'Terminée' };

function statusLabel(status) {
  return statusLabels[status] || status;
}

const terminationLabels = { none: 'Aucune', submitted: 'Soumise', blurred: 'Floutée', timeout: 'Timeout' };

function terminationLabel(termination) {
  return terminationLabels[termination] || termination;
}

function getAnswerColorClass(status) {
  if (status === 'correct') return 'bg-success text-white';
  if (status === 'incorrect') return 'bg-error text-white';
  if (status === 'unanswered') return 'bg-gray-200 text-text-muted dark:bg-gray-700';
  return 'bg-gray-300 text-text-muted dark:bg-gray-600'; // pending or default
}

function initializeForm() {
  if (session.value) {
    const prefs = session.value.preferences || {};
    const grading = prefs.grading || {};
    editForm.privacy = session.value.privacy;
    editForm.preferences.correction = prefs.correction || 'delayed';
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
    isLoading.value = true;
    error.value = null;
    await sessionStore.updateSession(session.value.id, {
      privacy: editForm.privacy,
      preferences: editForm.preferences,
    });
    await loadSessionDetails();
  } catch (err) {
    error.value = err.message || 'Failed to save changes';
  } finally {
    isLoading.value = false;
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

onMounted(() => {
  loadSessionDetails();
});
</script>
