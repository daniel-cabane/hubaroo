<template>
  <div
    v-if="visible"
    class="mb-6 space-y-4"
  >
          <div v-if="pocketStore.isLoading && !pocketStore.pocket" class="text-sm text-text-muted">Chargement...</div>
          <div v-if="pocketStore.error" class="flex items-center justify-between gap-3 bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg text-sm">
            <span>Impossible de charger la poche.</span>
            <button @click="retry" class="px-3 py-1 rounded-lg bg-surface text-text-main text-xs font-medium">Réessayer</button>
          </div>

          <div v-if="pocketStore.invites.length" class="space-y-2">
            <h4 class="text-xs font-medium text-text-muted uppercase tracking-wide">Invitations de classe</h4>
            <div v-for="invite in pocketStore.invites" :key="invite.id" class="flex items-center justify-between p-3 bg-surface dark:bg-gray-900 rounded-lg border border-secondary/20">
              <div>
                <p class="font-medium text-sm text-text-main dark:text-surface">{{ invite.division?.name }}</p>
                <p class="text-xs text-text-muted">Invitation à rejoindre la classe</p>
              </div>
              <div class="flex gap-2">
                <button @click="openInviteModal(invite.id)" class="px-2 py-1 rounded-lg bg-success hover:bg-success/80 text-white text-xs font-medium transition-colors">Accepter</button>
                <button @click="decline(invite.id)" class="px-2 py-1 rounded-lg bg-error/20 hover:bg-error/30 text-error text-xs font-medium transition-colors">Refuser</button>
              </div>
            </div>
          </div>

          <div v-if="pocketStore.classes.length" class="space-y-4">
            <div class="flex flex-wrap gap-3 pt-4 pr-2">
              <button
                v-for="division in pocketStore.classes"
                :key="division.id"
                :ref="(el) => setButton(division.id, el)"
                type="button"
                class="relative px-4 py-3 rounded-xl border-2 bg-surface dark:bg-gray-900 text-sm font-semibold transition-colors"
                :class="openClassId === division.id ? 'border-primary text-primary' : 'border-border text-text-main dark:text-surface hover:border-primary'"
                :aria-expanded="openClassId === division.id"
                :aria-controls="openClassId === division.id ? 'pocket-panel' : undefined"
                @click="toggle(division.id)"
              >
                {{ division.name }}
                <span
                  v-if="badgeCount(division) > 0"
                  class="absolute -top-2 -right-2 inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-primary text-white text-xs motion-safe:animate-badge-shadow"
                >{{ badgeCount(division) }}</span>
              </button>
            </div>

            <div
              v-if="openDivision"
              id="pocket-panel"
              :ref="setPanel"
              class="relative rounded-xl border-2 border-primary bg-surface dark:bg-gray-900 p-4 pr-12"
            >
                <button
                  type="button"
                  class="absolute top-3 right-3 text-text-muted hover:text-text-main transition-colors cursor-pointer"
                  aria-label="Fermer"
                  @click="openClassId = null"
                >
                  <X class="w-5 h-5" />
                </button>
                <span
                  class="absolute -top-2 h-3 w-3 rotate-45 border-l-2 border-t-2 border-primary bg-surface dark:bg-gray-900"
                  :style="{ left: arrowLeft + 'px' }"
                />

                <p v-if="isEmpty(openDivision)" class="text-sm text-text-muted">Rien à signaler pour cette classe.</p>
                <div v-else class="space-y-5">
                  <section v-if="visibleJumps(openDivision).length">
                    <h4 class="text-xs font-medium text-text-muted uppercase tracking-wide mb-2">Sauts ouverts</h4>
                    <div class="space-y-3">
                      <template v-for="jump in visibleJumps(openDivision)" :key="jump.id">
                        <router-link
                          v-if="isTeacher"
                          :to="jumpRoute(openDivision, jump)"
                          class="block rounded-xl border border-success/30 bg-surface p-3 hover:border-success dark:bg-gray-900"
                        >
                          <p class="font-medium text-text-main dark:text-surface">{{ jump.course_title }}</p>
                          <p class="text-xs text-text-muted">{{ jumpMeta(jump) }}</p>
                        </router-link>
                        <div
                          v-else
                          class="flex items-center justify-between gap-4 rounded-xl border border-border bg-surface p-4 shadow-2xl motion-safe:animate-levitate dark:bg-gray-900"
                        >
                          <div class="min-w-0">
                            <p class="text-lg font-bold text-text-main dark:text-surface">{{ jump.course_title }}</p>
                            <p class="text-sm text-text-muted">{{ jumpMeta(jump) }}</p>
                          </div>
                          <router-link
                            :to="jumpRoute(openDivision, jump)"
                            class="relative shrink-0 overflow-hidden rounded-xl bg-success px-5 py-4 text-lg font-bold text-white"
                          >
                            <span class="pointer-events-none absolute inset-y-0 left-0 w-1/3 -skew-x-12 bg-linear-to-r from-transparent via-white/50 to-transparent motion-safe:animate-swipe" aria-hidden="true" />
                            <span class="relative">{{ jumpLabel(jump) }}</span>
                          </router-link>
                        </div>
                      </template>
                    </div>
                  </section>

                  <section v-if="visibleSessions(openDivision).length">
                    <h4 class="text-xs font-medium text-text-muted uppercase tracking-wide mb-2">Sessions ouvertes</h4>
                    <div class="space-y-3">
                      <template v-for="session in visibleSessions(openDivision)" :key="session.id">
                        <router-link
                          v-if="isTeacher"
                          :to="sessionRoute(openDivision, session)"
                          class="block rounded-xl border border-primary/30 bg-surface p-3 hover:border-primary dark:bg-gray-900"
                        >
                          <p class="text-xs text-text-muted">Session ouverte</p>
                          <p class="font-medium text-text-main dark:text-surface">{{ session.paper_title }}</p>
                          <p class="text-xs text-text-muted">{{ sessionLabel(session) }} · {{ countLabel(session.attempts_count) }}</p>
                        </router-link>
                        <div
                          v-else
                          class="flex items-center justify-between gap-4 rounded-xl border border-border bg-surface p-4 shadow-2xl motion-safe:animate-levitate dark:bg-gray-900"
                        >
                          <div class="min-w-0">
                            <p class="text-lg font-bold text-text-main dark:text-surface">Nouvelle session</p>
                            <p class="text-sm text-text-muted">{{ sessionMeta(session) }}</p>
                          </div>
                          <router-link
                            :to="sessionRoute(openDivision, session)"
                            class="relative shrink-0 overflow-hidden rounded-xl bg-primary px-5 py-4 text-lg font-bold text-white"
                          >
                            <span class="pointer-events-none absolute inset-y-0 left-0 w-1/3 -skew-x-12 bg-linear-to-r from-transparent via-white/50 to-transparent motion-safe:animate-swipe" aria-hidden="true" />
                            <span class="relative">{{ sessionLabel(session) }}</span>
                          </router-link>
                        </div>
                      </template>
                    </div>
                  </section>

                  <section v-if="openDivision.fresh_grades?.length">
                    <h4 class="text-xs font-medium text-text-muted uppercase tracking-wide mb-2">{{ isTeacher ? 'Vient de se terminer' : 'Vient d\'être noté' }}</h4>
                    <div class="space-y-2">
                      <button
                        v-for="grade in openDivision.fresh_grades"
                        :key="grade.attempt_id || grade.jump_id"
                        type="button"
                        class="w-full text-left p-3 rounded-lg border border-border hover:border-primary"
                        @click="openFresh(openDivision, grade)"
                      >
                        {{ grade.course_title }} · Saut {{ grade.number }}<span v-if="!isTeacher"> · {{ grade.score }}</span>
                      </button>
                    </div>
                  </section>

                  <div
                    v-if="openDivision.active_course || openDivision.open_questions?.length"
                    class="grid grid-cols-1 gap-5 lg:grid-cols-2"
                  >
                    <section v-if="openDivision.active_course" :class="{ 'lg:col-span-2': !openDivision.open_questions?.length }">
                      <div class="flex items-center gap-2 mb-2">
                        <h4 class="font-semibold text-text-main dark:text-surface">{{ openDivision.active_course.title }}</h4>
                        <span v-if="!isTeacher" class="w-px shrink-0" aria-hidden="true" >·</span>
                        <p v-if="!isTeacher" class="font-semibold text-text-main dark:text-surface">{{ openDivision.active_course.score }} pts</p>
                      </div>
                      <div v-if="openDivision.active_course.recent_jumps?.length" class="flex flex-wrap gap-2">
                        <button
                          v-for="jump in openDivision.active_course.recent_jumps"
                          :key="jump.id"
                          type="button"
                          class="px-3 py-2 rounded-lg border border-border hover:border-primary text-left"
                          @click="openRecap(openDivision, jump)"
                        >
                          <span class="block text-sm font-medium">Saut {{ jump.number }}</span>
                          <span class="block text-xs text-text-muted">{{ formatDate(jump.created_at) }}</span>
                          <span class="block text-xs">{{ isTeacher ? gradedLabel(jump.graded_attempts_count) : jump.score }} pts</span>
                        </button>
                      </div>
                      <p v-else class="text-sm text-text-muted">Aucun saut terminé pour l'instant.</p>
                    </section>

                    <section v-if="openDivision.open_questions?.length" :class="{ 'lg:col-span-2': !openDivision.active_course }">
                      <h4 class="text-xs font-medium text-text-muted uppercase tracking-wide mb-2 flex items-center gap-1">
                        <Lightbulb class="w-3 h-3" />
                        Questions à revoir
                      </h4>
                      <div class="space-y-2">
                        <button
                          v-for="question in openDivision.open_questions"
                          :key="question.id"
                          type="button"
                          class="w-full flex items-center justify-between p-3 rounded-lg border border-warning/30 hover:border-warning"
                          @click="openQuestion(openDivision, question)"
                        >
                          <span class="flex items-center gap-1">
                            <Star v-for="n in question.level" :key="n" class="w-4 h-4 fill-primary text-warning" />
                            Question niveau {{ question.level }}
                          </span>
                          <span class="text-xs text-text-muted truncate ml-2">{{ question.course_title }}</span>
                        </button>
                      </div>
                    </section>
                  </div>

                  <section v-if="openDivision.recent_sessions?.length">
                    <h4 class="text-xs font-medium text-text-muted uppercase tracking-wide mb-2">Sessions récentes</h4>
                    <div class="space-y-2">
                      <router-link
                        v-for="session in openDivision.recent_sessions"
                        :key="session.id"
                        :to="recentSessionRoute(openDivision, session)"
                        class="block p-3 rounded-lg border border-border hover:border-primary"
                      >
                        <p class="font-medium text-sm">{{ session.paper_title }}</p>
                        <p class="text-xs text-text-muted">
                          {{ formatDate(session.revealed_at || session.expires_at) }}
                          <span v-if="!isTeacher"> · {{ session.score }}</span>
                          <span v-else> · {{ countLabel(session.attempts_count) }}</span>
                          <span v-if="session.fresh" class="text-primary"> · Nouveau</span>
                        </p>
                      </router-link>
                    </div>
                  </section>

                  <div class="flex justify-end">
                    <router-link
                      :to="{ name: 'DivisionDetails', params: { id: openDivision.id } }"
                      class="inline-flex items-center rounded-lg bg-primary/10 px-4 py-2 text-sm font-medium text-primary transition-colors hover:bg-primary/20"
                    >
                      Voir la classe
                    </router-link>
                  </div>
                </div>
            </div>
          </div>
  </div>

  <div
    v-if="showInviteModal"
    class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
    @click.self="showInviteModal = false"
  >
    <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-md">
      <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-4">Accepter l'invitation</h3>
      <form @submit.prevent="acceptInvite" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Prénom</label>
            <input v-model="inviteFirstName" type="text" required class="w-full px-4 py-2 border border-border rounded-lg dark:bg-gray-800 dark:text-surface" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Nom</label>
            <input v-model="inviteLastName" type="text" required class="w-full px-4 py-2 border border-border rounded-lg dark:bg-gray-800 dark:text-surface" />
          </div>
        </div>
        <div v-if="divisionStore.error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg text-sm">{{ divisionStore.error }}</div>
        <div class="flex justify-end gap-2">
          <button type="button" @click="showInviteModal = false" class="px-4 py-2 text-sm text-text-muted">Annuler</button>
          <button type="submit" class="px-4 py-2 bg-primary text-surface rounded-lg text-sm font-medium">Accepter</button>
        </div>
      </form>
    </div>
  </div>

  <div
    v-if="selectedQuestion"
    class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center"
    @click.self="selectedQuestion = null"
  >
    <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-2xl p-6 w-full max-w-[80vw] flex flex-col items-center gap-4">
      <div class="flex w-full justify-between items-center">
        <div>
          <div class="flex items-center gap-1">
            <div class="text-2xl font-bold mr-2">Question à revoir</div>
            <Star v-for="n in selectedQuestion.level" :key="n" class="w-6 h-6 fill-warning text-warning" />
          </div>
          <div class="text-xl text-text-muted">{{ selectedQuestion.division_name }} · {{ selectedQuestion.course_title }}</div>
        </div>
        <button @click="selectedQuestion = null" class="text-text-muted hover:text-text-main cursor-pointer"><X class="w-5 h-5" /></button>
      </div>
      <img v-if="selectedQuestion.question?.image" :src="'/' + selectedQuestion.question.image" class="max-h-64 object-contain select-none pointer-events-none" />
    </div>
  </div>

  <SuggestedQuestionOverlay
    v-if="managedQuestion"
    :question="managedQuestion"
    @close="managedQuestion = null"
    @updated="pocketStore.fetchPocket({ quiet: true })"
    @deleted="pocketStore.fetchPocket({ quiet: true })"
  />

  <JumpAttemptDetailModal
    :open="jumpModal.open"
    :attempt-id="jumpModal.attemptId"
    :number="jumpModal.number"
    :date="jumpModal.date"
    @close="jumpModal.open = false"
  />
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { Lightbulb, Star, X } from 'lucide-vue-next';
import axios from 'axios';
import { useAuthStore } from '@/stores/authStore';
import { useDivisionStore } from '@/stores/divisionStore';
import { usePocketStore } from '@/stores/pocketStore';
import JumpAttemptDetailModal from '@/components/JumpAttemptDetailModal.vue';
import SuggestedQuestionOverlay from '@/components/SuggestedQuestionOverlay.vue';

