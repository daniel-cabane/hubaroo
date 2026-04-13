import { createRouter, createWebHistory } from "vue-router";
import Home from "./components/views/home-view.vue";
import LoginView from "./components/views/login-view.vue";
import ForgotPasswordView from "./components/views/forgot-password-view.vue";
import ResetPasswordView from "./components/views/reset-password-view.vue";
import CreateSessionView from "./components/views/create-session-view.vue";
import JoinSessionView from "./components/views/join-session-view.vue";
import SessionView from "./components/views/session-view.vue";
import AttemptView from "./components/views/attempt-view.vue";
import ResultsView from "./components/views/results-view.vue";
import MySessionsView from "./components/views/my-sessions-view.vue";
import MyAttemptsView from "./components/views/my-attempts-view.vue";
import { useAuthStore } from "./stores/authStore";

const routes = [
    {
        path: "/",
        name: "Home",
        component: Home,
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
    {
        path: "/kangourou/create",
        name: "CreateSession",
        component: CreateSessionView,
    },
    {
        path: "/kangourou/join",
        name: "JoinSession",
        component: JoinSessionView,
    },
    {
        path: "/kangourou/:code",
        name: "Session",
        component: SessionView,
    },
    {
        path: "/kangourou/:code/attempt/:attemptId",
        name: "Attempt",
        component: AttemptView,
    },
    {
        path: "/kangourou/:code/results/:attemptId",
        name: "Results",
        component: ResultsView,
    },
    {
        path: "/my/sessions",
        name: "MySessions",
        component: MySessionsView,
        meta: { requiresAuth: true }
    },
    {
        path: "/my/attempts",
        name: "MyAttempts",
        component: MyAttemptsView,
        meta: { requiresAuth: true }
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