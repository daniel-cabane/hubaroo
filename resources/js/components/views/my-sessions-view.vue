<template>
  <div class="container mx-auto p-6">
    <h2 class="text-2xl font-bold mb-6 text-text-main dark:text-surface">Mes sessions</h2>

    <div v-if="sessionStore.isLoading && !sessionStore.mySessions.length" class="text-text-muted">Chargement...</div>

    <div v-else-if="sessionStore.mySessions.length === 0" class="text-text-muted">
      Aucune session créée.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="session in sessionStore.mySessions"
        :key="session.id"
        class="flex items-center justify-between p-4 rounded-lg border transition-colors"
        :class="session.status === 'active' ? 'bg-success/5 border-success/30' : 'bg-surface dark:bg-gray-900 border-border'"
      >
        <div class="min-w-0 flex-1">
          <p class="font-medium text-text-main dark:text-surface">{{ session.paper?.title }}</p>
          <p class="text-sm text-text-muted">
            Code : <span class="font-mono font-bold">{{ session.code }}</span>
            &middot;
            <span :class="session.status === 'active' ? 'text-success font-medium' : ''">{{ statusLabel(session.status) }}</span>
            &middot; {{ new Date(session.created_at).toLocaleDateString() }}
          </p>
          <p v-if="session.expires_at" class="text-xs text-text-muted mt-0.5">
            Expire : {{ formatExpiry(session.expires_at) }}
          </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0 ml-4">
          <button
            @click="openEdit(session)"
            class="px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-text-main dark:text-surface text-sm transition-colors"
            v-if="session.status == 'active'"
          >
            <Pencil class="w-4 h-4" />
          </button>
          <router-link
            :to="{ name: 'Session', params: { code: session.code } }"
            class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface text-sm font-medium transition-colors"
          >
            Voir
          </router-link>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div
      v-if="editSession"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
      @click.self="editSession = null"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-md w-full mx-4 shadow-xl space-y-5">
        <h3 class="text-lg font-bold text-text-main dark:text-surface">Modifier la session</h3>
        <p class="text-sm text-text-muted">{{ editSession.paper?.title }}</p>

        <!-- Privacy -->
        <div class="space-y-1">
          <label class="block text-sm font-medium text-text-main dark:text-surface/80">Confidentialité</label>
          <select
            v-model="editForm.privacy"
            class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
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
            class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
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
              <input v-model.number="editForm.preferences.grading.tier1" type="number" min="0" step="1" class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary" />
            </div>
            <div class="space-y-1">
              <label class="block text-xs text-text-muted">Palier 2 (Q9–16)</label>
              <input v-model.number="editForm.preferences.grading.tier2" type="number" min="0" step="1" class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary" />
            </div>
            <div class="space-y-1">
              <label class="block text-xs text-text-muted">Palier 3 (Q17–24)</label>
              <input v-model.number="editForm.preferences.grading.tier3" type="number" min="0" step="1" class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block text-xs text-text-muted">Fraction de pénalité</label>
              <input v-model.number="editForm.preferences.grading.penalty_fraction" type="number" min="0" max="1" step="0.05" class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary" />
              <p class="text-xs text-text-muted">ex. 0,25 = 1/4 des points déduits</p>
            </div>
            <div class="space-y-1">
              <label class="block text-xs text-text-muted">Bonus palier 4 (Q25–26)</label>
              <input v-model.number="editForm.preferences.grading.tier4_bonus" type="number" min="0" step="1" class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface text-center focus:outline-none focus:ring-2 focus:ring-primary" />
            </div>
          </div>
        </fieldset>

        <!-- Error -->
        <div v-if="sessionStore.error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg text-sm">
          {{ sessionStore.error }}
        </div>

        <!-- Actions -->
        <div class="flex gap-3">
          <button
            @click="editSession = null; sessionStore.clearError()"
            class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors"
          >
            Annuler
          </button>
          <button
            @click="saveEdit"
            :disabled="sessionStore.isLoading"
            class="flex-1 px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors disabled:opacity-50"
          >
            {{ sessionStore.isLoading ? 'Enregistrement...' : 'Enregistrer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { Pencil } from 'lucide-vue-next';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';

const sessionStore = useKangourouSessionStore();

const editSession = ref(null);
const editForm = reactive({
  privacy: 'public',
  preferences: {
    correction: 'delayed',
    grading: { tier1: 3, tier2: 4, tier3: 5, tier4_bonus: 1, penalty_fraction: 0.25 },
  },
});

const statusLabels = { active: 'Active', expired: 'Expirée', draft: 'Brouillon' };
function statusLabel(status) {
  return statusLabels[status] || status;
}

function formatExpiry(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
}

function openEdit(session) {
  sessionStore.clearError();
  editSession.value = session;
  const prefs = session.preferences || {};
  const grading = prefs.grading || {};
  editForm.privacy = session.privacy;
  editForm.preferences.correction = prefs.correction || 'delayed';
  editForm.preferences.grading.tier1 = grading.tier1 ?? 3;
  editForm.preferences.grading.tier2 = grading.tier2 ?? 4;
  editForm.preferences.grading.tier3 = grading.tier3 ?? 5;
  editForm.preferences.grading.tier4_bonus = grading.tier4_bonus ?? 1;
  editForm.preferences.grading.penalty_fraction = grading.penalty_fraction ?? 0.25;
}

async function saveEdit() {
  try {
    await sessionStore.updateSession(editSession.value.id, {
      privacy: editForm.privacy,
      preferences: editForm.preferences,
    });
    editSession.value = null;
  } catch {
    // error displayed in modal
  }
}

onMounted(() => {
  sessionStore.fetchMySessions();
});
</script>