const authStore = useAuthStore();
const divisionStore = useDivisionStore();
const pocketStore = usePocketStore();
const router = useRouter();

const openClassId = ref(null);
const initialized = ref(false);
const seen = ref({});
const nowMs = ref(Date.now());
const arrowLeft = ref(24);
const buttonRefs = new Map();
const panelRef = ref(null);
const showInviteModal = ref(false);
const pendingInviteId = ref(null);
const inviteFirstName = ref('');
const inviteLastName = ref('');
const selectedQuestion = ref(null);
const managedQuestion = ref(null);
const jumpModal = reactive({ open: false, attemptId: null, number: '', date: '' });

let clock = null;

const isTeacher = computed(() => pocketStore.viewer === 'teacher');

const openDivision = computed(() => pocketStore.classes.find(division => division.id === openClassId.value) ?? null);

const visible = computed(() => {
  if (pocketStore.isLoading && !pocketStore.pocket) {
    return true;
  }
  if (pocketStore.error) {
    return true;
  }
  return pocketStore.classes.length > 0 || pocketStore.invites.length > 0;
});

function isStillOpen(iso) {
  if (!iso) {
    return true;
  }
  return new Date(iso).getTime() > nowMs.value;
}

function visibleJumps(division) {
  return (division.open_jumps ?? []).filter(jump => isStillOpen(jump.expiration));
}

