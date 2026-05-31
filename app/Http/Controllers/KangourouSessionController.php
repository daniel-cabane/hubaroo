<?php

namespace App\Http\Controllers;

use App\Events\SessionOpenedForDivision;
use App\Http\Requests\CreateKangourouSessionRequest;
use App\Http\Requests\UpdateKangourouSessionRequest;
use App\Http\Resources\QuestionResource;
use App\Models\KangourouSession;
use App\Models\Paper;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KangourouSessionController extends Controller
{
    public function store(CreateKangourouSessionRequest $request): JsonResponse
    {
        do {
            try {
                $session = KangourouSession::create([
                    'paper_id' => $request->validated('paper_id'),
                    'code' => KangourouSession::generateCode(),
                    'author_id' => $request->user()?->id,
                    'status' => $request->validated('status', 'draft'),
                    'privacy' => $request->validated('privacy', 'public'),
                    'expires_at' => now()->addMinutes(120),
                    'preferences' => $request->validated('preferences', KangourouSession::DEFAULT_PREFERENCES),
                ]);
                break;
            } catch (UniqueConstraintViolationException) {
                // retry on rare code collision
            }
        } while (true);

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

        $preferences = $session->getEffectivePreferences();
        $correctionMode = $preferences['correction'] ?? 'delayed';

        // Determine whether to reveal correct answers
        if ($correctionMode === 'immediate') {
            // Show answers once the user's attempt is finished
            $shouldHideAnswers = true;
            if ($request->has('attempt_id')) {
                $attempt = $session->attempts()->find($request->query('attempt_id'));
                if ($attempt && $attempt->status === 'finished') {
                    $shouldHideAnswers = false;
                }
            }
        } else {
            // Delayed: only show answers when session is expired
            $shouldHideAnswers = ! $session->isExpired();
        }

        if ($shouldHideAnswers) {
            $data['paper']['questions'] = $session->paper->questions
                ->map(fn ($question) => (new QuestionResource($question, false))->toArray($request))
                ->values()
                ->all();
        }

        return response()->json(['session' => $data]);
    }

    public function activate(KangourouSession $kangourouSession): JsonResponse
    {
        $kangourouSession->update(['status' => 'active']);
        $kangourouSession->load(['paper', 'divisions']);

        foreach ($kangourouSession->divisions as $division) {
            broadcast(new SessionOpenedForDivision($division, $kangourouSession));
        }

        return response()->json([
            'message' => 'Session activated.',
            'session' => $kangourouSession->fresh(),
        ]);
    }

    public function update(UpdateKangourouSessionRequest $request, KangourouSession $kangourouSession): JsonResponse
    {
        $data = [];

        if ($request->has('privacy')) {
            $data['privacy'] = $request->validated('privacy');
        }

        if ($request->has('expires_at')) {
            $data['expires_at'] = $request->validated('expires_at');
        }

        if ($request->has('preferences')) {
            $current = $kangourouSession->preferences ?? [];
            $incoming = $request->validated('preferences');

            $data['preferences'] = array_replace_recursive($current, $incoming);
        }

        $kangourouSession->update($data);

        return response()->json([
            'message' => 'Session updated.',
            'session' => $kangourouSession->fresh()->load('paper'),
        ]);
    }

    public function myIndex(Request $request): JsonResponse
    {
        $paginator = $request->user()
            ->kangourouSessions()
            ->with(['paper', 'divisions'])
            ->withCount('attempts')
            ->latest()
            ->paginate(15);

        return response()->json([
            'sessions' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function details(Request $request, KangourouSession $kangourouSession): JsonResponse
    {
        $this->authorize('view', $kangourouSession);

        $kangourouSession->load(['paper', 'divisions', 'attempts' => function ($query) {
            $query->latest()->select('id', 'kangourou_session_id', 'user_id', 'name', 'code', 'status', 'score', 'timer', 'termination', 'answers', 'created_at', 'updated_at');
        }]);

        return response()->json(['session' => $kangourouSession]);
    }

    public function changeCode(Request $request, KangourouSession $kangourouSession): JsonResponse
    {
        $this->authorize('view', $kangourouSession);

        do {
            try {
                $kangourouSession->update(['code' => KangourouSession::generateCode()]);
                break;
            } catch (UniqueConstraintViolationException) {
                // retry on rare code collision
            }
        } while (true);

        return response()->json([
            'message' => 'Session code changed.',
            'session' => $kangourouSession->fresh()->load('paper'),
        ]);
    }

    public function papers(): JsonResponse
    {
        $papers = Paper::select('id', 'title', 'level', 'year')
            ->orderByDesc('year')
            ->orderBy('level')
            ->get();

        return response()->json(['papers' => $papers]);
    }

    public function destroy(KangourouSession $kangourouSession, Request $request): JsonResponse
    {
        $this->authorize('view', $kangourouSession);

        $kangourouSession->delete();

        return response()->json(['message' => 'Session deleted.']);
    }
}
