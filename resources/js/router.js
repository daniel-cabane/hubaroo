import { createRouter, createWebHistory } from "vue-router";
import Home from "./components/views/home-view.vue";
import LoginView from "./components/views/login-view.vue";
import ForgotPasswordView from "./components/views/forgot-password-view.vue";
import ResetPasswordView from "./components/views/reset-password-view.vue";
import { useAuthStore } from "./stores/authStore";

const routes = [
    {
        path: "/",
        name: "Home",
        component: Home,
        meta: { requiresAuth: true }
    },
    {
        path: "/login",
        name: "Login",
        component: LoginView,
        meta: { requiresGuest: true }
    },
    {
        path: "/forgot-password",
        name: "ForgotPassword",
        component: ForgotPasswordView,
        meta: { requiresGuest: true }
    },
    {
        path: "/password/reset/:token",
        name: "ResetPassword",
        component: ResetPasswordView,
        meta: { requiresGuest: true }
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, from) => {
    const authStore = useAuthStore();

    // Check if user is authenticated on initial load
    if (!authStore.user && !authStore.isLoading) {
        await authStore.checkAuth();
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return '/login';
    } else if (to.meta.requiresGuest && authStore.isAuthenticated) {
        return '/';
    }
});

export default router;