function visibleSessions(division) {
  return (division.open_sessions ?? []).filter(session => isStillOpen(session.expires_at));
}

function badgeCount(division) {
  return visibleJumps(division).length + visibleSessions(division).length;
}

function isEmpty(division) {
  return !division.active_course
    && visibleJumps(division).length === 0
    && visibleSessions(division).length === 0
    && !(division.fresh_grades?.length)
    && !(division.recent_sessions?.length)
    && !(division.open_questions?.length);
}

function countLabel(count) {
  return `${count} tentative${count === 1 ? '' : 's'}`;
}

function gradedLabel(count) {
  return count === 1 ? '1 tentative notée' : `${count} tentatives notées`;
}

function jumpLabel(jump) {
  if (isTeacher.value) {
    return 'Saut en cours';
  }
  return jump.action === 'resume' ? 'Reprendre le saut' : 'Commencer le saut';
}

function jumpMeta(jump) {
  const base = `${jump.nb_questions} questions · ${jump.time} min`;
  return isTeacher.value ? `${base} · ${countLabel(jump.attempts_count)}` : base;
}

function sessionMeta(session) {
  if (!session.expires_at) {
    return 'Session ouverte';
  }
  const diffMins = Math.max(0, Math.floor((new Date(session.expires_at).getTime() - nowMs.value) / 60000));
  if (diffMins < 60) {
    return `Expire dans ${diffMins} min`;
  }
  const hours = Math.floor(diffMins / 60);
  const mins = diffMins % 60;
  return mins ? `Expire dans ${hours} h ${mins} min` : `Expire dans ${hours} h`;
}

