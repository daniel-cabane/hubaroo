<template>
  <div class="container mx-auto p-6" @click="openMenuJumpId = null">
    <!-- Back link -->
    <div class="flex items-center justify-between mb-6">
      <router-link :to="`/my/divisions/${courseData?.course?.division_id ?? divisionId}`" class="text-primary hover:underline flex items-center gap-2">
        <ChevronLeft class="w-4 h-4" />
        Retour à la classe
      </router-link>
      <span v-if="courseData?.course?.division?.name" class="text-sm text-text-muted">{{ courseData.course.division.name }}</span>
    </div>

    <div v-if="courseStore.isLoading && !courseData" class="text-text-muted">Chargement...</div>
    <div v-else-if="courseStore.error && !courseData" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg">
      {{ courseStore.error }}
    </div>

    <template v-else-if="courseData">
      <!-- Header -->
      <div class="flex items-start justify-between mb-6">
        <div>
          <h2 class="text-2xl font-bold text-text-main dark:text-surface">{{ courseData.course.title }}</h2>
          <p class="text-text-muted text-sm mt-1">{{ courseData.students?.length ?? 0 }} élève{{ (courseData.students?.length ?? 0) !== 1 ? 's' : '' }}</p>
        </div>
        <div class="flex gap-2">
          <button
            v-if="!courseData.course.archived && courseData.jumps?.length"
            @click="showArchiveConfirm = true"
            class="px-3 py-1.5 text-sm border border-border text-text-muted rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
          >
            Archiver
          </button>
          <button
            v-else-if="courseData.course.archived"
            @click="handleUnarchive"
            class="px-3 py-1.5 text-sm border border-success text-success rounded-lg hover:bg-success/10 transition-colors cursor-pointer"
          >
            Activer
          </button>
          <button
            v-if="!courseData.jumps?.length"
            @click="showDeleteCourseConfirm = true"
            class="px-3 py-1.5 text-sm bg-error/10 text-error border border-error/30 rounded-lg hover:bg-error/20 transition-colors cursor-pointer"
          >
            Supprimer
          </button>
          <button
            @click="showNewJumpModal = true"
            class="px-3 py-1.5 text-sm bg-primary text-surface rounded-lg hover:bg-primary-hover transition-colors cursor-pointer flex items-center gap-1"
          >
            <Plus class="w-4 h-4" />
            Nouveau saut
          </button>
        </div>
      </div>

      <!-- Jumps x Students Table -->
      <div class="bg-surface dark:bg-gray-900 border border-border rounded-xl overflow-hidden">
        <div v-if="!courseData.jumps?.length" class="p-8 text-center text-text-muted">
          Aucun saut pour ce parcours. Créez le premier !
        </div>
        <template v-else>
          <div class="px-4 py-3 border-b border-border">
            <input
              v-model="studentSearch"
              type="text"
              placeholder="Rechercher un élève..."
              class="w-full max-w-xs px-3 py-1.5 text-sm border border-border rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
          <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800 border-b border-border">
              <tr>
                <th class="px-4 py-3 text-left font-semibold text-text-main dark:text-surface sticky left-0 z-10 bg-gray-50 dark:bg-gray-800 min-w-[250px]">
                  <button @click="setSort('name')" class="flex items-center gap-1 hover:text-primary transition-colors cursor-pointer">
                    Élève
                    <span class="text-xs opacity-50">{{ sortKey === 'name' ? (sortDir === 'asc' ? '↑' : '↓') : '↕' }}</span>
                  </button>
                </th>
                <th class="px-4 py-3 text-center font-semibold text-text-main dark:text-surface">
                  <button @click="setSort('total')" class="flex items-center justify-center gap-1 hover:text-primary transition-colors cursor-pointer w-full">
                    Total
                    <span class="text-xs opacity-50">{{ sortKey === 'total' ? (sortDir === 'asc' ? '↑' : '↓') : '↕' }}</span>
                  </button>
                </th>
                <th
                  v-for="jump in courseData.jumps"
                  :key="jump.id"
                  class="px-4 py-3 text-center font-semibold text-text-main dark:text-surface min-w-[120px]"
                >
                  <div class="flex flex-col items-center gap-1">
                    <button @click="setSort(jump.id)" class="flex items-center gap-1 hover:text-primary transition-colors cursor-pointer">
                      Saut {{ jumpNumber(jump) }}
                      <span class="text-xs opacity-50">{{ sortKey === jump.id ? (sortDir === 'asc' ? '↑' : '↓') : '↕' }}</span>
                    </button>
                    <span class="text-xs text-text-muted font-normal">{{ formatJumpDate(jump.created_at) }}</span>
                    <!-- Status tag as menu trigger -->
                    <div class="relative">
                      <button
                        @click.stop="openMenuJumpId = openMenuJumpId === jump.id ? null : jump.id"
                        class="text-xs px-2 py-0.5 rounded-full cursor-pointer hover:opacity-75 transition-opacity"
                        :class="{
                          'bg-warning/15 text-warning': jump.status === 'draft',
                          'bg-success/15 text-success': jump.status === 'active',
                          'bg-primary/15 text-primary': jump.status === 'expiring',
                          'bg-text-muted/15 text-text-muted': jump.status === 'expired',
                        }"
                      >{{ jumpStatusLabel(jump.status) }}</button>
                      <div
                        v-if="openMenuJumpId === jump.id"
                        class="absolute top-full mt-1 left-1/2 -translate-x-1/2 z-20 bg-surface dark:bg-gray-800 border border-border rounded-lg shadow-lg py-1 min-w-[160px] text-left"
                      >
                        <button
                          @click.stop="openJumpObservation(jump); openMenuJumpId = null"
                          class="w-full px-3 py-1.5 text-xs text-text-main dark:text-surface hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer text-left flex items-center gap-2"
                        ><Eye class="w-3.5 h-3.5 shrink-0" /> Voir les détails</button>
                        <button
                          v-if="jump.status === 'draft'"
                          @click.stop="activateJump(jump); openMenuJumpId = null"
                          class="w-full px-3 py-1.5 text-xs text-success hover:bg-success/10 transition-colors cursor-pointer text-left"
                        >Activer</button>
                        <button
                          v-if="jump.status === 'active'"
                          @click.stop="openEditExpiryModal(jump); openMenuJumpId = null"
                          class="w-full px-3 py-1.5 text-xs text-primary hover:bg-primary/10 transition-colors cursor-pointer text-left flex items-center gap-2"
                        ><Clock class="w-3.5 h-3.5 shrink-0" /> Modifier l'expiration</button>
                        <button
                          v-if="jump.status === 'expired'"
                          @click.stop="reopenJump(jump); openMenuJumpId = null"
                          class="w-full px-3 py-1.5 text-xs text-success hover:bg-success/10 transition-colors cursor-pointer text-left flex items-center gap-2"
                        ><RotateCcw class="w-3.5 h-3.5 shrink-0" /> Réouvrir</button>
                        <button
                          @click.stop="confirmDeleteJump(jump); openMenuJumpId = null"
                          class="w-full px-3 py-1.5 text-xs text-error hover:bg-error/10 transition-colors cursor-pointer text-left flex items-center gap-2"
                        ><Trash2 class="w-3.5 h-3.5 shrink-0" /> Supprimer</button>
                      </div>
                    </div>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr v-for="student in filteredSortedStudents" :key="student.id" class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                <td class="px-4 py-3 font-medium text-text-main dark:text-surface sticky left-0 z-10 bg-surface dark:bg-gray-900 group-hover:bg-gray-50 dark:group-hover:bg-gray-800 transition-colors">
                  {{ student.pivot?.class_name ?? student.name }}
                </td>
                <td class="px-4 py-3 text-center font-semibold text-text-main dark:text-surface">
                  {{ studentTotal(student) }}
                </td>
                <td
                  v-for="jump in courseData.jumps"
                  :key="jump.id"
                  class="px-4 py-3 text-center"
                >
                  <button
                    v-if="getAttempt(jump, student)"
                    @click="openAttemptDetail(jump, student)"
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

    <!-- New Jump Modal -->
    <div
      v-if="showNewJumpModal"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
      @click.self="showNewJumpModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-md w-full mx-4 shadow-xl">
        <h3 class="text-lg font-bold text-text-main dark:text-surface mb-4">Nouveau saut</h3>
        <div class="space-y-4">
          <!-- Time -->
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Durée (minutes)</label>
            <input
              v-model.number="newJump.time"
              type="number"
              min="5"
              max="60"
              class="w-full px-3 py-2 border border-border rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm"
              @input="updateAutoQuestions"
            />
          </div>

          <!-- Auto questions switch -->
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-text-main dark:text-surface/80">Calculer les questions automatiquement (1 pour 2 min)</span>
            <button
              @click="newJump.autoQuestions = !newJump.autoQuestions; updateAutoQuestions()"
              :class="[
                'relative inline-flex h-6 w-11 items-center rounded-full transition-colors cursor-pointer',
                newJump.autoQuestions ? 'bg-success' : 'bg-gray-300 dark:bg-gray-600'
              ]"
            >
              <span :class="['inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow', newJump.autoQuestions ? 'translate-x-6' : 'translate-x-1']" />
            </button>
          </div>

          <!-- Nb questions -->
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Nombre de questions</label>
            <input
              v-model.number="newJump.nb_questions"
              type="number"
              min="1"
              max="30"
              :disabled="newJump.autoQuestions"
              class="w-full px-3 py-2 border border-border rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm disabled:opacity-50 disabled:cursor-not-allowed"
            />
          </div>

          <!-- Growth slider -->
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Croissance de difficulté : {{ newJump.growth }}</label>
            <input
              v-model.number="newJump.growth"
              type="range"
              min="0"
              max="10"
              class="w-full"
            />
            <div class="flex justify-between text-xs text-text-muted mt-1">
              <span>0 (uniforme)</span>
              <span>10 (très difficile)</span>
            </div>
          </div>

          <!-- Status -->
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Statut initial</label>
            <select
              v-model="newJump.status"
              class="w-full px-3 py-2 border border-border rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm"
            >
              <option value="draft">Brouillon</option>
              <option value="active">Actif</option>
            </select>
          </div>
        </div>
        <div class="flex gap-3 mt-6">
          <button
            @click="showNewJumpModal = false"
            class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface transition-colors cursor-pointer"
          >Annuler</button>
          <button
            @click="handleCreateJump"
            :disabled="courseStore.isLoading"
            class="flex-1 px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium transition-colors cursor-pointer disabled:opacity-50"
          >{{ courseStore.isLoading ? 'Création...' : 'Créer' }}</button>
        </div>
      </div>
    </div>

    <!-- Edit Expiry Modal -->
    <div
      v-if="showEditExpiryModal && editingJump"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
      @click.self="showEditExpiryModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-xl">
        <h3 class="text-lg font-bold text-text-main dark:text-surface mb-4">Modifier l'expiration</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-text-main dark:text-surface/80 mb-1">Expiration</label>
            <input
              v-model="editExpiryValue"
              type="datetime-local"
              class="w-full px-3 py-2 border border-border rounded-lg dark:bg-gray-800 dark:text-surface focus:outline-none focus:ring-2 focus:ring-primary text-sm"
            />
          </div>
          <button
            @click="handleExpireNow"
            class="w-full px-4 py-2 rounded-lg bg-error/10 text-error border border-error/30 hover:bg-error/20 text-sm font-medium transition-colors cursor-pointer"
          >Expirer maintenant</button>
        </div>
        <div class="flex gap-3 mt-6">
          <button @click="showEditExpiryModal = false" class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface cursor-pointer">Annuler</button>
          <button @click="handleSaveExpiry" :disabled="courseStore.isLoading" class="flex-1 px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium cursor-pointer disabled:opacity-50">Enregistrer</button>
        </div>
      </div>
    </div>

    <!-- Archive Course Confirm -->
    <div
      v-if="showArchiveConfirm"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
      @click.self="showArchiveConfirm = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-xl">
        <h3 class="text-lg font-bold text-text-main dark:text-surface mb-2">Archiver ce parcours ?</h3>
        <p class="text-sm text-text-muted mb-4">Le parcours ne sera plus accessible aux élèves. Vous pourrez le réactiver à tout moment.</p>
        <div class="flex gap-3">
          <button @click="showArchiveConfirm = false" class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface cursor-pointer">Annuler</button>
          <button @click="handleArchive" :disabled="courseStore.isLoading" class="flex-1 px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-surface font-medium cursor-pointer disabled:opacity-50">Archiver</button>
        </div>
      </div>
    </div>

    <!-- Delete Course Confirm -->
    <div
      v-if="showDeleteCourseConfirm"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
      @click.self="showDeleteCourseConfirm = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-xl">
        <h3 class="text-lg font-bold text-text-main dark:text-surface mb-2">Supprimer ce parcours ?</h3>
        <p class="text-sm text-text-muted mb-4">Cette action est irréversible. Un parcours avec des sauts ne peut pas être supprimé.</p>
        <div class="flex gap-3">
          <button @click="showDeleteCourseConfirm = false" class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface cursor-pointer">Annuler</button>
          <button @click="handleDeleteCourse" :disabled="courseStore.isLoading" class="flex-1 px-4 py-2 rounded-lg bg-error hover:bg-error/80 text-white font-medium cursor-pointer disabled:opacity-50">Supprimer</button>
        </div>
      </div>
    </div>

    <!-- Delete Jump Confirm -->
    <div
      v-if="showDeleteJumpConfirm && deletingJump"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center"
      @click.self="showDeleteJumpConfirm = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-xl">
        <h3 class="text-lg font-bold text-text-main dark:text-surface mb-2">Supprimer ce saut ?</h3>
        <p v-if="deletingJump.attempts_count > 0" class="text-sm text-warning mb-2">
          Attention : {{ deletingJump.attempts_count }} élève(s) ont déjà tenté ce saut. Leurs données seront perdues.
        </p>
        <p class="text-sm text-text-muted mb-4">Cette action est irréversible.</p>
        <div class="flex gap-3">
          <button @click="showDeleteJumpConfirm = false" class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-main dark:text-surface cursor-pointer">Annuler</button>
          <button @click="handleDeleteJump" :disabled="courseStore.isLoading" class="flex-1 px-4 py-2 rounded-lg bg-error hover:bg-error/80 text-white font-medium cursor-pointer disabled:opacity-50">Supprimer</button>
        </div>
      </div>
    </div>

    <!-- Jump Observation Modal -->
    <div
      v-if="showJumpObservationModal && observedJump"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
      @click.self="closeJumpObservation"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-border">
          <div>
            <h3 class="text-lg font-semibold text-text-main dark:text-surface flex items-center gap-2">
              Saut {{ jumpNumber(observedJump) }}
              <span v-if="observedJump.status === 'active'" class="text-xs text-success bg-success/10 px-2 py-0.5 rounded-full">En direct</span>
            </h3>
            <p class="text-sm text-text-muted">{{ courseData.students?.length ?? 0 }} élève{{ (courseData.students?.length ?? 0) !== 1 ? 's' : '' }}</p>
          </div>
          <button @click="closeJumpObservation" class="text-text-muted hover:text-text-main transition-colors cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>
        <div class="overflow-y-auto flex-1">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800 border-b border-border sticky top-0">
              <tr>
                <th class="px-4 py-3 text-left font-semibold text-text-main dark:text-surface">Élève</th>
                <th class="px-4 py-3 text-center font-semibold text-text-main dark:text-surface">Statut</th>
                <th class="px-4 py-3 text-center font-semibold text-text-main dark:text-surface">Temps restant</th>
                <th class="px-4 py-3 text-center font-semibold text-text-main dark:text-surface">Réponses</th>
                <th v-if="observedJump.status === 'expired'" class="px-4 py-3 text-center font-semibold text-text-main dark:text-surface">Score</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr
                v-for="student in courseData.students"
                :key="student.id"
                class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
              >
                <td class="px-4 py-3 font-medium text-text-main dark:text-surface">
                  {{ student.pivot?.class_name ?? student.name }}
                </td>
                <td class="px-4 py-3 text-center">
                  <span v-if="!getAttempt(observedJump, student)" class="text-xs text-text-muted">Non commencé</span>
                  <span v-else-if="getAttempt(observedJump, student).status === 'inProgress'" class="text-xs px-2 py-0.5 rounded-full bg-success/10 text-success">En cours</span>
                  <span v-else class="text-xs px-2 py-0.5 rounded-full bg-text-muted/10 text-text-muted">Terminé</span>
                </td>
                <td class="px-4 py-3 text-center font-mono text-sm text-text-muted">
                  <template v-if="getAttempt(observedJump, student)?.status === 'inProgress'">
                    {{ formatObservationTimer(getAttempt(observedJump, student).timer) }}
                  </template>
                  <span v-else class="opacity-30">—</span>
                </td>
                <td class="px-4 py-3">
                  <div v-if="getAttempt(observedJump, student)" class="flex gap-1 justify-center flex-wrap">
                    <span
                      v-for="(item, idx) in getAttempt(observedJump, student).question_list"
                      :key="idx"
                      class="w-6 h-6 rounded text-xs font-bold flex items-center justify-center"
                      :class="observedJump.status === 'expired'
                        ? item.status === 'correct' ? 'bg-success text-white' : item.status === 'incorrect' ? 'bg-error text-white' : 'bg-gray-200 dark:bg-gray-700 text-text-muted'
                        : (item.answer !== null && item.answer !== undefined) ? 'bg-primary/20 text-primary' : 'bg-gray-200 dark:bg-gray-700 text-text-muted'"
                    >{{ idx + 1 }}</span>
                  </div>
                  <span v-else class="block text-center opacity-30 text-xs">—</span>
                </td>
                <td v-if="observedJump.status === 'expired'" class="px-4 py-3 text-center font-semibold text-primary">
                  {{ getAttempt(observedJump, student)?.score ?? '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Attempt Detail Modal -->
    <div
      v-if="showAttemptDetailModal && selectedAttemptDetail"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
      @click.self="showAttemptDetailModal = false"
    >
      <div class="bg-surface dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-border">
          <div>
            <h3 class="text-lg font-semibold text-text-main dark:text-surface">
              {{ selectedAttemptDetail.student?.pivot?.class_name ?? selectedAttemptDetail.student?.name }}
            </h3>
            <p class="text-sm text-text-muted">Saut {{ jumpNumber(selectedAttemptDetail.jump) }} · {{ formatJumpDate(selectedAttemptDetail.jump.created_at) }}</p>
          </div>
          <button @click="showAttemptDetailModal = false" class="text-text-muted hover:text-text-main transition-colors cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>
        <div class="p-4 overflow-y-auto flex-1 space-y-3">
          <p class="text-center text-3xl font-bold text-primary mb-1">Score : {{ selectedAttemptDetail.attempt.score }}</p>
          <div
            v-for="(item, idx) in selectedAttemptDetail.attempt.question_list"
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronLeft, Plus, X, Clock, Trash2, Eye, RotateCcw } from 'lucide-vue-next';
import { useCourseStore } from '@/stores/courseStore';

const route = useRoute();
const router = useRouter();
const courseStore = useCourseStore();

const courseId = computed(() => route.params.courseId);
const divisionId = computed(() => route.params.id);
const courseData = ref(null);

const showNewJumpModal = ref(false);
const showJumpObservationModal = ref(false);
const observingJumpId = ref(null);
const observedJump = computed(() => courseData.value?.jumps?.find(j => j.id === observingJumpId.value) ?? null);
const showDeleteCourseConfirm = ref(false);
const showArchiveConfirm = ref(false);
const showDeleteJumpConfirm = ref(false);
const showEditExpiryModal = ref(false);
const showAttemptDetailModal = ref(false);

const studentSearch = ref('');
const sortKey = ref('name');
const sortDir = ref('asc');
const openMenuJumpId = ref(null);

const editingJump = ref(null);
const deletingJump = ref(null);
const editExpiryValue = ref('');
const selectedAttemptDetail = ref(null);

const newJump = ref({
  time: 15,
  nb_questions: 7,
  growth: 3,
  status: 'active',
  autoQuestions: true,
});

function updateAutoQuestions() {
  if (newJump.value.autoQuestions) {
    newJump.value.nb_questions = Math.max(1, Math.ceil(newJump.value.time / 2));
  }
}

function formatObservationTimer(seconds) {
  if (!seconds) return '0:00';
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `${m}:${String(s).padStart(2, '0')}`;
}

function formatJumpDate(val) {
  if (!val) return '';
  return new Date(val).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
}

function jumpNumber(jump) {
  if (!courseData.value?.jumps) return '';
  const sorted = [...courseData.value.jumps].sort((a, b) => a.id - b.id);
  return sorted.findIndex(j => j.id === jump.id) + 1;
}

function jumpStatusLabel(status) {
  const labels = { draft: 'Brouillon', active: 'Actif', expiring: 'En notation', expired: 'Expiré' };
  return labels[status] ?? status;
}

function getAttempt(jump, student) {
  return jump.attempts?.find(a => a.user_id === student.id) ?? null;
}

function openAttemptDetail(jump, student) {
  const attempt = getAttempt(jump, student);
  if (!attempt) return;
  selectedAttemptDetail.value = { jump, student, attempt };
  showAttemptDetailModal.value = true;
}

function openEditExpiryModal(jump) {
  editingJump.value = jump;
  const d = jump.expiration ? new Date(jump.expiration) : new Date();
  // datetime-local expects local time; offset from UTC to local
  const offset = d.getTimezoneOffset() * 60_000;
  editExpiryValue.value = new Date(d.getTime() - offset).toISOString().slice(0, 16);
  showEditExpiryModal.value = true;
}

function confirmDeleteJump(jump) {
  deletingJump.value = jump;
  showDeleteJumpConfirm.value = true;
}

async function handleCreateJump() {
  try {
    await courseStore.createJump(courseId.value, {
      nb_questions: newJump.value.nb_questions,
      time: newJump.value.time,
      growth: newJump.value.growth,
      status: newJump.value.status,
    });
    showNewJumpModal.value = false;
    await loadDetails();
  } catch {
    // error shown by store
  }
}

async function activateJump(jump) {
  try {
    await courseStore.updateJump(jump.id, { status: 'active' });
    await loadDetails();
  } catch {
    // error shown by store
  }
}

async function reopenJump(jump) {
  try {
    await courseStore.updateJump(jump.id, { status: 'active' });
    await loadDetails();
    subscribeToActiveJumps();
  } catch {
    // error shown by store
  }
}

async function handleSaveExpiry() {
  try {
    await courseStore.updateJump(editingJump.value.id, { expiration: new Date(editExpiryValue.value).toISOString() });
    showEditExpiryModal.value = false;
    await loadDetails();
  } catch {
    // error shown by store
  }
}

async function handleExpireNow() {
  try {
    await courseStore.updateJump(editingJump.value.id, { expiration: new Date().toISOString(), status: 'expiring' });
    showEditExpiryModal.value = false;
    await loadDetails();
  } catch {
    // error shown by store
  }
}

async function handleDeleteJump() {
  try {
    await courseStore.deleteJump(deletingJump.value.id);
    showDeleteJumpConfirm.value = false;
    await loadDetails();
  } catch {
    // error shown by store
  }
}

async function handleArchive() {
  try {
    await courseStore.updateCourse(courseId.value, { archived: true });
    showArchiveConfirm.value = false;
    router.push(`/my/divisions/${courseData.value?.course?.division_id ?? divisionId.value}`);
  } catch {
    // error shown by store
  }
}

async function handleUnarchive() {
  try {
    await courseStore.updateCourse(courseId.value, { archived: false });
    await loadDetails();
  } catch {
    // error shown by store
  }
}

async function handleDeleteCourse() {
  try {
    await courseStore.deleteCourse(courseId.value);
    router.push(`/my/divisions/${divisionId.value}`);
  } catch {
    showDeleteCourseConfirm.value = false;
    // error shown by store
  }
}

const filteredSortedStudents = computed(() => {
  if (!courseData.value?.students) return [];

  let students = courseData.value.students;

  const q = studentSearch.value.trim().toLowerCase();
  if (q) {
    students = students.filter((s) => {
      const name = (s.pivot?.class_name ?? s.name).toLowerCase();
      return name.includes(q);
    });
  }

  const dir = sortDir.value === 'asc' ? 1 : -1;

  return [...students].sort((a, b) => {
    if (sortKey.value === 'name') {
      const na = (a.pivot?.class_name ?? a.name).toLowerCase();
      const nb = (b.pivot?.class_name ?? b.name).toLowerCase();
      return na.localeCompare(nb) * dir;
    }
    if (sortKey.value === 'total') {
      return (studentTotal(a) - studentTotal(b)) * dir;
    }
    // sort by jump id
    const jump = courseData.value.jumps?.find((j) => j.id === sortKey.value);
    if (!jump) return 0;
    const sa = getAttempt(jump, a)?.score ?? -1;
    const sb = getAttempt(jump, b)?.score ?? -1;
    return (sa - sb) * dir;
  });
});

function studentTotal(student) {
  if (!courseData.value?.jumps) return 0;
  return courseData.value.jumps
    .filter((j) => j.status === 'expired')
    .reduce((sum, jump) => {
      const attempt = getAttempt(jump, student);
      return sum + (attempt?.score ?? 0);
    }, 0);
}

function setSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortDir.value = 'asc';
  }
}

