import { createRouter, createWebHistory } from "vue-router";
import Home from "./components/views/home-view.vue";
import LoginView from "./components/views/login-view.vue";
import RegisterView from "./components/views/register-view.vue";
import ForgotPasswordView from "./components/views/forgot-password-view.vue";
import ResetPasswordView from "./components/views/reset-password-view.vue";
import CreateSessionView from "./components/views/create-session-view.vue";
import JoinSessionView from "./components/views/join-session-view.vue";
import SessionView from "./components/views/session-view.vue";
import SessionDetailsView from "./components/views/session-details-view.vue";
import AttemptView from "./components/views/attempt-view.vue";
import ResultsView from "./components/views/results-view.vue";
import MySessionsView from "./components/views/my-sessions-view.vue";
import MyAttemptsView from "./components/views/my-attempts-view.vue";
import MyDivisionsView from "./components/views/my-divisions-view.vue";
import DivisionDetailsView from "./components/views/division-details-view.vue";
import PaperView from "./components/views/paper-view.vue";
import AdminView from "./components/views/admin-view.vue";
import PrivacyPolicyView from "./components/views/privacy-policy-view.vue";
import TermsOfServiceView from "./components/views/terms-of-service-view.vue";
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
        path: "/register",
        name: "Register",
        component: RegisterView,
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
        meta: { requiresAuth: true }
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
        path: "/my/sessions/:id",
        name: "SessionDetails",
        component: SessionDetailsView,
        meta: { requiresAuth: true }
    },
    {
        path: "/my/attempts",
        name: "MyAttempts",
        component: MyAttemptsView,
        meta: { requiresAuth: true }
    },
    {
        path: "/my/divisions",
        name: "MyDivisions",
        component: MyDivisionsView,
        meta: { requiresAuth: true }
    },
    {
        path: "/my/divisions/:id",
        name: "DivisionDetails",
        component: DivisionDetailsView,
        meta: { requiresAuth: true }
    },
    {
        path: "/papers",
        name: "Papers",
        component: PaperView,
        meta: { requiresAuth: true }
    },
    {
        path: "/admin",
        name: "Admin",
        component: AdminView,
        meta: { requiresAuth: true, requiresAdmin: true }
    },
    {
        path: "/terms/privacy",
        name: "PrivacyPolicy",
        component: PrivacyPolicyView,
    },
    {
        path: "/terms/service",
        name: "TermsOfService",
        component: TermsOfServiceView,
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
    } else if (to.meta.requiresAdmin && !authStore.user?.is_admin) {
        return '/';
    }
});

export default router;