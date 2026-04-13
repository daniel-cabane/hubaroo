<template>
  <div class="min-h-screen flex flex-col">
    <header class="bg-primary text-surface p-4 shadow-md">
      <div class="container mx-auto flex items-center justify-between">
        <h1 class="text-xl font-bold">
          <router-link to="/" class="hover:text-surface/80 transition-colors">Hubaroo</router-link>
        </h1>
        <nav class="flex space-x-4 items-center">
          <!-- Active attempt banner -->
          <div
            v-if="attemptStore.activeRecovery"
            class="flex items-center gap-2 bg-surface/20 rounded-lg px-3 py-1"
          >
            <span class="text-sm font-medium">Active attempt</span>
            <button
              @click="resumeStoredAttempt"
              class="text-sm font-bold underline hover:text-surface/80 transition-colors"
            >
              Resume
            </button>
            <button
              @click="attemptStore.dismissRecovery()"
              class="text-sm text-surface/60 hover:text-surface/80 transition-colors"
            >
              Dismiss
            </button>
          </div>

          <!-- Account menu -->
          <div class="relative" ref="menuContainer">
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
                  <p class="text-sm font-medium text-text-main truncate">{{ authStore.user?.name || 'Account' }}</p>
                  <p class="text-xs text-text-muted truncate">{{ authStore.user?.email }}</p>
                </div>
                <router-link
                  to="/my/sessions"
                  @click="showAccountMenu = false"
                  class="block px-4 py-2 text-sm text-text-main hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  My Sessions
                </router-link>
                <router-link
                  to="/my/attempts"
                  @click="showAccountMenu = false"
                  class="block px-4 py-2 text-sm text-text-main hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  My Attempts
                </router-link>
                <div class="border-t border-border">
                  <button
                    @click="handleLogout"
                    class="w-full text-left px-4 py-2 text-sm text-error hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                  >
                    Logout
                  </button>
                </div>
              </template>
              <template v-else>
                <router-link
                  to="/login"
                  @click="showAccountMenu = false"
                  class="block px-4 py-2 text-sm text-text-main hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  Login
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
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { CircleUserRound } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/authStore';
import { useAttemptStore } from '@/stores/attemptStore';

const router = useRouter();
const authStore = useAuthStore();
const attemptStore = useAttemptStore();
const showAccountMenu = ref(false);
const menuContainer = ref(null);

function handleClickOutside(e) {
  if (menuContainer.value && !menuContainer.value.contains(e.target)) {
    showAccountMenu.value = false;
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

async function handleLogout() {
  try {
    await authStore.logout();
    router.push('/login');
  } catch (error) {
    console.error('Logout failed:', error);
  }
}
</script>