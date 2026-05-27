<?php

namespace App\Http\Controllers;

use App\Events\JumpAttemptUpdated;
use App\Events\JumpRejoinDemandCreated;
use App\Events\JumpRejoinDemandResolved;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\JumpRejoinDemand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JumpRejoinDemandController extends Controller
{
    public function store(JumpAttempt $jumpAttempt, Request $request): JsonResponse
    {
        if ($request->user()->id !== $jumpAttempt->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($jumpAttempt->jump->isExpired()) {
            return response()->json(['message' => 'Ce saut est terminé.'], 403);
        }

        $demand = JumpRejoinDemand::firstOrCreate(['jump_attempt_id' => $jumpAttempt->id]);

        broadcast(new JumpRejoinDemandCreated($demand->load('jumpAttempt.jump.course.division')));

        return response()->json([
            'message' => 'Rejoin demand created.',
            'demand' => ['id' => $demand->id],
        ], 201);
    }

    public function approve(JumpRejoinDemand $jumpRejoinDemand, Request $request): JsonResponse
    {
        $division = $jumpRejoinDemand->jumpAttempt->jump->course->division;

        if ($division->teacher_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'extra_time' => ['nullable', 'integer', 'min:-3600', 'max:3600'],
        ]);

        $extraTime = $request->integer('extra_time', 0);
        $attempt = $jumpRejoinDemand->jumpAttempt;
        $newExtraTime = $attempt->extra_time + $extraTime;

        $attempt->update([
            'status' => 'inProgress',
            'termination' => 'none',
            'extra_time' => $newExtraTime,
            'timer' => $attempt->timer + $extraTime,
        ]);

        broadcast(new JumpRejoinDemandResolved($jumpRejoinDemand, 'approved', $newExtraTime));
        broadcast(new JumpAttemptUpdated($attempt->fresh()));

        $jumpRejoinDemand->delete();

        return response()->json(['message' => 'Rejoin demand approved.']);
    }

    public function reject(JumpRejoinDemand $jumpRejoinDemand, Request $request): JsonResponse
    {
        $division = $jumpRejoinDemand->jumpAttempt->jump->course->division;

        if ($division->teacher_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        broadcast(new JumpRejoinDemandResolved($jumpRejoinDemand, 'denied'));

        $jumpRejoinDemand->delete();

        return response()->json(['message' => 'Rejoin demand rejected.']);
    }

    public function myIndex(Request $request): JsonResponse
    {
        $demands = JumpRejoinDemand::whereHas('jumpAttempt.jump.course.division', function ($query) use ($request) {
            $query->where('teacher_id', $request->user()->id);
        })->with(['jumpAttempt.user', 'jumpAttempt.jump.course'])->get();

        $formatted = $demands->map(function ($demand) {
            $attempt = $demand->jumpAttempt;
            $answeredCount = collect($attempt->question_list)
                ->filter(fn ($q) => isset($q['answer']) && $q['answer'] !== null)
                ->count();

            return [
                'id' => $demand->id,
                'created_at' => $demand->created_at,
                'attempt' => [
                    'id' => $attempt->id,
                    'jump_id' => $attempt->jump_id,
                    'user' => [
                        'id' => $attempt->user->id,
                        'name' => $attempt->user->name,
                    ],
                    'timer' => $attempt->timer,
                    'extra_time' => $attempt->extra_time,
                    'termination' => $attempt->termination,
                    'status' => $attempt->status,
                    'answered_count' => $answeredCount,
                    'jump' => [
                        'id' => $attempt->jump->id,
                        'rank' => Jump::where('course_id', $attempt->jump->course_id)->where('id', '<=', $attempt->jump->id)->count(),
                        'course' => ['title' => $attempt->jump->course->title],
                    ],
                ],
            ];
        });

        return response()->json(['demands' => $formatted]);
    }
}
