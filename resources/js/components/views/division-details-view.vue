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
      <div class="flex items-start justify-between mb-6">
        <div>
          <h2 class="text-2xl font-bold text-text-main dark:text-surface">{{ divisionStore.division.name }}</h2>
          <p class="text-text-muted text-sm mt-1">{{ divisionStore.division.students_count }} élève{{ divisionStore.division.students_count !== 1 ? 's' : '' }}</p>
        </div>
        <div class="flex gap-2">
          <button
            v-if="!divisionStore.division.archived"
            @click="showArchiveConfirm = true"
            class="px-3 py-1.5 text-sm border border-border text-text-muted rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
          >
            Archiver
          </button>
          <button
            v-else
            @click="showUnarchiveConfirm = true"
            class="px-3 py-1.5 text-sm border border-success text-success rounded-lg hover:bg-success/10 transition-colors cursor-pointer"
          >
            Activer
          </button>
          <button
            @click="showDeleteConfirm = true"
            class="px-3 py-1.5 text-sm bg-error/10 text-error border border-error/30 rounded-lg hover:bg-error/20 transition-colors cursor-pointer"
          >
            Supprimer
          </button>
        </div>
      </div>

      <div v-if="divisionStore.division.archived" class="mb-6 p-4 bg-warning/10 border border-warning rounded-lg">
        <p class="text-sm text-warning font-medium">Cette classe est archivée. Cliquez sur "Activer" pour la rendre active.</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left column: settings -->
        <div class="space-y-4">
          <!-- Settings card -->
          <div class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4 space-y-4">
            <h3 class="font-semibold text-text-main dark:text-surface">Paramètres</h3>

            <!-- Name -->
            <div>
              <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Nom</label>
              <div class="flex gap-2">
                <input
                  v-model="editName"
                  type="text"
                  :disabled="divisionStore.division.archived"
                  class="flex-1 px-3 py-2 border border-border dark:border-border/50 rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                />
                <button
                  @click="handleRename"
                  :disabled="editName === divisionStore.division.name || divisionStore.division.archived"
                  class="px-3 py-2 bg-primary hover:bg-primary-hover text-surface rounded-lg text-sm disabled:opacity-40 transition-colors cursor-pointer"
                >
                  OK
                </button>
              </div>
            </div>
        </div>
        <div class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4">
            <!-- Code -->
            <div>
              <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Code d'invitation</label>
              <div class="flex gap-2 items-center mb-3">
                <span class="flex-1 font-mono font-bold text-3xl text-text-main dark:text-surface">{{ divisionStore.division.code }}</span>
                <button
                  @click="showJoinInfoModal = true"
                  title="Afficher le code de classe"
                  class="text-text-muted hover:text-primary cursor-pointer transition-colors"
                >
                  <Fullscreen class="w-5 h-5" />
                </button>
                <button
                  @click="showChangeCodeConfirm = true"
                  :disabled="divisionStore.division.archived"
                  title="Générer un nouveau code"
                  class="text-text-muted hover:text-primary cursor-pointer transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  <RefreshCw class="w-5 h-5" />
                </button>
              </div>
            </div>

            <!-- Accepting students -->
            <div class="flex items-center justify-between">
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
          </div>
          <!-- Invite form -->
          <div class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4">
            <h3 class="font-semibold text-text-main dark:text-surface mb-3">Inviter par email</h3>
            <form @submit.prevent="handleInvite" class="flex gap-2">
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
                class="px-3 py-2 bg-primary cursor-pointer hover:bg-primary-hover text-surface rounded-lg text-sm disabled:opacity-50 transition-colors"
              >
                Inviter
              </button>
            </form>
            <div v-if="inviteSuccess" class="mt-2 text-sm text-success">Invitation envoyée !</div>
            <div v-if="divisionStore.error" class="mt-2 text-sm text-error">{{ divisionStore.error }}</div>

            <!-- Pending invites list -->
            <div v-if="pendingInvites.length" class="mt-3 space-y-1">
              <p class="text-xs text-text-muted font-medium uppercase tracking-wide">En attente</p>
              <div
                v-for="invite in pendingInvites"
                :key="invite.id"
                class="flex items-center justify-between text-sm"
              >
                <span class="text-text-muted">{{ invite.email }}</span>
                <span class="text-xs bg-warning/20 text-warning px-2 py-0.5 rounded-full">En attente</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Middle column: students + invites -->
        <div v-if="!divisionStore.division.archived" class="space-y-4">
          <!-- Students -->
          <div class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4">
            <h3 class="font-semibold text-text-main dark:text-surface mb-3">Élèves ({{ divisionStore.division.students?.length ?? 0 }})</h3>
            <ul v-if="divisionStore.division.students?.length" class="space-y-2">
              <li
                v-for="student in divisionStore.division.students"
                :key="student.id"
                class="flex items-center justify-between py-1"
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
            <p v-else class="text-sm text-text-muted">Aucun élève.</p>
          </div>

          
        </div>

        <!-- Right column: sessions -->
        <div v-if="!divisionStore.division.archived" class="space-y-4">
        <div class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4">
          <h3 class="font-semibold text-text-main dark:text-surface">Sessions Actives</h3>
          <p class="text-sm text-text-muted mb-3">Ouvrir ou fermer vos sessions pour cette classe.</p>
          <div v-if="teacherSessions.length === 0" class="text-sm text-text-muted">
            Aucune session active.
          </div>
          <ul v-else class="space-y-3">
            <li
              v-for="session in teacherSessions"
              :key="session.id"
              class="flex items-center justify-between"
            >
              <div>
                <p class="text-sm font-medium text-text-main dark:text-surface">{{ session.paper?.title }}</p>
                <p class="text-xs text-text-muted">{{ session.code }}</p>
              </div>
              <div class="flex items-center gap-2">
                <button
                  @click="openActiveSessionModal(session)"
                  class="text-text-muted hover:text-primary transition-colors cursor-pointer"
                  title="Voir les tentatives"
                >
                  <Eye class="w-6 h-6" />
                </button>
                <button
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
                </button>
              </div>
            </li>
          </ul>
        </div>

        <!-- Courses (Parcours) -->
        <div class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-text-main dark:text-surface">Parcours</h3>
            <button
              @click="showNewCourseModal = true"
              class="flex items-center gap-1 px-3 py-1.5 text-sm bg-primary text-surface rounded-lg hover:bg-primary-hover transition-colors cursor-pointer"
            >
              <Plus class="w-4 h-4" />
              Nouveau
            </button>
          </div>
          <div v-if="!courseStore.courses.length" class="text-sm text-text-muted">Aucun parcours.</div>
          <ul v-else class="space-y-2">
            <li
              v-for="course in courseStore.courses"
              :key="course.id"
              class="flex items-center justify-between py-1"
            >
              <router-link
                :to="{ name: 'CourseDetails', params: { id: divisionId, courseId: course.id } }"
                class="text-sm font-medium text-primary hover:underline"
              >
                {{ course.title }}
              </router-link>
              <div class="flex items-center gap-2">
                <span v-if="course.archived" class="text-xs bg-gray-200 dark:bg-gray-700 text-text-muted px-2 py-0.5 rounded-full">Archivé</span>
                <span class="text-xs text-text-muted">{{ course.jumps_count ?? 0 }} saut{{ (course.jumps_count ?? 0) !== 1 ? 's' : '' }}</span>
              </div>
            </li>
          </ul>
        </div>

        <!-- Expired Sessions with Attempts -->
        <div v-if="expiredSessionsWithAttempts.length > 0" class="bg-surface dark:bg-gray-900 border border-border rounded-xl p-4">
          <h3 class="font-semibold text-text-main dark:text-surface">Sessions expirées</h3>
          <p class="text-sm text-text-muted mb-3">Sessions terminées avec des tentatives d'élèves.</p>
          <ul class="space-y-3">
            <li
              v-for="session in expiredSessionsWithAttempts"
              :key="session.id"
            >
              <button
                @click="openExpiredSessionModal(session)"
                class="w-full flex items-center cursor-pointer justify-between group hover:bg-gray-50 dark:hover:bg-gray-800 -mx-2 px-2 py-1 rounded-lg transition-colors text-left"
              >
                <div>
                  <p class="text-sm font-medium text-text-main dark:text-surface group-hover:text-primary transition-colors">{{ session.paper?.title }}</p>
                  <p class="text-xs text-text-muted">{{ session.attempts_count }} tentative{{ session.attempts_count > 1 ? 's' : '' }}</p>
                </div>
                <span class="text-xs bg-gray-100 dark:bg-gray-800 text-text-muted px-2 py-0.5 rounded-full">{{ formatExpiredTime(session.expires_at) }}</span>
              </button>
            </li>
          </ul>
        </div>
        </div>
      </div>
    </template>

    <!-- Student View -->
    <template v-else-if="!isTeacher && divisionStore.division">
      <div class="mb-6">
        <h2 class="text-8xl text-center font-bold text-text-main dark:text-surface">{{ divisionStore.division.name }}</h2>
        <p class="text-text-muted text-center text-sm mt-1">{{ divisionStore.division.teacher?.name }}</p>
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
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-7xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-border">
          <h3 class="text-lg font-semibold text-text-main dark:text-surface">{{ expiredSessionDetail?.paper?.title }}</h3>
          <button @click="closeExpiredSessionModal" class="text-text-muted hover:text-text-main transition-colors cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>
        <div class="p-4 overflow-y-auto flex-1">
          <DivisionAttemptsTable
            :students="divisionStore.division?.students ?? []"
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
      <div class="bg-surface dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-7xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-border">
          <h3 class="text-lg font-semibold text-text-main dark:text-surface">{{ activeSessionDetail?.paper?.title }}</h3>
          <button @click="closeActiveSessionModal" class="text-text-muted hover:text-text-main transition-colors cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>
        <div class="p-4 overflow-y-auto flex-1">
          <DivisionAttemptsTable
            :students="divisionStore.division?.students ?? []"
            :attempts="activeSessionDetail?.attempts ?? []"
            :loading="isLoadingActiveSession"
            @delete="openDeleteAttemptModal($event, 'active')"
          />
        </div>
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

        <p class="text-5xl font-semibold text-text-muted tracking-wide">hubaroo.online</p>

        <div class="flex items-center gap-4 w-full">
          <img
            :src="'/mes classes.png'"
            alt="Page Mes classes"
            class="flex-1 max-h-[350px] object-contain"
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
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { ChevronLeft, RefreshCw, X, Eye, Pencil, Fullscreen, ChevronRight } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/authStore';
import { useDivisionStore } from '@/stores/divisionStore';
import { useKangourouSessionStore } from '@/stores/kangourouSessionStore';
import { useCourseStore } from '@/stores/courseStore';
import { useJumpAttemptStore } from '@/stores/jumpAttemptStore';
import { useJumpRejoinDemandStore } from '@/stores/jumpRejoinDemandStore';
import DivisionAttemptsTable from '@/components/DivisionAttemptsTable.vue';
import { Plus } from 'lucide-vue-next';

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

