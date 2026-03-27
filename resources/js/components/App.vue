<template>
  <div class="min-h-screen flex flex-col">
    <header class="bg-primary text-surface p-4 shadow-md">
      <div class="container mx-auto flex items-center justify-between">
        <h1 class="text-xl font-bold">Hubaroo</h1>
        <nav class="flex space-x-4 items-center">
          <template v-if="authStore.isAuthenticated">
            <span class="text-sm text-surface/80">{{ authStore.user?.name || authStore.user?.email }}</span>
            <button
              @click="handleLogout"
              class="text-sm hover:text-surface/80 transition-colors"
            >
              Logout
            </button>
          </template>
          <template v-else>
            <router-link to="/login" class="text-sm hover:text-surface/80 transition-colors">
              Login
            </router-link>
          </template>
        </nav>
      </div>
    </header>
    <main class="flex-1">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';

const router = useRouter();
const authStore = useAuthStore();

async function handleLogout() {
  try {
    await authStore.logout();
    router.push('/login');
  } catch (error) {
    console.error('Logout failed:', error);
  }
}
</script>