<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateKangourouSessionRequest;
use App\Models\KangourouSession;
use App\Models\Paper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KangourouSessionController extends Controller
{
    public function store(CreateKangourouSessionRequest $request): JsonResponse
    {
        $session = KangourouSession::create([
            'paper_id' => $request->validated('paper_id'),
            'code' => KangourouSession::generateCode(),
            'author_id' => $request->user()?->id,
            'status' => 'active',
            'privacy' => $request->validated('privacy', 'public'),
            'expires_at' => now()->addMinutes(120),
            'preferences' => $request->validated('preferences', KangourouSession::DEFAULT_PREFERENCES),
        ]);

        return response()->json([
            'message' => 'Session created successfully.',
            'session' => $session,
        ], 201);
    }

    public function show(string $code, Request $request): JsonResponse
    {
        $session = KangourouSession::where('code', $code)
            ->with(['paper.questions' => fn ($q) => $q->orderByPivot('order')])
            ->firstOrFail();

        $data = $session->toArray();

        // Hide correct answers while session is active, unless the user's attempt is finished
        $shouldHideAnswers = $session->isActive();

        if ($shouldHideAnswers && $request->has('attempt_id')) {
            $attempt = $session->attempts()->find($request->query('attempt_id'));
            if ($attempt && $attempt->status === 'finished') {
                $shouldHideAnswers = false;
            }
        }

        if ($shouldHideAnswers) {
            $data['paper']['questions'] = collect($data['paper']['questions'])
                ->map(function ($question) {
                    unset($question['correct_answer']);

                    return $question;
                })
                ->all();
        }

        return response()->json(['session' => $data]);
    }

    public function activate(KangourouSession $kangourouSession): JsonResponse
    {
        $kangourouSession->update(['status' => 'active']);

        return response()->json([
            'message' => 'Session activated.',
            'session' => $kangourouSession->fresh(),
        ]);
    }

    public function myIndex(Request $request): JsonResponse
    {
        $sessions = $request->user()
            ->kangourouSessions()
            ->with('paper')
            ->latest()
            ->get();

        return response()->json(['sessions' => $sessions]);
    }

    public function papers(): JsonResponse
    {
        $papers = Paper::select('id', 'title', 'level', 'year')
            ->orderByDesc('year')
            ->orderBy('level')
            ->get();

        return response()->json(['papers' => $papers]);
    }
}
