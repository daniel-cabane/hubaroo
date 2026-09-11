<?php

namespace App\Http\Controllers;

use App\Events\AttemptNameUpdated;
use App\Events\AttemptUpdated;
use App\Http\Requests\SubmitAttemptRequest;
use App\Http\Requests\SyncAttemptRequest;
use App\Http\Requests\UpdateAnswerRequest;
use App\Jobs\UpdateMasteryAndDifficulty;
use App\Models\Attempt;
use App\Models\KangourouSession;
use App\Models\User;
use App\Services\GradingService;
use App\Services\SessionAnalysisService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttemptController extends Controller
{
    public function __construct(
        public GradingService $gradingService,
        public SessionAnalysisService $sessionAnalysisService,
    ) {}

    public function store(string $code, Request $request): JsonResponse
    {
        $session = KangourouSession::where('code', $code)->firstOrFail();

        if ($session->status === 'draft') {
            return response()->json(['message' => "Cette session n'est pas encore disponible."], 403);
        }

        if (! $session->isActive()) {
            return response()->json(['message' => 'Cette session est terminée.'], 403);
        }

        if ($session->privacy === 'private') {
            $user = $request->user();

            if (! $user) {
                return response()->json(['message' => 'Vous devez être connecté pour rejoindre cette session.'], 401);
            }

            $isMember = $session->divisions()
                ->whereHas('students', fn ($q) => $q->where('users.id', $user->id))
                ->exists();

            if (! $isMember) {
                return response()->json(['message' => "Vous n'êtes pas membre d'une classe pour laquelle cette session est ouverte."], 403);
            }
        }

        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        // Check if the user already has an attempt for this session
        $userId = $request->user()?->id;
        if ($userId) {
            $existingAttempt = Attempt::where('kangourou_session_id', $session->id)
                ->where('user_id', $userId)
                ->first();

            if ($existingAttempt) {
                return response()->json([
                    'message' => 'You already have an attempt for this session.',
                    'requires_rejoin' => true,
                    'attempt' => $existingAttempt,
                ], 409);
            }
        } elseif ($guestName = $request->input('name')) {
            $existingAttempt = Attempt::where('kangourou_session_id', $session->id)
                ->whereNull('user_id')
                ->where('name', $guestName)
                ->first();

            if ($existingAttempt) {
                return response()->json([
                    'message' => 'You already have an attempt for this session.',
                    'requires_rejoin' => true,
                    'attempt' => $existingAttempt,
                ], 409);
            }
        }

        $attemptName = $this->resolveAttemptName($request->user(), $session, $request->input('name'));

        do {
            try {
                $attempt = Attempt::create([
                    'kangourou_session_id' => $session->id,
                    'user_id' => $userId,
                    'name' => $attemptName,
                    'code' => Attempt::generateCode(),
                    'answers' => Attempt::defaultAnswers(),
                    'status' => 'inProgress',
                    'termination' => 'none',
                ]);
                break;
            } catch (UniqueConstraintViolationException) {
                // retry on rare code collision
            }
        } while (true);

        broadcast(new AttemptUpdated($attempt->fresh()));

        return response()->json([
            'message' => 'Attempt created.',
            'attempt' => $attempt,
        ], 201);
    }

    private function resolveAttemptName(?User $user, KangourouSession $session, ?string $guestName): ?string
    {
        if (! $user) {
            return $guestName;
        }

        $division = $session->divisions()
            ->whereHas('students', fn ($q) => $q->where('users.id', $user->id))
            ->first();

        if ($division) {
            $pivotClassName = $division->students()
                ->where('users.id', $user->id)
                ->first()
                ?->pivot
                ?->class_name;

            if ($pivotClassName) {
                return $pivotClassName;
            }
        }

        return $user->name;
    }

    public function show(Attempt $attempt, Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && $attempt->user_id !== null && $attempt->user_id !== $user->id) {
            $session = $attempt->kangourouSession;
            if ($session->author_id !== $user->id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        $attempt->load('kangourouSession.paper');

        return response()->json(['attempt' => $this->maskCorrectionIfNeeded($attempt)]);
    }

    public function updateAnswer(UpdateAnswerRequest $request, Attempt $attempt): JsonResponse
    {
        if ($forbidden = $this->forbiddenUnlessOwner($request, $attempt)) {
            return $forbidden;
        }

        $session = $attempt->kangourouSession;

        if ($session->isExpired()) {
            if (! $this->canSyncAnswersAfterExpiry($attempt, $session)) {
                return response()->json(['attempt' => $attempt->fresh()]);
            }
        } elseif (! $session->isActive()) {
            return response()->json(['message' => 'This session is no longer active.'], 403);
        } elseif ($attempt->status === 'finished') {
            return response()->json(['message' => 'This attempt has already been submitted.'], 403);
        }

        $answers = $attempt->answers;
        $index = $request->validated('question_index');
        $answer = $request->validated('answer');

        $answers[$index]['answer'] = $answer;
        $answers[$index]['status'] = $answer ? 'answered' : 'unanswered';

        $attempt->update([
            'answers' => $answers,
            'timer' => $request->validated('timer'),
        ]);

        broadcast(new AttemptUpdated($attempt->fresh()));

        return response()->json(['attempt' => $attempt->fresh()]);
    }

    public function sync(SyncAttemptRequest $request, Attempt $attempt): Response
    {
        if ($forbidden = $this->forbiddenUnlessOwner($request, $attempt)) {
            return $forbidden;
        }

        $session = $attempt->kangourouSession;

        if ($session->isActive()) {
            if ($attempt->status === 'finished') {
                return response()->json(['message' => 'This attempt has already been submitted.'], 403);
            }

            return $this->persistSyncChanges($request, $attempt, expired: false);
        }

        if ($session->isExpired()) {
            if (! $this->canSyncAnswersAfterExpiry($attempt, $session)) {
                return response()->json([
                    'session_expired' => true,
                    'saved' => false,
                ]);
            }

            return $this->persistSyncChanges($request, $attempt, expired: true);
        }

        return response()->json(['message' => 'This session is no longer active.'], 403);
    }

    public function submit(SubmitAttemptRequest $request, Attempt $attempt): JsonResponse
    {
        if ($forbidden = $this->forbiddenUnlessOwner($request, $attempt)) {
            return $forbidden;
        }

        $session = $attempt->kangourouSession;
        $alreadyFinished = $attempt->status === 'finished';
        $alreadyScored = $attempt->score !== null;

        if ($alreadyFinished && ! $session->isExpired()) {
            return response()->json(['message' => 'This attempt has already been submitted.'], 403);
        }

        $hasAnswers = $request->has('answers') && is_array($request->input('answers'));
        $shouldPersistAnswers = $hasAnswers && (! $session->isExpired() || $session->allowsLateAnswerSave());

        if ($alreadyFinished && ! $shouldPersistAnswers) {
            return $this->submitSuccessResponse($attempt, $attempt->score, delayGrading: false, broadcast: false);
        }

        $previousAnswers = $attempt->answers;
        $updateData = [];

        if ($request->exists('timer')) {
            $updateData['timer'] = $request->input('timer');
        }

        $requestedTermination = $request->input('termination', 'submitted');

        if ($attempt->status === 'inProgress' || $attempt->termination === 'timeout') {
            $updateData['termination'] = $requestedTermination;
        }

        if ($shouldPersistAnswers) {
            $updateData['answers'] = $this->normalizeSubmittedAnswers($request->input('answers'));
        }

        $delayGrading = $session->shouldDelayGrading();

        if ($delayGrading) {
            $updateData['status'] = 'finished';
        }

        if ($updateData !== []) {
            $attempt->update($updateData);
        }

        $answersChanged = $shouldPersistAnswers
            && json_encode($previousAnswers) !== json_encode($attempt->answers);

        $score = $alreadyScored ? $attempt->score : null;

        if (! $delayGrading) {
            $score = $this->gradingService->gradeAndSave($attempt);

            if (! $alreadyScored) {
                UpdateMasteryAndDifficulty::dispatch($attempt);
            }
        }

        if ($session->isExpired() && $answersChanged) {
            $this->sessionAnalysisService->compute($session);
        }

        return $this->submitSuccessResponse($attempt, $score, $delayGrading, broadcast: true);
    }

    public function recover(string $code): JsonResponse
    {
        $attempt = Attempt::where('code', $code)
            ->with('kangourouSession')
            ->firstOrFail();

        return response()->json(['attempt' => $attempt]);
    }

    public function myIndex(Request $request): JsonResponse
    {
        $attempts = $request->user()
            ->attempts()
            ->with('kangourouSession.paper')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json(['attempts' => $attempts]);
    }

    public function update(Request $request, Attempt $attempt): JsonResponse
    {
        // Authorize user (session owner or admin can edit)
        $session = $attempt->kangourouSession;
        $this->authorize('update', $session);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $attempt->update([
            'name' => $request->input('name'),
        ]);

        broadcast(new AttemptNameUpdated($attempt->fresh()));

        return response()->json([
            'message' => 'Attempt updated successfully.',
            'attempt' => $attempt,
        ]);
    }

    public function destroy(Attempt $attempt): JsonResponse
    {
        // Authorize user (session owner or admin can delete)
        $session = $attempt->kangourouSession;
        $this->authorize('delete', $session);

        $attempt->delete();

        return response()->json([
            'message' => 'Attempt deleted successfully.',
        ]);
    }

    public function claim(Request $request): JsonResponse
    {
        $request->validate([
            'attempt_ids' => ['required', 'array', 'min:1'],
            'attempt_ids.*' => ['required', 'integer', 'exists:attempts,id'],
        ]);

        $user = $request->user();
        $claimed = 0;

        // Get session IDs where user already has an attempt
        $existingSessionIds = Attempt::where('user_id', $user->id)
            ->pluck('kangourou_session_id')
            ->toArray();

        $attempts = Attempt::whereIn('id', $request->input('attempt_ids'))
            ->whereNull('user_id')
            ->whereNotIn('kangourou_session_id', $existingSessionIds)
            ->get();

        foreach ($attempts as $attempt) {
            $attempt->update([
                'user_id' => $user->id,
                'name' => $user->name,
            ]);
            $claimed++;
        }

        return response()->json([
            'message' => "$claimed attempt(s) claimed successfully.",
            'claimed' => $claimed,
        ]);
    }

    private function forbiddenUnlessOwner(Request $request, Attempt $attempt): ?JsonResponse
    {
        $user = $request->user();
        if ($user && $attempt->user_id !== null && $attempt->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return null;
    }

    private function canSyncAnswersAfterExpiry(Attempt $attempt, KangourouSession $session): bool
    {
        if (! $session->allowsLateAnswerSave()) {
            return false;
        }

        if ($attempt->status === 'inProgress') {
            return true;
        }

        return $attempt->status === 'finished' && $attempt->termination === 'timeout';
    }

    private function persistSyncChanges(SyncAttemptRequest $request, Attempt $attempt, bool $expired): Response
    {
        $answers = $attempt->answers ?? Attempt::defaultAnswers();
        $answersChanged = false;

        foreach ($request->validated('changes', []) as $change) {
            $index = (int) $change['question_index'];

            if (! isset($answers[$index])) {
                return response()->json(['message' => 'Invalid question index.'], 422);
            }

            $newAnswer = $change['answer'];
            if ($newAnswer !== null && in_array($index, [24, 25], true)) {
                $newAnswer = (int) $newAnswer;
            }

            $currentAnswer = $answers[$index]['answer'] ?? null;

            if ((string) ($currentAnswer ?? '') !== (string) ($newAnswer ?? '')) {
                $answers[$index]['answer'] = $newAnswer;
                $answers[$index]['status'] = $newAnswer !== null && $newAnswer !== '' ? 'answered' : 'unanswered';
                $answersChanged = true;
            }
        }

        $timer = $request->has('timer') ? $request->integer('timer') : $attempt->timer;
        $timerChanged = $timer !== $attempt->timer;

        if ($answersChanged || $timerChanged) {
            $attempt->update([
                'answers' => $answers,
                'timer' => $timer,
            ]);

            broadcast(new AttemptUpdated($attempt->fresh()));
        }

        if ($expired) {
            return response()->json([
                'session_expired' => true,
                'saved' => true,
            ]);
        }

        return response()->noContent();
    }

    private function submitSuccessResponse(Attempt $attempt, mixed $score, bool $delayGrading, bool $broadcast): JsonResponse
    {
        $fresh = $this->maskCorrectionIfNeeded($attempt->fresh());

        if ($broadcast) {
            broadcast(new AttemptUpdated($fresh));
        }

        return response()->json([
            'message' => $delayGrading ? 'Attempt submitted.' : 'Attempt submitted and graded.',
            'score' => $score,
            'attempt' => $fresh,
        ]);
    }

    /**
     * @param  array<int, mixed>  $answers
     * @return array<int, array{answer: mixed, status: string}>
     */
    private function normalizeSubmittedAnswers(array $answers): array
    {
        $normalized = Attempt::defaultAnswers();

        foreach ($answers as $index => $item) {
            if (! isset($normalized[$index]) || ! is_array($item)) {
                continue;
            }

            $answer = $item['answer'] ?? null;
            if ($answer !== null && in_array((int) $index, [24, 25], true) && is_numeric($answer)) {
                $answer = (int) $answer;
            }
            $normalized[$index]['answer'] = $answer;
            $normalized[$index]['status'] = $answer ? 'answered' : 'unanswered';
        }

        return $normalized;
    }

    /**
     * Mask answer statuses when correction is delayed and session is still active.
     */
    private function maskCorrectionIfNeeded(Attempt $attempt): Attempt
    {
        $session = $attempt->kangourouSession;
        if (! $session) {
            return $attempt;
        }

        $preferences = $session->getEffectivePreferences();

        if ($preferences['correction'] === 'delayed' && ! $session->isExpired()) {
            $answers = array_map(function ($answer) {
                $answer['status'] = $answer['answer'] !== null ? 'answered' : 'unanswered';

                return $answer;
            }, $attempt->answers);

            $attempt->setAttribute('answers', $answers);
        }

        return $attempt;
    }
}
