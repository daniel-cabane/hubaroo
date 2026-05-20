<?php

namespace App\Http\Controllers;

use App\Events\JumpAttemptUpdated;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\JumpQuestionSelector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JumpAttemptController extends Controller
{
    public function __construct(public JumpQuestionSelector $questionSelector) {}

    public function store(Jump $jump, Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $jump->isActive()) {
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
            'timer' => 0,
            'extra_time' => 0,
            'termination' => 'none',
        ]);

        broadcast(new JumpAttemptUpdated($attempt->fresh()->load('user:id,name,email')));

        return response()->json([
            'message' => 'Attempt created.',
            'attempt' => $this->withQuestionImages($attempt->load('jump.course')),
        ], 201);
    }

    public function show(JumpAttempt $jumpAttempt, Request $request): JsonResponse
    {
        $this->authorizeAttemptAccess($jumpAttempt, $request->user());

        $attempt = $jumpAttempt->load(['jump.course.division', 'user:id,name,email']);

        $shouldHideCorrectAnswers = ! $attempt->jump->isExpired();

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

    public function updateAnswer(JumpAttempt $jumpAttempt, Request $request): JsonResponse
    {
        if ($jumpAttempt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($jumpAttempt->status !== 'inProgress') {
            return response()->json(['message' => 'This attempt is not in progress.'], 403);
        }

        $request->validate([
            'question_index' => ['required', 'integer', 'min:0'],
            'answer' => ['nullable', 'string', 'max:10'],
            'timer' => ['nullable', 'integer', 'min:0'],
        ]);

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

        broadcast(new JumpAttemptUpdated($jumpAttempt->fresh()));

        return response()->json(['attempt' => $this->withQuestionImages($jumpAttempt->fresh())]);
    }

    public function submit(JumpAttempt $jumpAttempt, Request $request): JsonResponse
    {
        if ($jumpAttempt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($jumpAttempt->status !== 'inProgress') {
            return response()->json(['message' => 'Already submitted.'], 403);
        }

        $request->validate([
            'timer' => ['nullable', 'integer', 'min:0'],
            'termination' => ['nullable', 'string', 'in:submitted,timeout,blurred'],
        ]);

        $jumpAttempt->update([
            'status' => 'finished',
            'timer' => $request->integer('timer', $jumpAttempt->timer),
            'termination' => $request->input('termination', 'submitted'),
        ]);

        $fresh = $jumpAttempt->fresh()->load('jump.course');

        broadcast(new JumpAttemptUpdated($fresh));

        return response()->json(['attempt' => $this->withQuestionImages($fresh)]);
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

    public function myIndex(Request $request): JsonResponse
    {
        $attempts = JumpAttempt::where('user_id', $request->user()->id)
            ->with('jump.course.division')
            ->orderByDesc('id')
            ->get();

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
