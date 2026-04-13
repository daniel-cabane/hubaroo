<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAnswerRequest;
use App\Models\Attempt;
use App\Models\KangourouSession;
use App\Services\GradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttemptController extends Controller
{
    public function __construct(public GradingService $gradingService) {}

    public function store(string $code, Request $request): JsonResponse
    {
        $session = KangourouSession::where('code', $code)->firstOrFail();

        if (! $session->isActive()) {
            return response()->json(['message' => 'This session is no longer active.'], 403);
        }

        $attempt = Attempt::create([
            'kangourou_session_id' => $session->id,
            'user_id' => $request->user()?->id,
            'code' => Attempt::generateCode(),
            'answers' => Attempt::defaultAnswers(),
            'status' => 'inProgress',
        ]);

        return response()->json([
            'message' => 'Attempt created.',
            'attempt' => $attempt,
        ], 201);
    }

    public function show(Attempt $attempt): JsonResponse
    {
        return response()->json(['attempt' => $attempt->load('kangourouSession.paper')]);
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

        $attempt->update(['answers' => $answers]);

        return response()->json([
            'message' => 'Answer saved.',
            'attempt' => $attempt->fresh(),
        ]);
    }

    public function submit(Attempt $attempt): JsonResponse
    {
        if ($attempt->status === 'finished') {
            return response()->json(['message' => 'This attempt has already been submitted.'], 403);
        }

        $score = $this->gradingService->gradeAndSave($attempt);

        return response()->json([
            'message' => 'Attempt submitted and graded.',
            'score' => $score,
            'attempt' => $attempt->fresh(),
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
}
