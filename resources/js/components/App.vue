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

          <!-- Account menu -->
          <div v-if="!isAttemptView" class="relative" ref="menuContainer">
            <button
              @click="showAccountMenu = !showAccountMenu"
              class="w-9 h-9 rounded-full flex items-center justify-center hover:bg-surface/20 transition-colors"
            >
              <CircleUserRound class="w-6 h-6" />
            </button>

            <div
              v-if="showAccountMenu"
              class="absolute right-0 mt-2 w-56 bg-surface dark:bg-gray-900 rounded-lg shadow-xl border border-border py-1 z-50"
            >
              <template v-if="authStore.isAuthenticated">
                <div class="px-4 py-2 border-b border-border">
                  <p class="text-sm font-medium text-text-main truncate">{{ authStore.user?.name || 'Compte' }}</p>
                  <p class="text-xs text-text-muted truncate">{{ authStore.user?.email }}</p>
                </div>
                <router-link
                  to="/my/sessions"
                  @click="showAccountMenu = false"
                  class="block px-4 py-2 text-sm text-text-main hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  Mes sessions
                </router-link>
                <router-link
                  to="/my/attempts"
                  @click="showAccountMenu = false"
                  class="block px-4 py-2 text-sm text-text-main hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  Mes tentatives
                </router-link>
                <div class="border-t border-border">
                  <button
                    @click="handleLogout"
                    class="w-full text-left px-4 py-2 text-sm text-error hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                  >
                    Déconnexion
                  </button>
                </div>
              </template>
              <template v-else>
                <router-link
                  to="/login"
                  @click="showAccountMenu = false"
                  class="block px-4 py-2 text-sm text-text-main hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  Connexion
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { CircleUserRound, ChevronDown } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/authStore';
import { useAttemptStore } from '@/stores/attemptStore';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const attemptStore = useAttemptStore();
const showAccountMenu = ref(false);
const showBannerMenu = ref(false);
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
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

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

async function handleLogout() {
  try {
    await authStore.logout();
    router.push('/login');
  } catch (error) {
    console.error('Logout failed:', error);
  }
}
</script>