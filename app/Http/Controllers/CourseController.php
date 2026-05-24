<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Division;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Division $division, Request $request): JsonResponse
    {
        $this->authorize('view', $division);

        $user = $request->user();
        $isTeacher = $division->teacher_id === $user->id;

        $query = $division->courses();

        if ($isTeacher) {
            $query->with(['jumps' => fn ($q) => $q->withCount('attempts')->orderBy('id')])->withCount('jumps');
        } else {
            $query->where('archived', false)->with([
                'jumps' => function ($q) use ($user) {
                    $q->whereIn('status', ['active', 'expired'])
                        ->orderBy('id')
                        ->with(['attempts' => fn ($a) => $a->where('user_id', $user->id)->select('id', 'jump_id', 'user_id', 'score', 'status', 'termination')]);
                },
            ]);
        }

        $courses = $query->latest()->get();

        // Compute rank for each jump based on ordered array position (avoids N+1 — W10)
        $courses->each(function ($course): void {
            $course->jumps->values()->each(function ($jump, int $index): void {
                $jump->rank = $index + 1;
            });
        });

        return response()->json(['courses' => $courses]);
    }

    public function store(Division $division, Request $request): JsonResponse
    {
        $this->authorize('update', $division);

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $course = $division->courses()->create([
            'title' => $request->string('title'),
            'archived' => false,
        ]);

        return response()->json([
            'message' => 'Course created.',
            'course' => $course->load('jumps'),
        ], 201);
    }

    public function update(Course $course, Request $request): JsonResponse
    {
        $this->authorize('update', $course->division);

        $request->validate([
            'archived' => ['required', 'boolean'],
        ]);

        $course->update(['archived' => $request->boolean('archived')]);

        return response()->json([
            'message' => 'Course updated.',
            'course' => $course,
        ]);
    }

    public function destroy(Course $course, Request $request): JsonResponse
    {
        $this->authorize('update', $course->division);

        if ($course->jumps()->exists()) {
            return response()->json(['message' => 'Cannot delete a course that has jumps.'], 422);
        }

        $course->delete();

        return response()->json(['message' => 'Course deleted.']);
    }

    public function details(Course $course, Request $request): JsonResponse
    {
        $this->authorize('update', $course->division);

        $division = $course->division->load('students');
        $jumps = $course->jumps()->with('attempts.user')->orderBy('id')->get();

        // Compute rank based on ordered array position (avoids N+1 — W10)
        $jumps->values()->each(function ($jump, int $index): void {
            $jump->rank = $index + 1;
        });

        // Enrich each attempt's question_list with the question image
        $allQuestionIds = $jumps->flatMap(
            fn ($j) => $j->attempts->flatMap(fn ($a) => collect($a->question_list)->pluck('id'))
        )->unique();

        $images = Question::whereIn('id', $allQuestionIds)->pluck('image', 'id');
        $correctAnswers = Question::whereIn('id', $allQuestionIds)->pluck('correct_answer', 'id');

        $jumps->each(function ($jump) use ($images, $correctAnswers): void {
            $jump->attempts->each(function ($attempt) use ($images, $correctAnswers): void {
                $attempt->question_list = collect($attempt->question_list)
                    ->map(function ($item) use ($images, $correctAnswers) {
                        $item['image'] = $images->get($item['id']);
                        $item['correct_answer'] = $correctAnswers->get($item['id']);

                        return $item;
                    })->all();
            });
        });

        return response()->json([
            'course' => $course->load('division'),
            'jumps' => $jumps,
            'students' => $division->students,
        ]);
    }
}
