<template>
  <div class="min-h-[calc(100vh-64px)] p-6 flex flex-col">
    <!-- Dans la poche -->
    <div
      v-if="authStore.isAuthenticated && ((!authStore.user?.is_teacher && (divisionStore.invites.length > 0 || activeJumps.length > 0 || publicSuggestedQuestions.length > 0)) || activeSessions.length > 0)"
      class="mb-4 rounded-xl border border-primary/20 overflow-hidden"
    >
      <!-- Toggle Header -->
      <button
        @click="showHighlights = !showHighlights"
        class="w-full flex items-center justify-between px-4 py-3 bg-gradient-to-r from-primary/10 to-primary/5 hover:from-primary/15 hover:to-primary/10 transition-colors"
      >
        <h3 class="text-base font-semibold text-text-main dark:text-surface">Dans la poche</h3>
        <div class="flex items-center gap-2">
          <span class="inline-flex items-center justify-center w-5 h-5 bg-primary/20 text-primary rounded-full text-xs font-medium">{{ (divisionStore.invites.length + activeJumps.length + publicSuggestedQuestions.length) + activeSessions.length }}</span>
          <span class="text-sm text-primary font-medium">{{ showHighlights ? 'Masquer' : 'Afficher' }}</span>
          <ChevronDown class="w-4 h-4 text-primary transition-transform duration-300" :class="showHighlights ? 'rotate-180' : ''" />
        </div>
      </button>

      <!-- Animated content -->
      <div
        class="grid transition-[grid-template-rows] duration-300 ease-in-out"
        :class="showHighlights ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
      >
        <div class="overflow-hidden">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 p-4 bg-gradient-to-b from-primary/10 to-primary/5">
            <!-- Classe Invites -->
            <div v-if="!authStore.user?.is_teacher && divisionStore.invites.length > 0">
              <h4 class="text-xs font-medium text-text-muted uppercase tracking-wide mb-2">Invitations de classe</h4>
              <div class="space-y-2">
                <div v-for="invite in divisionStore.invites" :key="invite.id" class="flex items-center justify-between p-3 bg-surface dark:bg-gray-900 rounded-lg border border-secondary/20">
                  <div>
                    <p class="font-medium text-sm text-text-main dark:text-surface">{{ invite.division?.name }}</p>
                    <p class="text-xs text-text-muted">Invitation à rejoindre la classe</p>
                  </div>
                  <div class="flex gap-2">
                    <button @click="openInviteModal(invite.id)" class="px-2 py-1 rounded-lg bg-success hover:bg-success/80 text-white text-xs font-medium transition-colors">Accepter</button>
                    <button @click="divisionStore.declineInvite(invite.id)" class="px-2 py-1 rounded-lg bg-error/20 hover:bg-error/30 text-error text-xs font-medium transition-colors">Refuser</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Active Jumps -->
            <div v-if="!authStore.user?.is_teacher && activeJumps.length > 0">
              <h4 class="text-xs font-medium text-text-muted uppercase tracking-wide mb-2">Sauts actifs</h4>
              <div class="space-y-2">
                <router-link
                  v-for="jump in activeJumps"
                  :key="jump.id"
                  :to="{ name: 'JumpAttempt', params: { jumpId: jump.id } }"
                  class="group flex flex-col gap-1 p-3 rounded-lg bg-surface dark:bg-gray-900 border border-success/30 hover:border-success hover:shadow-md transition-all"
                >
                  <div class="text-xs text-text-muted flex justify-end">{{ jump.division_name }}</div>
                  <p class="font-medium text-text-main dark:text-surface truncate">{{ jump.course?.title }}</p>
                  <p class="text-xs text-text-muted">{{ jump.nb_questions }} questions · {{ jump.time }} min</p>
                </router-link>
              </div>
            </div>

            <!-- Active Sessions -->
            <div v-if="activeSessions.length > 0">
              <h4 class="text-xs font-medium text-text-muted uppercase tracking-wide mb-2">Sessions actives</h4>
              <div class="space-y-2">
                <router-link
                  v-for="session in activeSessions"
                  :key="session.id"
                  :to="{ name: 'Session', params: { code: session.code } }"
                  class="group flex flex-col gap-1 p-3 rounded-lg bg-surface dark:bg-gray-900 border border-primary/30 hover:border-primary hover:shadow-md transition-all"
                >
                  <div class="text-xs text-text-muted flex justify-end">Session ouverte</div>
                  <p class="font-medium text-text-main dark:text-surface truncate">Kangourou {{ session.division_name }}</p>
                </router-link>
              </div>
            </div>

            <!-- Public Suggested Questions -->
            <div v-if="!authStore.user?.is_teacher && publicSuggestedQuestions.length > 0" :class="publicSuggestedQuestions.length > 3 ? 'lg:col-span-2' : ''">
              <h4 class="text-xs font-medium text-text-muted uppercase tracking-wide mb-2 flex items-center gap-1">
                <Lightbulb class="w-3 h-3" />
                Questions à revoir
              </h4>
              <div class="questions-grid">
                <div
                  v-for="sq in publicSuggestedQuestions"
                  :key="sq.id"
                  class="flex items-center justify-between p-3 bg-surface dark:bg-gray-900 rounded-lg border border-warning/30 cursor-pointer hover:border-warning hover:shadow-md transition-all"
                  @click="openPublicQuestionOverlay(sq)"
                >
                  <div class="flex items-center gap-1">
                    <Star v-for="n in sq.level" :key="n" class="w-4 h-4 fill-primary text-warning" />
                    Question niveau {{ sq.level }}
                  </div>
                  <span class="text-xs text-text-muted truncate ml-2">{{ sq.course_title }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="flex items-center justify-center flex-1">
      <div 
        class=" gap-8 max-w-3xl w-full justify-items-center"
        :class="authStore.isAuthenticated ? 'grid grid-cols-1 md:grid-cols-2' : 'grid grid-cols-1'"
      >
      <!-- Random Question (students) -->
      <button
        v-if="authStore.isAuthenticated && !authStore.user?.is_teacher"
        @click="openRandomQuestionModal"
        class="group flex flex-col items-center justify-center gap-4 rounded-2xl border-2 border-primary/20 bg-surface p-10 shadow-sm transition-all hover:border-primary hover:shadow-lg hover:-translate-y-1 cursor-pointer"
      >
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white">
          <CircleQuestionMark class="h-8 w-8" />
        </div>
        <h2 class="text-xl font-bold text-text-main">Répondre à une question</h2>
        <p class="text-sm text-text-muted text-center">Répondez à une problème aléatoire adaptée à votre niveau.</p>
      </button>

      <!-- Create Session (teachers) -->
      <router-link
        v-if="authStore.isAuthenticated && authStore.user?.is_teacher"
        to="/kangourou/create"
        class="group flex flex-col items-center justify-center gap-4 rounded-2xl border-2 border-primary/20 bg-surface p-10 shadow-sm transition-all hover:border-primary hover:shadow-lg hover:-translate-y-1"
      >
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white">
          <Layers class="h-8 w-8" />
        </div>
        <h2 class="text-xl font-bold text-text-main">Créer une session</h2>
        <p class="text-sm text-text-muted text-center">Choisissez un sujet et lancez une nouvelle session Kangourou pour vos élèves.</p>
      </router-link>

      <!-- Create Jump (teachers) or Join Session (students) -->
      <button
        v-if="authStore.isAuthenticated && authStore.user?.is_teacher"
        @click="openCreateJumpModal"
        class="group flex flex-col items-center justify-center gap-4 rounded-2xl border-2 border-secondary/20 bg-surface p-10 shadow-sm transition-all hover:border-secondary hover:shadow-lg hover:-translate-y-1 cursor-pointer"
      >
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-secondary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white">
          <Redo class="h-8 w-8" />
        </div>
        <h2 class="text-xl font-bold text-text-main">Créer un saut</h2>
        <p class="text-sm text-text-muted text-center">Créez un nouveau saut pour que vos élèves puissent améliorer leurs compétences.</p>
      </button>

      <router-link
        v-else
        to="/kangourou/join"
        class="group flex flex-col items-center justify-center gap-4 rounded-2xl border-2 border-secondary/20 bg-surface p-10 shadow-sm transition-all hover:border-secondary hover:shadow-lg hover:-translate-y-1"
      >
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-secondary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white">
          <TvMinimalPlay class="h-8 w-8" />
        </div>
        <h2 class="text-xl font-bold text-text-main">Rejoindre une session</h2>
        <p class="text-sm text-text-muted text-center">Entrez un code de session pour rejoindre une session Kangourou existante.</p>
      </router-link>
    </div>
    </div>

    <!-- Footer Links -->
    <div class="mt-8 flex justify-center gap-4 text-xs text-text-muted">
      <router-link to="/terms/service" class="hover:text-primary transition-colors">Conditions d'utilisation</router-link>
      <span>·</span>
      <router-link to="/terms/privacy" class="hover:text-primary transition-colors">Politique de confidentialité</router-link>
    </div>
  </div>

  <!-- Accept Invite Modal -->
  <div
    v-if="showInviteModal"
    class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
    @click.self="showInviteModal = false"
  >
    <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-md">
      <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-4">Accepter l'invitation</h3>
      <form @submit.prevent="handleAcceptInviteWithName" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Prénom</label>
            <input
              v-model="inviteFirstName"
              type="text"
              required
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Nom</label>
            <input
              v-model="inviteLastName"
              type="text"
              required
              class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
        </div>
        <div v-if="divisionStore.error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg text-sm">
          {{ divisionStore.error }}
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" @click="showInviteModal = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors">
            Annuler
          </button>
          <button type="submit" :disabled="divisionStore.isLoading" class="px-4 py-2 bg-primary hover:bg-primary-hover cursor-pointer text-surface rounded-lg text-sm font-medium disabled:opacity-50 transition-colors">
            Accepter
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Public Question Overlay -->
  <div
    v-if="showPublicQuestionOverlay && selectedPublicQuestion"
    class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center"
    @click.self="closePublicQuestionOverlay"
  >
    <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-2xl p-6 w-full max-w-[80vw] flex flex-col items-center gap-4">
      <div class="flex w-full justify-between items-center">
        <div>
          <div class="flex items-center gap-1 self-start">
            <div class="text-2xl font-bold mr-2">
              Question à revoir
            </div>
            <Star v-for="n in selectedPublicQuestion.level" :key="n" class="w-6 h-6 fill-warning text-warning" />
          </div>
          <div class="text-xl text-text-muted">
            {{ selectedPublicQuestion.division_name }} · {{ selectedPublicQuestion.course_title }}
          </div>
        </div>
        <button @click="closePublicQuestionOverlay" class="text-text-muted hover:text-text-main transition-colors cursor-pointer">
          <X class="w-5 h-5" />
        </button>
      </div>
      <img
        v-if="selectedPublicQuestion.question.image"
        :src="'/' + selectedPublicQuestion.question.image"
        class="max-h-64 object-contain select-none pointer-events-none"
        alt="Question"
      />

    </div>
  </div>

  <!-- Random Question Modal -->
  <div
    v-if="showRandomQuestionModal"
    class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center"
    @click.self="closeRandomQuestionModal"
  >
    <div class="bg-surface dark:bg-gray-900 rounded-2xl shadow-2xl p-6 w-[80vw] mx-4 flex flex-col gap-5">
      <!-- Header -->
      <div>
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <h3 class="text-lg font-bold text-text-main dark:text-surface">
              {{ randomQuestion?.isSuggested ? 'Question à revoir' : 'Question aléatoire' }}
            </h3>
            <template v-if="randomQuestion?.isSuggested && randomQuestion.level">
              <Star v-for="n in randomQuestion.level" :key="n" class="w-4 h-4 fill-warning text-warning" />
            </template>
          </div>
          <button @click="closeRandomQuestionModal" class="text-text-muted hover:text-text-main transition-colors cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Question display -->
        <div class="flex flex-col items-center gap-4 min-h-32">
          <div v-if="randomQuestionLoading" class="flex items-center justify-center w-full h-32 text-text-muted text-sm">
            Chargement...
          </div>
          <template v-else-if="randomQuestion">
            <img
              v-if="randomQuestion.image"
              :src="'/' + randomQuestion.image"
              class="max-h-64 object-contain select-none pointer-events-none"
              alt="Question"
            />
            <p v-else class="text-text-muted text-sm">Aucune image pour cette question.</p>

            <!-- Reveal answer (only for non-suggested questions) -->
            <div v-if="!randomQuestion.isSuggested" class="w-full">
              <div v-if="showRandomAnswer" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-success/10 border border-success/30 cursor-pointer" @click="showRandomAnswer = false">
                <span class="text-sm font-semibold text-success">Réponse : {{ randomQuestion.correct_answer }}</span>
              </div>
              <button
                @click="showRandomAnswer = true"
                class="w-full px-4 py-3 rounded-lg border border-border text-sm text-text-muted hover:text-text-main hover:border-primary transition-colors cursor-pointer"
                v-else
              >
                Révéler la réponse
              </button>
            </div>
          </template>
        </div>

        <!-- Divider -->
        <div class="border-t border-border mt-8"></div>
      </div>

      <!-- Choose another question section -->
      <div>
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-lg font-bold text-text-main dark:text-surface">Choisir une autre question</h3>
          <div class="flex items-center gap-2 text-sm font-medium text-text-main dark:text-surface">
            <Lightbulb class="w-4 h-4 text-warning" />
            Parmi les questions à revoir
            <button
              type="button"
              role="switch"
              :aria-checked="prioritizeSuggested"
              :disabled="publicSuggestedQuestions.length === 0"
              @click="togglePrioritizeSuggested"
              class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none"
              :class="prioritizeSuggested ? 'bg-warning' : 'bg-gray-300 dark:bg-gray-600'"
            >
              <span
                class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition-transform duration-200"
                :class="prioritizeSuggested ? 'translate-x-5' : 'translate-x-0'"
              />
            </button>
          </div>
        </div>

        <!-- Navigation buttons: single button when prioritizing, 3 buttons otherwise -->
        <div v-if="prioritizeSuggested" class="w-full">
          <button
            @click="fetchNextSuggestedQuestion"
            :disabled="randomQuestionLoading || publicSuggestedQuestions.length === 0"
            class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg border border-primary/40 bg-primary/5 hover:bg-primary/10 transition-colors disabled:opacity-50 cursor-pointer text-sm font-medium text-primary"
          >
          Question suivante
          <ArrowBigRightDash class="w-4 h-4" />
          </button>
        </div>
        <div v-else class="grid grid-cols-3 gap-2">
          <button
            @click="fetchRandomQuestion(-200)"
            :disabled="randomQuestionLoading"
            class="flex justify-center items-center gap-1 px-3 py-2 rounded-lg border border-border hover:border-primary hover:bg-primary/5 transition-colors disabled:opacity-50 cursor-pointer text-sm"
          >
            <ArrowDownRight class="w-4 h-4 rotate-0 text-text-muted" />
            Plus facile
          </button>
          <button
            @click="fetchRandomQuestion(0)"
            :disabled="randomQuestionLoading"
            class="flex justify-center items-center gap-1 px-3 py-2 rounded-lg border border-primary/40 bg-primary/5 hover:bg-primary/10 transition-colors disabled:opacity-50 cursor-pointer text-sm font-medium text-primary"
          >
          Même niveau
          <ArrowRight class="w-4 h-4" />
          </button>
          <button
            @click="fetchRandomQuestion(200)"
            :disabled="randomQuestionLoading"
            class="flex justify-center items-center gap-1 px-3 py-2 rounded-lg border border-border hover:border-primary hover:bg-primary/5 transition-colors disabled:opacity-50 cursor-pointer text-sm"
          >
          Plus difficile
          <ArrowUpRight class="w-4 h-4 text-text-muted" />
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Claim Attempts Modal -->
  <div
    v-if="showClaimModal && claimableAttempts.length > 0"
    class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
    @click.self="dismissClaimModal"
  >
    <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-lg w-full mx-4 shadow-xl space-y-5">
      <h3 class="text-lg font-bold text-text-main dark:text-surface">Récupérer vos tentatives ?</h3>
      <p class="text-sm text-text-muted">Des tentatives effectuées en tant qu'invité ont été trouvées. Souhaitez-vous les ajouter à votre historique ?</p>

      <div class="space-y-2 max-h-64 overflow-y-auto">
        <label
          v-for="ca in claimableAttempts"
          :key="ca.id"
          class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg cursor-pointer"
        >
          <input
            type="checkbox"
            :value="ca.id"
            v-model="selectedClaimIds"
            class="w-4 h-4"
          />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-text-main dark:text-surface truncate">{{ ca.kangourou_session?.paper?.title || 'Session' }}</p>
            <div class="flex items-center gap-2 text-xs text-text-muted">
              <span>{{ ca.name || 'Anonyme' }}</span>
              <span v-if="ca.score !== null">· Score: {{ ca.score }}</span>
              <span :class="ca.status === 'finished' ? 'text-success' : 'text-warning'">
                · {{ ca.status === 'finished' ? 'Terminée' : 'En cours' }}
              </span>
            </div>
          </div>
        </label>
      </div>

      <div class="flex gap-3">
        <button
          @click="dismissClaimModal"
          class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors"
        >
          Ignorer
        </button>
        <button
          @click="handleClaimAttempts"
          :disabled="selectedClaimIds.length === 0 || isClaiming"
          class="flex-1 px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors disabled:opacity-50"
        >
          {{ isClaiming ? 'Récupération...' : `Récupérer (${selectedClaimIds.length})` }}
        </button>
      </div>
    </div>
  </div>

  <!-- Create Jump Modal -->
  <div
    v-if="showCreateJumpModal"
    class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
    @click.self="closeCreateJumpModal"
  >
    <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-md">
      <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-4">Créer un saut</h3>
      
      <!-- Step 1: Select Division and Course -->
      <div v-if="createJumpStep === 1" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-2">Sélectionner une classe</label>
          <select
            v-model="selectedDivisionId"
            @change="handleDivisionChange"
            class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
          >
            <option value="">-- Choisir une classe --</option>
            <option v-for="division in divisionStore.divisions" :key="division.id" :value="division.id">
              {{ division.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-2">Sélectionner un cours</label>
          <select
            v-model="selectedCourseId"
            :disabled="!selectedDivisionId || loadingCourses"
            class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <option value="">
              {{ loadingCourses ? 'Chargement des cours...' : '-- Choisir un cours --' }}
            </option>
            <option v-for="course in availableCourses" :key="course.id" :value="course.id">
              {{ course.title }}
            </option>
          </select>
        </div>
        <div class="flex justify-end gap-2">
          <button @click="closeCreateJumpModal" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors">
            Annuler
          </button>
          <button
            @click="proceedToStep2"
            :disabled="!selectedCourseId"
            class="px-4 py-2 bg-primary hover:bg-primary-hover cursor-pointer text-surface rounded-lg text-sm font-medium disabled:opacity-50 transition-colors"
          >
            Suivant
          </button>
        </div>
      </div>

      <!-- Step 2: Configure Jump -->
      <div v-if="createJumpStep === 2" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Nombre de questions</label>
          <input
            v-model.number="jumpForm.nb_questions"
            type="number"
            min="1"
            max="30"
            class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Durée (minutes)</label>
          <input
            v-model.number="jumpForm.time"
            type="number"
            min="5"
            max="60"
            class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Difficulté progressive (0-10)</label>
          <input
            v-model.number="jumpForm.growth"
            type="number"
            min="0"
            max="10"
            class="w-full px-4 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
          />
        </div>
        <div v-if="createJumpError" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg text-sm">
          {{ createJumpError }}
        </div>
        <div class="flex justify-end gap-2">
          <button @click="createJumpStep = 1" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors">
            Retour
          </button>
          <button
            @click="handleCreateJump"
            :disabled="isCreatingJump"
            class="px-4 py-2 bg-primary hover:bg-primary-hover cursor-pointer text-surface rounded-lg text-sm font-medium disabled:opacity-50 transition-colors"
          >
            {{ isCreatingJump ? 'Création...' : 'Créer le saut' }}
          </button>
        </div>
      </div>

      <!-- Step 3: Confirmation -->
      <div v-if="createJumpStep === 3" class="space-y-4 text-center">
        <div class="flex justify-center">
          <div class="flex h-16 w-16 items-center justify-center rounded-full bg-success/10 text-success">
            <PlusCircle class="h-8 w-8" />
          </div>
        </div>
        <div>
          <h4 class="text-lg font-semibold text-text-main dark:text-surface mb-2">Saut créé avec succès !</h4>
          <p class="text-sm text-text-muted">Le nouveau saut a été créé et activé pour votre classe.</p>
        </div>
        <div class="space-y-2">
          <router-link
            :to="{ name: 'DivisionDetails', params: { id: selectedDivisionId } }"
            class="block w-full px-4 py-2 bg-primary hover:bg-primary-hover text-surface rounded-lg text-sm font-medium transition-colors"
            @click="closeCreateJumpModal"
          >
            Voir la classe
          </router-link>
          <button
            @click="closeCreateJumpModal"
            class="block w-full px-4 py-2 border border-border hover:border-primary text-text-main dark:text-surface rounded-lg text-sm font-medium transition-colors"
          >
            Fermer
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { PlusCircle, Redo, X, Star, Lightbulb, ChevronDown, TvMinimalPlay, Layers, ArrowBigRightDash, ArrowDownRight, ArrowUpRight, ArrowRight, CircleQuestionMark } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/authStore';
import { useDivisionStore } from '@/stores/divisionStore';
import { useAttemptStore } from '@/stores/attemptStore';
import { useCourseStore } from '@/stores/courseStore';
import axios from 'axios';

const authStore = useAuthStore();
const divisionStore = useDivisionStore();
const attemptStore = useAttemptStore();
const courseStore = useCourseStore();

const showHighlights = ref(true);
const showInviteModal = ref(false);
const pendingInviteId = ref(null);
const inviteFirstName = ref('');
const inviteLastName = ref('');
const showClaimModal = ref(false);
const showPublicQuestionOverlay = ref(false);
const selectedPublicQuestion = ref(null);
const publicSuggestedQuestions = ref([]);
const selectedClaimIds = ref([]);
const isClaiming = ref(false);

// Create Jump Modal
const showCreateJumpModal = ref(false);
const createJumpStep = ref(1);
const selectedDivisionId = ref('');
const selectedCourseId = ref('');
const loadingCourses = ref(false);
const courses = ref([]);
const createdJump = ref(null);
const jumpForm = ref({
  nb_questions: 7,
  time: 15,
  growth: 3,
});
const isCreatingJump = ref(false);
const createJumpError = ref('');

// Random Question Modal
const showRandomQuestionModal = ref(false);
const randomQuestion = ref(null);
const randomQuestionReference = ref(0);
const randomQuestionLoading = ref(false);
const showRandomAnswer = ref(false);
const prioritizeSuggested = ref(false);

const claimableAttempts = computed(() => {
  return attemptStore.guestAttempts.filter(a => !a.user_id);
});

const displayAttempts = computed(() => {
  if (authStore.isAuthenticated) {
    return attemptStore.myAttempts.slice(0, 3);
  }
  return attemptStore.guestAttempts;
});

const activeSessions = computed(() => {
  if (authStore.user?.is_teacher) {
    return [];
  }

  const userAttemptSessionIds = new Set(
    attemptStore.myAttempts.map(attempt => attempt.kangourou_session_id)
  );

  const sessions = [];
  divisionStore.divisions.forEach(division => {
    if (division.kangourou_sessions) {
      division.kangourou_sessions.forEach(session => {
        if (session.status === 'active' && !userAttemptSessionIds.has(session.id)) {
          sessions.push({
            ...session,
            division_name: division.name,
          });
        }
      });
    }
  });
  return sessions;
});

const activeJumps = computed(() => {
  const jumps = [];
  divisionStore.divisions.forEach(division => {
    if (division.active_jumps) {
      division.active_jumps.forEach(jump => {
        jumps.push({ ...jump, division_name: division.name });
      });
    }
  });
  return jumps;
});

const availableCourses = computed(() => {
  return courses.value;
});


function openInviteModal(inviteId) {
  pendingInviteId.value = inviteId;
  inviteFirstName.value = '';
  inviteLastName.value = '';
  divisionStore.clearError();
  showInviteModal.value = true;
}

async function handleAcceptInviteWithName() {
  await divisionStore.acceptInvite(pendingInviteId.value, inviteFirstName.value, inviteLastName.value);
  if (!divisionStore.error) {
    showInviteModal.value = false;
    await divisionStore.fetchMyDivisions();
  }
}

async function handleClaimAttempts() {
  if (selectedClaimIds.value.length === 0) return;
  isClaiming.value = true;
  try {
    await attemptStore.claimAttempts(selectedClaimIds.value);
    await attemptStore.fetchMyAttempts();
    showClaimModal.value = false;
    selectedClaimIds.value = [];
  } catch {
    // error handled by store
  } finally {
    isClaiming.value = false;
  }
}

function dismissClaimModal() {
  showClaimModal.value = false;
  selectedClaimIds.value = [];
  // Clear guest attempts from localStorage since user chose to ignore
  attemptStore.removeGuestAttemptIds(claimableAttempts.value.map(a => a.id));
}

function openCreateJumpModal() {
  showCreateJumpModal.value = true;
  createJumpStep.value = 1;
  selectedDivisionId.value = '';
  selectedCourseId.value = '';
  loadingCourses.value = false;
  courses.value = [];
  jumpForm.value = {
    nb_questions: 7,
    time: 15,
    growth: 3,
  };
  createJumpError.value = '';
}

function closeCreateJumpModal() {
  showCreateJumpModal.value = false;
  createJumpStep.value = 1;
  selectedDivisionId.value = '';
  selectedCourseId.value = '';
  loadingCourses.value = false;
  courses.value = [];
  createJumpError.value = '';
}

async function handleDivisionChange() {
  selectedCourseId.value = '';
  courses.value = [];
  
  if (!selectedDivisionId.value) {
    return;
  }

  loadingCourses.value = true;
  try {
    const response = await axios.get(`/api/divisions/${selectedDivisionId.value}/courses`);
    courses.value = response.data.courses ?? [];
  } catch (err) {
    createJumpError.value = err.response?.data?.message || 'Erreur lors du chargement des cours';
  } finally {
    loadingCourses.value = false;
  }
}

function proceedToStep2() {
  if (selectedCourseId.value) {
    createJumpStep.value = 2;
  }
}

async function handleCreateJump() {
  if (!selectedCourseId.value || !jumpForm.value.nb_questions || !jumpForm.value.time) {
    createJumpError.value = 'Tous les champs sont obligatoires';
    return;
  }

  isCreatingJump.value = true;
  createJumpError.value = '';

  try {
    const response = await axios.post(`/api/courses/${selectedCourseId.value}/jumps`, {
      nb_questions: jumpForm.value.nb_questions,
      time: jumpForm.value.time,
      growth: jumpForm.value.growth,
      status: 'active',
    });

    createdJump.value = response.data.jump;
    createJumpStep.value = 3;
    // Refresh divisions to show the newly created jump
    await divisionStore.fetchMyDivisions();
  } catch (err) {
    createJumpError.value = err.response?.data?.message || 'Erreur lors de la création du saut';
  } finally {
    isCreatingJump.value = false;
  }
}

// When user logs in, check for guest attempts to claim
watch(() => authStore.isAuthenticated, async (isAuth) => {
  if (isAuth && attemptStore.getGuestAttemptIds().length > 0) {
    await attemptStore.fetchGuestAttempts();
    if (claimableAttempts.value.length > 0) {
      selectedClaimIds.value = claimableAttempts.value.map(a => a.id);
      showClaimModal.value = true;
    }
  }
});

onMounted(async () => {
  if (authStore.isAuthenticated) {
    await divisionStore.fetchMyDivisions();
    await attemptStore.fetchMyAttempts();

    // Subscribe to division channels for real-time session updates
    divisionStore.divisions.forEach(division => {
      window.Echo.private(`division.${division.id}`)
        .listen('.SessionOpenedForDivision', (e) => {
          const div = divisionStore.divisions.find(d => d.id === division.id);
          if (!div) { return; }
          if (!div.kangourou_sessions) { div.kangourou_sessions = []; }
          const existingIndex = div.kangourou_sessions.findIndex(s => s.id === e.session.id);
          if (existingIndex === -1) {
            div.kangourou_sessions.push(e.session);
          } else {
            div.kangourou_sessions[existingIndex] = e.session;
          }
        })
        .listen('.SessionClosedForDivision', (e) => {
          const div = divisionStore.divisions.find(d => d.id === division.id);
          if (!div) { return; }
          div.kangourou_sessions = (div.kangourou_sessions ?? []).filter(s => s.id !== e.session.id);
        })
        .listen('.JumpActivated', (e) => {
          const div = divisionStore.divisions.find(d => d.id === division.id);
          if (!div) { return; }
          if (!div.active_jumps) { div.active_jumps = []; }
          const alreadyExists = div.active_jumps.some(j => j.id === e.jump.id);
          if (!alreadyExists) {
            div.active_jumps.push(e.jump);
          }
        })
        .listen('.JumpReopened', (e) => {
          if (authStore.user?.is_teacher) { return; }
          const hasNoAttempt = e.user_ids_without_attempt?.includes(authStore.user?.id);
          if (!hasNoAttempt) { return; }
          const div = divisionStore.divisions.find(d => d.id === division.id);
          if (!div) { return; }
          if (!div.active_jumps) { div.active_jumps = []; }
          const alreadyExists = div.active_jumps.some(j => j.id === e.jump.id);
          if (!alreadyExists) {
            div.active_jumps.push(e.jump);
          }
        });
    });

    // Fetch invites if student
    if (!authStore.user?.is_teacher) {
      await divisionStore.fetchMyInvites();

      // Fetch public suggested questions for all divisions
      const allPublic = [];
      await Promise.all(divisionStore.divisions.map(async (division) => {
        try {
          const res = await axios.get(`/api/divisions/${division.id}/public-suggested-questions`);
          allPublic.push(...(res.data.suggested_questions ?? []).map(sq => ({ ...sq, division_name: division.name })));
        } catch {
          // handled silently
        }
      }));
      publicSuggestedQuestions.value = allPublic;
    }

    // Check for unclaimed guest attempts
    if (attemptStore.getGuestAttemptIds().length > 0) {
      await attemptStore.fetchGuestAttempts();
      if (claimableAttempts.value.length > 0) {
        selectedClaimIds.value = claimableAttempts.value.map(a => a.id);
        showClaimModal.value = true;
      }
    }
  } else {
    // Load guest attempts for display
    await attemptStore.fetchGuestAttempts();
  }
});

function openPublicQuestionOverlay(sq) {
  selectedPublicQuestion.value = sq;
  showPublicQuestionOverlay.value = true;
}

function closePublicQuestionOverlay() {
  showPublicQuestionOverlay.value = false;
  selectedPublicQuestion.value = null;
}

async function openRandomQuestionModal() {
  showRandomQuestionModal.value = true;
  prioritizeSuggested.value = publicSuggestedQuestions.value.length > 0;
  randomQuestionReference.value = 0;
  await fetchRandomQuestion(0);
}

function closeRandomQuestionModal() {
  showRandomQuestionModal.value = false;
  randomQuestion.value = null;
  showRandomAnswer.value = false;
}

function togglePrioritizeSuggested() {
  if (publicSuggestedQuestions.value.length === 0) { return; }
  prioritizeSuggested.value = !prioritizeSuggested.value;
}

async function fetchNextSuggestedQuestion() {
  showRandomAnswer.value = false;
  randomQuestionLoading.value = true;

  try {
    const pool = publicSuggestedQuestions.value;
    const picked = pool[Math.floor(Math.random() * pool.length)];
    randomQuestion.value = {
      image: picked.question.image,
      correct_answer: picked.question.correct_answer,
      isSuggested: true,
      level: picked.level,
    };
  } catch {
    // handled silently
  } finally {
    randomQuestionLoading.value = false;
  }
}

async function fetchRandomQuestion(referenceAdjustment) {
  showRandomAnswer.value = false;
  randomQuestionLoading.value = true;

  try {
    if (prioritizeSuggested.value && publicSuggestedQuestions.value.length > 0) {
      const pool = publicSuggestedQuestions.value;
      const picked = pool[Math.floor(Math.random() * pool.length)];
      randomQuestion.value = {
        image: picked.question.image,
        correct_answer: picked.question.correct_answer,
        isSuggested: true,
        level: picked.level,
      };
    } else {
      randomQuestionReference.value += referenceAdjustment;
      const params = randomQuestionReference.value !== 0
        ? { reference: randomQuestionReference.value }
        : {};
      const res = await axios.get('/api/random-question', { params });
      randomQuestionReference.value = res.data.reference;
      randomQuestion.value = {
        image: res.data.question.image,
        correct_answer: res.data.question.correct_answer,
        isSuggested: false,
      };
    }
  } catch {
    // handled silently
  } finally {
    randomQuestionLoading.value = false;
  }
}

onUnmounted(() => {
  divisionStore.divisions.forEach(division => {
    window.Echo.leave(`division.${division.id}`);
  });
});
</script>

<style>
.questions-grid {
  display: grid;
  grid-template-rows: repeat(3, auto);
  grid-auto-flow: column;
  grid-auto-columns: 1fr;
  gap: 0.5rem;
}
</style>