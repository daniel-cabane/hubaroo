<?php

namespace App\Http\Controllers;

use App\Events\JumpAttemptUpdated;
use App\Http\Requests\SubmitJumpAttemptRequest;
use App\Http\Requests\SyncJumpAttemptRequest;
use App\Jobs\AnalyseJump;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\JumpGradingService;
use App\Services\JumpQuestionSelector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JumpAttemptController extends Controller
{
    public function __construct(
        public JumpQuestionSelector $questionSelector,
        public JumpGradingService $jumpGradingService,
    ) {}

    public function store(Jump $jump, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($jump->isClosed() || ! $jump->isActive()) {
            return response()->json(['message' => "Ce saut n'est pas disponible."], 403);
        }

        $division = $jump->course->division;
        $isMember = $division->students()->where('users.id', $user->id)->exists();

        if (! $isMember) {
            return response()->json(['message' => "Vous n'êtes pas membre de cette classe."], 403);
        }

        $existing = JumpAttempt::where('jump_id', $jump->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Vous avez déjà une tentative pour ce saut.',
                'requires_rejoin' => true,
                'attempt' => $existing,
            ], 409);
        }

        $questionList = $this->questionSelector->selectQuestions($jump, $user, $division);

        $attempt = JumpAttempt::create([
            'jump_id' => $jump->id,
            'user_id' => $user->id,
            'question_list' => $questionList,
            'score' => 0,
            'status' => 'inProgress',
            'timer' => $jump->time * 60,
            'extra_time' => 0,
            'termination' => 'none',
        ]);

        broadcast(new JumpAttemptUpdated($attempt->fresh()));

        return response()->json([
            'message' => 'Attempt created.',
            'attempt' => $this->withQuestionImages($attempt->load('jump.course')),
        ], 201);
    }

    public function show(JumpAttempt $jumpAttempt, Request $request): JsonResponse
    {
        $this->authorizeAttemptAccess($jumpAttempt, $request->user());

        $attempt = $jumpAttempt->load(['jump.course.division', 'user:id,name,email']);

        // Compute rank once (avoids N+1 from $appends — W10)
        $jump = $attempt->jump;
        $jump->rank = Jump::where('course_id', $jump->course_id)->where('id', '<=', $jump->id)->count();

        $shouldHideCorrectAnswers = ! $jump->isExpired();

        $questionIds = collect($attempt->question_list)->pluck('id');
        $questions = Question::whereIn('id', $questionIds)->get()->keyBy('id');

        $questionList = collect($attempt->question_list)->map(function ($item) use ($shouldHideCorrectAnswers, $attempt, $questions) {
            $question = $questions->get($item['id']);
            if ($question) {
                $item['image'] = $question->image;
                $item['tier'] = $question->tier;
            }

            if ($shouldHideCorrectAnswers && $attempt->status === 'inProgress') {
                return $item;
            }
            if ($shouldHideCorrectAnswers) {
                unset($item['correct_answer']);
            } elseif ($question) {
                $item['correct_answer'] = $question->correct_answer;
            }

            return $item;
        })->toArray();

        $attemptData = $attempt->toArray();
        $attemptData['question_list'] = $questionList;

        return response()->json(['attempt' => $attemptData]);
    }

    public function updateAnswer(JumpAttempt $jumpAttempt, Request $request): Response
    {
        if ($jumpAttempt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $jump = $jumpAttempt->jump;

        $request->validate([
            'question_index' => ['required', 'integer', 'min:0'],
            'answer' => ['nullable', 'string', 'max:10'],
            'timer' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($jump->isClosed()) {
            if (! $this->canWriteAnswersAfterClose($jumpAttempt, $jump)) {
                return response()->json([
                    'jump_closed' => true,
                    'saved' => false,
                ]);
            }
        } elseif ($jumpAttempt->status !== 'inProgress') {
            return response()->json(['message' => 'This attempt is not in progress.'], 403);
        }

        $index = $request->integer('question_index');
        $questionList = $jumpAttempt->question_list ?? [];

        if (! isset($questionList[$index])) {
            return response()->json(['message' => 'Invalid question index.'], 422);
        }

        $questionList[$index]['answer'] = $request->input('answer');

        $jumpAttempt->update([
            'question_list' => $questionList,
            'timer' => $request->integer('timer', $jumpAttempt->timer),
        ]);

        if ($jump->isClosed()) {
            broadcast(new JumpAttemptUpdated($jumpAttempt->fresh()));

            return response()->json([
                'jump_closed' => true,
                'saved' => true,
            ]);
        }

        return response()->noContent();
    }

    public function sync(SyncJumpAttemptRequest $request, JumpAttempt $jumpAttempt): Response
    {
        $jump = $jumpAttempt->jump;

        if ($jump->isClosed()) {
            if (! $this->canWriteAnswersAfterClose($jumpAttempt, $jump)) {
                return response()->json([
                    'jump_closed' => true,
                    'saved' => false,
                ]);
            }

            return $this->persistSyncChanges($request, $jumpAttempt, closed: true);
        }

        if ($jumpAttempt->status !== 'inProgress') {
            return response()->json(['message' => 'This attempt is not in progress.'], 403);
        }

        return $this->persistSyncChanges($request, $jumpAttempt, closed: false);
    }

    public function submit(SubmitJumpAttemptRequest $request, JumpAttempt $jumpAttempt): JsonResponse
    {
        $jump = $jumpAttempt->jump;
        $alreadyFinished = $jumpAttempt->status === 'finished';
        $jumpClosed = $jump->isClosed();

        if ($alreadyFinished && ! $jumpClosed) {
            return response()->json(['message' => 'Already submitted.'], 403);
        }

        $hasQuestionList = $request->has('question_list') && is_array($request->input('question_list'));
        $shouldPersistQuestionList = $hasQuestionList && (! $jumpClosed || $jump->allowsLateAnswerSave());

        if ($alreadyFinished && ! $shouldPersistQuestionList) {
            return $this->submitSuccessResponse($jumpAttempt, broadcast: false);
        }

        $previousQuestionList = $jumpAttempt->question_list;
        $updateData = [];

        if ($request->exists('timer')) {
            $updateData['timer'] = $request->integer('timer', $jumpAttempt->timer);
        }

        $requestedTermination = $request->input('termination', 'submitted');

        if ($jumpAttempt->status === 'inProgress' || $jumpAttempt->termination === 'timeout') {
            $updateData['termination'] = $requestedTermination;
        }

        if ($jumpAttempt->status === 'inProgress') {
            $updateData['status'] = 'finished';
        }

        if ($shouldPersistQuestionList) {
            $updateData['question_list'] = $request->input('question_list');
        }

        if ($updateData !== []) {
            $jumpAttempt->update($updateData);
        }

        $answersChanged = $shouldPersistQuestionList
            && $this->questionListAnswersChanged($previousQuestionList, $jumpAttempt->question_list);

        if ($jump->isExpired() && $answersChanged) {
            $this->jumpGradingService->gradeAttempt($jumpAttempt->fresh());
            AnalyseJump::dispatch($jump->fresh());
        }

        return $this->submitSuccessResponse($jumpAttempt, broadcast: true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $questionList
     * @return array<string, mixed>
     */
    private function withQuestionImages(JumpAttempt $attempt): array
    {
        $questionIds = collect($attempt->question_list)->pluck('id');
        $questions = Question::whereIn('id', $questionIds)->get()->keyBy('id');

        $questionList = collect($attempt->question_list)->map(function ($item) use ($questions) {
            $question = $questions->get($item['id']);
            if ($question) {
                $item['image'] = $question->image;
                $item['tier'] = $question->tier;
            }

            return $item;
        })->toArray();

        $data = $attempt->toArray();
        $data['question_list'] = $questionList;

        return $data;
    }

    private function canWriteAnswersAfterClose(JumpAttempt $attempt, Jump $jump): bool
    {
        if (! $jump->allowsLateAnswerSave()) {
            return false;
        }

        if ($attempt->status === 'inProgress') {
            return true;
        }

        return $attempt->status === 'finished' && $attempt->termination === 'timeout';
    }

    private function persistSyncChanges(SyncJumpAttemptRequest $request, JumpAttempt $jumpAttempt, bool $closed): Response
    {
        $questionList = $jumpAttempt->question_list ?? [];
        $questionListChanged = false;

        foreach ($request->validated('changes', []) as $change) {
            $index = $change['question_index'];

            if (! isset($questionList[$index])) {
                return response()->json(['message' => 'Invalid question index.'], 422);
            }

            $newAnswer = $change['answer'];
            $currentAnswer = $questionList[$index]['answer'] ?? null;

            if ($currentAnswer !== $newAnswer) {
                $questionList[$index]['answer'] = $newAnswer;
                $questionListChanged = true;
            }
        }

        $timer = $request->integer('timer', $jumpAttempt->timer);
        $timerChanged = $timer !== $jumpAttempt->timer;

        if ($questionListChanged || $timerChanged) {
            $jumpAttempt->update([
                'question_list' => $questionList,
                'timer' => $timer,
            ]);

            broadcast(new JumpAttemptUpdated($jumpAttempt->fresh()));
        }

        if ($closed) {
            return response()->json([
                'jump_closed' => true,
                'saved' => true,
            ]);
        }

        return response()->noContent();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $previous
     * @param  array<int, array<string, mixed>>|null  $next
     */
    private function questionListAnswersChanged(?array $previous, ?array $next): bool
    {
        $normalize = function (?array $list): array {
            return collect($list ?? [])
                ->map(fn (array $item): array => [
                    'id' => $item['id'] ?? null,
                    'answer' => $item['answer'] ?? null,
                ])
                ->values()
                ->all();
        };

        return $normalize($previous) !== $normalize($next);
    }

    private function submitSuccessResponse(JumpAttempt $jumpAttempt, bool $broadcast): JsonResponse
    {
        $fresh = $jumpAttempt->fresh()->load('jump.course');

        if ($broadcast) {
            broadcast(new JumpAttemptUpdated($fresh));
        }

        return response()->json(['attempt' => $this->withQuestionImages($fresh)]);
    }

    public function myIndex(Request $request): JsonResponse
    {
        $attempts = JumpAttempt::where('user_id', $request->user()->id)
            ->with('jump.course.division')
            ->orderByDesc('id')
            ->get();

        // Compute rank for each jump (avoids N+1 from $appends — W10)
        // Group by course so we can count once per course
        $jumpsByCourse = $attempts->pluck('jump')->filter()->groupBy('course_id');
        $jumpsByCourse->each(function ($jumps): void {
            $courseId = $jumps->first()->course_id;
            $orderedIds = Jump::where('course_id', $courseId)->orderBy('id')->pluck('id');
            $rankMap = $orderedIds->values()->mapWithKeys(fn ($id, $index) => [$id => $index + 1]);
            $jumps->each(fn ($jump) => $jump->rank = $rankMap->get($jump->id));
        });

        return response()->json(['attempts' => $attempts]);
    }

    private function authorizeAttemptAccess(JumpAttempt $attempt, ?User $user): void
    {
        if (! $user) {
            abort(401);
        }

        $division = $attempt->jump->course->division;

        if ($attempt->user_id === $user->id) {
            return;
        }

        if ($division->teacher_id === $user->id) {
            return;
        }

        abort(403);
    }
}
