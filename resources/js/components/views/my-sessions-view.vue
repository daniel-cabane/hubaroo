<template>
  <div class="container mx-auto p-6">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-2xl font-bold text-text-main dark:text-surface">Mes sessions</h2>
      <router-link
        to="/kangourou/create"
        class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors"
      >
        + Créer une session
      </router-link>
    </div>

    <div v-if="sessionStore.isLoading && !sessionStore.mySessions.length" class="text-text-muted">Chargement...</div>

    <div v-else-if="sessionStore.mySessions.length === 0" class="text-text-muted">
      Aucune session créée.
    </div>

    <div v-else class="space-y-3">
      <router-link
        v-for="session in sessionStore.mySessions"
        :key="session.id"
        :to="{ name: 'SessionDetails', params: { id: session.id } }"
        class="flex items-center justify-between p-4 rounded-lg border transition-colors hover:border-primary/50 cursor-pointer"
        :class="session.status === 'active' ? 'bg-success/5 border-success/30' : 'bg-surface dark:bg-gray-900 border-border'"
      >
        <div class="min-w-0 flex-1">
          <p class="font-medium text-text-main dark:text-surface">{{ session.paper?.title }}</p>
          <p class="text-sm text-text-muted">
            Code : <span class="font-mono font-bold">{{ session.code }}</span>
            <!-- &middot;
            <span :class="session.status === 'active' ? 'text-success font-medium' : ''">{{ statusLabel(session.status) }}</span>
            &middot; {{ new Date(session.created_at).toLocaleDateString() }} -->
          </p>
          <p v-if="session.expires_at" class="text-xs text-text-muted mt-0.5">
            Expire : {{ formatExpiry(session.expires_at) }}
          </p>
        </div>
        <div>
          <span :class="session.status === 'active' ? 'text-success font-medium' : ''">{{ statusLabel(session.status) }}</span>
        </div>
        <button
          @click.stop="() => { sessionIdToDelete = session.id; showDeleteConfirm = true; }"
          :disabled="session.status === 'active'"
          class="w-10 h-10 rounded-full flex items-center justify-center bg-error/10 hover:bg-error/20 text-error transition-colors disabled:opacity-40 disabled:cursor-not-allowed flex-shrink-0 ml-4"
          title="Supprimer"
        >
          <Trash class="w-5 h-5" />
        </button>
      </router-link>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showDeleteConfirm"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showDeleteConfirm = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-2">Supprimer la session ?</h3>
        <p class="text-sm text-text-muted mb-4">Cette action est irréversible.</p>
        <div class="flex justify-end gap-2">
          <button @click="showDeleteConfirm = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors">
            Annuler
          </button>
          <button
            @click="handleDeleteSession"
            :disabled="sessionStore.isLoading"
            class="px-4 py-2 bg-error text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors"
          >
            Supprimer
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Trash } from 'lucide-vue-next';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';

const sessionStore = useKangourouSessionStore();

const showDeleteConfirm = ref(false);
const sessionIdToDelete = ref(null);

const statusLabels = { active: 'Active', expired: 'Expirée', draft: 'Brouillon' };
function statusLabel(status) {
  return statusLabels[status] || status;
}

function formatExpiry(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
}

async function handleDeleteSession() {
  try {
    await sessionStore.deleteSession(sessionIdToDelete.value);
    showDeleteConfirm.value = false;
    sessionIdToDelete.value = null;
  } catch (err) {
    // Error is handled by the store
  }
}

onMounted(() => {
  sessionStore.fetchMySessions();
});
</script>
