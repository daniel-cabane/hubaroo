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

    <div v-else-if="!sessionStore.mySessions.length" class="text-text-muted">
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
          <div class="flex items-center gap-2 flex-wrap mb-0.5">
            <p class="font-medium text-text-main dark:text-surface">{{ session.paper?.title }}</p>
            <!-- Privacy tag -->
            <span
              class="text-xs font-medium px-2 py-0.5 rounded-full"
              :class="session.privacy === 'private' ? 'bg-warning/15 text-warning' : 'bg-primary/10 text-primary'"
            >
              {{ session.privacy === 'private' ? 'Privée' : 'Publique' }}
            </span>
          </div>

          <p v-if="session.expires_at" class="text-sm text-text-muted mt-0.5">
            Expire {{ formatExpiry(session.expires_at) }}
          </p>

          <!-- Division chips for private sessions -->
          <div v-if="session.privacy === 'private' && session.divisions?.length" class="flex flex-wrap gap-1 mt-1.5">
            <span
              v-for="division in session.divisions"
              :key="division.id"
              class="text-xs px-2 py-0.5 rounded-full bg-surface dark:bg-gray-800 border border-border text-text-muted"
            >
              {{ division.name }}
            </span>
          </div>
        </div>

        <div class="font-mono text-xl">
          {{ session.code }}
        </div>
        
        <div class="flex justify-end items-center gap-4 flex-1 ml-4">
          <!-- Attempt count -->
          <div class="text-center">
            <p class="text-lg font-bold text-text-main dark:text-surface leading-none">{{ session.attempts_count ?? 0 }}</p>
            <p class="text-xs text-text-muted">tentative{{ (session.attempts_count ?? 0) !== 1 ? 's' : '' }}</p>
          </div>

          <!-- Status -->
          <span :class="session.status === 'active' ? 'text-success font-medium' : 'text-text-muted'">{{ statusLabel(session.status) }}</span>

          <button
            @click.stop.prevent="() => { sessionIdToDelete = session.id; showDeleteConfirm = true; }"
            :disabled="session.status === 'active'"
            class="w-10 h-10 rounded-full flex items-center justify-center bg-error/10 hover:bg-error/20 text-error transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            title="Supprimer"
          >
            <Trash class="w-5 h-5" />
          </button>
        </div>
      </router-link>
    </div>

    <!-- Pagination -->
    <div v-if="sessionStore.mySessionsMeta && sessionStore.mySessionsMeta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
      <button
        @click="goToPage(currentPage - 1)"
        :disabled="currentPage <= 1 || sessionStore.isLoading"
        class="px-3 py-1.5 rounded-lg border border-border text-sm text-text-muted hover:border-primary hover:text-primary transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
      >
        ←
      </button>

      <button
        v-for="page in pageNumbers"
        :key="page"
        @click="goToPage(page)"
        :disabled="sessionStore.isLoading"
        class="px-3 py-1.5 rounded-lg border text-sm transition-colors disabled:cursor-not-allowed"
        :class="page === currentPage ? 'border-primary bg-primary/10 text-primary font-medium' : 'border-border text-text-muted hover:border-primary hover:text-primary'"
      >
        {{ page }}
      </button>

      <button
        @click="goToPage(currentPage + 1)"
        :disabled="currentPage >= sessionStore.mySessionsMeta.last_page || sessionStore.isLoading"
        class="px-3 py-1.5 rounded-lg border border-border text-sm text-text-muted hover:border-primary hover:text-primary transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
      >
        →
      </button>
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
import { ref, computed, onMounted } from 'vue';
import { Trash } from 'lucide-vue-next';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';

const sessionStore = useKangourouSessionStore();

const showDeleteConfirm = ref(false);
const sessionIdToDelete = ref(null);
const currentPage = ref(1);

const statusLabels = { active: 'Active', expired: 'Expirée', draft: 'Brouillon' };
function statusLabel(status) {
  return statusLabels[status] || status;
}

function formatExpiry(dateStr) {
  const d = new Date(dateStr);
  const isCurrentYear = d.getFullYear() === new Date().getFullYear();
  const datePart = isCurrentYear
    ? d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })
    : d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: '2-digit' });
  const timePart = d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', hour12: false }).replace(':', 'h');
  return `le ${datePart} à ${timePart}`;
}

const pageNumbers = computed(() => {
  const meta = sessionStore.mySessionsMeta;
  if (!meta) return [];
  const total = meta.last_page;
  const current = currentPage.value;
  const pages = [];
  for (let i = Math.max(1, current - 2); i <= Math.min(total, current + 2); i++) {
    pages.push(i);
  }
  return pages;
});

async function goToPage(page) {
  if (page < 1 || page > (sessionStore.mySessionsMeta?.last_page ?? 1)) return;
  currentPage.value = page;
  await sessionStore.fetchMySessions(page);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function handleDeleteSession() {
  try {
    await sessionStore.deleteSession(sessionIdToDelete.value);
    showDeleteConfirm.value = false;
    sessionIdToDelete.value = null;
    // Reload current page (may shift if last item on page)
    const meta = sessionStore.mySessionsMeta;
    if (meta && sessionStore.mySessions.length === 0 && currentPage.value > 1) {
      await goToPage(currentPage.value - 1);
    } else {
      await sessionStore.fetchMySessions(currentPage.value);
    }
  } catch (err) {
    // Error is handled by the store
  }
}

onMounted(() => {
  sessionStore.fetchMySessions(1);
});
</script>