function sessionLabel(session) {
  if (!isTeacher.value) {
    return session.action === 'resume' ? 'Reprendre la session' : 'Rejoindre la session';
  }
  return session.is_author ? 'Suivre la session' : 'Voir la classe';
}

function jumpRoute(division, jump) {
  if (isTeacher.value) {
    return { name: 'CourseDetails', params: { id: division.id, courseId: jump.course_id } };
  }
  return { name: 'JumpAttempt', params: { jumpId: jump.id } };
}

function sessionRoute(division, session) {
  if (!isTeacher.value) {
    if (session.action === 'resume') {
      return { name: 'Attempt', params: { code: session.code, attemptId: session.attempt_id } };
    }
    return { name: 'Session', params: { code: session.code } };
  }
  if (session.is_author) {
    return { name: 'SessionDetails', params: { id: session.id } };
  }
  return { name: 'DivisionDetails', params: { id: division.id } };
}

function recentSessionRoute(division, session) {
  if (isTeacher.value) {
    return { name: 'DivisionDetails', params: { id: division.id } };
  }
  return { name: 'Results', params: { code: session.code, attemptId: session.attempt_id } };
}

function formatDate(value) {
  if (!value) {
    return '';
  }
  return new Date(value).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}

function pick(list) {
  return [...list].filter(division => division.priority != null).sort((a, b) => {
    if (a.priority !== b.priority) {
      return a.priority - b.priority;
    }
    const at = a.signal_at ? Date.parse(a.signal_at) : 0;
    const bt = b.signal_at ? Date.parse(b.signal_at) : 0;
    if (at !== bt) {
      return bt - at;
    }
    const name = a.name.localeCompare(b.name, 'fr', { sensitivity: 'base' });
    return name !== 0 ? name : a.id - b.id;
  })[0] ?? null;
}