async function loadDetails() {
  const data = await courseStore.fetchCourseDetails(courseId.value);
  courseData.value = data;
}

let subscribedJumpIds = [];
let observedJumpPrivateChannelId = null;
let expiringPollTimer = null;

function openJumpObservation(jump) {
  observingJumpId.value = jump.id;
  showJumpObservationModal.value = true;

  loadDetails();

  if (jump.status === 'active') {
    observedJumpPrivateChannelId = jump.id;
    window.Echo.private(`jump.${jump.id}`)
      .listen('.JumpAttemptUpdated', (e) => {
        const jumpData = courseData.value?.jumps?.find(j => j.id === jump.id);
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
  if (observedJumpPrivateChannelId) {
    window.Echo.leaveChannel(`private-jump.${observedJumpPrivateChannelId}`);
    observedJumpPrivateChannelId = null;
  }
  showJumpObservationModal.value = false;
  observingJumpId.value = null;
}

function subscribeToActiveJumps() {
  const activeJumps = courseData.value?.jumps?.filter(j => j.status === 'active' || j.status === 'expiring') ?? [];
  for (const jump of activeJumps) {
    if (subscribedJumpIds.includes(jump.id)) continue;
    subscribedJumpIds.push(jump.id);
    window.Echo.channel(`jump.${jump.id}`)
      .listen('.JumpExpired', async () => {
        await loadDetails();
        subscribeToActiveJumps();
      });
  }
}

watch(
  () => courseData.value?.jumps?.some(j => j.status === 'expiring'),
  (hasExpiring) => {
    if (hasExpiring && !expiringPollTimer) {
      expiringPollTimer = setInterval(async () => {
        await loadDetails();
        subscribeToActiveJumps();
      }, 3000);
    } else if (!hasExpiring && expiringPollTimer) {
      clearInterval(expiringPollTimer);
      expiringPollTimer = null;
    }
  },
);

onMounted(async () => {
  await loadDetails();
  updateAutoQuestions();
  subscribeToActiveJumps();
});

onUnmounted(() => {
  for (const id of subscribedJumpIds) {
    window.Echo.leave(`jump.${id}`);
  }
  subscribedJumpIds = [];
  if (observedJumpPrivateChannelId) {
    window.Echo.leaveChannel(`private-jump.${observedJumpPrivateChannelId}`);
    observedJumpPrivateChannelId = null;
  }
  if (expiringPollTimer) {
    clearInterval(expiringPollTimer);
    expiringPollTimer = null;
  }
});
</script>
