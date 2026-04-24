<?php

namespace App\Http\Controllers;

use App\Events\RejoinDemandCreated;
use App\Events\RejoinDemandResolved;
use App\Models\Attempt;
use App\Models\RejoinDemand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RejoinDemandController extends Controller
{
    public function store(Attempt $attempt): JsonResponse
    {
        $session = $attempt->kangourouSession;

        if ($session->isExpired()) {
            return response()->json(['message' => 'This session has expired.'], 403);
        }

        if (! $session->author_id) {
            return response()->json(['message' => 'This session has no author to approve the request.'], 422);
        }

        $demand = RejoinDemand::firstOrCreate(['attempt_id' => $attempt->id]);

        broadcast(new RejoinDemandCreated($demand->load('attempt.kangourouSession.paper')));

        return response()->json([
            'message' => 'Rejoin demand created.',
            'demand' => ['id' => $demand->id],
        ], 201);
    }

    public function approve(Request $request, RejoinDemand $rejoinDemand): JsonResponse
    {
        $session = $rejoinDemand->attempt->kangourouSession;
        $this->authorize('update', $session);

        $request->validate([
            'extra_time' => ['nullable', 'integer', 'min:-3600', 'max:3600'],
        ]);

        $extraTime = $request->integer('extra_time', 0);
        $attempt = $rejoinDemand->attempt;

        $newExtraTime = max(0, $attempt->extra_time + $extraTime);
        $attempt->update([
            'status' => 'inProgress',
            'extra_time' => $newExtraTime,
        ]);

        broadcast(new RejoinDemandResolved($rejoinDemand, 'approved', $newExtraTime));

        $rejoinDemand->delete();

        return response()->json(['message' => 'Rejoin demand approved.']);
    }

    public function reject(RejoinDemand $rejoinDemand): JsonResponse
    {
        $session = $rejoinDemand->attempt->kangourouSession;
        $this->authorize('update', $session);

        broadcast(new RejoinDemandResolved($rejoinDemand, 'denied'));

        $rejoinDemand->delete();

        return response()->json(['message' => 'Rejoin demand rejected.']);
    }

    public function myIndex(Request $request): JsonResponse
    {
        $demands = RejoinDemand::whereHas('attempt.kangourouSession', function ($query) use ($request) {
            $query->where('author_id', $request->user()->id);
        })->with('attempt.kangourouSession.paper')->get();

        $result = $demands->map(function (RejoinDemand $demand) {
            $attempt = $demand->attempt;
            $answeredCount = collect($attempt->answers)->filter(fn ($a) => $a['answer'] !== null)->count();

            return [
                'id' => $demand->id,
                'created_at' => $demand->created_at,
                'attempt' => [
                    'id' => $attempt->id,
                    'name' => $attempt->name,
                    'timer' => $attempt->timer,
                    'extra_time' => $attempt->extra_time,
                    'termination' => $attempt->termination,
                    'status' => $attempt->status,
                    'answered_count' => $answeredCount,
                    'updated_at' => $attempt->updated_at,
                    'session' => [
                        'id' => $attempt->kangourouSession->id,
                        'code' => $attempt->kangourouSession->code,
                        'paper_title' => $attempt->kangourouSession->paper?->title,
                        'preferences' => $attempt->kangourouSession->preferences,
                    ],
                ],
            ];
        });

        return response()->json(['demands' => $result]);
    }
}