function snapshot(list) {
  const next = {};
  for (const division of list) {
    next[division.id] = {
      jumps: (division.open_jumps ?? []).map(jump => jump.id),
      sessions: (division.open_sessions ?? []).map(session => session.id),
    };
  }
  return next;
}

function setButton(id, el) {
  if (el) {
    buttonRefs.set(id, el);
  } else {
    buttonRefs.delete(id);
  }
}

function setPanel(el) {
  panelRef.value = el;
  if (el) {
    nextTick(placeArrow);
  }
}

function placeArrow() {
  const button = buttonRefs.get(openClassId.value);
  const panel = panelRef.value;
  if (!button || !panel) {
    return;
  }
  const buttonBox = button.getBoundingClientRect();
  const panelBox = panel.getBoundingClientRect();
  arrowLeft.value = Math.max(12, buttonBox.left + buttonBox.width / 2 - panelBox.left - 6);
}

function toggle(id) {
  openClassId.value = openClassId.value === id ? null : id;
  nextTick(placeArrow);
}

function openRecap(division, jump) {
  if (isTeacher.value) {
    router.push({ name: 'CourseDetails', params: { id: division.id, courseId: jump.course_id } });
    return;
  }
  jumpModal.attemptId = jump.attempt_id;
  jumpModal.number = jump.number;
  jumpModal.date = formatDate(jump.created_at);
  jumpModal.open = true;
}

