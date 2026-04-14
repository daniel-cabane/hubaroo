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
        <div class="flex items-center gap-2 flex-shrink-0 ml-4">
          <router-link
            :to="{ name: 'SessionDetails', params: { id: session.id } }"
            class="w-10 h-10 rounded-full flex items-center justify-center bg-primary hover:bg-primary-hover text-surface transition-colors"
            title="Voir les détails"
          >
            <Eye class="w-5 h-5" />
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { Eye } from 'lucide-vue-next';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';

const sessionStore = useKangourouSessionStore();

const statusLabels = { active: 'Active', expired: 'Expirée', draft: 'Brouillon' };
function statusLabel(status) {
  return statusLabels[status] || status;
}

function formatExpiry(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
}

onMounted(() => {
  sessionStore.fetchMySessions();
});
</script>