const selectedAttemptSessionDetail = computed(() =>
  selectedAttemptContext.value === 'active' ? activeSessionDetail.value : expiredSessionDetail.value
);

const pendingInvites = computed(() =>
  (divisionStore.division?.invites ?? []).filter(i => i.status === 'pending')
);

const teacherSessions = computed(() =>
  (sessionStore.mySessions ?? []).filter(s => s.status === 'active')
);

const expiredSessionsWithAttempts = computed(() =>
  (divisionStore.division?.kangourou_sessions ?? []).filter(s => s.status === 'expired' && s.attempts_count > 0)
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
  }
  await courseStore.fetchCourses(divisionId.value);

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
      const alreadyExists = (divisionStore.division.kangourou_sessions ?? []).some(s => s.id === session.id);

      if (!alreadyExists) {
        if (!divisionStore.division.kangourou_sessions) {
          divisionStore.division.kangourou_sessions = [];
        }
        divisionStore.division.kangourou_sessions.push(session);
      }
    })
    .listen('.JumpActivated', async (e) => {
      await courseStore.fetchCourses(divisionId.value);
      subscribeToActiveJump(e.jump.id);
    });

  // Subscribe to the active jump's channel if one exists
  if (activeCourseActiveJump.value?.id) {
    subscribeToActiveJump(activeCourseActiveJump.value.id);
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

async function handleRename() {
  await divisionStore.updateDivision(divisionId.value, { name: editName.value });
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