function openFresh(division, grade) {
  if (isTeacher.value) {
    router.push({ name: 'CourseDetails', params: { id: division.id, courseId: grade.course_id } });
    return;
  }
  jumpModal.attemptId = grade.attempt_id;
  jumpModal.number = grade.number;
  jumpModal.date = formatDate(grade.created_at);
  jumpModal.open = true;
}

async function openQuestion(division, question) {
  if (!isTeacher.value) {
    managedQuestion.value = null;
    selectedQuestion.value = { ...question, division_name: division.name };
    return;
  }

  selectedQuestion.value = null;
  try {
    const response = await axios.get(`/api/courses/${question.course_id}/suggested-questions`);
    managedQuestion.value = (response.data.suggested_questions ?? []).find(item => item.id === question.id) ?? null;
  } catch {
    managedQuestion.value = null;
  }
}

function openInviteModal(inviteId) {
  pendingInviteId.value = inviteId;
  inviteFirstName.value = '';
  inviteLastName.value = '';
  divisionStore.clearError();
  showInviteModal.value = true;
}

async function acceptInvite() {
  await divisionStore.acceptInvite(pendingInviteId.value, inviteFirstName.value, inviteLastName.value);
  if (!divisionStore.error) {
    showInviteModal.value = false;
    await pocketStore.fetchPocket({ quiet: true });
  }
}

async function decline(inviteId) {
  await divisionStore.declineInvite(inviteId);
  if (!divisionStore.error) {
    await pocketStore.fetchPocket({ quiet: true });
  }
}

async function retry() {
  await pocketStore.fetchPocket();
}

watch(() => pocketStore.pocket, (value) => {
  const list = value?.classes ?? [];
  if (!value) {
    return;
  }
  if (!initialized.value) {
    openClassId.value = pick(list)?.id ?? null;
    initialized.value = true;
    seen.value = snapshot(list);
    nextTick(placeArrow);
    return;
  }
  const gained = list.filter((division) => {
    const previous = seen.value[division.id] ?? { jumps: [], sessions: [] };
    const jumps = (division.open_jumps ?? []).map(jump => jump.id);
    const sessions = (division.open_sessions ?? []).map(session => session.id);
    return jumps.some(id => !previous.jumps.includes(id)) || sessions.some(id => !previous.sessions.includes(id));
  });
  if (gained.length) {
    const next = pick(gained);
    if (next) {
      openClassId.value = next.id;
    }
  } else if (openClassId.value && !list.some(division => division.id === openClassId.value)) {
    openClassId.value = null;
  }
  seen.value = snapshot(list);
  nextTick(placeArrow);
});

onMounted(async () => {
  if (!authStore.isAuthenticated) {
    return;
  }
  clock = setInterval(() => {
    nowMs.value = Date.now();
  }, 30000);
  window.addEventListener('resize', placeArrow);
  if (authStore.user?.id) {
    pocketStore.subscribeUser(authStore.user.id);
  }
  try {
    await pocketStore.fetchPocket();
  } catch {
    // The shell shows the retry state.
  }
});

onUnmounted(() => {
  clearInterval(clock);
  window.removeEventListener('resize', placeArrow);
  pocketStore.leaveAll();
});
</script>
