<?php

namespace App\Http\Controllers;

use App\Events\AttemptUpdated;
use App\Http\Requests\UpdateAnswerRequest;
use App\Models\Attempt;
use App\Models\KangourouSession;
use App\Models\User;
use App\Services\GradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttemptController extends Controller
{
    public function __construct(public GradingService $gradingService) {}

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

        // Check if authenticated user already has an attempt for this session
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
        }

        $attemptName = $this->resolveAttemptName($request->user(), $session, $request->input('name'));

        $attempt = Attempt::create([
            'kangourou_session_id' => $session->id,
            'user_id' => $userId,
            'name' => $attemptName,
            'code' => Attempt::generateCode(),
            'answers' => Attempt::defaultAnswers(),
            'status' => 'inProgress',
            'termination' => 'none',
        ]);

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

    public function show(Attempt $attempt): JsonResponse
    {
        $attempt->load('kangourouSession.paper');

        return response()->json(['attempt' => $this->maskCorrectionIfNeeded($attempt)]);
    }

    public function updateAnswer(UpdateAnswerRequest $request, Attempt $attempt): JsonResponse
    {
        if ($attempt->status === 'finished') {
            return response()->json(['message' => 'This attempt has already been submitted.'], 403);
        }

        $session = $attempt->kangourouSession;
        if (! $session->isActive()) {
            return response()->json(['message' => 'This session is no longer active.'], 403);
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

        $fresh = $attempt->fresh();
        broadcast(new AttemptUpdated($fresh));

        return response()->json([
            'message' => 'Answer saved.',
            'attempt' => $fresh,
        ]);
    }

    public function submit(Attempt $attempt, Request $request): JsonResponse
    {
        if ($attempt->status === 'finished') {
            return response()->json(['message' => 'This attempt has already been submitted.'], 403);
        }

        $request->validate([
            'timer' => ['nullable', 'integer', 'min:0'],
            'termination' => ['nullable', 'in:submitted,blurred,timeout'],
        ]);

        $attempt->update([
            'timer' => $request->input('timer'),
            'termination' => $request->input('termination', 'submitted'),
        ]);

        $score = $this->gradingService->gradeAndSave($attempt);

        $fresh = $this->maskCorrectionIfNeeded($attempt->fresh());
        broadcast(new AttemptUpdated($fresh));

        return response()->json([
            'message' => 'Attempt submitted and graded.',
            'score' => $score,
            'attempt' => $fresh,
        ]);
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
