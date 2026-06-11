<template>
  <div class="container mx-auto p-6">
    <!-- Back link -->
    <div class="flex items-center gap-4 mb-6">
      <router-link to="/my/divisions" class="text-primary hover:underline flex items-center gap-2">
        <ChevronLeft class="w-4 h-4" />
        Retour
      </router-link>
    </div>

    <!-- Loading -->
    <div v-if="divisionStore.isLoading && !divisionStore.division" class="text-text-muted">
      Chargement...
    </div>

    <!-- Error -->
    <div v-else-if="divisionStore.error && !divisionStore.division" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg">
      {{ divisionStore.error }}
    </div>

    <!-- Teacher View -->
    <template v-else-if="isTeacher && divisionStore.division">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6 gap-3">
        <div class="flex items-center gap-3">
        <div class="relative mt-0.5">
          <button
            @click.stop="showHeaderMenu = !showHeaderMenu"
            class="p-1.5 rounded-lg border border-border text-text-muted bg-surface dark:bg-gray-900 hover:text-text-main hover:border-primary hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer shadow-sm"
          >
            <MoreVertical class="w-5 h-5" />
          </button>
          <div v-if="showHeaderMenu" class="fixed inset-0 z-10" @click="showHeaderMenu = false" />
          <div
            v-if="showHeaderMenu"
            class="absolute left-0 top-full mt-1 z-20 bg-surface dark:bg-gray-900 border border-border rounded-xl shadow-lg py-1 min-w-44"
          >
            <button
              @click="openEditDivisionModal"
              class="w-full flex items-center gap-2 px-4 py-2 text-sm text-text-main dark:text-surface hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
            >
              <Pencil class="w-4 h-4" />
              Modifier
            </button>
            <div class="h-px bg-border my-1" />
            <button
              v-if="!divisionStore.division.archived"
              @click="showArchiveConfirm = true; showHeaderMenu = false"
              class="w-full flex items-center gap-2 px-4 py-2 text-sm text-text-muted hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
            >
              <Archive class="w-4 h-4" />
              Archiver
            </button>
            <button
              v-else
              @click="showUnarchiveConfirm = true; showHeaderMenu = false"
              class="w-full flex items-center gap-2 px-4 py-2 text-sm text-success hover:bg-success/10 transition-colors cursor-pointer"
            >
              <ArchiveRestore class="w-4 h-4" />
              Activer
            </button>
            <button
              v-if="divisionStore.division.archived"
              @click="showDeleteConfirm = true; showHeaderMenu = false"
              class="w-full flex items-center gap-2 px-4 py-2 text-sm text-error hover:bg-error/10 transition-colors cursor-pointer"
            >
              <Trash2 class="w-4 h-4" />
              Supprimer
            </button>
          </div>
        </div>
        <div>
          <h2 class="text-4xl font-bold text-text-main dark:text-surface">{{ divisionStore.division.name }}</h2>
          <!-- <p class="text-text-muted text-sm mt-1">{{ divisionStore.division.students_count }} élève{{ divisionStore.division.students_count !== 1 ? 's' : '' }}</p> -->
        </div>
        </div>
        <!-- Class code -->
        <div class="flex flex-col items-end gap-0.5">
          <div class="flex items-center gap-2">
            <span
              class="font-mono font-bold text-3xl transition-colors"
              :class="divisionStore.division.accepting_students ? 'text-text-main dark:text-surface' : 'text-text-muted/50'"
            >{{ divisionStore.division.code }}</span>
            <button
              v-if="divisionStore.division.accepting_students"
              @click="showJoinInfoModal = true"
              title="Afficher le code de classe"
              class="text-text-muted hover:text-primary cursor-pointer transition-colors"
            >
              <Fullscreen class="w-8 h-8" />
            </button>
          </div>
          <p v-if="!divisionStore.division.accepting_students" class="text-xs text-text-muted">Inscriptions fermées</p>
        </div>
      </div>

      <div v-if="divisionStore.division.archived" class="mb-6 p-4 bg-warning/10 border border-warning rounded-lg">
        <p class="text-sm text-warning font-medium">Cette classe est archivée. Cliquez sur "Activer" pour la rendre active.</p>
      </div>

      <!-- Tabs -->
      <div class="flex border-b border-border mb-6">
        <button
          v-for="tab in [{ id: 'eleves', label: 'Élèves' }, { id: 'sessions', label: 'Sessions' }, { id: 'parcours', label: 'Parcours' }]"
          :key="tab.id"
          @click="switchTab(tab.id)"
          class="px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors cursor-pointer"
          :class="activeTab === tab.id ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main'"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Tab panels -->
      <Transition :name="tabSlideDirection" mode="out-in">

      <!-- Élèves tab -->
      <div v-if="activeTab === 'eleves'" key="eleves" class="flex gap-6 items-start">
        <!-- Left: student list -->
        <div class="flex-1 min-w-0 bg-surface dark:bg-gray-900 border border-border rounded-xl p-4">
          <div class="flex items-center justify-between mb-3 gap-3">
            <h3 class="font-semibold text-text-main dark:text-surface">Élèves ({{ divisionStore.division.students?.length ?? 0 }})</h3>
            <input
              v-model="divisionStudentSearch"
              type="text"
              placeholder="Chercher..."
              class="px-3 py-1.5 text-sm border border-border rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary w-44"
            />
          </div>
          <ul v-if="divisionFilteredStudents.length" class="divide-y divide-border">
            <li
              v-for="(student, index) in divisionFilteredStudents"
              :key="student.id"
              class="flex items-center justify-between px-2 py-2 rounded-lg transition-colors hover:bg-primary/5"
              :class="index % 2 === 0 ? '' : 'bg-gray-50 dark:bg-gray-800/50'"
            >
              <div>
                <p class="text-sm font-medium text-text-main dark:text-surface">{{ student.pivot?.class_name ?? student.name }}</p>
                <p class="text-xs text-text-muted">{{ student.email }}</p>
              </div>
              <div class="flex items-center gap-1">
                <button
                  @click="openEditClassNameModal(student)"
                  :disabled="divisionStore.division.archived"
                  class="text-text-muted hover:text-primary transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                  title="Modifier le nom de classe"
                >
                  <Pencil class="w-4 h-4" />
                </button>
                <button
                  @click="() => { studentIdToRemove = student.id; showRemoveStudentConfirm = true; }"
                  :disabled="divisionStore.division.archived"
                  class="text-text-muted hover:text-error transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                  title="Retirer l'élève"
                >
                  <X class="w-4 h-4" />
                </button>
              </div>
            </li>
          </ul>
          <p v-else class="text-sm text-text-muted">{{ divisionStudentSearch ? 'Aucun résultat.' : 'Aucun élève.' }}</p>
        </div>

        <!-- Right: toggle + invite -->
        <div class="w-80 shrink-0 space-y-4">
          <!-- Accepting students toggle -->
          <div class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4 flex items-center justify-between">
            <span class="text-sm font-medium text-text-main dark:text-surface/80">Ouvert aux inscriptions</span>
            <button
              @click="handleToggleAccepting"
              :disabled="divisionStore.division.archived"
              :class="[
                'relative inline-flex h-6 w-11 items-center rounded-full transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed',
                divisionStore.division.accepting_students ? 'bg-success' : 'bg-gray-300 dark:bg-gray-600'
              ]"
            >
              <span
                :class="[
                  'inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow',
                  divisionStore.division.accepting_students ? 'translate-x-6' : 'translate-x-1'
                ]"
              />
            </button>
          </div>

          <!-- Invite form -->
          <div class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4">
            <h3 class="font-semibold text-text-main dark:text-surface mb-3">Inviter par email</h3>
            <form @submit.prevent="handleInvite" class="flex flex-col gap-2">
              <input
                v-model="inviteEmail"
                type="email"
                placeholder="email@exemple.com"
                :disabled="divisionStore.division.archived"
                required
                class="flex-1 px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              />
              <button
                type="submit"
                :disabled="divisionStore.isLoading || divisionStore.division.archived"
                class="w-full px-3 py-2 bg-primary cursor-pointer hover:bg-primary-hover text-surface rounded-lg text-sm disabled:opacity-50 transition-colors"
              >
                Inviter
              </button>
            </form>
            <div v-if="inviteSuccess" class="mt-2 text-sm text-success">Invitation envoyée !</div>
            <div v-if="divisionStore.error" class="mt-2 text-sm text-error">{{ divisionStore.error }}</div>
            <div v-if="pendingInvites.length" class="mt-3 space-y-1">
              <p class="text-xs text-text-muted font-medium uppercase tracking-wide">En attente</p>
              <div v-for="invite in pendingInvites" :key="invite.id" class="flex items-center justify-between text-sm">
                <span class="text-text-muted">{{ invite.email }}</span>
                <span class="text-xs bg-warning/20 text-warning px-2 py-0.5 rounded-full">En attente</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sessions tab -->
      <div v-else-if="activeTab === 'sessions'" key="sessions">
        <!-- Active sessions -->
        <div v-if="teacherSessions.length" class="mb-8">
          <h3 class="font-semibold text-text-main dark:text-surface mb-3">Sessions actives</h3>
          <div class="flex gap-4 overflow-x-auto pb-2">
            <div
              v-for="session in teacherSessions"
              :key="session.id"
              class="min-w-[280px] max-w-[320px] bg-surface dark:bg-gray-900 border border-border rounded-xl p-4 shrink-0"
            >
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-medium text-text-main dark:text-surface">{{ session.paper?.title }}</p>
                  <p class="text-xs text-text-muted mt-0.5">{{ session.code }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <!-- <button
                    @click="openActiveSessionModal(session)"
                    class="text-text-muted hover:text-primary transition-colors cursor-pointer"
                    title="Voir les tentatives"
                  >
                    <Eye class="w-5 h-5" />
                  </button> -->
                  <!-- <button
                    @click="handleToggleSession(session)"
                    :class="[
                      'relative inline-flex h-6 w-11 items-center rounded-full transition-colors cursor-pointer',
                      isSessionOpen(session.id) ? 'bg-success' : 'bg-gray-300 dark:bg-gray-600'
                    ]"
                  >
                    <span
                      :class="[
                        'inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow',
                        isSessionOpen(session.id) ? 'translate-x-6' : 'translate-x-1'
                      ]"
                    />
                  </button> -->
                </div>
              </div>
              <button
                @click="openActiveSessionModal(session)"
                class="flex justify-center items-center mt-2 w-full py-2 bg-primary hover:bg-primary-hover cursor-pointer text-surface rounded-lg text-sm font-medium disabled:opacity-50 transition-colors"
              >
                <span>Suivre la session</span>
                <Eye class="w-6 h-6 ml-2" />
              </button>
            </div>
          </div>
        </div>

        <!-- Expired sessions with inline analysis -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-text-main dark:text-surface">Sessions expirées</h3>
            <div v-if="expiredSessionsWithAttempts.length > 1" class="flex items-center gap-1">
              <button
                @click="expiredCarouselRef?.scrollBy({ left: -320, behavior: 'smooth' })"
                class="p-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface hover:bg-primary hover:text-white transition-colors cursor-pointer"
              ><ChevronLeft class="w-5 h-5" /></button>
              <button
                @click="expiredCarouselRef?.scrollBy({ left: 320, behavior: 'smooth' })"
                class="p-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface hover:bg-primary hover:text-white transition-colors cursor-pointer"
              ><ChevronRight class="w-5 h-5" /></button>
            </div>
          </div>
          <div v-if="!expiredSessionsWithAttempts.length" class="text-sm text-text-muted">Aucune session expirée.</div>
          <div v-else ref="expiredCarouselRef" class="flex gap-4 overflow-x-auto pb-2 items-start [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <div
              v-for="session in expiredSessionsWithAttempts"
              :key="session.id"
              class="min-w-[280px] max-w-[360px] bg-surface dark:bg-gray-900 border border-border rounded-xl p-4 shrink-0"
            >
              <!-- Session header -->
              <div class="mb-3">
                <p class="font-medium text-text-main dark:text-surface">{{ session.paper?.title }}</p>
                <p class="text-xs text-text-muted mt-0.5">{{ session.attempts_count }} tentative{{ session.attempts_count > 1 ? 's' : '' }} · {{ formatExpiredTime(session.expires_at) }}</p>
                <button
                  @click="openExpiredSessionModal(session)"
                  class="mt-2 w-full px-3 py-1.5 text-sm border border-border rounded-lg text-text-muted hover:text-primary hover:border-primary transition-colors cursor-pointer"
                >
                  Voir les performances
                </button>
              </div>

              <!-- Inline analysis -->
              <template v-if="session.pivot?.analysis">
                <div v-if="sessionQuestionsLoading[session.id]" class="text-xs text-text-muted py-2">Chargement...</div>
                <div v-else-if="getSessionAnalysisData(session).length">
                  <p class="text-xs font-medium text-text-muted uppercase tracking-wide mb-2">Analyse des questions</p>
                  <div class="space-y-1.5">
                    <div
                      v-for="(item, index) in getSessionAnalysisData(session)"
                      :key="item.question_id"
                      class="flex items-center gap-2"
                    >
                      <span class="text-xs text-text-muted w-5 text-right shrink-0">{{ index + 1 }}</span>
                      <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 min-w-0">
                        <div
                          class="h-1.5 rounded-full transition-all"
                          :class="successRatioBarClass(item.success_ratio)"
                          :style="{ width: `${Math.round(item.success_ratio * 100)}%` }"
                        />
                      </div>
                      <span class="text-xs font-semibold tabular-nums w-9 text-right shrink-0" :class="successRatioTextClass(item.success_ratio)">
                        {{ Math.round(item.success_ratio * 100) }}%
                      </span>
                      <button
                        @click="openSessionQuestionOverlay(item, session)"
                        class="text-text-muted hover:text-primary transition-colors cursor-pointer shrink-0"
                        title="Voir la question"
                      >
                        <Eye class="w-5 h-5" />
                      </button>
                      <button
                        @click="toggleReviewedForSession(item.question_id, session)"
                        class="shrink-0 w-5 flex items-center justify-center"
                        :title="item.reviewed ? 'Marquer comme non revue' : 'Marquer comme revue'"
                      >
                        <CheckCircle2 v-if="item.reviewed" class="w-5 h-5 text-success" />
                        <span v-else class="w-5 h-5 rounded-full border border-gray-300 dark:border-gray-600 block" />
                      </button>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- Parcours tab -->
      <div v-else key="parcours" @click="courseOpenMenuJumpId = null">
        <div v-if="!courseStore.courses.length" class="text-sm text-text-muted">Aucun parcours. Créez-en un !</div>
        <template v-else>
          <!-- Course select + action buttons -->
          <div class="flex items-center gap-3 mb-6 flex-wrap">
            <select
              v-model="selectedCourseId"
              class="flex-1 min-w-[200px] px-4 py-2.5 text-base font-medium border border-border rounded-xl dark:bg-gray-900 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary bg-surface cursor-pointer"
            >
              <option v-for="course in courseStore.courses" :key="course.id" :value="course.id">
                {{ course.title }}{{ course.archived ? ' (Archivé)' : '' }}
              </option>
            </select>
            <button
              @click.stop="showNewJumpModal = true"
              :disabled="!selectedCourseId"
              class="flex items-center gap-1 px-3 py-2.5 text-sm bg-primary text-surface rounded-xl hover:bg-primary-hover transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <Plus class="w-4 h-4" />
              Nouveau saut
            </button>
            <button
              @click.stop="selectedCourseId && openSuggestedQuestionsModal(courseStore.courses.find(c => c.id === selectedCourseId))"
              :disabled="!selectedCourseId"
              class="flex items-center gap-1 px-3 py-2.5 text-sm border border-border text-text-muted rounded-xl hover:text-primary hover:border-primary transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <Lightbulb class="w-4 h-4" />
              Questions suggérées
            </button>
            <button
              @click.stop="showNewCourseModal = true"
              class="flex items-center gap-1 px-3 py-2.5 text-sm border border-border text-text-muted rounded-xl hover:text-primary hover:border-primary transition-colors cursor-pointer"
            >
              <Plus class="w-4 h-4" />
              Nouveau parcours
            </button>
          </div>

          <!-- Course details table -->
          <div v-if="courseStore.isLoading && !selectedCourseData" class="text-sm text-text-muted">Chargement...</div>
          <div v-else-if="selectedCourseData" class="bg-surface dark:bg-gray-900 border border-border rounded-xl overflow-hidden">
            <div v-if="!selectedCourseData.jumps?.length" class="p-8 text-center text-text-muted">
              Aucun saut pour ce parcours. Créez le premier !
            </div>
            <template v-else>
              <div class="px-4 py-3 border-b border-border">
                <input
                  v-model="courseStudentSearch"
                  type="text"
                  placeholder="Chercher un élève..."
                  class="w-full max-w-xs px-3 py-1.5 text-sm border border-border rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
                />
              </div>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50 dark:bg-gray-800 border-b border-border">
                    <tr>
                      <th class="px-4 py-3 text-left font-semibold text-text-main dark:text-surface sticky left-0 z-10 bg-gray-50 dark:bg-gray-800 min-w-[250px]">
                        <button @click="setCourseSort('name')" class="flex items-center gap-1 hover:text-primary transition-colors cursor-pointer">
                          Élève
                          <span class="text-xs opacity-50">{{ courseSortKey === 'name' ? (courseSortDir === 'asc' ? '↑' : '↓') : '↕' }}</span>
                        </button>
                      </th>
                      <th class="px-4 py-3 text-center font-semibold text-text-main dark:text-surface">
                        <button @click="setCourseSort('total')" class="flex items-center justify-center gap-1 hover:text-primary transition-colors cursor-pointer w-full">
                          Total
                          <span class="text-xs opacity-50">{{ courseSortKey === 'total' ? (courseSortDir === 'asc' ? '↑' : '↓') : '↕' }}</span>
                        </button>
                      </th>
                      <th
                        v-for="jump in courseJumpsSorted"
                        :key="jump.id"
                        class="px-4 py-3 text-center font-semibold text-text-main dark:text-surface min-w-[120px]"
                      >
                        <div class="flex flex-col items-center gap-1">
                          <div class="flex items-center gap-1">
                            <button @click="setCourseSort(jump.id)" class="flex items-center gap-1 hover:text-primary transition-colors cursor-pointer">
                              Saut {{ jumpNumber(jump) }}
                              <span class="text-xs opacity-50">{{ courseSortKey === jump.id ? (courseSortDir === 'asc' ? '↑' : '↓') : '↕' }}</span>
                            </button>
                            <div class="relative flex items-center">
                              <button
                                @click.stop="courseOpenMenuJumpId = courseOpenMenuJumpId === jump.id ? null : jump.id"
                                class="p-0.5 rounded text-text-muted hover:text-primary hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer"
                              ><MoreVertical class="w-4 h-4" /></button>
                              <div
                                v-if="courseOpenMenuJumpId === jump.id"
                                class="absolute top-full mt-1 left-1/2 -translate-x-1/2 z-20 bg-surface dark:bg-gray-800 border border-border rounded-lg shadow-lg py-1 min-w-[160px] text-left"
                              >
                                <button
                                  @click.stop="openJumpObservation(jump); courseOpenMenuJumpId = null"
                                  class="w-full px-3 py-1.5 text-xs text-text-main dark:text-surface hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer text-left flex items-center gap-2"
                                ><Eye class="w-3.5 h-3.5 shrink-0" /> Voir les détails</button>
                                <button
                                  v-if="jump.status === 'draft'"
                                  @click.stop="activateJump(jump); courseOpenMenuJumpId = null"
                                  class="w-full px-3 py-1.5 text-xs text-success hover:bg-success/10 transition-colors cursor-pointer text-left"
                                >Activer</button>
                                <button
                                  v-if="jump.status === 'active'"
                                  @click.stop="openEditExpiryModal(jump); courseOpenMenuJumpId = null"
                                  class="w-full px-3 py-1.5 text-xs text-primary hover:bg-primary/10 transition-colors cursor-pointer text-left flex items-center gap-2"
                                ><Clock class="w-3.5 h-3.5 shrink-0" /> Modifier l'expiration</button>
                                <button
                                  v-if="jump.status === 'expired'"
                                  @click.stop="reopenJump(jump); courseOpenMenuJumpId = null"
                                  class="w-full px-3 py-1.5 text-xs text-success hover:bg-success/10 transition-colors cursor-pointer text-left flex items-center gap-2"
                                ><RotateCcw class="w-3.5 h-3.5 shrink-0" /> Réouvrir</button>
                                <button
                                  @click.stop="confirmDeleteJump(jump); courseOpenMenuJumpId = null"
                                  class="w-full px-3 py-1.5 text-xs text-error hover:bg-error/10 transition-colors cursor-pointer text-left flex items-center gap-2"
                                ><Trash2 class="w-3.5 h-3.5 shrink-0" /> Supprimer</button>
                              </div>
                            </div>
                          </div>
                          <span class="text-xs text-text-muted font-normal">{{ formatJumpDate(jump.created_at) }}</span>
                          <span
                            class="text-xs px-2 py-0.5 rounded-full"
                            :class="{
                              'bg-warning/15 text-warning': jump.status === 'draft',
                              'bg-success/15 text-success': jump.status === 'active',
                              'bg-primary/15 text-primary': jump.status === 'expiring',
                              'bg-text-muted/15 text-text-muted': jump.status === 'expired',
                            }"
                          >{{ jumpStatusLabel(jump.status) }}</span>
                        </div>
                      </th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-border">
                    <tr v-for="student in courseFilteredSortedStudents" :key="student.id" class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                      <td class="px-4 py-3 font-medium text-text-main dark:text-surface sticky left-0 z-10 bg-surface dark:bg-gray-900 group-hover:bg-gray-50 dark:group-hover:bg-gray-800 transition-colors">
                        {{ student.pivot?.class_name ?? student.name }}
                      </td>
                      <td class="px-4 py-3 text-center font-semibold text-text-main dark:text-surface">
                        {{ courseStudentTotal(student) }}
                      </td>
                      <td
                        v-for="jump in courseJumpsSorted"
                        :key="jump.id"
                        class="px-4 py-3 text-center"
                      >
                        <button
                          v-if="getAttempt(jump, student)"
                          @click="openCourseAttemptDetail(jump, student)"
                          class="font-semibold cursor-pointer hover:underline"
                          :class="jump.status === 'expired' ? 'text-primary' : 'text-text-muted'"
                        >
                          {{ jump.status === 'expired' ? getAttempt(jump, student).score : '—' }}
                        </button>
                        <span v-else class="text-text-muted text-xs">—</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
          </div>
        </template>
      </div>

      </Transition>

    </template>

    <!-- Student View -->
    <template v-else-if="!isTeacher && divisionStore.division">
      <div class="mb-6">
        <h2 class="text-8xl text-center font-bold text-text-main dark:text-surface">{{ divisionStore.division.name }}</h2>
        <p class="text-text-muted text-center text-sm mt-1">{{ divisionStore.division.teacher?.name }}</p>
      </div>

      <!-- Public Suggested Questions -->
      <div v-if="publicSuggestedQuestions.length" class="mb-6 bg-surface dark:bg-gray-900 border border-border rounded-xl p-4">
        <h3 class="font-semibold text-text-main dark:text-surface mb-3 flex items-center gap-2">
          <Lightbulb class="w-4 h-4 text-primary" />
          Questions à revoir
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
          <div
            v-for="(sq, index) in publicSuggestedQuestions"
            :key="sq.id"
            class="group flex items-center justify-between gap-2 p-3 rounded-xl border border-warning/30 bg-warning/5 hover:border-warning hover:shadow-md cursor-pointer transition-all"
            @click="openPublicQuestionOverlay(sq)"
          >
            <div class="flex items-center gap-0.5">
              <span class="mr-1">Question</span>
              <Star v-for="n in sq.level" :key="n" class="w-5 h-5 fill-warning text-warning" />
            </div>
            <span class="text-xs text-text-muted leading-snug">{{ sq.course_title }}</span>
          </div>
        </div>
      </div>

      <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-3">Sessions disponibles</h3>
      <div v-if="!divisionStore.division.kangourou_sessions?.length" class="text-text-muted">
        Aucune session disponible pour le moment.
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <router-link
          v-for="session in divisionStore.division.kangourou_sessions"
          :key="session.id"
          :to="{ name: 'Session', params: { code: session.code } }"
          class="block p-5 rounded-xl border border-border bg-surface dark:bg-gray-900 hover:border-primary hover:shadow-md transition-all"
        >
          <p class="font-semibold text-text-main dark:text-surface">{{ session.paper?.title }}</p>
          <p class="text-sm text-text-muted mt-1">{{ formatTimeUntilExpiration(session.expires_at) }}</p>
        </router-link>
      </div>

      <!-- Courses (student view) -->
      <template v-if="courseStore.courses.length">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mt-8 mb-4">Parcours</h3>

        <!-- Tabs -->
        <div class="flex gap-1 mb-4 border-b border-border overflow-x-auto">
          <button
            v-for="course in courseStore.courses"
            :key="course.id"
            @click="activeCourseId = course.id"
            class="px-4 py-2 text-sm font-medium whitespace-nowrap border-b-2 transition-colors cursor-pointer"
            :class="activeCourse?.id === course.id
              ? 'border-primary text-primary'
              : 'border-transparent text-text-muted hover:text-text-main'"
          >
            {{ course.title }}
          </button>
        </div>

        <!-- Active tab content -->
        <div v-if="activeCourse" class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-5">
          <!-- Tab header -->
          <div class="flex items-center justify-between mb-5">
            <p class="text-3xl font-semibold text-text-main dark:text-surface">{{ activeCourse.title }}</p>
            <p class="text-3xl font-bold text-primary">{{ activeCourseTotal }} pts</p>
            <router-link
              v-if="canStartActiveJump"
              :to="{ name: 'JumpAttempt', params: { jumpId: activeCourseActiveJump.id } }"
              class="px-4 py-2 rounded-lg bg-primary text-surface hover:bg-primary-hover transition-colors text-sm font-medium"
            >
              Saut actif — Commencer
            </router-link>
            <button
              v-else-if="activeJumpRejoinableAttempt && pendingRejoinAttemptId !== activeJumpRejoinableAttempt.id"
              @click="requestRejoinForActiveJump"
              :disabled="jumpAttemptStore.isLoading"
              class="px-4 py-2 rounded-lg bg-info hover:bg-info-hover text-surface text-sm font-medium cursor-pointer transition-colors disabled:opacity-50"
            >
              Demander à reprendre
            </button>
            <span v-else-if="activeJumpRejoinableAttempt" class="text-sm text-text-muted">Demande envoyée</span>
            <span v-else class="text-sm text-text-muted">Aucun saut actif</span>
          </div>

          <!-- Graph toggle -->
          <div class="flex items-center gap-3 mb-4" v-if="activeCourseExpiredJumps.length">
            <span class="text-xs text-text-muted">Score par saut</span>
            <button
              @click="graphCumulative = !graphCumulative"
              :class="[
                'relative inline-flex h-5 w-9 items-center rounded-full transition-colors cursor-pointer',
                graphCumulative ? 'bg-primary' : 'bg-gray-300 dark:bg-gray-600'
              ]"
            >
              <span :class="['inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform', graphCumulative ? 'translate-x-4.5' : 'translate-x-0.5']" />
            </button>
            <span class="text-xs text-text-muted">Total cumulé</span>
          </div>

          <!-- Line graph -->
          <div v-if="activeCourseExpiredJumps.length" ref="svgContainer" class="w-full">
            <svg :viewBox="`0 0 ${svgW} ${svgH}`" class="w-full" style="height:180px" preserveAspectRatio="none">
              <!-- Grid lines -->
              <line v-for="i in 4" :key="i"
                :x1="svgPad" :y1="svgPad + (i - 1) * (svgH - 2 * svgPad) / 3"
                :x2="svgW - svgPad" :y2="svgPad + (i - 1) * (svgH - 2 * svgPad) / 3"
                stroke="currentColor" stroke-opacity="0.08" stroke-width="1"
              />
              <!-- Zero line -->
              <line
                :x1="svgPad" :y1="svgYZero"
                :x2="svgW - svgPad" :y2="svgYZero"
                stroke="currentColor" stroke-opacity="0.15" stroke-width="1"
              />
              <!-- Area fill -->
              <path :d="svgAreaPath" fill="var(--color-primary)" fill-opacity="0.08" />
              <!-- Line -->
              <polyline :points="svgLinePoints" fill="none" stroke="var(--color-primary)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
              <!-- Dots -->
              <g v-for="(pt, i) in svgPoints" :key="i" style="cursor:pointer" @click="openJumpDetail(pt.jump)">
                <circle :cx="pt.x" :cy="pt.y" r="7" fill="var(--color-primary)" fill-opacity="0.15" />
                <circle :cx="pt.x" :cy="pt.y" r="4" fill="var(--color-primary)" />
                <text :x="pt.x" :y="pt.y - 12" text-anchor="middle" font-size="16" fill="currentColor" opacity="0.7">{{ pt.label }}</text>
              </g>
              <!-- X labels -->
              <text v-for="(pt, i) in svgPoints" :key="'l' + i"
                :x="pt.x" :y="svgH - 4"
                text-anchor="middle" font-size="14" fill="currentColor" opacity="0.6"
              >S{{ i + 1 }}</text>
            </svg>
          </div>
          <p v-else class="text-sm text-text-muted text-center py-6">Aucun saut terminé pour l'instant.</p>
        </div>
      </template>
    </template>

    <!-- Jump Detail Modal (student) -->
    <div
      v-if="showJumpDetailModal && selectedJumpDetail"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
      @click.self="showJumpDetailModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-border">
          <div>
            <h3 class="text-lg font-semibold text-text-main dark:text-surface">Saut {{ jumpDetailNumber(selectedJumpDetail.jump) }}</h3>
            <p class="text-sm text-text-muted">{{ formatJumpDate(selectedJumpDetail.jump.created_at) }}</p>
          </div>
          <button @click="showJumpDetailModal = false" class="text-text-muted hover:text-text-main transition-colors cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>
        <div v-if="jumpDetailLoading" class="flex items-center justify-center p-10">
          <RefreshCw class="w-6 h-6 animate-spin text-text-muted" />
        </div>
        <div v-else class="p-4 overflow-y-auto flex-1 space-y-3">
          <p class="text-center text-3xl font-bold text-primary mb-1">Score : {{ selectedJumpDetail.attempt.score }}</p>
          <div
            v-for="(item, idx) in selectedJumpDetail.attempt.question_list"
            :key="idx"
            class="flex flex-col rounded-lg border overflow-hidden"
            :class="{
              'border-success': item.status === 'correct',
              'border-error': item.status === 'incorrect',
              'border-border': item.status === 'pending',
            }"
          >
            <img
              v-if="item.image"
              :src="'/' + item.image"
              :alt="'Question ' + (idx + 1)"
              class="w-full object-contain bg-gray-50 dark:bg-gray-800 select-none pointer-events-none"
              draggable="false"
              oncontextmenu="return false;"
            />
            <div
              class="flex items-center justify-between p-3"
              :class="{
                'bg-success/5': item.status === 'correct',
                'bg-error/5': item.status === 'incorrect',
              }"
            >
              <div class="flex items-center gap-3">
                <span
                  class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0"
                  :class="{
                    'bg-success text-white': item.status === 'correct',
                    'bg-error text-white': item.status === 'incorrect',
                    'bg-gray-200 dark:bg-gray-700 text-text-muted': item.status === 'pending',
                  }"
                >{{ idx + 1 }}</span>
                <div>
                  <p class="text-sm font-medium text-text-main dark:text-surface">Question #{{ item.id }}</p>
                  <p class="text-xs text-text-muted">Difficulté : {{ item.difficulty }}</p>
                </div>
              </div>
              <div class="text-right">
                <p class="text-sm font-medium"
                  :class="{
                    'text-success': item.status === 'correct',
                    'text-error': item.status === 'incorrect',
                    'text-text-muted': item.status === 'pending',
                  }"
                >
                  {{ item.status === 'correct' ? '+' + item.difficulty : item.status === 'incorrect' ? '0' : '—' }}
                </p>
                <p v-if="item.answer" class="text-xs text-text-muted">Réponse : {{ item.answer }}</p>
                <p v-if="item.status === 'incorrect' && item.correct_answer" class="text-xs text-success font-medium">Correcte : {{ item.correct_answer }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Expired Session Attempts Modal -->
    <div
      v-if="showExpiredSessionModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-6"
      @click.self="closeExpiredSessionModal"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-7xl h-[80vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-border gap-4">
          <h3 class="text-lg font-semibold text-text-main dark:text-surface shrink-0">{{ expiredSessionDetail?.paper?.title }}</h3>
          <div class="relative flex-1 max-w-xs">
            <input
              v-model="sessionStudentSearch"
              type="text"
              placeholder="Chercher un élève…"
              class="w-full px-3 py-1.5 pr-8 text-sm border border-border rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
            />
            <button
              v-if="sessionStudentSearch"
              @click="sessionStudentSearch = ''"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-text-muted hover:text-text-main transition-colors cursor-pointer"
            ><X class="w-3.5 h-3.5" /></button>
          </div>
          <button @click="closeExpiredSessionModal" class="text-text-muted hover:text-text-main transition-colors cursor-pointer shrink-0">
            <X class="w-5 h-5" />
          </button>
        </div>
        <div class="p-4 overflow-y-auto flex-1">
          <DivisionAttemptsTable
            :students="sessionFilteredStudents"
            :attempts="expiredSessionDetail?.attempts ?? []"
            :loading="isLoadingExpiredSession"
            @delete="openDeleteAttemptModal($event, 'expired')"
          />
        </div>
      </div>
    </div>

    <!-- Active Session Attempts Modal -->
    <div
      v-if="showActiveSessionModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-6"
      @click.self="closeActiveSessionModal"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-7xl h-[80vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-border gap-4">
          <h3 class="text-lg font-semibold text-text-main dark:text-surface shrink-0">{{ activeSessionDetail?.paper?.title }}</h3>
          <div class="relative flex-1 max-w-xs">
            <input
              v-model="sessionStudentSearch"
              type="text"
              placeholder="Chercher un élève…"
              class="w-full px-3 py-1.5 pr-8 text-sm border border-border rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
            />
            <button
              v-if="sessionStudentSearch"
              @click="sessionStudentSearch = ''"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-text-muted hover:text-text-main transition-colors cursor-pointer"
            ><X class="w-3.5 h-3.5" /></button>
          </div>
          <button @click="closeActiveSessionModal" class="text-text-muted hover:text-text-main transition-colors cursor-pointer shrink-0">
            <X class="w-5 h-5" />
          </button>
        </div>
        <div class="p-4 overflow-y-auto flex-1">
          <DivisionAttemptsTable
            :students="sessionFilteredStudents"
            :attempts="activeSessionDetail?.attempts ?? []"
            :loading="isLoadingActiveSession"
            @delete="openDeleteAttemptModal($event, 'active')"
          />
        </div>
      </div>
    </div>

    <!-- New Jump Modal -->
    <div
      v-if="showNewJumpModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-6"
      @click.self="showNewJumpModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-4">Nouveau saut</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Durée (minutes)</label>
            <input v-model.number="newJump.time" type="number" min="1" class="w-full px-3 py-2 border border-border rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Nombre de questions</label>
            <input v-model.number="newJump.nb_questions" type="number" min="1" class="w-full px-3 py-2 border border-border rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Progression (sauts)</label>
            <input v-model.number="newJump.growth" type="number" min="0" class="w-full px-3 py-2 border border-border rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm" />
          </div>
          <!-- <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-text-main dark:text-surface/80">Questions automatiques</span>
            <button
              @click="newJump.autoQuestions = !newJump.autoQuestions"
              :class="['relative inline-flex h-6 w-11 items-center rounded-full transition-colors cursor-pointer', newJump.autoQuestions ? 'bg-success' : 'bg-gray-300 dark:bg-gray-600']"
            >
              <span :class="['inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow', newJump.autoQuestions ? 'translate-x-6' : 'translate-x-1']" />
            </button>
          </div> -->
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Statut</label>
            <select v-model="newJump.status" class="w-full px-3 py-2 border border-border rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm cursor-pointer">
              <option value="draft">Brouillon</option>
              <option value="active">Actif</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="showNewJumpModal = false" class="px-4 py-2 text-sm text-text-muted border border-border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer">Annuler</button>
          <button @click="handleCreateJump" class="px-4 py-2 text-sm bg-primary text-surface rounded-lg hover:bg-primary-hover transition-colors cursor-pointer">Créer</button>
        </div>
      </div>
    </div>

    <!-- Edit Expiry Modal -->
    <div
      v-if="showEditExpiryModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-6"
      @click.self="showEditExpiryModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-4">Modifier l'expiration</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Date d'expiration</label>
            <input v-model="editExpiryValue" type="datetime-local" class="w-full px-3 py-2 border border-border rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm" />
          </div>
          <button @click="handleExpireNow" class="w-full px-4 py-2 text-sm text-error border border-error/40 rounded-lg hover:bg-error/10 transition-colors cursor-pointer">Expirer maintenant</button>
        </div>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="showEditExpiryModal = false" class="px-4 py-2 text-sm text-text-muted border border-border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer">Annuler</button>
          <button @click="handleSaveExpiry" class="px-4 py-2 text-sm bg-primary text-surface rounded-lg hover:bg-primary-hover transition-colors cursor-pointer">Enregistrer</button>
        </div>
      </div>
    </div>

    <!-- Delete Jump Confirm -->
    <div
      v-if="showDeleteJumpConfirm"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-6"
      @click.self="showDeleteJumpConfirm = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-2">Supprimer ce saut ?</h3>
        <p class="text-sm text-text-muted mb-5">Cette action est irréversible. Toutes les tentatives associées seront supprimées.</p>
        <div class="flex justify-end gap-2">
          <button @click="showDeleteJumpConfirm = false" class="px-4 py-2 text-sm text-text-muted border border-border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer">Annuler</button>
          <button @click="handleDeleteJump" class="px-4 py-2 text-sm bg-error text-surface rounded-lg hover:bg-error/80 transition-colors cursor-pointer">Supprimer</button>
        </div>
      </div>
    </div>

    <!-- Jump Observation Modal -->
    <div
      v-if="showJumpObservationModal && observingJumpData"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-6"
      @click.self="closeJumpObservation"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-border">
          <h3 class="text-lg font-semibold text-text-main dark:text-surface">
            Saut {{ jumpNumber(observingJumpData) }} — {{ observingJumpData.status === 'active' || observingJumpData.status === 'expiring' ? 'En cours' : 'Terminé' }}
          </h3>
          <button @click="closeJumpObservation" class="text-text-muted hover:text-text-main transition-colors cursor-pointer"><X class="w-5 h-5" /></button>
        </div>
        <div class="p-4 overflow-y-auto flex-1">
          <div v-if="!selectedCourseData?.students?.length" class="text-sm text-text-muted text-center py-8">Aucun élève.</div>
          <ul v-else class="space-y-2">
            <li
              v-for="student in selectedCourseData.students"
              :key="student.id"
              class="flex items-center justify-between py-1.5 border-b border-border last:border-0"
            >
              <span class="text-sm font-medium text-text-main dark:text-surface">{{ student.pivot?.class_name ?? student.name }}</span>
              <div class="text-sm">
                <template v-if="getAttempt(observingJumpData, student)">
                  <span v-if="observingJumpData.status === 'expired'" class="font-semibold text-primary">{{ getAttempt(observingJumpData, student).score }}</span>
                  <span v-else class="text-success font-medium">En cours</span>
                </template>
                <span v-else class="text-text-muted">—</span>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Course Attempt Detail Modal -->
    <div
      v-if="showCourseAttemptDetailModal && selectedCourseAttemptDetail"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-6"
      @click.self="showCourseAttemptDetailModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-text-main dark:text-surface">Détail de la tentative</h3>
          <button @click="showCourseAttemptDetailModal = false" class="text-text-muted hover:text-text-main cursor-pointer"><X class="w-5 h-5" /></button>
        </div>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-text-muted">Élève</span>
            <span class="font-medium text-text-main dark:text-surface">{{ selectedCourseAttemptDetail.student?.pivot?.class_name ?? selectedCourseAttemptDetail.student?.name }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-text-muted">Saut</span>
            <span class="font-medium text-text-main dark:text-surface">Saut {{ jumpNumber(selectedCourseAttemptDetail.jump) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-text-muted">Score</span>
            <span class="font-semibold text-primary text-lg">{{ selectedCourseAttemptDetail.attempt?.score }}</span>
          </div>
          <div v-if="selectedCourseAttemptDetail.attempt?.completed_at" class="flex justify-between">
            <span class="text-text-muted">Complété le</span>
            <span class="font-medium text-text-main dark:text-surface">{{ formatJumpDate(selectedCourseAttemptDetail.attempt.completed_at) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Question Image Overlay -->
    <div
      v-if="showQuestionOverlay && selectedAnalysisQuestion"
      class="fixed inset-0 bg-black/80 flex flex-col items-center justify-center z-60 p-6 gap-4"
      @click.self="closeQuestionOverlay"
    >
      <img
        :src="'/' + selectedAnalysisQuestion.image"
        :alt="`Question ${selectedAnalysisQuestion.questionNumber}`"
        class="max-h-[80vh] object-contain rounded-lg"
        style="width: 80vw;"
      />
      <div class="flex items-center gap-4">
        <button
          v-if="!revealAnswer"
          @click="revealAnswer = true"
          class="px-4 py-2 bg-primary hover:bg-primary-hover text-surface rounded-lg text-sm font-medium transition-colors cursor-pointer"
        >
          Révéler la réponse
        </button>
        <div v-else class="px-6 py-3 bg-surface dark:bg-gray-900 rounded-xl text-2xl font-bold text-success">
          {{ selectedAnalysisQuestion.correct_answer }}
        </div>
        <button
          @click="toggleReviewed(selectedAnalysisQuestion.question_id)"
          :class="[
            'px-4 py-2 rounded-lg text-sm font-medium transition-colors cursor-pointer',
            selectedAnalysisQuestion.reviewed
              ? 'bg-success/20 text-success hover:bg-success/30'
              : 'bg-gray-100 dark:bg-gray-700 text-text-muted hover:bg-gray-200 dark:hover:bg-gray-600',
          ]"
        >
          {{ selectedAnalysisQuestion.reviewed ? 'Revue ✓' : 'Marquer comme revue' }}
        </button>
        <button @click="closeQuestionOverlay" class="text-gray-400 hover:text-white transition-colors cursor-pointer">
          <X class="w-6 h-6" />
        </button>
      </div>
    </div>

    <!-- Edit Division Modal -->
    <div
      v-if="showEditDivisionModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showEditDivisionModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-4">Modifier la classe</h3>
        <form @submit.prevent="handleEditDivisionSave" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Nom</label>
            <input
              v-model="editName"
              type="text"
              required
              class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Code d'invitation</label>
            <div class="flex items-center gap-2">
              <span class="flex-1 font-mono font-bold text-2xl text-text-main dark:text-surface">{{ divisionStore.division?.code }}</span>
              <button
                type="button"
                @click="showChangeCodeConfirm = true; showEditDivisionModal = false"
                :disabled="divisionStore.division?.archived"
                title="Générer un nouveau code"
                class="p-2 text-text-muted hover:text-primary transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <RefreshCw class="w-4 h-4" />
              </button>
            </div>
          </div>
          <div v-if="divisionStore.error" class="bg-error/10 border border-error/30 text-error px-3 py-2 rounded-lg text-sm">
            {{ divisionStore.error }}
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" @click="showEditDivisionModal = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors cursor-pointer">
              Annuler
            </button>
            <button
              type="submit"
              :disabled="divisionStore.isLoading"
              class="px-4 py-2 bg-primary hover:bg-primary-hover text-surface rounded-lg text-sm font-medium disabled:opacity-50 transition-colors cursor-pointer"
            >
              Enregistrer
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Archive Confirmation Modal -->
    <div
      v-if="showArchiveConfirm"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showArchiveConfirm = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-2">Archiver la classe ?</h3>
        <p class="text-sm text-text-muted mb-4">Une fois archivée, cette classe ne sera plus accessible et toutes les sessions et invitations seront masquées.</p>
        <div class="flex justify-end gap-2">
          <button @click="showArchiveConfirm = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors cursor-pointer">
            Annuler
          </button>
          <button
            @click="handleArchive"
            :disabled="divisionStore.isLoading"
            class="px-4 py-2 bg-warning text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors cursor-pointer"
          >
            Archiver
          </button>
        </div>
      </div>
    </div>

    <!-- Unarchive Confirmation Modal -->
    <div
      v-if="showUnarchiveConfirm"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showUnarchiveConfirm = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-2">Activer la classe ?</h3>
        <p class="text-sm text-text-muted mb-4">Cette classe deviendra à nouveau active et toutes les sessions et invitations seront visibles.</p>
        <div class="flex justify-end gap-2">
          <button @click="showUnarchiveConfirm = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors cursor-pointer">
            Annuler
          </button>
          <button
            @click="handleUnarchive"
            :disabled="divisionStore.isLoading"
            class="px-4 py-2 bg-success text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors cursor-pointer"
          >
            Activer
          </button>
        </div>
      </div>
    </div>

    <!-- Change Code Confirmation Modal -->
    <div
      v-if="showChangeCodeConfirm"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showChangeCodeConfirm = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-2">Changer le code d'invitation ?</h3>
        <p class="text-sm text-text-muted mb-4">Un nouveau code sera généré. L'ancien code ne fonctionnera plus.</p>
        <div class="flex justify-end gap-2">
          <button @click="showChangeCodeConfirm = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors cursor-pointer">
            Annuler
          </button>
          <button
            @click="handleChangeCode"
            :disabled="divisionStore.isLoading"
            class="px-4 py-2 bg-primary hover:bg-primary-hover text-surface rounded-lg text-sm font-medium disabled:opacity-50 transition-colors cursor-pointer"
          >
            Changer
          </button>
        </div>
      </div>
    </div>

    <!-- Edit Class Name Modal -->
    <div
      v-if="showEditClassNameModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showEditClassNameModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-4">Modifier le nom de classe</h3>
        <form @submit.prevent="handleEditClassName" class="space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Prénom</label>
              <input
                v-model="editClassFirstName"
                type="text"
                required
                class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Nom</label>
              <input
                v-model="editClassLastName"
                type="text"
                required
                class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm"
              />
            </div>
          </div>
          <div v-if="divisionStore.error" class="bg-error/10 border border-error/30 text-error px-3 py-2 rounded-lg text-sm">
            {{ divisionStore.error }}
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" @click="showEditClassNameModal = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors cursor-pointer">
              Annuler
            </button>
            <button
              type="submit"
              :disabled="divisionStore.isLoading"
              class="px-4 py-2 bg-primary hover:bg-primary-hover text-surface rounded-lg text-sm font-medium disabled:opacity-50 transition-colors cursor-pointer"
            >
              Enregistrer
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Remove Student Confirmation Modal -->
    <div
      v-if="showRemoveStudentConfirm"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showRemoveStudentConfirm = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-2">Retirer l'élève ?</h3>
        <p class="text-sm text-text-muted mb-4">L'élève sera retiré de la classe. Il pourra la rejoindre en utilisant le code.</p>
        <div class="flex justify-end gap-2">
          <button @click="showRemoveStudentConfirm = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors cursor-pointer">
            Annuler
          </button>
          <button
            @click="() => { handleRemoveStudent(studentIdToRemove); }"
            :disabled="divisionStore.isLoading"
            class="px-4 py-2 bg-error text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors cursor-pointer"
          >
            Retirer
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Modal -->
    <!-- Delete Attempt Modal -->
    <div
      v-if="showDeleteAttemptModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="closeDeleteAttemptModal"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-xl space-y-5">
        <h3 class="text-lg font-bold text-text-main dark:text-surface">Supprimer la tentative ?</h3>
        <p class="text-sm text-text-muted">
          Êtes-vous sûr de vouloir supprimer la tentative de <span class="font-semibold">{{ selectedAttempt?.name || `Utilisateur #${selectedAttempt?.user_id}` }}</span> ?
        </p>
        <label class="flex items-center gap-3 p-3 bg-error/10 border border-error/30 rounded-lg">
          <input v-model="deleteAttemptConfirmed" type="checkbox" class="w-4 h-4" />
          <span class="text-sm text-error font-medium cursor-pointer">Je confirme la suppression</span>
        </label>
        <div class="flex gap-3">
          <button
            @click="closeDeleteAttemptModal"
            class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors cursor-pointer"
          >
            Annuler
          </button>
          <button
            @click="confirmDeleteAttempt"
            :disabled="isDeletingAttempt || !deleteAttemptConfirmed"
            class="flex-1 px-4 py-2 rounded-lg bg-error hover:bg-error-hover text-surface font-medium transition-colors disabled:opacity-50 cursor-pointer"
          >
            {{ isDeletingAttempt ? 'Suppression...' : 'Supprimer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Join Class Info Modal -->
    <div
      v-if="showJoinInfoModal"
      class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center"
      @click.self="showJoinInfoModal = false"
    >
      <div class="relative bg-surface dark:bg-gray-900 rounded-2xl p-12 max-w-3xl w-full mx-4 shadow-2xl flex flex-col items-center gap-6">
        <button
          @click="showJoinInfoModal = false"
          class="absolute top-4 right-4 text-text-muted hover:text-text-main dark:text-surface/60 dark:hover:text-surface transition-colors cursor-pointer"
        >
          <X class="w-6 h-6" />
        </button>

        <p class="text-5xl font-semibold text-text-muted tracking-wide mb-8">hubaroo.online</p>

        <div class="flex items-center gap-4 w-full">
          <img
            :src="'/mes classes loc.png'"
            alt="Page Mes classes"
            class="flex-1 max-w-[400px] object-contain"
          />
          <ChevronRight class="w-8 h-8 text-text-muted shrink-0" />
          <div class="px-4 py-2 rounded-lg bg-primary text-surface font-medium text-lg shrink-0">
            Rejoindre une classe
          </div>
        </div>

        <p class="font-mono font-black text-8xl tracking-widest text-primary">{{ divisionStore.division?.code }}</p>
      </div>
    </div>

    <!-- New Course Modal -->
    <div
      v-if="showNewCourseModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showNewCourseModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-4">Nouveau parcours</h3>
        <form @submit.prevent="handleCreateCourse" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Titre</label>
            <input
              v-model="newCourseTitle"
              type="text"
              required
              class="w-full px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm"
            />
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" @click="showNewCourseModal = false" class="px-4 py-2 text-sm text-text-muted cursor-pointer hover:text-text-main transition-colors">Annuler</button>
            <button type="submit" :disabled="courseStore.isLoading" class="px-4 py-2 bg-primary hover:bg-primary-hover text-surface rounded-lg text-sm font-medium disabled:opacity-50 cursor-pointer transition-colors">Créer</button>
          </div>
        </form>
      </div>
    </div>

    <div
      v-if="showDeleteConfirm"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showDeleteConfirm = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-text-main dark:text-surface mb-2">Supprimer la classe ?</h3>
        <p class="text-sm text-text-muted mb-4">Cette action est irréversible. Tous les élèves seront retirés.</p>
        <div class="flex justify-end gap-2">
          <button @click="showDeleteConfirm = false" class="px-4 py-2 text-sm text-text-muted hover:text-text-main transition-colors cursor-pointer">
            Annuler
          </button>
          <button
            @click="handleDelete"
            :disabled="divisionStore.isLoading"
            class="px-4 py-2 bg-error text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors cursor-pointer"
          >
            Supprimer
          </button>
        </div>
      </div>
    </div>

    <!-- Suggested Questions Modal (teacher) -->
    <div
      v-if="showSuggestedQuestionsModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="closeSuggestedQuestionsModal"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-2xl h-[90vh] flex flex-col">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-text-main dark:text-surface flex items-center gap-2">
            <Lightbulb class="w-5 h-5 text-primary" />
            Questions suggérées — {{ suggestedQuestionsCourseName }}
          </h3>
          <button @click="closeSuggestedQuestionsModal" class="text-text-muted hover:text-text-main transition-colors cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>
        <div v-if="isLoadingSuggestedQuestions" class="flex justify-center py-8">
          <span class="text-text-muted text-sm">Chargement...</span>
        </div>
        <div v-else-if="!suggestedQuestions.length" class="py-8 text-center text-text-muted text-sm">
          Aucune question suggérée pour l'instant.
        </div>
        <div v-else class="flex flex-col min-h-0 flex-1">
          <!-- Tabs -->
          <div class="flex border-b border-border mb-4">
            <button
              v-for="level in [1, 2, 3]"
              :key="level"
              @click="suggestedQuestionsTab = level"
              class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium transition-colors cursor-pointer border-b-2 -mb-px"
              :class="suggestedQuestionsTab === level
                ? 'border-primary text-primary'
                : 'border-transparent text-text-muted hover:text-text-main'"
            >
              <Star v-for="n in level" :key="n" class="w-3.5 h-3.5 fill-warning text-warning" />
              <span>Niveau {{ level }}</span>
              <span class="ml-1 text-xs px-1.5 py-0.5 rounded-full"
                :class="suggestedQuestionsTab === level ? 'bg-primary/10 text-primary' : 'bg-gray-100 dark:bg-gray-800 text-text-muted'"
              >{{ suggestedQuestionsByLevel(level).length }}</span>
            </button>
          </div>
          <!-- Tab content -->
          <div class="overflow-y-auto space-y-2">
            <div v-if="suggestedQuestionsByLevel(suggestedQuestionsTab).length === 0" class="py-8 text-center text-text-muted text-sm">
              Aucune question pour ce niveau.
            </div>
            <div
              v-for="sq in suggestedQuestionsByLevel(suggestedQuestionsTab)"
              :key="sq.id"
              class="rounded-lg border border-border hover:border-primary/40 transition-colors overflow-hidden"
            >
              <button
                @click="openSuggestedQuestionOverlay(sq)"
                class="w-full cursor-pointer group"
              >
                <img
                  v-if="sq.question.image"
                  :src="'/' + sq.question.image"
                  class="w-full object-contain group-hover:opacity-80 transition-opacity"
                  alt="Question"
                />
                <span v-else class="text-sm text-text-muted p-3 block">Question #{{ sq.question.id }}</span>
              </button>
              <div class="flex justify-end gap-1 px-2 py-1.5 border-t border-border">
                <button
                  @click="handleToggleSuggestedPublic(sq)"
                  :class="['p-1.5 rounded-lg transition-colors cursor-pointer', sq.is_public ? 'text-primary bg-primary/10' : 'text-text-muted hover:text-primary hover:bg-primary/10']"
                  :title="sq.is_public ? 'Retirer de la vue élèves' : 'Rendre public'"
                >
                  <Globe class="w-4 h-4" />
                </button>
                <button
                  @click="handleDeleteSuggestedQuestion(sq)"
                  class="p-1.5 rounded-lg text-text-muted hover:text-error hover:bg-error/10 transition-colors cursor-pointer"
                  title="Supprimer"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Suggested Question Overlay (teacher) -->
    <div
      v-if="showSuggestedQuestionOverlay && selectedSuggestedQuestion"
      class="fixed inset-0 bg-black/80 flex items-center justify-center z-[60]"
      @click.self="closeSuggestedQuestionOverlay"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-2xl p-6 w-full max-w-[80vw] flex flex-col items-center gap-4">
        <button @click="closeSuggestedQuestionOverlay" class="self-end text-text-muted hover:text-text-main transition-colors cursor-pointer">
          <X class="w-5 h-5" />
        </button>
        <img
          v-if="selectedSuggestedQuestion.question.image"
          :src="'/' + selectedSuggestedQuestion.question.image"
          class="max-h-64 object-contain"
          alt="Question"
        />
        <div v-if="revealSuggestedAnswer" class="text-xl font-bold text-success">
          Réponse : {{ selectedSuggestedQuestion.question.correct_answer }}
        </div>
        <div class="flex gap-3">
          <button
            v-if="!revealSuggestedAnswer"
            @click="revealSuggestedAnswer = true"
            class="px-4 py-2 bg-primary hover:bg-primary-hover text-surface rounded-lg text-sm font-medium transition-colors cursor-pointer"
          >
            Révéler la réponse
          </button>
          <button
            @click="handleToggleSuggestedPublic(selectedSuggestedQuestion)"
            :class="['px-4 py-2 rounded-lg text-sm font-medium transition-colors cursor-pointer flex items-center gap-2', selectedSuggestedQuestion.is_public ? 'bg-primary/10 text-primary hover:bg-primary/20' : 'bg-gray-100 dark:bg-gray-800 text-text-muted hover:text-primary']"
          >
            <Globe class="w-4 h-4" />
            {{ selectedSuggestedQuestion.is_public ? 'Retirer' : 'Rendre public' }}
          </button>
          <button
            @click="handleDeleteSuggestedQuestion(selectedSuggestedQuestion); closeSuggestedQuestionOverlay()"
            class="px-4 py-2 bg-error/10 text-error hover:bg-error/20 rounded-lg text-sm font-medium transition-colors cursor-pointer flex items-center gap-2"
          >
            <Trash2 class="w-4 h-4" />
            Supprimer
          </button>
        </div>
      </div>
    </div>

    <!-- Public Question Overlay (student) -->
    <div
      v-if="showPublicQuestionOverlay && selectedPublicQuestion"
      class="fixed inset-0 bg-black/80 flex items-center justify-center z-50"
      @click.self="closePublicQuestionOverlay"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-2xl p-6 w-full max-w-[80vw] flex flex-col items-center gap-4">
        <button @click="closePublicQuestionOverlay" class="self-end text-text-muted hover:text-text-main transition-colors cursor-pointer">
          <X class="w-5 h-5" />
        </button>
        <div class="flex items-center gap-1 self-start">
          <Star v-for="n in selectedPublicQuestion.level" :key="n" class="w-4 h-4 fill-warning text-warning" />
          <span class="text-xs text-text-muted ml-1">{{ selectedPublicQuestion.course_title }}</span>
        </div>
        <img
          v-if="selectedPublicQuestion.question.image"
          :src="'/' + selectedPublicQuestion.question.image"
          class="max-h-64 object-contain"
          alt="Question"
        />
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/authStore';
import { useDivisionStore } from '@/stores/divisionStore';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';
import { useCourseStore } from '@/stores/courseStore';
import { useJumpAttemptStore } from '@/stores/jumpAttemptStore';
import { useJumpRejoinDemandStore } from '@/stores/jumpRejoinDemandStore';
import DivisionAttemptsTable from '@/components/DivisionAttemptsTable.vue';
import { ChevronLeft, RefreshCw, X, Eye, Pencil, Fullscreen, ChevronRight, Lightbulb, Star, Trash2, Globe, MoreVertical, Archive, ArchiveRestore, Plus, Clock, RotateCcw, CheckCircle2 } from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const divisionStore = useDivisionStore();
const sessionStore = useKangourouSessionStore();
const courseStore = useCourseStore();
const jumpAttemptStore = useJumpAttemptStore();
const jumpRejoinDemandStore = useJumpRejoinDemandStore();

const showNewCourseModal = ref(false);
const newCourseTitle = ref('');

// Header dropdown menu
const showHeaderMenu = ref(false);
const showEditDivisionModal = ref(false);

function openEditDivisionModal() {
  editName.value = divisionStore.division?.name ?? '';
  showHeaderMenu.value = false;
  showEditDivisionModal.value = true;
}

async function handleEditDivisionSave() {
  await divisionStore.updateDivision(divisionId.value, { name: editName.value });
  if (!divisionStore.error) {
    showEditDivisionModal.value = false;
  }
}

// Student course tabs & graph
const activeCourseId = ref(null);
const graphCumulative = ref(true);

const showJumpDetailModal = ref(false);
const selectedJumpDetail = ref(null);
const jumpDetailLoading = ref(false);

const activeCourse = computed(() => {
  if (!courseStore.courses.length) return null;
  if (activeCourseId.value) {
    return courseStore.courses.find(c => c.id === activeCourseId.value) ?? courseStore.courses[0];
  }
  // Default: course with the highest jump id
  let best = courseStore.courses[0];
  let bestJumpId = -1;
  for (const course of courseStore.courses) {
    for (const jump of course.jumps ?? []) {
      if (jump.id > bestJumpId) {
        bestJumpId = jump.id;
        best = course;
      }
    }
  }
  return best;
});

const activeCourseActiveJump = computed(() =>
  activeCourse.value?.jumps?.find(j => j.status === 'active' || j.status === 'expiring') ?? null
);

// Show the button only if the student has no attempt yet, or was approved to rejoin (status back to inProgress)
const canStartActiveJump = computed(() => {
  const jump = activeCourseActiveJump.value;
  if (!jump || jump.status !== 'active') return false;
  const attempt = jump.attempts?.[0];
  return !attempt || attempt.status === 'inProgress';
});

// Show rejoin button if the student finished with blurred/submitted (not timeout)
const activeJumpRejoinableAttempt = computed(() => {
  const jump = activeCourseActiveJump.value;
  if (!jump || jump.status !== 'active') return null;
  const attempt = jump.attempts?.[0];
  if (!attempt || attempt.status !== 'finished') return null;
  if (attempt.termination === 'timeout') return null;
  return attempt;
});

const pendingRejoinAttemptId = ref(null);

async function requestRejoinForActiveJump() {
  const attempt = activeJumpRejoinableAttempt.value;
  if (!attempt) return;
  try {
    const data = await jumpAttemptStore.createRejoinDemand(attempt.id);
    const demandId = data?.id;
    if (demandId) {
      pendingRejoinAttemptId.value = attempt.id;
      jumpRejoinDemandStore.addStudentDemand({
        id: demandId,
        attemptId: attempt.id,
        jumpId: activeCourseActiveJump.value.id,
      });
    }
  } catch {
    // error handled by store
  }
}

const activeCourseExpiredJumps = computed(() =>
  (activeCourse.value?.jumps ?? []).filter(j => j.status === 'expired')
);

const activeCourseTotal = computed(() =>
  activeCourseExpiredJumps.value.reduce((sum, j) => sum + (j.attempts?.[0]?.score ?? 0), 0)
);

// SVG graph constants
const svgW = ref(600);
const svgContainer = ref(null);
const svgH = 160;
const svgPad = 30;
let svgResizeObserver = null;
let activeJumpChannelId = null;

function subscribeToActiveJump(jumpId) {
  if (activeJumpChannelId === jumpId) return;
  if (activeJumpChannelId) {
    window.Echo.leave(`jump.${activeJumpChannelId}`);
  }
  activeJumpChannelId = jumpId;
  window.Echo.channel(`jump.${jumpId}`)
    .listen('.JumpExpired', async () => {
      activeJumpChannelId = null;
      await courseStore.fetchCourses(divisionId.value);
    });
}

const svgGraphValues = computed(() => {
  const jumps = activeCourseExpiredJumps.value;
  if (!jumps.length) return [];
  const scores = jumps.map(j => j.attempts?.[0]?.score ?? 0);
  if (graphCumulative.value) {
    let acc = 0;
    return scores.map(s => { acc += s; return acc; });
  }
  return scores;
});

const svgYZero = computed(() => {
  const vals = svgGraphValues.value;
  if (!vals.length) return svgH / 2;
  const minVal = Math.min(0, ...vals);
  const maxVal = Math.max(0, ...vals);
  const range = maxVal - minVal || 1;
  return svgPad + ((maxVal - 0) / range) * (svgH - 2 * svgPad);
});

const svgPoints = computed(() => {
  const vals = svgGraphValues.value;
  if (!vals.length) return [];
  const n = vals.length;
  const minVal = Math.min(0, ...vals);
  const maxVal = Math.max(0, ...vals);
  const range = maxVal - minVal || 1;
  const xStep = n > 1 ? (svgW.value - 2 * svgPad) / (n - 1) : 0;
  return vals.map((v, i) => ({
    x: svgPad + i * xStep,
    y: svgPad + ((maxVal - v) / range) * (svgH - 2 * svgPad),
    label: v,
    jump: activeCourseExpiredJumps.value[i],
  }));
});

const svgLinePoints = computed(() =>
  svgPoints.value.map(p => `${p.x},${p.y}`).join(' ')
);

const svgAreaPath = computed(() => {
  const pts = svgPoints.value;
  if (!pts.length) return '';
  const zero = svgYZero.value;
  const first = pts[0];
  const last = pts[pts.length - 1];
  return `M${first.x},${zero} ` + pts.map(p => `L${p.x},${p.y}`).join(' ') + ` L${last.x},${zero} Z`;
});

const divisionId = computed(() => Number(route.params.id));
const isTeacher = computed(() => {
  const div = divisionStore.division;
  return div && authStore.user && div.teacher_id === authStore.user.id;
});

const editName = ref('');
const inviteEmail = ref('');
const inviteSuccess = ref(false);
const showDeleteConfirm = ref(false);
const showArchiveConfirm = ref(false);
const showUnarchiveConfirm = ref(false);
const showChangeCodeConfirm = ref(false);
const showJoinInfoModal = ref(false);
const showRemoveStudentConfirm = ref(false);
const studentIdToRemove = ref(null);
const showEditClassNameModal = ref(false);
const editClassStudentId = ref(null);
const editClassFirstName = ref('');
const editClassLastName = ref('');
const showExpiredSessionModal = ref(false);
const expiredSessionDetail = ref(null);
const isLoadingExpiredSession = ref(false);
const expiredSessionChannelId = ref(null);
const showActiveSessionModal = ref(false);
const activeSessionDetail = ref(null);
const isLoadingActiveSession = ref(false);
const activeSessionChannelId = ref(null);
const showDeleteAttemptModal = ref(false);
const selectedAttempt = ref(null);
const selectedAttemptContext = ref(null);
const deleteAttemptConfirmed = ref(false);
const isDeletingAttempt = ref(false);

const analysisModalSession = ref(null);
const selectedAnalysisQuestion = ref(null);
const showQuestionOverlay = ref(false);
const revealAnswer = ref(false);

// Tab state
const tabs = ['eleves', 'sessions', 'parcours'];
const activeTab = ref('eleves');
const tabSlideDirection = ref('tab-slide-left');
function switchTab(id) {
  tabSlideDirection.value = tabs.indexOf(id) > tabs.indexOf(activeTab.value) ? 'tab-slide-left' : 'tab-slide-right';
  activeTab.value = id;
}
const expiredCarouselRef = ref(null);

// Session inline analysis
const sessionQuestionsCache = ref({});
const sessionQuestionsLoading = ref({});

// Parcours tab - selected course details
const selectedCourseId = ref(null);
const selectedCourseData = ref(null);
const courseStudentSearch = ref('');
const divisionStudentSearch = ref('');
const sessionStudentSearch = ref('');

const sessionFilteredStudents = computed(() => {
  const students = divisionStore.division?.students ?? [];
  const q = sessionStudentSearch.value.trim().toLowerCase();
  return q
    ? students.filter(s => (s.pivot?.class_name ?? s.name).toLowerCase().includes(q))
    : students;
});

const divisionFilteredStudents = computed(() => {
  const students = divisionStore.division?.students ?? [];
  const q = divisionStudentSearch.value.trim().toLowerCase();
  const filtered = q
    ? students.filter(s => (s.pivot?.class_name ?? s.name).toLowerCase().includes(q))
    : students;
  return [...filtered].sort((a, b) => {
    const nameA = (a.pivot?.class_name ?? a.name).toLowerCase();
    const nameB = (b.pivot?.class_name ?? b.name).toLowerCase();
    const lastA = nameA.split(' ').pop();
    const lastB = nameB.split(' ').pop();
    return lastA.localeCompare(lastB);
  });
});
const courseSortKey = ref('name');
const courseSortDir = ref('asc');
const courseOpenMenuJumpId = ref(null);

// Jump management (Parcours tab)
const showNewJumpModal = ref(false);
const newJump = ref({ time: 15, nb_questions: 7, growth: 3, status: 'active', autoQuestions: true });
const showEditExpiryModal = ref(false);
const editingJump = ref(null);
const editExpiryValue = ref('');
const showDeleteJumpConfirm = ref(false);
const deletingJump = ref(null);
const showJumpObservationModal = ref(false);
const observingJumpData = ref(null);
const showCourseAttemptDetailModal = ref(false);
const selectedCourseAttemptDetail = ref(null);

// Suggested Questions (teacher)
const showSuggestedQuestionsModal = ref(false);
const suggestedQuestionsCourseName = ref('');
const suggestedQuestionsForCourse = ref(null);
const suggestedQuestions = ref([]);
const suggestedQuestionsTab = ref(1);
const isLoadingSuggestedQuestions = ref(false);
const showSuggestedQuestionOverlay = ref(false);
const selectedSuggestedQuestion = ref(null);
const revealSuggestedAnswer = ref(false);

// Public Suggested Questions (student)
const publicSuggestedQuestions = ref([]);
const showPublicQuestionOverlay = ref(false);
const selectedPublicQuestion = ref(null);
const pendingInvites = computed(() =>
  (divisionStore.division?.invites ?? []).filter(i => i.status === 'pending')
);

const teacherSessions = computed(() =>
  (sessionStore.mySessions ?? []).filter(s => s.status === 'active')
);

const expiredSessionsWithAttempts = computed(() =>
  (divisionStore.division?.kangourou_sessions ?? [])
    .filter(s => s.status === 'expired' && s.attempts_count > 0)
    .sort((a, b) => new Date(b.expires_at) - new Date(a.expires_at))
);

const openSessionIds = computed(() =>
  (divisionStore.division?.kangourou_sessions ?? []).map(s => s.id)
);

function isSessionOpen(sessionId) {
  return openSessionIds.value.includes(sessionId);
}

watch(svgContainer, (el) => {
  svgResizeObserver?.disconnect();
  if (el) {
    svgResizeObserver = new ResizeObserver(entries => {
      svgW.value = entries[0].contentRect.width || 600;
    });
    svgResizeObserver.observe(el);
    svgW.value = el.offsetWidth || 600;
  }
});

onMounted(async () => {
  await divisionStore.fetchDivision(divisionId.value);
  if (divisionStore.division) {
    editName.value = divisionStore.division.name;
  }
  if (isTeacher.value) {
    await sessionStore.fetchMySessions();
  } else {
    try {
      const res = await axios.get(`/api/divisions/${divisionId.value}/public-suggested-questions`);
      publicSuggestedQuestions.value = res.data.suggested_questions ?? [];
    } catch {
      // handled silently
    }
  }
  await courseStore.fetchCourses(divisionId.value);

  // Init selectedCourseId to the course with the latest jump
  if (isTeacher.value && courseStore.courses.length) {
    let bestCourseId = courseStore.courses[0].id;
    let bestJumpId = -1;
    for (const course of courseStore.courses) {
      for (const jump of course.jumps ?? []) {
        if (jump.id > bestJumpId) {
          bestJumpId = jump.id;
          bestCourseId = course.id;
        }
      }
    }
    selectedCourseId.value = bestCourseId;
  }

  // Pre-load session questions for expired sessions with analysis
  if (isTeacher.value && divisionStore.division) {
    const sessionsWithAnalysis = (divisionStore.division.kangourou_sessions ?? [])
      .filter(s => s.status === 'expired' && s.pivot?.analysis);
    for (const session of sessionsWithAnalysis) {
      loadSessionQuestions(session);
    }
  }

  window.Echo.private(`division.${divisionId.value}`)
    .listen('.StudentJoinedDivision', (e) => {
      if (!divisionStore.division) {
        return;
      }

      const student = e.student;
      const alreadyExists = (divisionStore.division.students ?? []).some(s => s.id === student.id);

      if (!alreadyExists) {
        if (!divisionStore.division.students) {
          divisionStore.division.students = [];
        }
        divisionStore.division.students.push(student);
        divisionStore.division.students_count = (divisionStore.division.students_count ?? 0) + 1;
      }
    })
    .listen('.SessionOpenedForDivision', (e) => {
      if (!divisionStore.division) {
        return;
      }

      const session = e.session;
      if (!divisionStore.division.kangourou_sessions) {
        divisionStore.division.kangourou_sessions = [];
      }
      const existingIndex = divisionStore.division.kangourou_sessions.findIndex(s => s.id === session.id);
      if (existingIndex === -1) {
        divisionStore.division.kangourou_sessions.push(session);
      } else {
        divisionStore.division.kangourou_sessions[existingIndex] = session;
      }
    })
    .listen('.JumpActivated', async (e) => {
      await courseStore.fetchCourses(divisionId.value);
      subscribeToActiveJump(e.jump.id);
    })
    .listen('.JumpReopened', async (e) => {
      await courseStore.fetchCourses(divisionId.value);
      subscribeToActiveJump(e.jump.id);
    });

  // Subscribe to the active jump's channel if one exists
  if (activeCourseActiveJump.value?.id) {
    subscribeToActiveJump(activeCourseActiveJump.value.id);
  }

  // Auto-focus tabs based on active jumps/sessions
  if (isTeacher.value) {
    // Check for active jumps first
    let hasActiveJump = false;
    for (const course of courseStore.courses) {
      const activeJump = course.jumps?.find(j => j.status === 'active' || j.status === 'expiring');
      if (activeJump) {
        selectedCourseId.value = course.id;
        switchTab('parcours');
        hasActiveJump = true;
        break;
      }
    }

    // If no active jump, check for active sessions
    if (!hasActiveJump) {
      const hasActiveSession = (divisionStore.division?.kangourou_sessions ?? []).some(s => s.status === 'active');
      if (hasActiveSession) {
        switchTab('sessions');
      }
    }
  }
});

onUnmounted(() => {
  svgResizeObserver?.disconnect();
  window.Echo.leave(`division.${divisionId.value}`);
  if (activeJumpChannelId) {
    window.Echo.leave(`jump.${activeJumpChannelId}`);
  }
  if (expiredSessionChannelId.value !== null) {
    window.Echo.leave(`session.${expiredSessionChannelId.value}`);
  }
  if (activeSessionChannelId.value !== null) {
    window.Echo.leave(`session.${activeSessionChannelId.value}`);
  }
  if (courseExpiringPollTimer) {
    clearInterval(courseExpiringPollTimer);
  }
  if (courseObservedJumpChannelId) {
    window.Echo.leaveChannel(`private-jump.${courseObservedJumpChannelId}`);
  }
  for (const jumpId of courseSubscribedJumpIds) {
    window.Echo.leave(`jump.${jumpId}`);
  }
});

function jumpDetailNumber(jump) {
  if (!activeCourse.value?.jumps) return '';
  const sorted = [...activeCourseExpiredJumps.value].sort((a, b) => a.id - b.id);
  return sorted.findIndex(j => j.id === jump.id) + 1;
}

function formatJumpDate(val) {
  if (!val) return '';
  return new Date(val).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}

async function openJumpDetail(jump) {
  const attempt = jump?.attempts?.[0];
  if (!attempt?.id) return;
  showJumpDetailModal.value = true;
  jumpDetailLoading.value = true;
  selectedJumpDetail.value = { jump, attempt };
  try {
    const res = await axios.get(`/api/jump-attempts/${attempt.id}`);
    selectedJumpDetail.value = { jump, attempt: res.data.attempt };
  } finally {
    jumpDetailLoading.value = false;
  }
}

async function handleChangeCode() {
  await divisionStore.changeCode(divisionId.value);
  if (!divisionStore.error) {
    showChangeCodeConfirm.value = false;
  }
}

async function handleToggleAccepting() {
  const current = divisionStore.division.accepting_students;
  await divisionStore.updateDivision(divisionId.value, { accepting_students: !current });
}

async function handleArchive() {
  await divisionStore.updateDivision(divisionId.value, { archived: true });
  if (!divisionStore.error) {
    showArchiveConfirm.value = false;
  }
}

async function handleUnarchive() {
  await divisionStore.updateDivision(divisionId.value, { archived: false });
  if (!divisionStore.error) {
    showUnarchiveConfirm.value = false;
  }
}

async function handleDelete() {
  await divisionStore.deleteDivision(divisionId.value);
  if (!divisionStore.error) {
    router.push({ name: 'MyDivisions' });
  }
  showDeleteConfirm.value = false;
}

async function handleRemoveStudent(studentId) {
  await divisionStore.removeStudent(divisionId.value, studentId);
  if (!divisionStore.error) {
    studentIdToRemove.value = null;
    showRemoveStudentConfirm.value = false;
  }
}

function openEditClassNameModal(student) {
  editClassStudentId.value = student.id;
  editClassFirstName.value = '';
  editClassLastName.value = '';
  divisionStore.clearError();
  showEditClassNameModal.value = true;
}

async function handleEditClassName() {
  await divisionStore.updateStudentClassName(divisionId.value, editClassStudentId.value, editClassFirstName.value, editClassLastName.value);
  if (!divisionStore.error) {
    showEditClassNameModal.value = false;
  }
}

async function handleInvite() {
  inviteSuccess.value = false;
  await divisionStore.inviteStudent(divisionId.value, inviteEmail.value);
  if (!divisionStore.error) {
    inviteSuccess.value = true;
    inviteEmail.value = '';
    await divisionStore.fetchDivision(divisionId.value);
  }
}

async function openExpiredSessionModal(session) {
  sessionStudentSearch.value = '';
  showExpiredSessionModal.value = true;
  isLoadingExpiredSession.value = true;
  expiredSessionDetail.value = null;
  try {
    const data = await sessionStore.fetchSessionDetails(session.id);
    expiredSessionDetail.value = data;
    expiredSessionChannelId.value = session.id;
    window.Echo.private(`session.${session.id}`)
      .listen('.AttemptUpdated', (e) => {
        if (!expiredSessionDetail.value) {
          return;
        }
        const updated = e.attempt;
        const attempts = expiredSessionDetail.value.attempts ?? [];
        const idx = attempts.findIndex(a => a.id === updated.id);
        if (idx !== -1) {
          attempts[idx] = updated;
        } else {
          attempts.push(updated);
        }
        expiredSessionDetail.value = { ...expiredSessionDetail.value, attempts };
      });
  } catch (err) {
    // error handled by store
  } finally {
    isLoadingExpiredSession.value = false;
  }
}

function closeExpiredSessionModal() {
  showExpiredSessionModal.value = false;
  if (expiredSessionChannelId.value !== null) {
    window.Echo.leave(`session.${expiredSessionChannelId.value}`);
    expiredSessionChannelId.value = null;
  }
  expiredSessionDetail.value = null;
}

async function openActiveSessionModal(session) {
  sessionStudentSearch.value = '';
  showActiveSessionModal.value = true;
  isLoadingActiveSession.value = true;
  activeSessionDetail.value = null;
  try {
    const data = await sessionStore.fetchSessionDetails(session.id);
    activeSessionDetail.value = data;
    activeSessionChannelId.value = session.id;
    window.Echo.private(`session.${session.id}`)
      .listen('.AttemptUpdated', (e) => {
        if (!activeSessionDetail.value) {
          return;
        }
        const updated = e.attempt;
        const attempts = activeSessionDetail.value.attempts ?? [];
        const idx = attempts.findIndex(a => a.id === updated.id);
        if (idx !== -1) {
          attempts[idx] = updated;
        } else {
          attempts.push(updated);
        }
        activeSessionDetail.value = { ...activeSessionDetail.value, attempts };
      });
  } catch (err) {
    // error handled by store
  } finally {
    isLoadingActiveSession.value = false;
  }
}

function openDeleteAttemptModal(attempt, context) {
  selectedAttempt.value = attempt;
  selectedAttemptContext.value = context;
  deleteAttemptConfirmed.value = false;
  showDeleteAttemptModal.value = true;
}

function closeDeleteAttemptModal() {
  showDeleteAttemptModal.value = false;
  selectedAttempt.value = null;
  selectedAttemptContext.value = null;
  deleteAttemptConfirmed.value = false;
}

async function confirmDeleteAttempt() {
  if (!selectedAttempt.value || !deleteAttemptConfirmed.value) { return; }
  isDeletingAttempt.value = true;
  try {
    await sessionStore.deleteAttempt(selectedAttempt.value.id);
    const detail = selectedAttemptContext.value === 'active' ? activeSessionDetail : expiredSessionDetail;
    if (detail.value?.attempts) {
      const index = detail.value.attempts.findIndex(a => a.id === selectedAttempt.value.id);
      if (index !== -1) {
        detail.value.attempts.splice(index, 1);
      }
    }
    closeDeleteAttemptModal();
  } finally {
    isDeletingAttempt.value = false;
  }
}

function closeActiveSessionModal() {
  showActiveSessionModal.value = false;
  if (activeSessionChannelId.value !== null) {
    window.Echo.leave(`session.${activeSessionChannelId.value}`);
    activeSessionChannelId.value = null;
  }
  activeSessionDetail.value = null;
}

async function handleCreateCourse() {
  try {
    await courseStore.createCourse(divisionId.value, newCourseTitle.value);
    showNewCourseModal.value = false;
    newCourseTitle.value = '';
    await courseStore.fetchCourses(divisionId.value);
  } catch {
    // error handled by store
  }
}

async function handleToggleSession(session) {
  if (isSessionOpen(session.id)) {
    await divisionStore.closeSessionForDivision(session.id, divisionId.value);
  } else {
    await divisionStore.openSessionForDivision(session.id, divisionId.value);
  }
  await divisionStore.fetchDivision(divisionId.value);
}

function successRatioBarClass(ratio) {
  if (ratio >= 0.7) { return 'bg-success'; }
  if (ratio >= 0.4) { return 'bg-warning'; }
  return 'bg-error';
}

function successRatioTextClass(ratio) {
  if (ratio >= 0.7) { return 'text-success'; }
  if (ratio >= 0.4) { return 'text-warning'; }
  return 'text-error';
}

async function toggleReviewed(questionId) {
  if (!analysisModalSession.value) { return; }
  const session = analysisModalSession.value;
  try {
    const res = await axios.patch(
      `/api/divisions/${divisionId.value}/kangourou-sessions/${session.id}/questions/${questionId}/reviewed`
    );
    const updated = res.data.analysis;
    // Update the local pivot analysis
    const sessions = divisionStore.division?.kangourou_sessions ?? [];
    const idx = sessions.findIndex(s => s.id === session.id);
    if (idx !== -1) {
      sessions[idx] = { ...sessions[idx], pivot: { ...sessions[idx].pivot, analysis: updated } };
    }
    analysisModalSession.value = { ...session, pivot: { ...session.pivot, analysis: updated } };
    // Sync question overlay reviewed state
    if (selectedAnalysisQuestion.value?.question_id === questionId) {
      const updatedItem = updated.find(a => a.question_id === questionId);
      if (updatedItem) {
        selectedAnalysisQuestion.value = { ...selectedAnalysisQuestion.value, reviewed: updatedItem.reviewed };
      }
    }
  } catch {
    // handled silently
  }
}

function openQuestionOverlay(item) {
  selectedAnalysisQuestion.value = item;
  revealAnswer.value = false;
  showQuestionOverlay.value = true;
}

function openSessionQuestionOverlay(item, session) {
  analysisModalSession.value = session;
  openQuestionOverlay(item);
}

function closeQuestionOverlay() {
  showQuestionOverlay.value = false;
  selectedAnalysisQuestion.value = null;
  revealAnswer.value = false;
}

// Session inline analysis functions
async function loadSessionQuestions(session) {
  if (sessionQuestionsCache.value[session.id] !== undefined || sessionQuestionsLoading.value[session.id]) { return; }
  sessionQuestionsLoading.value = { ...sessionQuestionsLoading.value, [session.id]: true };
  try {
    const res = await axios.get(`/api/kangourou-sessions/${session.code}`);
    sessionQuestionsCache.value = { ...sessionQuestionsCache.value, [session.id]: res.data.session?.paper?.questions ?? [] };
  } catch {
    sessionQuestionsCache.value = { ...sessionQuestionsCache.value, [session.id]: [] };
  } finally {
    sessionQuestionsLoading.value = { ...sessionQuestionsLoading.value, [session.id]: false };
  }
}

function getSessionAnalysisData(session) {
  const questions = sessionQuestionsCache.value[session.id] ?? [];
  const analysis = session.pivot?.analysis ?? [];
  return questions.map((question, index) => {
    const analysisItem = analysis[index] ?? {};
    return {
      ...question,
      question_id: question.id,
      questionNumber: index + 1,
      success_ratio: analysisItem.success_ratio ?? 0,
      reviewed: analysisItem.reviewed ?? false,
    };
  });
}

async function toggleReviewedForSession(questionId, session) {
  analysisModalSession.value = session;
  await toggleReviewed(questionId);
}

// Parcours tab — course details
async function loadCourseDetails() {
  if (!selectedCourseId.value) { return; }
  const data = await courseStore.fetchCourseDetails(selectedCourseId.value);
  selectedCourseData.value = data;
  subscribeToActiveCourseJumps();
}

function jumpNumber(jump) {
  if (!selectedCourseData.value?.jumps) { return ''; }
  const sorted = [...selectedCourseData.value.jumps].sort((a, b) => a.id - b.id);
  return sorted.findIndex(j => j.id === jump.id) + 1;
}

function getAttempt(jump, student) {
  return jump.attempts?.find(a => a.user_id === student.id) ?? null;
}

const courseJumpsSorted = computed(() =>
  [...(selectedCourseData.value?.jumps ?? [])].sort((a, b) => b.id - a.id)
);

const courseFilteredSortedStudents = computed(() => {
  if (!selectedCourseData.value?.students) { return []; }
  let students = selectedCourseData.value.students;
  const q = courseStudentSearch.value.trim().toLowerCase();
  if (q) {
    students = students.filter(s => (s.pivot?.class_name ?? s.name).toLowerCase().includes(q));
  }
  const dir = courseSortDir.value === 'asc' ? 1 : -1;
  return [...students].sort((a, b) => {
    if (courseSortKey.value === 'name') {
      return (a.pivot?.class_name ?? a.name).toLowerCase().localeCompare((b.pivot?.class_name ?? b.name).toLowerCase()) * dir;
    }
    if (courseSortKey.value === 'total') {
      return (courseStudentTotal(a) - courseStudentTotal(b)) * dir;
    }
    const jump = selectedCourseData.value.jumps?.find(j => j.id === courseSortKey.value);
    if (!jump) { return 0; }
    return ((getAttempt(jump, a)?.score ?? -1) - (getAttempt(jump, b)?.score ?? -1)) * dir;
  });
});

function courseStudentTotal(student) {
  if (!selectedCourseData.value?.jumps) { return 0; }
  return selectedCourseData.value.jumps
    .filter(j => j.status === 'expired')
    .reduce((sum, jump) => sum + (getAttempt(jump, student)?.score ?? 0), 0);
}

function setCourseSort(key) {
  if (courseSortKey.value === key) {
    courseSortDir.value = courseSortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    courseSortKey.value = key;
    courseSortDir.value = 'asc';
  }
}

function jumpStatusLabel(status) {
  const labels = { draft: 'Brouillon', active: 'Actif', expiring: 'En notation', expired: 'Expiré' };
  return labels[status] ?? status;
}

async function activateJump(jump) {
  try {
    await courseStore.updateJump(jump.id, { status: 'active' });
    await loadCourseDetails();
  } catch {
    // error shown by store
  }
}

async function reopenJump(jump) {
  try {
    await courseStore.updateJump(jump.id, { status: 'active' });
    await loadCourseDetails();
    subscribeToActiveCourseJumps();
  } catch {
    // error shown by store
  }
}

function openEditExpiryModal(jump) {
  editingJump.value = jump;
  const d = jump.expiration ? new Date(jump.expiration) : new Date();
  const offset = d.getTimezoneOffset() * 60_000;
  editExpiryValue.value = new Date(d.getTime() - offset).toISOString().slice(0, 16);
  showEditExpiryModal.value = true;
}

async function handleSaveExpiry() {
  try {
    await courseStore.updateJump(editingJump.value.id, { expiration: new Date(editExpiryValue.value).toISOString() });
    showEditExpiryModal.value = false;
    await loadCourseDetails();
  } catch {
    // error shown by store
  }
}

async function handleExpireNow() {
  try {
    await courseStore.updateJump(editingJump.value.id, { expiration: new Date().toISOString(), status: 'expiring' });
    showEditExpiryModal.value = false;
    await loadCourseDetails();
  } catch {
    // error shown by store
  }
}

function confirmDeleteJump(jump) {
  deletingJump.value = jump;
  showDeleteJumpConfirm.value = true;
}

async function handleDeleteJump() {
  try {
    await courseStore.deleteJump(deletingJump.value.id);
    showDeleteJumpConfirm.value = false;
    await loadCourseDetails();
  } catch {
    // error shown by store
  }
}

let courseSubscribedJumpIds = [];
let courseObservedJumpChannelId = null;
let courseExpiringPollTimer = null;

function openJumpObservation(jump) {
  observingJumpData.value = jump;
  showJumpObservationModal.value = true;
  loadCourseDetails();
  if (jump.status === 'active') {
    courseObservedJumpChannelId = jump.id;
    window.Echo.private(`jump.${jump.id}`)
      .listen('.JumpAttemptUpdated', (e) => {
        const jumpData = selectedCourseData.value?.jumps?.find(j => j.id === jump.id);
        if (!jumpData) { return; }
        const idx = jumpData.attempts.findIndex(a => a.user_id === e.attempt.user_id);
        if (idx !== -1) {
          jumpData.attempts[idx] = e.attempt;
        } else {
          jumpData.attempts.push(e.attempt);
        }
      });
  }
}

function closeJumpObservation() {
  if (courseObservedJumpChannelId) {
    window.Echo.leaveChannel(`private-jump.${courseObservedJumpChannelId}`);
    courseObservedJumpChannelId = null;
  }
  showJumpObservationModal.value = false;
  observingJumpData.value = null;
}

function subscribeToActiveCourseJumps() {
  const activeJumps = selectedCourseData.value?.jumps?.filter(j => j.status === 'active' || j.status === 'expiring') ?? [];
  for (const jump of activeJumps) {
    if (courseSubscribedJumpIds.includes(jump.id)) { continue; }
    courseSubscribedJumpIds.push(jump.id);
    window.Echo.channel(`jump.${jump.id}`)
      .listen('.JumpExpired', async () => {
        await loadCourseDetails();
        subscribeToActiveCourseJumps();
      });
  }
}

function openCourseAttemptDetail(jump, student) {
  const attempt = getAttempt(jump, student);
  if (!attempt) { return; }
  selectedCourseAttemptDetail.value = { jump, student, attempt };
  showCourseAttemptDetailModal.value = true;
}

async function handleCreateJump() {
  if (!selectedCourseId.value) { return; }
  try {
    await courseStore.createJump(selectedCourseId.value, {
      nb_questions: newJump.value.nb_questions,
      time: newJump.value.time,
      growth: newJump.value.growth,
      status: newJump.value.status,
    });
    showNewJumpModal.value = false;
    await loadCourseDetails();
  } catch {
    // error shown by store
  }
}

watch(selectedCourseId, async (id) => {
  if (id) { await loadCourseDetails(); }
});

watch(
  () => selectedCourseData.value?.jumps?.some(j => j.status === 'expiring'),
  (hasExpiring) => {
    if (hasExpiring && !courseExpiringPollTimer) {
      courseExpiringPollTimer = setInterval(async () => {
        await loadCourseDetails();
        subscribeToActiveCourseJumps();
      }, 3000);
    } else if (!hasExpiring && courseExpiringPollTimer) {
      clearInterval(courseExpiringPollTimer);
      courseExpiringPollTimer = null;
    }
  }
);

// Suggested Questions functions (teacher)
function suggestedQuestionsByLevel(level) {
  return suggestedQuestions.value.filter(sq => sq.level === level);
}

async function openSuggestedQuestionsModal(course) {
  suggestedQuestionsForCourse.value = course;
  suggestedQuestionsCourseName.value = course.title;
  showSuggestedQuestionsModal.value = true;
  suggestedQuestionsTab.value = 1;
  isLoadingSuggestedQuestions.value = true;
  suggestedQuestions.value = [];
  try {
    const res = await axios.get(`/api/courses/${course.id}/suggested-questions`);
    suggestedQuestions.value = res.data.suggested_questions ?? [];
  } catch {
    // handled silently
  } finally {
    isLoadingSuggestedQuestions.value = false;
  }
}

function closeSuggestedQuestionsModal() {
  showSuggestedQuestionsModal.value = false;
  suggestedQuestionsForCourse.value = null;
  suggestedQuestions.value = [];
}

function openSuggestedQuestionOverlay(sq) {
  selectedSuggestedQuestion.value = sq;
  revealSuggestedAnswer.value = false;
  showSuggestedQuestionOverlay.value = true;
}

function closeSuggestedQuestionOverlay() {
  showSuggestedQuestionOverlay.value = false;
  selectedSuggestedQuestion.value = null;
  revealSuggestedAnswer.value = false;
}

async function handleToggleSuggestedPublic(sq) {
  try {
    const res = await axios.patch(`/api/suggested-questions/${sq.id}/toggle-public`);
    const updated = res.data.suggested_question;
    const idx = suggestedQuestions.value.findIndex(q => q.id === sq.id);
    if (idx !== -1) {
      suggestedQuestions.value[idx] = { ...suggestedQuestions.value[idx], is_public: updated.is_public };
    }
    if (selectedSuggestedQuestion.value?.id === sq.id) {
      selectedSuggestedQuestion.value = { ...selectedSuggestedQuestion.value, is_public: updated.is_public };
    }
  } catch {
    // handled silently
  }
}

async function handleDeleteSuggestedQuestion(sq) {
  try {
    await axios.delete(`/api/suggested-questions/${sq.id}`);
    suggestedQuestions.value = suggestedQuestions.value.filter(q => q.id !== sq.id);
  } catch {
    // handled silently
  }
}

// Public question overlay (student)
function openPublicQuestionOverlay(sq) {
  selectedPublicQuestion.value = sq;
  showPublicQuestionOverlay.value = true;
}

function closePublicQuestionOverlay() {
  showPublicQuestionOverlay.value = false;
  selectedPublicQuestion.value = null;
}

function formatExpiredTime(expiresAt) {
  if (!expiresAt) return 'Expirée';

  const expiryDate = new Date(expiresAt);
  const now = new Date();
  const diffMs = now - expiryDate;
  const diffSecs = Math.floor(diffMs / 1000);
  const diffMins = Math.floor(diffSecs / 60);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);
  const diffWeeks = Math.floor(diffDays / 7);
  const diffMonths = Math.floor(diffDays / 30);

  if (diffMins < 1) return 'À l\'instant';
  if (diffMins < 60) return `il y a ${diffMins} minute${diffMins > 1 ? 's' : ''}`;
  if (diffHours < 24) return `il y a ${diffHours} heure${diffHours > 1 ? 's' : ''}`;
  if (diffDays < 7) return `il y a ${diffDays} jour${diffDays > 1 ? 's' : ''}`;
  if (diffWeeks < 4) return `il y a ${diffWeeks} semaine${diffWeeks > 1 ? 's' : ''}`;
  return `il y a ${diffMonths} mois`;
}

function formatTimeUntilExpiration(expiresAt) {
  if (!expiresAt) return 'Expirée';

  const expiryDate = new Date(expiresAt);
  const now = new Date();
  const diffMs = expiryDate - now;

  if (diffMs <= 0) return 'Expirée';

  const diffSecs = Math.floor(diffMs / 1000);
  const diffMins = Math.floor(diffSecs / 60);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMins < 1) return 'Expiration immédiate';
  if (diffMins < 60) return `expires dans ${diffMins} minute${diffMins > 1 ? 's' : ''}`;
  if (diffHours < 24) return `expires dans ${diffHours} heure${diffHours > 1 ? 's' : ''}`;
  return `expires dans ${diffDays} jour${diffDays > 1 ? 's' : ''}`;
}
</script>

<style scoped>
.tab-slide-left-enter-active,
.tab-slide-left-leave-active,
.tab-slide-right-enter-active,
.tab-slide-right-leave-active {
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.tab-slide-left-enter-from  { transform: translateX(30px);  opacity: 0; }
.tab-slide-left-leave-to    { transform: translateX(-30px); opacity: 0; }
.tab-slide-right-enter-from { transform: translateX(-30px); opacity: 0; }
.tab-slide-right-leave-to   { transform: translateX(30px);  opacity: 0; }
</style>
