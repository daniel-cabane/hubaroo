<?php

namespace App\Http\Controllers;

use App\Events\JumpActivated;
use App\Models\Course;
use App\Models\Jump;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JumpController extends Controller
{
    public function store(Course $course, Request $request): JsonResponse
    {
        $this->authorize('update', $course->division);

        $request->validate([
            'nb_questions' => ['required', 'integer', 'min:1', 'max:30'],
            'time' => ['required', 'integer', 'min:5', 'max:60'],
            'growth' => ['required', 'integer', 'min:0', 'max:10'],
            'status' => ['required', 'in:draft,active'],
        ]);

        $jump = $course->jumps()->create([
            'nb_questions' => $request->integer('nb_questions'),
            'time' => $request->integer('time'),
            'growth' => $request->integer('growth'),
            'status' => 'draft',
        ]);

        if ($request->input('status') === 'active') {
            $this->activateJump($jump, $course);
        }

        return response()->json([
            'message' => 'Jump created.',
            'jump' => $jump->fresh(),
        ], 201);
    }

    public function update(Jump $jump, Request $request): JsonResponse
    {
        $this->authorize('update', $jump->course->division);

        $request->validate([
            'status' => ['sometimes', 'in:draft,active,expired'],
            'expiration' => ['sometimes', 'nullable', 'date'],
            'nb_questions' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'time' => ['sometimes', 'integer', 'min:5', 'max:60'],
            'growth' => ['sometimes', 'integer', 'min:0', 'max:10'],
        ]);

        $data = $request->only(['nb_questions', 'time', 'growth', 'expiration']);

        if ($request->has('status') && $request->input('status') === 'active' && $jump->status === 'draft') {
            $this->activateJump($jump, $jump->course);
            $data['status'] = 'active';
        } elseif ($request->has('status')) {
            $data['status'] = $request->input('status');
        }

        if ($request->has('expiration')) {
            $data['expiration'] = $request->input('expiration');
        }

        $jump->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json([
            'message' => 'Jump updated.',
            'jump' => $jump->fresh(),
        ]);
    }

    public function destroy(Jump $jump, Request $request): JsonResponse
    {
        $this->authorize('update', $jump->course->division);

        $jump->delete();

        return response()->json(['message' => 'Jump deleted.']);
    }

    private function activateJump(Jump $jump, Course $course): void
    {
        $expiration = now()->addMinutes($jump->time + 15);
        $jump->update([
            'status' => 'active',
            'expiration' => $expiration,
        ]);

        $division = $course->division;
        $jump->load('course');
        broadcast(new JumpActivated($jump, $division));
    }
}
